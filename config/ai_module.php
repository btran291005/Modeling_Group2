<?php
/**
 * config/ai_module.php
 * Module tích hợp Gemini 2.0 Flash để định giá thiết bị
 *
 * API Key được đọc từ biến môi trường GEMINI_API_KEY.
 * Tạo file .env hoặc set biến môi trường trên server:
 *   export GEMINI_API_KEY="AIzaSy..."
 * hoặc trong Apache VirtualHost:
 *   SetEnv GEMINI_API_KEY "AIzaSy..."
 * hoặc trong .htaccess:
 *   SetEnv GEMINI_API_KEY "AIzaSy..."
 *
 * Xử lý 429 (rate limit): retry tối đa 2 lần với exponential backoff,
 * sau đó fallback sang công thức tính giá nội bộ.
 */

// ── API Key đọc từ môi trường, KHÔNG hardcode trong source ──────
$_envFile = __DIR__ . '/.env.php';
$_envSecrets = require $_envFile;
define('GEMINI_API_KEY', $_envSecrets['GEMINI_API_KEY'] ?? '');


if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', $_geminiApiKey);
}

if (!defined('GEMINI_MAX_RETRIES')) {
    define('GEMINI_MAX_RETRIES', 2);
}

unset($_geminiApiKey);

function getAISuggestedPrice(array $deviceInfo, array $activeRules): array
{
    $rulesText      = '';
    $totalDeduction = 0.0;

    if (!empty($activeRules)) {
        $rulesText = "Các quy tắc trừ giá đang áp dụng:\n";
        foreach ($activeRules as $rule) {
            $pct = (float) $rule['deduction_percent'];
            $totalDeduction += $pct;
            $rulesText .= "  - {$rule['condition_name']}: trừ {$pct}% giá cơ sở\n";
        }
    } else {
        $rulesText = "Không có quy tắc trừ giá nào được áp dụng.\n";
    }

    $batteryHealth    = (int) $deviceInfo['battery_health'];
    $batteryDeduction = 0.0;

    if ($batteryHealth < 80) {
        $batteryDeduction = (80 - $batteryHealth) * 0.3;
        $rulesText       .= "  - Chai pin ({$batteryHealth}%): trừ thêm "
                          . round($batteryDeduction, 1) . "% (0.3%/mỗi % dưới 80)\n";
        $totalDeduction  += $batteryDeduction;
    }

    $basePrice = (int) $deviceInfo['base_price'];
    $brandName = $deviceInfo['brand_name'];
    $modelName = $deviceInfo['model_name'];
    $ramGb     = (int) $deviceInfo['ram_gb'];
    $romGb     = (int) $deviceInfo['rom_gb'];

    $prompt = <<<PROMPT
Bạn là chuyên gia định giá thu mua thiết bị điện tử cũ tại Việt Nam.
Nhiệm vụ: Tính toán và trả về giá thu mua hợp lý (VNĐ) cho thiết bị sau.

THÔNG TIN THIẾT BỊ:
  - Hãng: {$brandName}
  - Mẫu máy: {$modelName}
  - RAM: {$ramGb}GB | Bộ nhớ: {$romGb}GB
  - Phần trăm pin hiện tại: {$batteryHealth}%
  - Giá cơ sở tham chiếu (giá máy cũ còn tốt): {$basePrice} VNĐ

QUY TẮC ĐỊNH GIÁ:
{$rulesText}
Tổng khấu trừ ước tính: {$totalDeduction}%

YÊU CẦU:
1. Áp dụng tất cả khấu trừ vào giá cơ sở.
2. Cân nhắc thêm xu hướng thị trường thực tế tại Việt Nam.
3. Giá đề xuất PHẢI là số nguyên, đơn vị VNĐ, làm tròn đến hàng nghìn.
4. Giá tối thiểu là 100.000 VNĐ.

TRẢ LỜI (JSON duy nhất, KHÔNG có markdown, KHÔNG có text thừa):
{
  "suggested_price": <số nguyên VNĐ>,
  "reasoning": "<giải thích ngắn gọn 1-2 câu bằng tiếng Việt>"
}
PROMPT;

    $payload = json_encode([
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature'      => 0.2,
            'maxOutputTokens'  => 300,
            'responseMimeType' => 'application/json',
        ],
    ]);

    // Kiểm tra API key có giá trị không
    if (GEMINI_API_KEY === '') {
        error_log('[AI MODULE] GEMINI_API_KEY chưa được cấu hình trong biến môi trường.');
        return _fallbackPrice($deviceInfo, $totalDeduction,
            'API key chưa được cấu hình. Đã dùng công thức dự phòng.');
    }

    $url      = GEMINI_API_URL . '?key=' . GEMINI_API_KEY;
    $response = null;
    $httpCode = 0;
    $curlErr  = '';

    for ($attempt = 0; $attempt <= GEMINI_MAX_RETRIES; $attempt++) {
        if ($attempt > 0) {
            sleep((int) pow(2, $attempt)); // 2s rồi 4s
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if (!$curlErr && $httpCode !== 429) break;
        error_log("[AI MODULE] Attempt {$attempt} — HTTP {$httpCode}, cURL: {$curlErr}");
    }

    if ($curlErr) {
        error_log('[AI MODULE] cURL Error (all retries): ' . $curlErr);
        return _fallbackPrice($deviceInfo, $totalDeduction,
            'Không thể kết nối đến AI. Đã dùng công thức dự phòng.');
    }

    if ($httpCode === 429) {
        error_log('[AI MODULE] Rate limited after ' . GEMINI_MAX_RETRIES . ' retries — using fallback.');
        return _fallbackPrice($deviceInfo, $totalDeduction,
            'AI tạm thời quá tải (rate limit). Giá được tính theo công thức dự phòng.');
    }

    if ($httpCode !== 200) {
        error_log('[AI MODULE] HTTP ' . $httpCode . ' | ' . $response);
        return _fallbackPrice($deviceInfo, $totalDeduction,
            "AI phản hồi lỗi HTTP {$httpCode}. Đã dùng công thức dự phòng.");
    }

    $body = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return _fallbackPrice($deviceInfo, $totalDeduction,
            'Không thể đọc phản hồi từ AI. Đã dùng công thức dự phòng.');
    }

    $rawText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($rawText)) {
        return _fallbackPrice($deviceInfo, $totalDeduction,
            'AI không trả về kết quả. Đã dùng công thức dự phòng.');
    }

    $clean  = preg_replace('/```(?:json)?|```/', '', trim($rawText));
    $parsed = json_decode(trim($clean), true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['suggested_price'])) {
        error_log('[AI MODULE] Parse fail: ' . $rawText);
        return _fallbackPrice($deviceInfo, $totalDeduction,
            'AI trả về định dạng không hợp lệ. Đã dùng công thức dự phòng.');
    }

    $suggestedPrice = (int) $parsed['suggested_price'];
    $reasoning      = (string) ($parsed['reasoning'] ?? '');

    if ($suggestedPrice < MIN_SUGGESTED_PRICE) {
        $suggestedPrice = MIN_SUGGESTED_PRICE;
        $reasoning     .= ' (Đã điều chỉnh về giá sàn tối thiểu.)';
    }

    $suggestedPrice = (int) round($suggestedPrice / 1000) * 1000;

    return [
        'success'   => true,
        'price'     => $suggestedPrice,
        'reasoning' => $reasoning,
        'error'     => null,
        'fallback'  => false,
    ];
}

/**
 * Tính giá dự phòng bằng công thức khi AI không khả dụng.
 * Công thức: basePrice × (1 − totalDeduction%), làm tròn đến nghìn.
 */
function _fallbackPrice(array $deviceInfo, float $totalDeduction, string $reason): array
{
    $basePrice      = (int) $deviceInfo['base_price'];
    $multiplier     = max(0.0, 1.0 - ($totalDeduction / 100.0));
    $suggestedPrice = (int) round($basePrice * $multiplier / 1000) * 1000;

    if ($suggestedPrice < MIN_SUGGESTED_PRICE) {
        $suggestedPrice = MIN_SUGGESTED_PRICE;
    }

    $reasoning = $reason . ' Khấu trừ ' . number_format($totalDeduction, 1) . '% từ giá cơ sở '
               . number_format($basePrice, 0, ',', '.') . ' ₫.';

    return [
        'success'   => true,
        'price'     => $suggestedPrice,
        'reasoning' => $reasoning,
        'error'     => null,
        'fallback'  => true,
    ];
}