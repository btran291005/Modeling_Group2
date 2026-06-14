<?php

declare(strict_types=1);

/**
 * services/ValuationService.php
 *
 * Toàn bộ business logic cho tính năng Định giá & Thu mua thiết bị.
 * Nguyên tắc:
 *   - Chỉ nhận input, gọi DB, trả về array. Không echo, không header().
 *   - Ném exception khi lỗi nghiệp vụ → API layer bắt và trả json_err.
 *   - Dùng PDO prepared statement cho mọi truy vấn có tham số.
 *   - Mọi thao tác ghi nhiều bảng đều bọc trong transaction.
 */
class ValuationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    // ═══════════════════════════════════════════════════════════
    // NHÓM 1: DỮ LIỆU THAM CHIẾU
    // ═══════════════════════════════════════════════════════════

    /**
     * Lấy danh sách hãng để render dropdown.
     *
     * @return array<int, array{brand_id:int, brand_name:string}>
     */
    public function getBrands(): array
    {
        return $this->pdo
            ->query(
                "SELECT brand_id, brand_name
                 FROM brands
                 ORDER BY brand_name ASC"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách model theo hãng.
     *
     * @return array<int, array{model_id:int, model_name:string, ram_gb:int, rom_gb:int, base_price:float}>
     * @throws InvalidArgumentException Nếu brand_id <= 0
     */
    public function getModelsByBrand(int $brandId): array
    {
        if ($brandId <= 0) {
            throw new InvalidArgumentException('brand_id không hợp lệ.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT dm.model_id,
                    dm.model_name,
                    dm.ram_gb,
                    dm.rom_gb,
                    dm.base_price
             FROM   device_models dm
             WHERE  dm.brand_id = :bid
             ORDER  BY dm.model_name ASC"
        );
        $stmt->execute([':bid' => $brandId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách quy tắc AI đang bật (is_active = 1).
     * Dùng để render checklist tình trạng vật lý trên giao diện Staff.
     *
     * @return array<int, array{rule_id:int, condition_name:string, deduction_percent:float}>
     */
    public function getActiveRules(): array
    {
        return $this->pdo
            ->query(
                "SELECT rule_id,
                        condition_name,
                        deduction_percent
                 FROM   ai_pricing_rules
                 WHERE  is_active = 1
                 ORDER  BY condition_name ASC"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }


    // ═══════════════════════════════════════════════════════════
    // NHÓM 2: ĐỊNH GIÁ AI – valuate()
    // ═══════════════════════════════════════════════════════════

    /**
     * Thực hiện định giá AI và lưu phiên vào DB.
     *
     * @param  int   $staffId       $_SESSION['user_id']
     * @param  int   $modelId       Model được Staff chọn
     * @param  int   $batteryHealth Phần trăm pin (1–100)
     * @param  int[] $ruleIds       Danh sách rule_id Staff tích chọn (có thể rỗng)
     *
     * @return array
     * @throws InvalidArgumentException Nếu model_id <= 0 hay battery_health ngoài phạm vi
     * @throws RuntimeException         Nếu model không tồn tại trong DB
     */
    public function valuate(
        int $staffId,
        int $modelId,
        int $batteryHealth,
        array $ruleIds = []
    ): array {
        // ── 1. Validate cơ bản ───────────────────────────────
        if ($modelId <= 0) {
            throw new InvalidArgumentException('model_id không hợp lệ.');
        }
        $batteryHealth = max(1, min(100, $batteryHealth)); // clamp 1–100

        // ── 2. Lấy thông tin thiết bị ────────────────────────
        $stmtModel = $this->pdo->prepare(
            "SELECT dm.model_id,
                    dm.model_name,
                    dm.ram_gb,
                    dm.rom_gb,
                    dm.base_price,
                    b.brand_name
             FROM   device_models dm
             JOIN   brands b ON b.brand_id = dm.brand_id
             WHERE  dm.model_id = :mid"
        );
        $stmtModel->execute([':mid' => $modelId]);
        $model = $stmtModel->fetch(PDO::FETCH_ASSOC);

        if (!$model) {
            throw new RuntimeException("Không tìm thấy thiết bị với model_id = {$modelId}.");
        }

        // ── 3. Lọc rule_ids – chỉ giữ rule active trong DB ──
        $validRules = [];
        if (!empty($ruleIds)) {
            $placeholders = implode(',', array_fill(0, count($ruleIds), '?'));
            $stmtRules = $this->pdo->prepare(
                "SELECT rule_id, condition_name, deduction_percent
                 FROM   ai_pricing_rules
                 WHERE  is_active = 1
                   AND  rule_id IN ({$placeholders})"
            );
            $stmtRules->execute(array_values($ruleIds));
            $validRules = $stmtRules->fetchAll(PDO::FETCH_ASSOC);
        }

        // ── 4. Gọi AI Module ─────────────────────────────────
        $aiInput = [
            'model_name'     => $model['model_name'],
            'brand_name'     => $model['brand_name'],
            'base_price'     => (float) $model['base_price'],
            'ram_gb'         => (int)   $model['ram_gb'],
            'rom_gb'         => (int)   $model['rom_gb'],
            'battery_health' => $batteryHealth,
        ];
        $aiResult = getAISuggestedPrice($aiInput, $validRules);
        $finalPrice = max(0.0, (float) $aiResult['price']);

        // ── 5. Lưu vào DB trong Transaction ──────────────────
        $this->pdo->beginTransaction();
        try {
            // 5a. INSERT valuation_sessions
            $stmtSession = $this->pdo->prepare(
                "INSERT INTO valuation_sessions
                    (user_id, model_id, battery_health, ai_suggested_price, final_status, created_at)
                 VALUES
                    (:user_id, :model_id, :battery, :price, 'Pending', NOW())"
            );
            $stmtSession->execute([
                ':user_id'  => $staffId,
                ':model_id' => $modelId,
                ':battery'  => $batteryHealth,
                ':price'    => $finalPrice,
            ]);
            $sessionId = (int) $this->pdo->lastInsertId();

            // 5b. INSERT session_rule_details (nếu có rule được chọn)
            if (!empty($validRules)) {
                $stmtLog = $this->pdo->prepare(
                    "INSERT INTO session_rule_details (session_id, rule_id)
                     VALUES (:session_id, :rule_id)"
                );
                foreach ($validRules as $rule) {
                    $stmtLog->execute([
                        ':session_id' => $sessionId,
                        ':rule_id'    => $rule['rule_id'],
                    ]);
                }
            }

            $this->pdo->commit();

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Lỗi lưu phiên định giá: ' . $e->getMessage());
        }

        // ── 6. Trả về kết quả ────────────────────────────────
        return [
            'session_id'      => $sessionId,
            'price'           => $finalPrice,
            'price_formatted' => number_format($finalPrice, 0, ',', '.') . ' ₫',
            'reasoning'       => $aiResult['reasoning']  ?? '',
            'fallback'        => (bool) ($aiResult['fallback'] ?? false),
            'device_name'     => $model['model_name'],
            'brand_name'      => $model['brand_name'],
            'battery_health'  => $batteryHealth,
            'rules_applied'   => $validRules,
        ];
    }


    // ═══════════════════════════════════════════════════════════
    // NHÓM 3: XÁC NHẬN THU MUA – confirmPurchase()
    // ═══════════════════════════════════════════════════════════

    /**
     * Xác nhận thu mua thiết bị và nhập kho.
     *
     * @param  int    $staffId       $_SESSION['user_id']
     * @param  int    $sessionId     ID phiên định giá cần xác nhận
     * @param  string $imei          IMEI thiết bị (15 chữ số)
     * @param  string $customerName  Tên khách bán
     * @param  string $customerPhone Số điện thoại khách
     *
     * @return array{session_id:int, imei:string, customer_id:int, inventory_id:string}
     * @throws InvalidArgumentException Nếu dữ liệu đầu vào không hợp lệ
     * @throws RuntimeException         Nếu session không hợp lệ, IMEI trùng, hoặc lỗi DB
     */
    public function confirmPurchase(
        int    $staffId,
        int    $sessionId,
        string $imei,
        string $customerName,
        string $customerPhone
    ): array {
        // ── 1. Validate & lấy thông tin session ──────────────
        if ($sessionId <= 0) {
            throw new InvalidArgumentException('session_id không hợp lệ.');
        }

        $stmtSess = $this->pdo->prepare(
            "SELECT vs.session_id,
                    vs.user_id,
                    vs.model_id,
                    vs.ai_suggested_price,
                    vs.final_status
             FROM   valuation_sessions vs
             WHERE  vs.session_id = :sid"
        );
        $stmtSess->execute([':sid' => $sessionId]);
        $session = $stmtSess->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            throw new RuntimeException('Phiên định giá không tồn tại.');
        }
        if ((int) $session['user_id'] !== $staffId) {
            throw new RuntimeException('Bạn không có quyền xác nhận phiên định giá này.');
        }
        if ($session['final_status'] !== 'Pending') {
            throw new RuntimeException(
                "Phiên định giá đã ở trạng thái '{$session['final_status']}', không thể xác nhận lại."
            );
        }

        // ── 2. Kiểm tra IMEI chưa tồn tại trong kho ─────────
        $stmtImei = $this->pdo->prepare(
            "SELECT imei FROM gadgets WHERE imei = :imei LIMIT 1"
        );
        $stmtImei->execute([':imei' => $imei]);
        if ($stmtImei->fetch()) {
            throw new RuntimeException("IMEI {$imei} đã tồn tại trong kho.");
        }

        // ── 3. UPSERT Customer ────────────────────────────────
        $stmtFindCust = $this->pdo->prepare(
            "SELECT customer_id FROM customers WHERE phone_number = :phone LIMIT 1"
        );
        $stmtFindCust->execute([':phone' => $customerPhone]);
        $existingCustomer = $stmtFindCust->fetch(PDO::FETCH_ASSOC);

        if ($existingCustomer) {
            $customerId = (int) $existingCustomer['customer_id'];
        } else {
            $stmtInsertCust = $this->pdo->prepare(
                "INSERT INTO customers (full_name, phone_number, created_at)
                 VALUES (:name, :phone, NOW())"
            );
            $stmtInsertCust->execute([
                ':name'  => $customerName,
                ':phone' => $customerPhone,
            ]);
            $customerId = (int) $this->pdo->lastInsertId();
        }

        // ── 4. Transaction: Ghi nhận & Nhập kho ──────────────
        $this->pdo->beginTransaction();
        try {
            // 4a. Cập nhật trạng thái session + gắn customer
            $stmtUpdateSess = $this->pdo->prepare(
                "UPDATE valuation_sessions
                 SET    final_status = 'Purchased', customer_id = :cid
                 WHERE  session_id = :sid"
            );
            $stmtUpdateSess->execute([
                ':cid' => $customerId,
                ':sid' => $sessionId,
            ]);

            // 4b. Nhập thiết bị vào kho
            $stmtGadget = $this->pdo->prepare(
                "INSERT INTO gadgets (imei, session_id, status, created_at)
                 VALUES (:imei, :session_id, 'Stored', NOW())"
            );
            $stmtGadget->execute([
                ':imei'       => $imei,
                ':session_id' => $sessionId,
            ]);

            $this->pdo->commit();

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Lỗi xác nhận thu mua: ' . $e->getMessage());
        }

        return [
            'session_id'   => $sessionId,
            'imei'         => $imei,
            'customer_id'  => $customerId,
            'inventory_id' => $imei,
        ];
    }


    // ═══════════════════════════════════════════════════════════
    // NHÓM 4: TỪ CHỐI – declineSession()
    // ═══════════════════════════════════════════════════════════

    /**
     * Ghi nhận khách từ chối đề giá.
     *
     * @return array{session_id:int, new_status:string}
     * @throws InvalidArgumentException | RuntimeException
     */
    public function declineSession(int $staffId, int $sessionId): array
    {
        if ($sessionId <= 0) {
            throw new InvalidArgumentException('session_id không hợp lệ.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT session_id, user_id, final_status
             FROM   valuation_sessions
             WHERE  session_id = :sid"
        );
        $stmt->execute([':sid' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            throw new RuntimeException('Phiên định giá không tồn tại.');
        }
        if ((int) $session['user_id'] !== $staffId) {
            throw new RuntimeException('Bạn không có quyền thực hiện thao tác này.');
        }
        if ($session['final_status'] !== 'Pending') {
            throw new RuntimeException(
                "Phiên đã ở trạng thái '{$session['final_status']}', không thể từ chối."
            );
        }

        $stmtUpdate = $this->pdo->prepare(
            "UPDATE valuation_sessions
             SET    final_status = 'Declined'
             WHERE  session_id = :sid"
        );
        $stmtUpdate->execute([':sid' => $sessionId]);

        return [
            'session_id' => $sessionId,
            'new_status' => 'Declined',
        ];
    }


    // ═══════════════════════════════════════════════════════════
    // NHÓM 5: LỊCH SỬ CÁ NHÂN – getHistory()
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array{sessions: array, total: int, page: int, per_page: int, stats: array}
     */
    public function getHistory(
        int    $staffId,
        int    $page    = 1,
        int    $perPage = 15,
        string $status  = '',
        string $search  = ''
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $where  = ['vs.user_id = :uid'];
        $params = [':uid' => $staffId];

        if ($status !== '' && in_array($status, ['Pending', 'Purchased', 'Declined'], true)) {
            $where[]           = 'vs.final_status = :status';
            $params[':status'] = $status;
        }
        if ($search !== '') {
            $where[]      = '(dm.model_name LIKE :q OR g.imei LIKE :q)';
            $params[':q'] = "%{$search}%";
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $stmtCount = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT vs.session_id)
             FROM   valuation_sessions vs
             LEFT JOIN device_models dm ON dm.model_id = vs.model_id
             LEFT JOIN gadgets g        ON g.session_id = vs.session_id
             {$whereClause}"
        );
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $stmtList = $this->pdo->prepare(
            "SELECT vs.session_id,
                    vs.final_status,
                    vs.ai_suggested_price,
                    vs.battery_health,
                    vs.created_at,
                    dm.model_name,
                    b.brand_name,
                    g.imei
             FROM   valuation_sessions vs
             LEFT JOIN device_models   dm ON dm.model_id = vs.model_id
             LEFT JOIN brands          b  ON b.brand_id  = dm.brand_id
             LEFT JOIN gadgets         g  ON g.session_id = vs.session_id
             {$whereClause}
             ORDER BY vs.created_at DESC
             LIMIT  :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmtList->bindValue($key, $value);
        }
        $stmtList->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmtList->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmtList->execute();
        $sessions = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        $stmtStats = $this->pdo->prepare(
            "SELECT
                COUNT(*)                                              AS total,
                SUM(final_status = 'Purchased')                       AS purchased,
                SUM(final_status = 'Declined')                        AS declined,
                SUM(final_status = 'Pending')                         AS pending,
                COALESCE(SUM(
                    CASE WHEN final_status='Purchased' THEN ai_suggested_price ELSE 0 END
                ), 0)                                                 AS total_spent
             FROM valuation_sessions
             WHERE user_id = :uid"
        );
        $stmtStats->execute([':uid' => $staffId]);
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

        return [
            'sessions' => $sessions,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'stats'    => $stats,
        ];
    }


    // ═══════════════════════════════════════════════════════════
    // NHÓM 6: NHẬT KÝ ĐỊNH GIÁ CÁ NHÂN – getStaffHistory()
    // ═══════════════════════════════════════════════════════════

    /**
     * @param  int $staffId
     * @return array<int, array<string, mixed>>
     */
    public function getStaffHistory(int $staffId): array
    {
        $sql = "
            SELECT
                vs.session_id,
                vs.created_at,
                vs.battery_health,
                vs.ai_suggested_price,
                vs.final_status,
                dm.model_name,
                dm.ram_gb,
                dm.rom_gb,
                b.brand_name,
                g.imei,
                CASE
                    WHEN vs.final_status = 'Purchased' THEN vs.ai_suggested_price
                    ELSE NULL
                END AS final_price,
                (
                    SELECT GROUP_CONCAT(apr.condition_name SEPARATOR ', ')
                    FROM session_rule_details srd
                    JOIN ai_pricing_rules apr ON srd.rule_id = apr.rule_id
                    WHERE srd.session_id = vs.session_id
                ) AS applied_rules
            FROM valuation_sessions vs
            JOIN device_models dm ON vs.model_id   = dm.model_id
            JOIN brands        b  ON dm.brand_id   = b.brand_id
            LEFT JOIN gadgets  g  ON g.session_id  = vs.session_id
            WHERE vs.user_id = :staff_id
            ORDER BY vs.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':staff_id' => $staffId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // ═══════════════════════════════════════════════════════════
    // NHÓM 7: NHẬT KÝ TOÀN HỆ THỐNG (Admin) – getAllSessions()
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array{sessions: array, total: int, page: int, per_page: int, stats: array}
     */
    public function getAllSessions(
        int    $page      = 1,
        int    $perPage   = 20,
        string $status    = '',
        string $search    = '',
        int    $staffId   = 0,
        string $dateFrom  = '',
        string $dateTo    = ''
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $where  = ['1 = 1'];
        $params = [];

        if ($status !== '' && in_array($status, ['Pending', 'Purchased', 'Declined'], true)) {
            $where[]           = 'vs.final_status = :status';
            $params[':status'] = $status;
        }
        if ($staffId > 0) {
            $where[]             = 'vs.user_id = :staff_id';
            $params[':staff_id'] = $staffId;
        }
        if ($search !== '') {
            $where[]      = '(dm.model_name LIKE :q OR u.full_name LIKE :q OR g.imei LIKE :q)';
            $params[':q'] = "%{$search}%";
        }
        if ($dateFrom !== '') {
            $where[]              = 'DATE(vs.created_at) >= :date_from';
            $params[':date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[]            = 'DATE(vs.created_at) <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // COUNT
        $stmtCount = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT vs.session_id)
             FROM   valuation_sessions vs
             LEFT JOIN device_models   dm ON dm.model_id = vs.model_id
             LEFT JOIN users           u  ON u.user_id   = vs.user_id
             LEFT JOIN gadgets         g  ON g.session_id = vs.session_id
             {$whereClause}"
        );
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // LIST
        $stmtList = $this->pdo->prepare(
            "SELECT vs.session_id,
                    vs.final_status,
                    vs.ai_suggested_price,
                    vs.battery_health,
                    vs.created_at,
                    dm.model_name,
                    b.brand_name,
                    u.full_name  AS staff_name,
                    g.imei
             FROM   valuation_sessions vs
             LEFT JOIN device_models   dm ON dm.model_id   = vs.model_id
             LEFT JOIN brands          b  ON b.brand_id    = dm.brand_id
             LEFT JOIN users           u  ON u.user_id     = vs.user_id
             LEFT JOIN gadgets         g  ON g.session_id  = vs.session_id
             {$whereClause}
             ORDER  BY vs.created_at DESC
             LIMIT  :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmtList->bindValue($key, $value);
        }
        $stmtList->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmtList->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmtList->execute();
        $sessions = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        $stats = $this->pdo
            ->query(
                "SELECT
                    COUNT(*)                                              AS total,
                    SUM(final_status = 'Purchased')                       AS purchased,
                    SUM(final_status = 'Declined')                        AS declined,
                    SUM(final_status = 'Pending')                         AS pending,
                    COALESCE(SUM(
                        CASE WHEN final_status='Purchased' THEN ai_suggested_price ELSE 0 END
                    ), 0)                                                 AS total_spent
                 FROM valuation_sessions"
            )
            ->fetch(PDO::FETCH_ASSOC);

        return [
            'sessions' => $sessions,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'stats'    => $stats,
        ];
    }


    // ═══════════════════════════════════════════════════════════
    // NHÓM 8: NHẬT KÝ TOÀN HỆ THỐNG – getGlobalValuationLogs()
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGlobalValuationLogs(): array
    {
        $sql = "
            SELECT
                vs.session_id,
                vs.created_at,
                vs.battery_health,
                vs.ai_suggested_price,
                vs.final_status,
                dm.model_id,
                dm.model_name,
                dm.ram_gb,
                dm.rom_gb,
                dm.base_price,
                b.brand_id,
                b.brand_name,
                u.user_id    AS staff_id,
                u.full_name  AS staff_name,
                c.full_name  AS customer_name,
                c.phone_number,
                g.imei,
                g.status     AS gadget_status,
                CASE
                    WHEN vs.final_status = 'Purchased' THEN vs.ai_suggested_price
                    ELSE NULL
                END AS final_price,
                (
                    SELECT GROUP_CONCAT(apr.condition_name SEPARATOR ', ')
                    FROM session_rule_details srd
                    JOIN ai_pricing_rules apr ON srd.rule_id = apr.rule_id
                    WHERE srd.session_id = vs.session_id
                ) AS applied_rules
            FROM valuation_sessions vs
            JOIN device_models dm ON vs.model_id    = dm.model_id
            JOIN brands        b  ON dm.brand_id    = b.brand_id
            JOIN users         u  ON vs.user_id     = u.user_id
            LEFT JOIN customers c ON vs.customer_id  = c.customer_id
            LEFT JOIN gadgets   g ON g.session_id    = vs.session_id
            ORDER BY vs.created_at DESC
        ";

        return $this->pdo
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}