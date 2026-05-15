<?php
$is_logged_in_header = function_exists('isLoggedIn') ? isLoggedIn() : false;
$header_user_name = function_exists('getActiveCashierName') ? getActiveCashierName() : 'Guest';
$header_user_photo = '';
$header_user_photo_default = true;

/**
 * Profile photo sources (in priority order):
 *  1. $_SESSION['last_profile_photo'] — updated immediately after profile save
 *  2. $_SESSION['cashier']['profile_photo'] — set at login / refreshed on profile update
 *  3. Falls back to placeholder icon
 *
 * Note: always starts with '/' so it resolves correctly from any page depth
 * (e.g. /transactions/index.php, /reports.php, /settings.php, etc.)
 */
if ($is_logged_in_header) {
    $header_user_photo = (string) ($_SESSION['last_profile_photo'] ?? '');
    if ($header_user_photo === '') {
        $current_cashier = function_exists('getCurrentCashier') ? getCurrentCashier() : null;
        $header_user_photo = (string) ($current_cashier['profile_photo'] ?? '');
    }
    $header_user_photo_default = $header_user_photo === '';
}
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
                <span class="header-profile-avatar" aria-hidden="true" id="headerProfileAvatarContainer">
                    <?php if ($header_user_photo_default) { ?>
                        <i class="fas fa-user"></i>
                    <?php } else {
                        $photo_path = __DIR__ . '/../assets/img/' . $header_user_photo;
                        $photo_t = is_file($photo_path) ? filemtime($photo_path) : time();
                    ?>
                        <img src="/assets/img/<?php echo htmlspecialchars($header_user_photo); ?>?t=<?php echo $photo_t; ?>" alt="Profile" class="header-profile-avatar-image" id="headerProfileAvatarImage">
                    <?php } ?>
                    <span class="header-profile-avatar-sync" aria-hidden="true"><i class="fas fa-sync-alt"></i></span>
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
