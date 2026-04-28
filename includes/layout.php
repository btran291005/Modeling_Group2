<?php
/**
 * includes/layout.php
 * Các component layout dùng chung: Sidebar, Topbar, Footer
 *
 * Cách dùng:
 *   renderSidebar('dashboard');   // Tên menu đang active
 *   renderTopbar('Tên trang');    // Tiêu đề trang hiện tại
 *   renderFooter();               // Đóng tag layout
 */

/**
 * Trả về danh sách menu theo role
 * Mỗi item: ['id', 'label', 'icon' (SVG path), 'href', 'roles']
 *
 * @return array[]
 */
function getMenuItems(): array
{
    return [
        [
            'id'    => 'dashboard',
            'label' => 'Tổng quan',
            'href'  => '{role}/dashboard.php',
            'roles' => ['Admin', 'Staff'],
            'icon'  => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        ],
        [
            'id'    => 'valuation',
            'label' => 'Định giá mới',
            'href'  => 'staff/valuation.php',
            'roles' => ['Staff'],
            'icon'  => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
        ],
        [
            'id'    => 'history',
            'label' => 'Lịch sử định giá',
            'href'  => 'staff/history.php',
            'roles' => ['Staff'],
            'icon'  => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        ],
        [
            'id'    => 'inventory_staff',
            'label' => 'Kho hàng',
            'href'  => 'staff/inventory.php',
            'roles' => ['Staff'],
            'icon'  => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>',
        ],
        // ---------- ADMIN-ONLY ----------
        [
            'id'    => 'accounts',
            'label' => 'Tài khoản',
            'href'  => 'admin/accounts.php',
            'roles' => ['Admin'],
            'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        ],
        [
            'id'    => 'master_data',
            'label' => 'Dữ liệu nền',
            'href'  => 'admin/master_data.php',
            'roles' => ['Admin'],
            'icon'  => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        ],
        [
            'id'    => 'ai_rules',
            'label' => 'Quy tắc AI',
            'href'  => 'admin/ai_rules.php',
            'roles' => ['Admin'],
            'icon'  => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        ],
        [
            'id'    => 'inventory_admin',
            'label' => 'Kho hàng',
            'href'  => 'admin/inventory.php',
            'roles' => ['Admin'],
            'icon'  => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>',
        ],
        [
            'id'    => 'valuation_log',
            'label' => 'Nhật ký định giá',
            'href'  => 'admin/valuation_log.php',
            'roles' => ['Admin'],
            'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        ],
    ];
}


/**
 * Render Sidebar (Navigation trái)
 *
 * @param  string $activeId  ID của menu item đang active
 */
function renderSidebar(string $activeId = 'dashboard'): void
{
    // Lấy role hiện tại từ session
    $role     = $_SESSION['role'] ?? 'Staff';
    $roleLower = strtolower($role);
    $menuItems = getMenuItems();

    // Lọc menu theo role
    $filtered = array_filter($menuItems, fn($m) => in_array($role, $m['roles']));

    // Resolve href placeholder {role}
    $resolved = array_map(function ($m) use ($roleLower) {
        $m['href'] = str_replace('{role}', $roleLower, $m['href']);
        return $m;
    }, $filtered);

    $isAdmin = $role === 'Admin';
    ?>
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">

        <!-- Logo -->
        <div class="sidebar-logo">
            <a href="<?= $roleLower ?>/dashboard.php" class="logo-link">
                <div class="logo-icon-wrap">⚡</div>
                <div class="logo-text-wrap">
                    <span class="logo-name">Smart<span>Gadget</span></span>
                    <span class="logo-sub"><?= $isAdmin ? 'Admin Portal' : 'Staff Panel' ?></span>
                </div>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <?php foreach ($resolved as $item): ?>
                    <?php $isActive = ($item['id'] === $activeId); ?>
                    <li class="nav-item <?= $isActive ? 'active' : '' ?>">
                        <a href="../<?= htmlspecialchars($item['href']) ?>" class="nav-link">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <?= $item['icon'] ?>
                                </svg>
                            </span>
                            <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
                            <?php if ($isActive): ?>
                                <span class="nav-active-dot"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- Sidebar Footer: thông tin user -->
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar">
                    <?= mb_strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8') ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
                    <span class="user-role"><?= $isAdmin ? '👑 Admin' : '👤 Staff' ?></span>
                </div>
                <a href="logout.php" class="logout-btn" title="Đăng xuất">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Overlay cho mobile -->
    <div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
    <?php
}


/**
 * Render Topbar (Header trên)
 *
 * @param  string $pageTitle    Tiêu đề trang hiện tại
 * @param  string $breadcrumb   Đường dẫn breadcrumb (HTML)
 */
function renderTopbar(string $pageTitle, string $breadcrumb = ''): void
{
    global $pdo;

    // Đếm thông báo chưa đọc
    $unreadCount = 0;
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0"
            );
            $stmt->execute([':uid' => $_SESSION['user_id'] ?? 0]);
            $unreadCount = (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            // Bỏ qua lỗi nếu bảng chưa tồn tại
        }
    }
    ?>
    <!-- ===== TOPBAR ===== -->
    <header class="topbar">
        <!-- Toggle sidebar (mobile) -->
        <button class="topbar-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <!-- Tiêu đề trang + breadcrumb -->
        <div class="topbar-title-wrap">
            <h1 class="topbar-title"><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if ($breadcrumb): ?>
                <nav class="topbar-breadcrumb" aria-label="breadcrumb">
                    <?= $breadcrumb ?>
                </nav>
            <?php endif; ?>
        </div>

        <!-- Actions bên phải -->
        <div class="topbar-actions">

            <!-- Nút thông báo -->
            <button class="topbar-action-btn" title="Thông báo" aria-label="Thông báo">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge"><?= min($unreadCount, 99) ?></span>
                <?php endif; ?>
            </button>

            <!-- Đồng hồ realtime -->
            <div class="topbar-clock" id="topbar-clock">--:--</div>

        </div>
    </header>
    <?php
}


/**
 * Render đầu file HTML (DOCTYPE, head, mở body + layout)
 * Gọi trước renderSidebar()
 *
 * @param  string $title        Tiêu đề tab trình duyệt
 * @param  array  $extraCss     Mảng đường dẫn CSS bổ sung
 */
function renderHtmlHead(string $title, array $extraCss = []): void
{
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Smart Gadget</title>

    <!-- Preconnect fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Base CSS -->
    <link rel="stylesheet" href="../assets/css/general.css">
    <link rel="stylesheet" href="../assets/css/layout.css">
    <link rel="stylesheet" href="../assets/css/components.css">

    <!-- Extra CSS (page-specific) -->
    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>
</head>
<body class="app-layout">
<div class="app-wrapper">
    <?php
}


/**
 * Mở vùng nội dung chính (sau sidebar)
 */
function renderMainOpen(): void
{
    echo '<main class="main-content" id="main-content">';
}


/**
 * Đóng layout + gọi script chung + render footer
 *
 * @param  array $extraJs  Mảng đường dẫn JS bổ sung
 */
function renderLayoutClose(array $extraJs = []): void
{
    ?>
        </main><!-- end .main-content -->
    </div><!-- end .app-wrapper -->

    <!-- Scripts chung -->
    <script>
    // ---- Clock realtime ----
    (function updateClock() {
        const el = document.getElementById('topbar-clock');
        if (!el) return;
        const now = new Date();
        el.textContent = now.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        setTimeout(updateClock, 1000);
    })();

    // ---- Sidebar toggle (mobile) ----
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebar-overlay').classList.toggle('visible');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebar-overlay').classList.remove('visible');
    }

    // ---- Định dạng tiền VNĐ (dùng ở toàn bộ trang) ----
    function formatVND(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency', currency: 'VND', maximumFractionDigits: 0
        }).format(amount);
    }

    // Tự động format các element có data-vnd
    document.querySelectorAll('[data-vnd]').forEach(function(el) {
        const val = parseInt(el.dataset.vnd, 10);
        if (!isNaN(val)) el.textContent = formatVND(val);
    });
    </script>

    <?php foreach ($extraJs as $js): ?>
        <script src="<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; ?>

</body>
</html>
    <?php
}