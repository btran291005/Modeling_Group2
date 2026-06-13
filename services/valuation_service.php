<?php

declare(strict_types=1);
 
class ValuationService
{
    // ──────────────────────────────────────────────────────────
    // CONSTRUCTOR
    // ──────────────────────────────────────────────────────────
 
    /**
     * @param PDO $pdo  Instance kết nối database đã khởi tạo sẵn
     */
    public function __construct(private PDO $pdo) {}
 
 
    // ══════════════════════════════════════════════════════════
    // NHÓM 1: CHUẨN BỊ DỮ LIỆU CHO FORM ĐỊNH GIÁ
    // ══════════════════════════════════════════════════════════
 
    /**
     * Lấy danh sách hãng (brands) để render dropdown "Chọn hãng".
     *
     * Logic (CHƯA IMPLEMENT):
     *   SELECT brand_id, brand_name FROM brands ORDER BY brand_name
     *
     * @return array[]  Mảng các ['brand_id', 'brand_name']
     *
     * @example
     *   $brands = $service->getBrands();
     *   // [['brand_id' => 1, 'brand_name' => 'Apple'], ...]
     */
    public function getBrands(): array
    {
        // TODO: implement
        throw new RuntimeException('Not implemented');
    }
 
    /**
     * Lấy danh sách model theo hãng để render dropdown "Chọn mẫu máy".
     *
     * Logic (CHƯA IMPLEMENT):
     *   SELECT model_id, model_name, ram_gb, rom_gb, base_price
     *   FROM device_models WHERE brand_id = :brand_id ORDER BY model_name
     *
     * @param  int     $brandId  ID hãng sản xuất (validated trước khi truyền vào)
     * @return array[]           Mảng các model thuộc hãng
     *
     * @throws InvalidArgumentException  Nếu $brandId <= 0
     */
    public function getModelsByBrand(int $brandId): array
    {
        // TODO: validate $brandId > 0
        // TODO: implement query
        throw new RuntimeException('Not implemented');
    }
 
    /**
     * Lấy toàn bộ quy tắc AI đang bật (is_active = 1).
     * Dùng để render checklist "Tình trạng vật lý" cho Staff.
     *
     * Logic (CHƯA IMPLEMENT):
     *   SELECT rule_id, condition_name, deduction_percent
     *   FROM ai_pricing_rules WHERE is_active = 1
     *   ORDER BY deduction_percent DESC
     *
     * @return array[]  Mảng các rule đang hoạt động
     */
    public function getActiveRules(): array
    {
        // TODO: implement
        throw new RuntimeException('Not implemented');
    }
 
 
    // ══════════════════════════════════════════════════════════
    // NHÓM 2: ĐỊNH GIÁ AI (UC11 / UC12)
    // ══════════════════════════════════════════════════════════
 
    /**
     * Thực hiện định giá AI và lưu kết quả vào DB (UC11 + UC12).
     *
     * Đây là hàm TRUNG TÂM của tính năng định giá.
     *
     * Logic (CHƯA IMPLEMENT):
     *   1. Validate input: $modelId > 0, $batteryHealth 0–100.
     *   2. Query thông tin thiết bị (brand, model, ram, rom, base_price).
     *      → Throw nếu không tìm thấy model.
     *   3. Query các rule đang bật, lọc theo $ruleIds nếu có.
     *   4. Gọi getAISuggestedPrice($deviceInfo, $activeRules) từ ai_module.php.
     *      → Nếu AI fail, ai_module tự fallback sang công thức nội bộ.
     *   5. Dùng Transaction:
     *      a. INSERT INTO valuation_sessions (Pending, chưa có customer_id).
     *      b. INSERT INTO session_rule_details cho mỗi rule đã áp dụng.
     *      c. INSERT INTO audit_logs.
     *   6. Commit và trả về mảng kết quả.
     *
     * @param  int   $userId        ID Staff thực hiện định giá ($_SESSION['user_id'])
     * @param  int   $modelId       ID model thiết bị
     * @param  int   $batteryHealth Phần trăm pin (1–100)
     * @param  int[] $ruleIds       Mảng rule_id Staff đã tích chọn (có thể rỗng)
     *
     * @return array {
     *   session_id:      int,
     *   price:           int,
     *   price_formatted: string,
     *   reasoning:       string,
     *   device_name:     string,
     *   battery_health:  int,
     *   rules_applied:   int,
     *   fallback:        bool,
     * }
     *
     * @throws InvalidArgumentException  Nếu input không hợp lệ
     * @throws RuntimeException          Nếu model không tồn tại hoặc DB lỗi
     */
    public function valuate(int $userId, int $modelId, int $batteryHealth, array $ruleIds = []): array
    {
        // TODO: step 1 — validate
        // TODO: step 2 — query device
        // TODO: step 3 — query rules
        // TODO: step 4 — call AI module
        // TODO: step 5 — DB transaction
        // TODO: step 6 — return result array
        throw new RuntimeException('Not implemented');
    }
 
 
    // ══════════════════════════════════════════════════════════
    // NHÓM 3: XÁC NHẬN THU MUA (UC13)
    // ══════════════════════════════════════════════════════════
 
    /**
     * Xác nhận thu mua: tạo/tìm khách hàng, nhập kho, cập nhật session.
     *
     * Logic (CHƯA IMPLEMENT):
     *   1. Validate: imei đúng 15 số, phone hợp lệ, tên không rỗng.
     *   2. Kiểm tra session: session_id tồn tại, user_id = $staffId, status = Pending.
     *      → Throw nếu không hợp lệ.
     *   3. Kiểm tra IMEI chưa tồn tại trong bảng gadgets.
     *      → Throw nếu trùng.
     *   4. Dùng Transaction:
     *      a. SELECT customer_id FROM customers WHERE phone = $phone.
     *         - Tồn tại → dùng luôn.
     *         - Chưa có → INSERT customer mới (R02: upsert by phone).
     *      b. UPDATE valuation_sessions SET final_status='Purchased', customer_id=X.
     *      c. INSERT INTO gadgets (imei, session_id, status='Stored').
     *      d. INSERT INTO audit_logs.
     *      e. INSERT INTO notifications cho tất cả Admin.
     *   5. Commit và trả về mảng kết quả.
     *
     * @param  int    $staffId     ID Staff thực hiện ($_SESSION['user_id'])
     * @param  int    $sessionId   ID phiên định giá cần xác nhận
     * @param  string $imei        IMEI 15 số
     * @param  string $customerName  Tên khách hàng
     * @param  string $customerPhone Số điện thoại (định dạng VN)
     *
     * @return array {
     *   imei:        string,
     *   customer_id: int,
     *   session_id:  int,
     * }
     *
     * @throws InvalidArgumentException  Nếu imei / phone không hợp lệ
     * @throws RuntimeException          Nếu session không hợp lệ, IMEI trùng, hoặc DB lỗi
     */
    public function confirmPurchase(
        int    $staffId,
        int    $sessionId,
        string $imei,
        string $customerName,
        string $customerPhone
    ): array {
        // TODO: step 1 — validate imei, phone, name
        // TODO: step 2 — verify session ownership & Pending status
        // TODO: step 3 — check IMEI uniqueness
        // TODO: step 4 — DB transaction (upsert customer, update session, insert gadget, audit, notify)
        // TODO: step 5 — return result
        throw new RuntimeException('Not implemented');
    }
 
 
    // ══════════════════════════════════════════════════════════
    // NHÓM 4: TỪ CHỐI VÀ LỊCH SỬ
    // ══════════════════════════════════════════════════════════
 
    /**
     * Từ chối phiên định giá (khách không đồng ý giá).
     *
     * Logic (CHƯA IMPLEMENT):
     *   1. Verify: session thuộc về $staffId và đang ở status Pending.
     *   2. UPDATE valuation_sessions SET final_status = 'Declined'.
     *   3. INSERT INTO audit_logs.
     *
     * @param  int $staffId    ID Staff thực hiện
     * @param  int $sessionId  ID phiên cần từ chối
     *
     * @return array { session_id: int, new_status: 'Declined' }
     *
     * @throws RuntimeException  Nếu session không hợp lệ / không thuộc Staff này
     */
    public function declineSession(int $staffId, int $sessionId): array
    {
        // TODO: verify ownership & Pending status
        // TODO: UPDATE + audit log
        throw new RuntimeException('Not implemented');
    }
 
    /**
     * Lấy lịch sử định giá của 1 Staff (UC14).
     *
     * Logic (CHƯA IMPLEMENT):
     *   JOIN valuation_sessions ↔ device_models ↔ brands ↔ customers ↔ gadgets
     *   WHERE user_id = $staffId
     *   OPTIONAL: filter by $status, $search (model name / imei)
     *   ORDER BY created_at DESC
     *   LIMIT + OFFSET cho phân trang
     *
     * @param  int    $staffId  ID Staff
     * @param  int    $page     Trang hiện tại (bắt đầu từ 1)
     * @param  int    $perPage  Số bản ghi/trang
     * @param  string $status   Filter: '' | 'Pending' | 'Purchased' | 'Declined'
     * @param  string $search   Từ khóa tìm kiếm (model name, imei)
     *
     * @return array {
     *   sessions: array[],   // Danh sách phiên
     *   total:    int,        // Tổng số bản ghi (để tính phân trang)
     *   stats:    array,      // { total, purchased, declined, pending, revenue }
     * }
     */
    public function getHistory(
        int    $staffId,
        int    $page    = 1,
        int    $perPage = 15,
        string $status  = '',
        string $search  = ''
    ): array {
        // TODO: build dynamic WHERE clause
        // TODO: COUNT query cho total
        // TODO: SELECT query với LIMIT/OFFSET
        // TODO: stats subquery
        throw new RuntimeException('Not implemented');
    }
 
    /**
     * Lấy tất cả phiên định giá toàn hệ thống (dành cho Admin — valuation_log).
     *
     * Logic (CHƯA IMPLEMENT):
     *   Tương tự getHistory() nhưng KHÔNG filter theo user_id,
     *   thêm filter: $staffId (0 = tất cả), $dateFrom, $dateTo.
     *
     * @param  int    $page
     * @param  int    $perPage
     * @param  string $status
     * @param  string $search
     * @param  int    $staffId   0 = không filter theo Staff
     * @param  string $dateFrom  'YYYY-MM-DD' hoặc ''
     * @param  string $dateTo    'YYYY-MM-DD' hoặc ''
     *
     * @return array { sessions: array[], total: int, stats: array }
     */
    public function getAllSessions(
        int    $page     = 1,
        int    $perPage  = 20,
        string $status   = '',
        string $search   = '',
        int    $staffId  = 0,
        string $dateFrom = '',
        string $dateTo   = ''
    ): array {
        // TODO: implement với filter đa chiều
        throw new RuntimeException('Not implemented');
    }
}