<?php
// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$is_transaction_page = strpos($request_uri, '/transactions/') !== false;
$is_dashboard_page = ($current_page === 'dashboard.php') || ($current_page === 'index.php' && !$is_transaction_page);
$is_logged_in_sidebar = function_exists('isLoggedIn') ? isLoggedIn() : false;
$active_cashier_name = $is_logged_in_sidebar
    ? (function_exists('getActiveCashierName') ? getActiveCashierName() : 'Cashier')
    : 'Guest';
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-top">
            <button type="button" class="sidebar-toggle-btn sidebar-toggle-btn--internal" id="sidebarToggleBtnInternal" data-sidebar-toggle="1" aria-label="Hide sidebar" aria-expanded="true">
                <i class="fas fa-chevron-left sidebar-toggle-icon" aria-hidden="true"></i>
            </button>
            <div class="sidebar-brand-copy">
                <h5>Kasir Pintar</h5>
            </div>
        </div>
        <small class="sidebar-meta">
            Active Cashier: <?php echo htmlspecialchars($active_cashier_name); ?>
        </small>
        <?php if (!$is_logged_in_sidebar) { ?>
            <small class="sidebar-meta-muted">
                Status: Belum Login
            </small>
        <?php } ?>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="/aplikasi-kasir-copy/dashboard.php" <?php echo $is_dashboard_page ? 'class="active"' : ''; ?>>
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="/aplikasi-kasir-copy/transactions/index.php" <?php echo ($current_page === 'transaction.php' || $is_transaction_page) ? 'class="active"' : ''; ?> <?php echo !$is_logged_in_sidebar ? 'data-guest-lock="1"' : ''; ?>>
                <i class="fas fa-exchange-alt"></i>
                <span>Transaction</span>
            </a>
        </li>
        <li>
            <a href="/aplikasi-kasir-copy/products/products.php" <?php echo ($current_page === 'products.php') ? 'class="active"' : ''; ?> <?php echo !$is_logged_in_sidebar ? 'data-guest-lock="1"' : ''; ?>>
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
        </li>
        <li class="sidebar-item sidebar-item--reports">
            <a href="/aplikasi-kasir-copy/reports.php"
               class="<?php echo trim((($current_page === 'reports.php') ? 'active ' : '') . 'reports-nav-link'); ?>"
               <?php echo !$is_logged_in_sidebar ? 'data-guest-lock="1"' : ''; ?>>
                <i class="fas fa-chart-pie"></i>
                <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="/aplikasi-kasir-copy/settings.php" <?php echo ($current_page === 'settings.php') ? 'class="active"' : ''; ?>>
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
        <?php if ($is_logged_in_sidebar) { ?>
            <li>
                <a href="/aplikasi-kasir-copy/logout.php">
                    <i class="fas fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </li>
        <?php } ?>
    </ul>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

<?php if (!$is_logged_in_sidebar) { ?>
    <div id="guestAccessOverlay" class="guest-access-overlay">
        <div class="guest-access-overlay-card">
            <i class="fas fa-lock guest-access-overlay-icon"></i>
            <div class="guest-access-overlay-title">Anda harus login untuk membuka akses ini</div>
            <small class="guest-access-overlay-subtitle">Buka menu Settings untuk mulai login.</small>
        </div>
    </div>
<?php } ?>
