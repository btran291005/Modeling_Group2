<?php
// ============================================================
// FILE: staff/valuation.php
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('Staff');

renderHeader('Định giá thiết bị');
?>

<h2 class="mb-1">⚡ Định giá thiết bị mới</h2>
<p class="text-muted mb-4">Chọn thiết bị, nhập tình trạng và để AI tự động đề xuất giá thu mua.</p>

<div class="row g-3">

    <!-- ===================== CỘT TRÁI: FORM ===================== -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Thông tin thiết bị</div>
            <div class="card-body">

                <form id="valuation-form">

                    <!-- Hãng -->
                    <div class="mb-3">
                        <label class="form-label">Hãng sản xuất <span class="text-danger">*</span></label>
                        <select id="brand-select" class="form-select" required>
                            <option value="">-- Chọn hãng --</option>
                        </select>
                    </div>

                    <!-- Dòng máy -->
                    <div class="mb-3">
                        <label class="form-label">Dòng máy <span class="text-danger">*</span></label>
                        <select id="model-select" class="form-select" required disabled>
                            <option value="">-- Chọn hãng trước --</option>
                        </select>
                    </div>

                    <!-- Cấu hình (hiển thị tham khảo từ model) -->
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label">RAM</label>
                            <input type="text" id="info-ram" class="form-control" readonly placeholder="—">
                        </div>
                        <div class="col-4">
                            <label class="form-label">ROM</label>
                            <input type="text" id="info-rom" class="form-control" readonly placeholder="—">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Giá cơ sở</label>
                            <input type="text" id="info-base-price" class="form-control" readonly placeholder="—">
                        </div>
                    </div>

                    <hr>

                    <!-- Tình trạng pin -->
                    <div class="mb-3">
                        <label class="form-label">Tình trạng pin (%) <span class="text-danger">*</span></label>
                        <input type="number" id="battery-health" class="form-control"
                               min="1" max="100" value="80" required>
                    </div>

                    <!-- Mức độ trầy xước -->
                    <div class="mb-3">
                        <label class="form-label">Mức độ trầy xước / ngoại hình</label>
                        <select id="scratch-level" class="form-select">
                            <option value="0">Như mới — không trầy xước</option>
                            <option value="1">Trầy xước nhẹ</option>
                            <option value="2">Trầy xước nặng / móp nhẹ</option>
                            <option value="3">Vỡ/nứt màn hình hoặc vỏ</option>
                        </select>
                    </div>

                    <!-- Quy tắc khấu trừ AI (checklist động) -->
                    <div class="mb-3">
                        <label class="form-label">Quy tắc khấu trừ áp dụng</label>
                        <div id="rules-checklist" class="border rounded p-2" style="max-height:160px; overflow-y:auto;">
                            <div class="text-muted small">Đang tải quy tắc...</div>
                        </div>
                    </div>

                    <button type="button" id="btn-run-ai" class="btn btn-primary w-100" disabled>
                        🤖 Chạy AI Định Giá
                    </button>

                </form>

            </div>
        </div>
    </div>

    <!-- ===================== CỘT PHẢI: KẾT QUẢ ===================== -->
    <div class="col-md-6">

        <!-- Placeholder -->
        <div class="card h-100" id="result-placeholder">
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center text-muted">
                <div style="font-size:3rem;">🤖</div>
                <p class="mt-2">Điền thông tin thiết bị và nhấn<br><strong>Chạy AI Định Giá</strong> để xem kết quả.</p>
            </div>
        </div>

        <!-- Kết quả -->
        <div class="card d-none" id="result-box">
            <div class="card-header">Kết quả định giá AI</div>
            <div class="card-body">

                <div class="text-center mb-3">
                    <div class="text-muted text-uppercase small">Giá thu mua đề xuất</div>
                    <div class="display-6 fw-bold text-primary" id="result-price">—</div>
                    <p class="text-muted small mt-2" id="result-reasoning"></p>
                </div>

                <hr>

                <!-- Thông tin khách hàng -->
                <div class="mb-2">
                    <label class="form-label">Số điện thoại khách hàng <span class="text-danger">*</span></label>
                    <input type="text" id="customer-phone" class="form-control" placeholder="09xxxxxxxx">
                </div>
                <div class="mb-3">
                    <label class="form-label">Tên khách hàng <span class="text-danger">*</span></label>
                    <input type="text" id="customer-name" class="form-control" placeholder="Nguyễn Văn A">
                </div>

                <!-- IMEI -->
                <div class="mb-3">
                    <label class="form-label">Số IMEI <span class="text-danger">*</span></label>
                    <input type="text" id="device-imei" class="form-control" maxlength="15" placeholder="15 chữ số">
                </div>

                <div class="d-flex gap-2">
                    <button type="button" id="btn-confirm" class="btn btn-success w-100" disabled>
                        ✅ Chốt thu mua &amp; Nhập kho
                    </button>
                    <button type="button" id="btn-decline" class="btn btn-outline-danger" disabled>
                        ❌ Từ chối
                    </button>
                </div>

                <div class="alert alert-success mt-3 d-none" id="result-done"></div>

            </div>
        </div>

    </div>

</div>

<?php
renderFooter();
?>
<script src="../assets/js/staff_app.js"></script>