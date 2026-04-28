<?php
/**
 * staff/valuation.php
 * UC11: Định giá thiết bị (cốt lõi)
 * UC12: Truy vấn đề xuất giá AI (via AJAX → api/ai_valuation.php)
 * UC13: Xác nhận thu mua & Nhập kho (extend từ UC11)
 *
 * Luồng:
 *   1. Staff chọn hãng → dòng máy → nhập tình trạng
 *   2. Nhấn "Định giá AI" → AJAX gọi api/ai_valuation.php
 *   3. AI trả về giá + phiên (session_id)
 *   4. Staff có thể "Xác nhận thu mua" → nhập IMEI + thông tin khách
 *      hoặc "Từ chối" → cập nhật final_status = Declined
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Staff');

// ============================================================
// XỬ LÝ XÁC NHẬN THU MUA (POST confirm_purchase)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_purchase') {
    header('Content-Type: application/json; charset=utf-8');

    $sessionId   = (int)  ($_POST['session_id']   ?? 0);
    $imei        = trim(  $_POST['imei']           ?? '');
    $customerName= trim(  $_POST['customer_name']  ?? '');
    $customerPhone=trim(  $_POST['customer_phone'] ?? '');

    // ---- Validate ----
    if ($sessionId <= 0 || empty($imei) || empty($customerName) || empty($customerPhone)) {
        exit(json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin.']));
    }

    if (!preg_match('/^\d{15}$/', $imei)) {
        exit(json_encode(['success' => false, 'message' => 'IMEI phải gồm đúng 15 chữ số.']));
    }

    if (!isValidVietnamesePhone($customerPhone)) {
        exit(json_encode(['success' => false, 'message' => 'Số điện thoại không hợp lệ.']));
    }

    // ---- Kiểm tra phiên thuộc về Staff này và đang Pending ----
    $stmtCheck = $pdo->prepare("
        SELECT session_id, model_id, ai_suggested_price
        FROM valuation_sessions
        WHERE session_id = ? AND user_id = ? AND final_status = 'Pending'
    ");
    $stmtCheck->execute([$sessionId, $_SESSION['user_id']]);
    $session = $stmtCheck->fetch();

    if (!$session) {
        exit(json_encode(['success' => false, 'message' => 'Phiên định giá không hợp lệ hoặc đã xử lý.']));
    }

    // ---- Kiểm tra IMEI đã tồn tại chưa ----
    $stmtImei = $pdo->prepare("SELECT imei FROM gadgets WHERE imei = ?");
    $stmtImei->execute([$imei]);
    if ($stmtImei->fetch()) {
        exit(json_encode(['success' => false, 'message' => "IMEI {$imei} đã tồn tại trong kho."]));
    }

    try {
        $pdo->beginTransaction();

        // R02: Tìm hoặc tạo khách hàng theo SĐT
        $stmtCust = $pdo->prepare("SELECT customer_id, full_name FROM customers WHERE phone_number = ?");
        $stmtCust->execute([$customerPhone]);
        $customer = $stmtCust->fetch();

        if ($customer) {
            $customerId = $customer['customer_id'];
        } else {
            $pdo->prepare("INSERT INTO customers (full_name, phone_number) VALUES (?, ?)")
                ->execute([$customerName, $customerPhone]);
            $customerId = (int) $pdo->lastInsertId();
        }

        // Cập nhật valuation_session → Purchased + map customer
        $pdo->prepare("
            UPDATE valuation_sessions
            SET final_status = 'Purchased', customer_id = ?
            WHERE session_id = ?
        ")->execute([$customerId, $sessionId]);

        // Nhập kho: Thêm IMEI vào bảng gadgets
        $pdo->prepare("INSERT INTO gadgets (imei, session_id, status) VALUES (?, ?, 'Stored')")
            ->execute([$imei, $sessionId]);

        // Upload ảnh nếu có (xử lý trong API riêng để giữ file này gọn)
        // Ghi audit log
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table) VALUES (?,?,?)")
            ->execute([
                $_SESSION['user_id'],
                "Thu mua thành công - Session #{$sessionId} | IMEI: {$imei} | KH: {$customerName} ({$customerPhone})",
                'valuation_sessions'
            ]);

        // Gửi thông báo cho Admin (nếu muốn)
        $adminIds = $pdo->query("SELECT user_id FROM users WHERE role='Admin' AND status='Active'")->fetchAll(PDO::FETCH_COLUMN);
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $deviceInfo = $pdo->prepare("SELECT dm.model_name, b.brand_name FROM valuation_sessions vs JOIN device_models dm ON vs.model_id=dm.model_id JOIN brands b ON dm.brand_id=b.brand_id WHERE vs.session_id=?");
        $deviceInfo->execute([$sessionId]);
        $dev = $deviceInfo->fetch();
        foreach ($adminIds as $adminId) {
            $stmtNotif->execute([
                $adminId,
                "✅ [{$_SESSION['full_name']}] vừa thu mua {$dev['brand_name']} {$dev['model_name']} (IMEI: {$imei})"
            ]);
        }

        $pdo->commit();
        exit(json_encode(['success' => true, 'message' => 'Thu mua thành công! Thiết bị đã nhập kho.', 'imei' => $imei]));

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[VALUATION CONFIRM] ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại.']));
    }
}

// ============================================================
// XỬ LÝ TỪ CHỐI (POST decline)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'decline') {
    header('Content-Type: application/json; charset=utf-8');

    $sessionId = (int) ($_POST['session_id'] ?? 0);
    if ($sessionId > 0) {
        $pdo->prepare("
            UPDATE valuation_sessions SET final_status = 'Declined'
            WHERE session_id = ? AND user_id = ? AND final_status = 'Pending'
        ")->execute([$sessionId, $_SESSION['user_id']]);

        $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table) VALUES (?,?,?)")
            ->execute([$_SESSION['user_id'], "Từ chối thu mua - Session #{$sessionId}", 'valuation_sessions']);

        exit(json_encode(['success' => true, 'message' => 'Đã từ chối phiên định giá.']));
    }
    exit(json_encode(['success' => false, 'message' => 'Phiên không hợp lệ.']));
}

// ============================================================
// LẤY DỮ LIỆU TRANG
// ============================================================

// Danh sách hãng
$brands = $pdo->query("SELECT brand_id, brand_name FROM brands ORDER BY brand_name")->fetchAll();

// Quy tắc AI đang hoạt động (hiển thị cho Staff biết)
$activeRules = $pdo->query("
    SELECT rule_id, condition_name, deduction_percent
    FROM ai_pricing_rules
    WHERE is_active = 1
    ORDER BY deduction_percent DESC
")->fetchAll();

// ============================================================
// RENDER
// ============================================================
renderHtmlHead('Định giá thiết bị', [
    '../assets/css/pages/staff/valuation.css'
]);
renderSidebar('valuation');
renderMainOpen();
renderTopbar('Định giá thiết bị mới', '<span>Staff</span> / <span>Định giá</span>');
?>

<div class="page-content">

    <div class="val-layout">

        <!-- ===================================================
             CỘT TRÁI: FORM NHẬP LIỆU
             =================================================== -->
        <div class="val-form-col">

            <!-- STEP 1: CHỌN THIẾT BỊ -->
            <div class="card val-step" id="step-device">
                <div class="val-step-header">
                    <span class="val-step-num">1</span>
                    <div>
                        <h3 class="val-step-title">Thông tin thiết bị</h3>
                        <p class="val-step-desc">Chọn hãng và mẫu máy cần định giá</p>
                    </div>
                </div>

                <div class="card-body">

                    <!-- Chọn hãng -->
                    <div class="form-group">
                        <label class="form-label" for="brand-select">
                            Hãng sản xuất <span class="required">*</span>
                        </label>
                        <select id="brand-select" class="form-control">
                            <option value="">— Chọn hãng —</option>
                            <?php foreach ($brands as $brand): ?>
                            <option value="<?= $brand['brand_id'] ?>"><?= e($brand['brand_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Chọn mẫu máy (load dynamic qua AJAX) -->
                    <div class="form-group">
                        <label class="form-label" for="model-select">
                            Mẫu máy <span class="required">*</span>
                        </label>
                        <select id="model-select" class="form-control" disabled>
                            <option value="">— Chọn hãng trước —</option>
                        </select>
                    </div>

                    <!-- Thông tin thiết bị (hiển thị sau khi chọn) -->
                    <div class="device-info-card" id="device-info" style="display:none;">
                        <div class="device-info-row">
                            <span class="device-info-label">RAM</span>
                            <strong id="info-ram">—</strong>
                        </div>
                        <div class="device-info-row">
                            <span class="device-info-label">Bộ nhớ</span>
                            <strong id="info-rom">—</strong>
                        </div>
                        <div class="device-info-row">
                            <span class="device-info-label">Giá cơ sở</span>
                            <strong id="info-base-price" class="text-primary">—</strong>
                        </div>
                    </div>

                </div>
            </div>

            <!-- STEP 2: TÌNH TRẠNG PIN -->
            <div class="card val-step" id="step-battery">
                <div class="val-step-header">
                    <span class="val-step-num">2</span>
                    <div>
                        <h3 class="val-step-title">Tỷ lệ pin</h3>
                        <p class="val-step-desc">Đọc từ cài đặt thiết bị → Cài đặt → Pin</p>
                    </div>
                </div>

                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" for="battery-input">
                            Phần trăm pin hiện tại <span class="required">*</span>
                        </label>
                        <div class="battery-input-wrap">
                            <input type="range"  id="battery-range" min="1" max="100" value="80" class="battery-range">
                            <div class="battery-display">
                                <input type="number" id="battery-input" min="1" max="100" value="80"
                                       class="form-control battery-number" placeholder="80">
                                <span class="battery-unit">%</span>
                            </div>
                        </div>
                        <div class="battery-indicator" id="battery-indicator">
                            <div class="battery-bar-wrap">
                                <div class="battery-bar" id="battery-bar" style="width:80%"></div>
                            </div>
                            <span class="battery-hint" id="battery-hint">Pin tốt</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: QUY TẮC KHẤU TRỪ -->
            <div class="card val-step" id="step-rules">
                <div class="val-step-header">
                    <span class="val-step-num">3</span>
                    <div>
                        <h3 class="val-step-title">Tình trạng vật lý</h3>
                        <p class="val-step-desc">Tích chọn các vấn đề của thiết bị (nếu có)</p>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (empty($activeRules)): ?>
                        <p class="text-muted text-sm">Chưa có quy tắc nào đang hoạt động.</p>
                    <?php else: ?>
                    <div class="rules-checklist" id="rules-checklist">
                        <?php foreach ($activeRules as $rule): ?>
                        <label class="rule-check-item">
                            <input type="checkbox" class="rule-checkbox"
                                   value="<?= $rule['rule_id'] ?>"
                                   data-pct="<?= $rule['deduction_percent'] ?>">
                            <span class="rule-check-content">
                                <span class="rule-check-name"><?= e($rule['condition_name']) ?></span>
                                <span class="rule-check-pct deduction-badge-sm">−<?= number_format((float)$rule['deduction_percent'], 1) ?>%</span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="rules-total" id="rules-total" style="display:none;">
                        Tổng khấu trừ vật lý: <strong id="rules-total-pct">0%</strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NÚT ĐỊNH GIÁ -->
            <button class="btn btn-primary btn-valuation" id="btn-valuate" disabled>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
                Định giá bằng AI
                <span class="btn-sub">Gemini 2.0 Flash</span>
            </button>

        </div><!-- end .val-form-col -->


        <!-- ===================================================
             CỘT PHẢI: KẾT QUẢ & THU MUA
             =================================================== -->
        <div class="val-result-col">

            <!-- PLACEHOLDER (chờ định giá) -->
            <div class="val-placeholder" id="val-placeholder">
                <div class="val-placeholder-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <p class="val-placeholder-text">Điền thông tin thiết bị và nhấn<br><strong>Định giá bằng AI</strong> để bắt đầu</p>
            </div>

            <!-- LOADING -->
            <div class="val-loading" id="val-loading" style="display:none;">
                <div class="ai-loading-animation">
                    <div class="ai-pulse"></div>
                    <div class="ai-pulse-ring"></div>
                </div>
                <p class="val-loading-text">AI đang phân tích thị trường...</p>
                <p class="val-loading-sub">Gemini 2.0 Flash đang tính toán</p>
            </div>

            <!-- KẾT QUẢ -->
            <div class="val-result" id="val-result" style="display:none;">

                <!-- Header kết quả -->
                <div class="result-header">
                    <div class="result-header-left">
                        <span class="result-ai-badge">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            AI Định giá
                        </span>
                        <h3 class="result-device-name" id="result-device-name">—</h3>
                    </div>
                    <button class="btn btn-ghost btn-sm" onclick="resetValuation()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.48"/></svg>
                        Định giá lại
                    </button>
                </div>

                <!-- Giá đề xuất -->
                <div class="result-price-card">
                    <span class="result-price-label">Giá thu mua đề xuất</span>
                    <div class="result-price-value" id="result-price">—</div>
                    <p class="result-reasoning" id="result-reasoning"></p>
                </div>

                <!-- Chi tiết tính toán -->
                <div class="result-breakdown" id="result-breakdown">
                    <h4 class="result-breakdown-title">Chi tiết</h4>
                    <div class="result-breakdown-rows" id="breakdown-rows"></div>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="result-actions" id="result-actions">
                    <button class="btn btn-success btn-lg" id="btn-purchase" onclick="openPurchaseModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Xác nhận thu mua
                    </button>
                    <button class="btn btn-ghost" id="btn-decline" onclick="declineSession()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Khách từ chối
                    </button>
                </div>

                <!-- Trạng thái đã xử lý -->
                <div class="result-done" id="result-done" style="display:none;">
                    <div class="result-done-icon" id="result-done-icon">✓</div>
                    <p class="result-done-msg" id="result-done-msg">Đã xử lý</p>
                </div>

            </div><!-- end .val-result -->

        </div><!-- end .val-result-col -->

    </div><!-- end .val-layout -->

</div><!-- end .page-content -->


<!-- ============================================================
     MODAL: XÁC NHẬN THU MUA (UC13)
     ============================================================ -->
<div class="modal-overlay" id="purchase-modal" onclick="closePurchaseModal(event)">
    <div class="modal" role="dialog" aria-modal="true">

        <div class="modal-header">
            <h3 class="modal-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Xác nhận Thu mua
            </h3>
            <button class="modal-close" onclick="closePurchaseModal()">&times;</button>
        </div>

        <div class="modal-body">

            <!-- Tóm tắt phiên -->
            <div class="purchase-summary">
                <div class="purchase-summary-row">
                    <span>Thiết bị:</span>
                    <strong id="pm-device-name">—</strong>
                </div>
                <div class="purchase-summary-row">
                    <span>Giá thu mua:</span>
                    <strong class="text-success" id="pm-price">—</strong>
                </div>
            </div>

            <!-- IMEI -->
            <div class="form-group">
                <label class="form-label" for="pm-imei">
                    Số IMEI <span class="required">*</span>
                </label>
                <input type="text" id="pm-imei" class="form-control"
                       maxlength="15" placeholder="Nhập 15 chữ số IMEI"
                       pattern="\d{15}" inputmode="numeric">
                <p class="form-hint">Tìm trong Cài đặt → Giới thiệu hoặc quay *#06#</p>
            </div>

            <!-- Upload ảnh thiết bị -->
            <div class="form-group">
                <label class="form-label">Ảnh thiết bị</label>
                <div class="upload-zone" id="upload-zone" onclick="document.getElementById('pm-images').click()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" opacity="0.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <p>Nhấn để chọn ảnh <span class="upload-sub">(JPG, PNG, WebP — tối đa 5MB/ảnh)</span></p>
                </div>
                <input type="file" id="pm-images" accept="image/jpeg,image/png,image/webp"
                       multiple style="display:none;" onchange="previewImages(this)">
                <div class="upload-previews" id="upload-previews"></div>
            </div>

            <!-- Thông tin khách hàng -->
            <div class="form-section-title">Thông tin khách hàng</div>

            <div class="form-group">
                <label class="form-label" for="pm-phone">
                    Số điện thoại <span class="required">*</span>
                </label>
                <div class="input-with-btn">
                    <input type="tel" id="pm-phone" class="form-control"
                           placeholder="0912 345 678" maxlength="11"
                           oninput="lookupCustomer(this.value)">
                    <div class="lookup-spinner" id="lookup-spinner" style="display:none;">
                        <div class="spinner-xs"></div>
                    </div>
                </div>
                <p class="form-hint" id="customer-lookup-hint"></p>
            </div>

            <div class="form-group">
                <label class="form-label" for="pm-cname">
                    Tên khách hàng <span class="required">*</span>
                </label>
                <input type="text" id="pm-cname" class="form-control"
                       placeholder="Nguyễn Văn A" maxlength="100">
            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closePurchaseModal()">Hủy</button>
            <button type="button" class="btn btn-success" id="btn-confirm-submit" onclick="submitPurchase()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                Xác nhận & Nhập kho
            </button>
        </div>
    </div>
</div>

<?php renderLayoutClose(); ?>

<style>
/* ============================================================
   Styles trang Valuation - inline
   ============================================================ */
.val-layout { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; align-items:start; }
@media (max-width: 900px) { .val-layout { grid-template-columns:1fr; } }

/* Steps */
.val-step { margin-bottom:1rem; }
.val-step-header { display:flex; align-items:flex-start; gap:0.875rem; padding:1rem 1.25rem 0; }
.val-step-num { display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; background:var(--primary-subtle); color:var(--primary-blue); font-size:0.8rem; font-weight:700; flex-shrink:0; margin-top:2px; }
.val-step-title { font-weight:600; font-size:0.95rem; margin-bottom:0.15rem; }
.val-step-desc { font-size:0.8rem; color:var(--text-secondary); }

/* Device info mini card */
.device-info-card { display:flex; flex-direction:column; gap:0.5rem; padding:0.875rem; background:var(--bg-elevated); border-radius:var(--radius-md); margin-top:0.5rem; }
.device-info-row { display:flex; justify-content:space-between; font-size:0.875rem; }
.device-info-label { color:var(--text-secondary); }

/* Battery */
.battery-input-wrap { display:flex; flex-direction:column; gap:0.75rem; }
.battery-range { width:100%; accent-color:var(--primary-blue); cursor:pointer; }
.battery-display { display:flex; align-items:center; gap:0.5rem; }
.battery-number { width:80px !important; text-align:center; }
.battery-unit { color:var(--text-secondary); font-weight:500; }
.battery-indicator { display:flex; align-items:center; gap:0.75rem; margin-top:0.25rem; }
.battery-bar-wrap { flex:1; height:8px; background:var(--bg-elevated); border-radius:4px; overflow:hidden; }
.battery-bar { height:100%; border-radius:4px; background:var(--success); transition:width 0.3s ease, background 0.3s ease; }
.battery-hint { font-size:0.8rem; color:var(--text-secondary); white-space:nowrap; }

/* Rules checklist */
.rules-checklist { display:flex; flex-direction:column; gap:0.5rem; }
.rule-check-item { display:flex; align-items:center; gap:0.75rem; padding:0.625rem 0.875rem; border:1px solid var(--border-subtle); border-radius:var(--radius-md); cursor:pointer; transition:border-color var(--transition-fast), background var(--transition-fast); }
.rule-check-item:hover { border-color:var(--border-default); background:var(--bg-hover); }
.rule-check-item input { flex-shrink:0; accent-color:var(--primary-blue); width:16px; height:16px; cursor:pointer; }
.rule-check-item:has(input:checked) { border-color:var(--danger); background:var(--danger-subtle); }
.rule-check-content { display:flex; justify-content:space-between; align-items:center; flex:1; gap:0.5rem; }
.rule-check-name { font-size:0.875rem; }
.rule-check-pct,.deduction-badge-sm { font-size:0.75rem; font-weight:600; padding:0.2rem 0.5rem; border-radius:var(--radius-full); background:var(--danger-subtle); color:var(--danger); white-space:nowrap; }
.rules-total { margin-top:0.75rem; padding:0.5rem 0.875rem; background:var(--danger-subtle); border-radius:var(--radius-md); font-size:0.875rem; color:var(--danger); }

/* Main valuate button */
.btn-valuation { width:100%; padding:0.875rem; font-size:1rem; display:flex; align-items:center; justify-content:center; gap:0.5rem; flex-wrap:wrap; border-radius:var(--radius-md); margin-top:0.5rem; }
.btn-sub { font-size:0.7rem; opacity:0.7; font-weight:400; }

/* Result panel */
.val-placeholder { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:3rem 1.5rem; text-align:center; }
.val-placeholder-text { color:var(--text-secondary); margin-top:1rem; line-height:1.6; }

/* AI Loading */
.val-loading { display:flex; flex-direction:column; align-items:center; padding:3rem 1.5rem; }
.ai-loading-animation { position:relative; width:64px; height:64px; margin-bottom:1.25rem; }
.ai-pulse { position:absolute; inset:0; border-radius:50%; background:var(--primary-blue); animation:pulse 1.4s ease-in-out infinite; }
.ai-pulse-ring { position:absolute; inset:-8px; border-radius:50%; border:2px solid var(--primary-blue); opacity:0; animation:pulse-ring 1.4s ease-in-out infinite 0.35s; }
@keyframes pulse { 0%,100%{transform:scale(0.9);opacity:0.6} 50%{transform:scale(1);opacity:1} }
@keyframes pulse-ring { 0%{opacity:0.6;transform:scale(0.9)} 100%{opacity:0;transform:scale(1.6)} }
.val-loading-text { font-weight:600; color:var(--text-light); }
.val-loading-sub { font-size:0.8rem; color:var(--text-secondary); margin-top:0.25rem; }

/* Result */
.val-result { padding:1.25rem; }
.result-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.25rem; }
.result-ai-badge { display:inline-flex; align-items:center; gap:0.3rem; font-size:0.7rem; font-weight:600; padding:0.2rem 0.5rem; background:var(--primary-subtle); color:var(--primary-blue); border-radius:var(--radius-full); margin-bottom:0.35rem; }
.result-device-name { font-size:1rem; font-weight:600; }
.result-price-card { background:var(--bg-elevated); border:1px solid var(--border-primary); border-radius:var(--radius-lg); padding:1.5rem; text-align:center; margin-bottom:1.25rem; }
.result-price-label { font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.05em; }
.result-price-value { font-size:2.25rem; font-weight:800; font-family:var(--font-display); color:var(--primary-blue); line-height:1.2; margin:0.5rem 0; }
.result-reasoning { font-size:0.8rem; color:var(--text-secondary); line-height:1.5; }
.result-breakdown { margin-bottom:1.25rem; }
.result-breakdown-title { font-size:0.8rem; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.5rem; }
.result-breakdown-rows { display:flex; flex-direction:column; gap:0.35rem; }
.breakdown-row { display:flex; justify-content:space-between; font-size:0.85rem; padding:0.4rem 0; border-bottom:1px solid var(--border-subtle); }
.breakdown-row:last-child { border-bottom:none; }
.breakdown-label { color:var(--text-secondary); }
.breakdown-value { font-weight:500; }
.breakdown-deduct { color:var(--danger); }
.result-actions { display:flex; flex-direction:column; gap:0.75rem; }
.result-done { display:flex; flex-direction:column; align-items:center; padding:1.5rem; }
.result-done-icon { font-size:2.5rem; margin-bottom:0.5rem; }
.result-done-msg { font-weight:600; color:var(--text-secondary); }

/* Purchase modal summary */
.purchase-summary { background:var(--bg-elevated); border-radius:var(--radius-md); padding:0.875rem 1rem; margin-bottom:1.25rem; }
.purchase-summary-row { display:flex; justify-content:space-between; font-size:0.9rem; padding:0.3rem 0; }
.form-section-title { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-secondary); margin-bottom:0.75rem; padding-top:0.75rem; border-top:1px solid var(--border-subtle); }

/* Upload zone */
.upload-zone { border:2px dashed var(--border-default); border-radius:var(--radius-md); padding:1.5rem; text-align:center; cursor:pointer; transition:border-color var(--transition-fast); font-size:0.85rem; color:var(--text-secondary); }
.upload-zone:hover { border-color:var(--primary-blue); }
.upload-sub { display:block; font-size:0.75rem; margin-top:0.25rem; }
.upload-previews { display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:0.75rem; }
.upload-preview-img { width:60px; height:60px; object-fit:cover; border-radius:var(--radius-sm); border:1px solid var(--border-default); }

/* Input with lookup */
.input-with-btn { position:relative; }
.lookup-spinner { position:absolute; right:12px; top:50%; transform:translateY(-50%); }
.spinner-xs { width:16px; height:16px; border:2px solid var(--border-default); border-top-color:var(--primary-blue); border-radius:50%; animation:spin 0.7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>

<script>
/* ============================================================
   valuation.js - Inline (UC11/UC12/UC13)
   ============================================================ */

const API = {
    models:    '../api/devices.php?action=get_models&brand_id=',
    valuate:   '../api/ai_valuation.php',
    confirm:   'valuation.php',
    lookup:    '../api/account.php?action=lookup_customer&phone=',
};

let currentSessionId = null;
let currentPrice     = null;
let currentDevice    = null;
let lookupTimer      = null;

/* ---- Hiển thị / Ẩn Loading ---- */
function showLoading() {
    document.getElementById('val-placeholder').style.display = 'none';
    document.getElementById('val-result').style.display      = 'none';
    document.getElementById('val-loading').style.display     = 'flex';
}
function showResult() {
    document.getElementById('val-loading').style.display     = 'none';
    document.getElementById('val-result').style.display      = 'block';
}
function showPlaceholder() {
    document.getElementById('val-loading').style.display     = 'none';
    document.getElementById('val-result').style.display      = 'none';
    document.getElementById('val-placeholder').style.display = 'flex';
}

/* ---- Load models theo brand ---- */
document.getElementById('brand-select').addEventListener('change', function() {
    const brandId = this.value;
    const modelSelect = document.getElementById('model-select');
    document.getElementById('device-info').style.display = 'none';
    document.getElementById('btn-valuate').disabled = true;

    if (!brandId) {
        modelSelect.innerHTML = '<option value="">— Chọn hãng trước —</option>';
        modelSelect.disabled = true;
        return;
    }

    modelSelect.disabled = true;
    modelSelect.innerHTML = '<option>Đang tải...</option>';

    fetch(API.models + brandId)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.models.length > 0) {
                modelSelect.innerHTML = '<option value="">— Chọn mẫu máy —</option>' +
                    data.models.map(m =>
                        `<option value="${m.model_id}"
                                 data-ram="${m.ram_gb}"
                                 data-rom="${m.rom_gb}"
                                 data-price="${m.base_price}">
                            ${m.model_name} (${m.ram_gb}GB/${m.rom_gb}GB)
                         </option>`
                    ).join('');
                modelSelect.disabled = false;
            } else {
                modelSelect.innerHTML = '<option>Không có mẫu nào</option>';
            }
        })
        .catch(() => { modelSelect.innerHTML = '<option>Lỗi tải dữ liệu</option>'; });
});

/* ---- Hiển thị thông tin model ---- */
document.getElementById('model-select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) {
        document.getElementById('device-info').style.display = 'none';
        document.getElementById('btn-valuate').disabled = true;
        return;
    }
    document.getElementById('info-ram').textContent        = opt.dataset.ram + 'GB';
    document.getElementById('info-rom').textContent        = opt.dataset.rom + 'GB';
    document.getElementById('info-base-price').textContent = formatVND(opt.dataset.price);
    document.getElementById('device-info').style.display   = 'flex';
    document.getElementById('btn-valuate').disabled        = false;
});

/* ---- Battery range ↔ number sync ---- */
const batteryRange = document.getElementById('battery-range');
const batteryInput = document.getElementById('battery-input');

function updateBatteryUI(val) {
    val = Math.min(100, Math.max(1, parseInt(val) || 80));
    batteryRange.value = val;
    batteryInput.value = val;
    const bar = document.getElementById('battery-bar');
    const hint = document.getElementById('battery-hint');
    bar.style.width = val + '%';
    if (val >= 80) {
        bar.style.background = 'var(--success)';
        hint.textContent = 'Pin tốt';
        hint.style.color = 'var(--success)';
    } else if (val >= 60) {
        bar.style.background = 'var(--warning)';
        hint.textContent = 'Pin trung bình';
        hint.style.color = 'var(--warning)';
    } else {
        bar.style.background = 'var(--danger)';
        hint.textContent = val < 40 ? 'Pin yếu, cần thay' : 'Pin thấp';
        hint.style.color = 'var(--danger)';
    }
}

batteryRange.addEventListener('input', () => updateBatteryUI(batteryRange.value));
batteryInput.addEventListener('input', () => updateBatteryUI(batteryInput.value));
updateBatteryUI(80);

/* ---- Rules checklist ---- */
document.querySelectorAll('.rule-checkbox').forEach(cb => {
    cb.addEventListener('change', updateRulesTotal);
});

function updateRulesTotal() {
    const checked = document.querySelectorAll('.rule-checkbox:checked');
    const total = Array.from(checked).reduce((sum, cb) => sum + parseFloat(cb.dataset.pct), 0);
    const totalEl = document.getElementById('rules-total');
    if (total > 0) {
        document.getElementById('rules-total-pct').textContent = total.toFixed(1) + '%';
        totalEl.style.display = 'block';
    } else {
        totalEl.style.display = 'none';
    }
}

/* ---- GỌI AI ĐỊNH GIÁ ---- */
document.getElementById('btn-valuate').addEventListener('click', function() {
    const modelId       = document.getElementById('model-select').value;
    const batteryHealth = document.getElementById('battery-input').value;
    const ruleIds       = Array.from(document.querySelectorAll('.rule-checkbox:checked')).map(cb => cb.value);
    const modelText     = document.getElementById('model-select').options[document.getElementById('model-select').selectedIndex].text;

    if (!modelId) { alert('Vui lòng chọn mẫu thiết bị.'); return; }

    this.disabled = true;
    showLoading();

    const formData = new FormData();
    formData.append('model_id',       modelId);
    formData.append('battery_health', batteryHealth);
    ruleIds.forEach(id => formData.append('rule_ids[]', id));

    fetch(API.valuate, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            document.getElementById('btn-valuate').disabled = false;
            if (!data.success) {
                showPlaceholder();
                alert('Lỗi: ' + (data.message || 'Không thể kết nối AI.'));
                return;
            }

            currentSessionId = data.session_id;
            currentPrice     = data.price;
            currentDevice    = data.device_name;

            // Populate result
            document.getElementById('result-device-name').textContent = data.device_name;
            document.getElementById('result-price').textContent       = data.price_formatted;
            document.getElementById('result-reasoning').textContent   = data.reasoning;

            // Breakdown rows
            const modelOpt = document.getElementById('model-select').options[document.getElementById('model-select').selectedIndex];
            const basePrice = parseInt(modelOpt.dataset.price);
            const rows = [];
            rows.push({ label: 'Giá cơ sở', value: formatVND(basePrice), cls: '' });
            document.querySelectorAll('.rule-checkbox:checked').forEach(cb => {
                const label = cb.parentElement.querySelector('.rule-check-name').textContent;
                rows.push({ label: '− ' + label, value: '−' + parseFloat(cb.dataset.pct).toFixed(1) + '%', cls: 'breakdown-deduct' });
            });
            const bat = parseInt(batteryHealth);
            if (bat < 80) {
                const dedPct = ((80 - bat) * 0.3).toFixed(1);
                rows.push({ label: `− Chai pin (${bat}%)`, value: `−${dedPct}%`, cls: 'breakdown-deduct' });
            }
            rows.push({ label: 'Giá đề xuất AI', value: data.price_formatted, cls: 'text-primary' });

            document.getElementById('breakdown-rows').innerHTML = rows.map(r =>
                `<div class="breakdown-row">
                    <span class="breakdown-label">${r.label}</span>
                    <span class="breakdown-value ${r.cls}">${r.value}</span>
                 </div>`
            ).join('');

            document.getElementById('result-actions').style.display = 'flex';
            document.getElementById('result-done').style.display    = 'none';
            showResult();
        })
        .catch(err => {
            document.getElementById('btn-valuate').disabled = false;
            showPlaceholder();
            console.error(err);
            alert('Lỗi kết nối. Vui lòng thử lại.');
        });
});

/* ---- Reset ---- */
function resetValuation() {
    currentSessionId = null;
    currentPrice     = null;
    currentDevice    = null;
    showPlaceholder();
}

/* ---- Mở modal thu mua ---- */
function openPurchaseModal() {
    document.getElementById('pm-device-name').textContent = currentDevice;
    document.getElementById('pm-price').textContent       = formatVND(currentPrice);
    document.getElementById('pm-imei').value              = '';
    document.getElementById('pm-phone').value             = '';
    document.getElementById('pm-cname').value             = '';
    document.getElementById('customer-lookup-hint').textContent = '';
    document.getElementById('upload-previews').innerHTML  = '';
    document.getElementById('purchase-modal').classList.add('visible');
    setTimeout(() => document.getElementById('pm-imei').focus(), 100);
}

function closePurchaseModal(e) {
    if (e && e.target !== document.getElementById('purchase-modal')) return;
    document.getElementById('purchase-modal').classList.remove('visible');
}

/* ---- Lookup khách hàng ---- */
function lookupCustomer(phone) {
    clearTimeout(lookupTimer);
    const hint = document.getElementById('customer-lookup-hint');
    if (phone.length < 10) { hint.textContent = ''; return; }

    document.getElementById('lookup-spinner').style.display = 'block';
    lookupTimer = setTimeout(() => {
        fetch(API.lookup + encodeURIComponent(phone))
            .then(r => r.json())
            .then(data => {
                document.getElementById('lookup-spinner').style.display = 'none';
                if (data.found) {
                    document.getElementById('pm-cname').value = data.full_name;
                    hint.textContent = '✓ Khách hàng cũ: ' + data.full_name;
                    hint.style.color = 'var(--success)';
                } else {
                    hint.textContent = '+ Khách hàng mới sẽ được tạo';
                    hint.style.color = 'var(--text-secondary)';
                }
            })
            .catch(() => { document.getElementById('lookup-spinner').style.display = 'none'; });
    }, 500);
}

/* ---- Preview ảnh ---- */
function previewImages(input) {
    const container = document.getElementById('upload-previews');
    container.innerHTML = '';
    Array.from(input.files).slice(0, 5).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'upload-preview-img';
            container.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

/* ---- Submit thu mua ---- */
function submitPurchase() {
    const imei  = document.getElementById('pm-imei').value.trim();
    const phone = document.getElementById('pm-phone').value.trim();
    const cname = document.getElementById('pm-cname').value.trim();

    if (!imei || !phone || !cname) { alert('Vui lòng điền đầy đủ thông tin.'); return; }
    if (!/^\d{15}$/.test(imei))    { alert('IMEI phải gồm đúng 15 chữ số.'); return; }

    const btn = document.getElementById('btn-confirm-submit');
    btn.disabled = true;
    btn.textContent = 'Đang xử lý...';

    const fd = new FormData();
    fd.append('action',         'confirm_purchase');
    fd.append('session_id',     currentSessionId);
    fd.append('imei',           imei);
    fd.append('customer_name',  cname);
    fd.append('customer_phone', phone);

    // Gắn ảnh nếu có
    const images = document.getElementById('pm-images').files;
    Array.from(images).forEach(f => fd.append('images[]', f));

    fetch('valuation.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Xác nhận & Nhập kho';

            if (data.success) {
                closePurchaseModal();
                document.getElementById('result-actions').style.display = 'none';
                document.getElementById('result-done-icon').textContent = '✓';
                document.getElementById('result-done-icon').style.color = 'var(--success)';
                document.getElementById('result-done-msg').textContent  = 'Thu mua thành công! IMEI: ' + data.imei;
                document.getElementById('result-done').style.display    = 'flex';
            } else {
                alert('Lỗi: ' + data.message);
            }
        })
        .catch(() => {
            btn.disabled = false;
            alert('Lỗi kết nối. Vui lòng thử lại.');
        });
}

/* ---- Từ chối ---- */
function declineSession() {
    if (!confirm('Xác nhận khách từ chối bán với mức giá này?')) return;

    const fd = new FormData();
    fd.append('action',     'decline');
    fd.append('session_id', currentSessionId);

    fetch('valuation.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('result-actions').style.display = 'none';
                document.getElementById('result-done-icon').textContent = '✕';
                document.getElementById('result-done-icon').style.color = 'var(--danger)';
                document.getElementById('result-done-msg').textContent  = 'Đã ghi nhận từ chối.';
                document.getElementById('result-done').style.display    = 'flex';
            } else {
                alert('Lỗi: ' + data.message);
            }
        });
}

/* ---- ESC ---- */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePurchaseModal();
});
</script>