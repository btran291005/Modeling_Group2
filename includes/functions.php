<?php
/**
 * includes/functions.php
 * Tập hợp các hàm tiện ích dùng chung toàn hệ thống
 * Include sau auth.php và db_connect.php
 */

/* ============================================================
 * NHÓM 1: ĐỊNH DẠNG DỮ LIỆU
 * ============================================================ */

/**
 * Format số tiền sang định dạng VNĐ
 * Dùng ở PHP khi cần render server-side
 * Ví dụ: 15000000 -> "15.000.000 ₫"
 *
 * @param  int|float $amount  Số tiền (lưu dạng INT trong DB)
 * @return string
 */
function formatCurrency(int|float $amount): string
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

/**
 * Format timestamp thành chuỗi ngày giờ thân thiện (vi-VN)
 * Ví dụ: "2025-06-01 14:30:00" -> "01/06/2025 14:30"
 *
 * @param  string $datetime  Chuỗi datetime từ MySQL
 * @param  bool   $showTime  Có hiển thị giờ không
 * @return string
 */
function formatDatetime(string $datetime, bool $showTime = true): string
{
    if (empty($datetime)) return '—';
    $ts = strtotime($datetime);
    return $showTime
        ? date('d/m/Y H:i', $ts)
        : date('d/m/Y', $ts);
}

/**
 * Trả về chuỗi thời gian tương đối kiểu "3 giờ trước"
 *
 * @param  string $datetime
 * @return string
 */
function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);

    return match (true) {
        $diff < 60        => 'Vừa xong',
        $diff < 3600      => floor($diff / 60) . ' phút trước',
        $diff < 86400     => floor($diff / 3600) . ' giờ trước',
        $diff < 604800    => floor($diff / 86400) . ' ngày trước',
        default           => formatDatetime($datetime, false),
    };
}

/**
 * Viết hoa chữ cái đầu mỗi từ (hỗ trợ tiếng Việt)
 *
 * @param  string $str
 * @return string
 */
function titleCase(string $str): string
{
    return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
}

/**
 * Cắt chuỗi dài, thêm "..." nếu vượt quá giới hạn
 *
 * @param  string $str
 * @param  int    $limit  Số ký tự tối đa
 * @return string
 */
function truncate(string $str, int $limit = 50): string
{
    return mb_strlen($str) > $limit
        ? mb_substr($str, 0, $limit) . '...'
        : $str;
}


/* ============================================================
 * NHÓM 2: BADGE / LABEL GIAO DIỆN
 * ============================================================ */

/**
 * Trả về HTML badge cho trạng thái phiên định giá
 * final_status: Pending | Purchased | Declined
 *
 * @param  string $status
 * @return string HTML
 */
function sessionStatusBadge(string $status): string
{
    $map = [
        'Pending'   => ['label' => 'Đang chờ',  'class' => 'badge-warning'],
        'Purchased' => ['label' => 'Đã mua',    'class' => 'badge-success'],
        'Declined'  => ['label' => 'Từ chối',   'class' => 'badge-danger'],
    ];

    $item  = $map[$status] ?? ['label' => $status, 'class' => 'badge-default'];
    $label = htmlspecialchars($item['label']);
    $class = htmlspecialchars($item['class']);

    return "<span class=\"badge {$class}\">{$label}</span>";
}

/**
 * Trả về HTML badge cho trạng thái tài khoản người dùng
 * status: Active | Locked
 *
 * @param  string $status
 * @return string HTML
 */
function userStatusBadge(string $status): string
{
    return $status === 'Active'
        ? '<span class="badge badge-success">Hoạt động</span>'
        : '<span class="badge badge-danger">Đã khóa</span>';
}

/**
 * Trả về HTML badge cho role người dùng
 * role: Admin | Staff
 *
 * @param  string $role
 * @return string HTML
 */
function roleBadge(string $role): string
{
    return $role === 'Admin'
        ? '<span class="badge badge-primary">👑 Admin</span>'
        : '<span class="badge badge-default">👤 Staff</span>';
}

/**
 * Trả về HTML badge cho trạng thái thiết bị (gadgets)
 * status: Stored | Refurbishing | Sold
 *
 * @param  string $status
 * @return string HTML
 */
function gadgetStatusBadge(string $status): string
{
    $map = [
        'Stored'       => ['label' => 'Trong kho',     'class' => 'badge-primary'],
        'Refurbishing' => ['label' => 'Đang tân trang', 'class' => 'badge-warning'],
        'Sold'         => ['label' => 'Đã bán',         'class' => 'badge-success'],
    ];

    $item  = $map[$status] ?? ['label' => $status, 'class' => 'badge-default'];
    $label = htmlspecialchars($item['label']);
    $class = htmlspecialchars($item['class']);

    return "<span class=\"badge {$class}\">{$label}</span>";
}


/* ============================================================
 * NHÓM 3: DATABASE HELPERS
 * ============================================================ */

/**
 * Lấy số liệu thống kê tổng quan cho Admin Dashboard
 * Trả về mảng chứa các chỉ số KPI
 *
 * @param  PDO $pdo
 * @return array
 */
function getAdminStats(PDO $pdo): array
{
    // Tổng phiên định giá
    $totalSessions = (int) $pdo
        ->query("SELECT COUNT(*) FROM valuation_sessions")
        ->fetchColumn();

    // Phiên thu mua hôm nay
    $todayPurchased = (int) $pdo
        ->query("SELECT COUNT(*) FROM valuation_sessions
                 WHERE final_status = 'Purchased'
                   AND DATE(created_at) = CURDATE()")
        ->fetchColumn();

    // Tổng thiết bị trong kho
    $inStock = (int) $pdo
        ->query("SELECT COUNT(*) FROM gadgets WHERE status = 'Stored'")
        ->fetchColumn();

    // Tổng staff đang hoạt động
    $activeStaff = (int) $pdo
        ->query("SELECT COUNT(*) FROM users
                 WHERE role = 'Staff' AND status = 'Active'")
        ->fetchColumn();

    // Doanh thu ước tính (tổng ai_suggested_price của Purchased sessions)
    $estimatedRevenue = (int) $pdo
        ->query("SELECT COALESCE(SUM(ai_suggested_price), 0)
                 FROM valuation_sessions
                 WHERE final_status = 'Purchased'")
        ->fetchColumn();

    return [
        'total_sessions'    => $totalSessions,
        'today_purchased'   => $todayPurchased,
        'in_stock'          => $inStock,
        'active_staff'      => $activeStaff,
        'estimated_revenue' => $estimatedRevenue,
    ];
}

/**
 * Lấy số liệu thống kê cho Staff Dashboard
 *
 * @param  PDO $pdo
 * @param  int $userId  ID của Staff đang đăng nhập
 * @return array
 */
function getStaffStats(PDO $pdo, int $userId): array
{
    // Tổng phiên của Staff này
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM valuation_sessions WHERE user_id = :uid"
    );
    $stmt->execute([':uid' => $userId]);
    $totalMine = (int) $stmt->fetchColumn();

    // Phiên hôm nay
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM valuation_sessions
         WHERE user_id = :uid AND DATE(created_at) = CURDATE()"
    );
    $stmt->execute([':uid' => $userId]);
    $todayMine = (int) $stmt->fetchColumn();

    // Đã thu mua (của Staff này)
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM valuation_sessions
         WHERE user_id = :uid AND final_status = 'Purchased'"
    );
    $stmt->execute([':uid' => $userId]);
    $purchased = (int) $stmt->fetchColumn();

    // Từ chối
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM valuation_sessions
         WHERE user_id = :uid AND final_status = 'Declined'"
    );
    $stmt->execute([':uid' => $userId]);
    $declined = (int) $stmt->fetchColumn();

    return [
        'total_mine' => $totalMine,
        'today_mine' => $todayMine,
        'purchased'  => $purchased,
        'declined'   => $declined,
    ];
}

/**
 * Lấy danh sách phiên định giá gần nhất (dùng cho cả 2 dashboard)
 *
 * @param  PDO      $pdo
 * @param  int      $limit   Số bản ghi tối đa
 * @param  int|null $userId  Nếu có -> lọc theo Staff
 * @return array
 */
function getRecentSessions(PDO $pdo, int $limit = 10, ?int $userId = null): array
{
    $where = $userId ? "WHERE vs.user_id = :uid" : "";
    $sql   = "
        SELECT
            vs.session_id,
            vs.battery_health,
            vs.ai_suggested_price,
            vs.final_status,
            vs.created_at,
            dm.model_name,
            b.brand_name,
            u.full_name AS staff_name,
            c.full_name AS customer_name,
            c.phone_number
        FROM valuation_sessions vs
        JOIN device_models dm ON vs.model_id   = dm.model_id
        JOIN brands        b  ON dm.brand_id   = b.brand_id
        JOIN users         u  ON vs.user_id    = u.user_id
        LEFT JOIN customers c ON vs.customer_id = c.customer_id
        {$where}
        ORDER BY vs.created_at DESC
        LIMIT :lim
    ";

    $stmt = $pdo->prepare($sql);
    if ($userId) $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Lấy danh sách audit log gần nhất (dành cho Admin)
 *
 * @param  PDO $pdo
 * @param  int $limit
 * @return array
 */
function getRecentAuditLogs(PDO $pdo, int $limit = 15): array
{
    $stmt = $pdo->prepare(
        "SELECT al.*, u.full_name, u.role
         FROM audit_logs al
         JOIN users u ON al.user_id = u.user_id
         ORDER BY al.created_at DESC
         LIMIT :lim"
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}


/* ============================================================
 * NHÓM 4: BẢO MẬT & INPUT VALIDATION
 * ============================================================ */

/**
 * Escape HTML để chống XSS khi render dữ liệu từ DB ra giao diện
 * Alias ngắn gọn của htmlspecialchars()
 *
 * @param  string|null $str
 * @return string
 */
function e(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Lấy và làm sạch giá trị POST
 *
 * @param  string $key
 * @param  string $default
 * @return string
 */
function postString(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $default);
}

/**
 * Lấy và ép kiểu INT từ POST
 *
 * @param  string $key
 * @param  int    $default
 * @return int
 */
function postInt(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}

/**
 * Validate số điện thoại Việt Nam
 * Hỗ trợ các đầu số: 03x, 05x, 07x, 08x, 09x
 *
 * @param  string $phone
 * @return bool
 */
function isValidVietnamesePhone(string $phone): bool
{
    return (bool) preg_match('/^(0[3|5|7|8|9])+([0-9]{8})\b/', $phone);
}


/* ============================================================
 * NHÓM 5: PAGINATION
 * ============================================================ */

/**
 * Tính toán thông số phân trang
 *
 * @param  PDO    $pdo
 * @param  string $table     Tên bảng
 * @param  string $where     Điều kiện WHERE (không cần keyword WHERE)
 * @param  int    $perPage   Số bản ghi mỗi trang
 * @return array  ['total', 'per_page', 'current_page', 'total_pages', 'offset']
 */
function paginate(PDO $pdo, string $table, string $where = '', int $perPage = 15): array
{
    $whereClause = $where ? "WHERE {$where}" : '';
    $total = (int) $pdo
        ->query("SELECT COUNT(*) FROM {$table} {$whereClause}")
        ->fetchColumn();

    $currentPage = max(1, (int) ($_GET['page'] ?? 1));
    $totalPages  = max(1, (int) ceil($total / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset      = ($currentPage - 1) * $perPage;

    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
    ];
}
