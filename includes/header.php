<?php
$is_logged_in_header = function_exists('isLoggedIn') ? isLoggedIn() : false;
$header_user_name = function_exists('getActiveCashierName') ? getActiveCashierName() : 'Guest';
?>

<!-- Header -->
<div class="header">
    <div class="header-top">
        <div class="header-left">
            <button type="button" class="sidebar-toggle-btn sidebar-toggle-btn--external" id="sidebarToggleBtnExternal" data-sidebar-toggle="1" aria-label="Toggle sidebar" aria-expanded="true">
                <span class="sidebar-toggle-line"></span>
                <span class="sidebar-toggle-line"></span>
                <span class="sidebar-toggle-line"></span>
            </button>
            <h1 class="header-title">Kasir Pintar</h1>
        </div>
        <div class="header-right">
            <div class="user-status user-status-primary">
                <span class="header-profile-avatar" aria-hidden="true">
                    <i class="fas fa-user"></i>
                </span>
                <span><?php echo htmlspecialchars($header_user_name); ?></span>
            </div>
            <div class="user-status">
                <span class="status-indicator <?php echo $is_logged_in_header ? 'online' : 'offline'; ?>"></span>
                <span><?php echo $is_logged_in_header ? 'Online' : 'Belum Login'; ?></span>
            </div>
            <div class="clock" id="realtimeClock">
                <span id="clockTime">00:00:00</span>
            </div>
        </div>
    </div>
</div>
