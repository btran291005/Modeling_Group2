<?php
/**
 * config/ai_module.php
 * Module tích hợp Gemini 2.0 Flash để định giá thiết bị
 */

// API Key đã được cập nhật
define('GEMINI_API_KEY', 'AIzaSyCJjGVqvylGsH8grCehVx-lgY-wd3rg70E');
// URL API chính thức của Google Gemini
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');
// Giá sàn mặc định
define('MIN_SUGGESTED_PRICE', 100000);

/**
 * Tính giá thu mua đề xuất bằng AI (Gemini)
 *
 * @param array $deviceInfo  Thông tin thiết bị
 * @param array $activeRules Danh sách quy tắc đang bật
 * @return array
 */

function getAISuggestedPrice(array $deviceInfo, array $activeRules): array
{
    // ---- Xây dựng danh sách quy tắc cho prompt ----
    $rulesText = '';
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
 
    // ---- Tính khấu hao pin ----
    $batteryHealth = (int) $deviceInfo['battery_health'];
    $batteryDeduction = 0.0;
 
    if ($batteryHealth < 80) {
        $batteryDeduction = (80 - $batteryHealth) * 0.3;
        $rulesText .= "  - Chai pin ({$batteryHealth}%): trừ thêm " . round($batteryDeduction, 1) . "% (0.3%/mỗi % dưới 80)\n";
        $totalDeduction += $batteryDeduction;
    }
 
    $basePrice  = (int) $deviceInfo['base_price'];
    $brandName  = $deviceInfo['brand_name'];
    $modelName  = $deviceInfo['model_name'];
    $ramGb      = (int) $deviceInfo['ram_gb'];
    $romGb      = (int) $deviceInfo['rom_gb'];
 
    // ---- Xây dựng prompt ----
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
 
    // ---- Gọi Gemini API ----
    $payload = json_encode([
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature'     => 0.2,    // Thấp → kết quả ổn định, ít sáng tạo
            'maxOutputTokens' => 300,
            'responseMimeType' => 'application/json',
        ],
    ]);
 
    $url = GEMINI_API_URL . '?key=' . GEMINI_API_KEY;
 
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
 
    // ---- Xử lý lỗi cURL ----
    if ($curlErr) {
        error_log('[AI MODULE] cURL Error: ' . $curlErr);
        return ['success' => false, 'price' => null, 'reasoning' => '', 'error' => 'Lỗi kết nối đến AI: ' . $curlErr];
    }
 
    if ($httpCode !== 200) {
        error_log('[AI MODULE] HTTP ' . $httpCode . ' | Response: ' . $response);
        return ['success' => false, 'price' => null, 'reasoning' => '', 'error' => "AI phản hồi lỗi HTTP {$httpCode}."];
    }
 
    // ---- Parse response Gemini ----
    $body = json_decode($response, true);
 
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[AI MODULE] JSON parse error: ' . $response);
        return ['success' => false, 'price' => null, 'reasoning' => '', 'error' => 'Không thể đọc phản hồi từ AI.'];
    }
 
    // Lấy text từ candidates[0].content.parts[0].text
    $rawText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
 
    if (empty($rawText)) {
        error_log('[AI MODULE] Empty AI response: ' . json_encode($body));
        return ['success' => false, 'price' => null, 'reasoning' => '', 'error' => 'AI không trả về kết quả.'];
    }
 
    // ---- Parse JSON từ AI ----
    // Loại bỏ markdown fences nếu có
    $clean = preg_replace('/```(?:json)?|```/', '', trim($rawText));
    $parsed = json_decode(trim($clean), true);
 
    if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['suggested_price'])) {
        error_log('[AI MODULE] Could not parse AI JSON: ' . $rawText);
        return ['success' => false, 'price' => null, 'reasoning' => '', 'error' => 'AI trả về định dạng không hợp lệ.'];
    }
 
    $suggestedPrice = (int) $parsed['suggested_price'];
    $reasoning      = (string) ($parsed['reasoning'] ?? '');
 
    // ---- Validate giá ----
    if ($suggestedPrice < MIN_SUGGESTED_PRICE) {
        $suggestedPrice = MIN_SUGGESTED_PRICE;
        $reasoning .= ' (Đã điều chỉnh về giá sàn tối thiểu.)';
    }
 
    // Làm tròn đến hàng nghìn
    $suggestedPrice = (int) round($suggestedPrice / 1000) * 1000;
 
    return [
        'success'   => true,
        'price'     => $suggestedPrice,
        'reasoning' => $reasoning,
        'error'     => null,
    ];
}