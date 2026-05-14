<?php
/**
 * Auth + Session Helpers
 * Keep authentication logic centralized and reusable.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return !empty($_SESSION['cashier']);
}

function getCurrentCashier() {
    return $_SESSION['cashier'] ?? null;
}

function requireLogin($redirect_to = 'login.php') {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect_to);
        exit;
    }
}

function redirectIfLoggedIn($redirect_to = 'index.php') {
    if (isLoggedIn()) {
        header('Location: ' . $redirect_to);
        exit;
    }
}

function loginCashierSession(array $cashier) {
    $_SESSION['cashier'] = [
        'id' => (int) ($cashier['id'] ?? 0),
        'username' => (string) ($cashier['username'] ?? ''),
        'name' => (string) ($cashier['nama_lengkap'] ?? $cashier['name'] ?? $cashier['username'] ?? 'Cashier'),
        'role' => (string) ($cashier['role'] ?? 'kasir'),
        'cashier_id' => (string) ($cashier['cashier_id'] ?? ''),
        'contact_number' => (string) ($cashier['contact_number'] ?? ''),
        'email' => (string) ($cashier['email'] ?? ''),
    ];
}

function logoutCashierSession() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function getActiveCashierName() {
    $cashier = getCurrentCashier();
    if (!$cashier) {
        return 'Guest';
    }

    $name = trim((string) ($cashier['name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    return (string) ($cashier['username'] ?? 'Cashier');
}

function getCurrentUserRole() {
    $cashier = getCurrentCashier();
    $role = strtolower((string) ($cashier['role'] ?? 'kasir'));
    return in_array($role, ['admin', 'kasir'], true) ? $role : 'kasir';
}

function isAdminUser() {
    return getCurrentUserRole() === 'admin';
}

/**
 * Verify login password for both hashed and legacy plain-text records.
 * Returns an array with verification status and whether rehash/migration is needed.
 */
function verifyUserPasswordCompatibility($input_password, $stored_password) {
    $input_password = (string) ($input_password ?? '');
    $stored_password = (string) ($stored_password ?? '');

    if ($input_password === '' || $stored_password === '') {
        return [
            'verified' => false,
            'needs_rehash' => false,
            'is_legacy_plain' => false,
        ];
    }

    $password_info = password_get_info($stored_password);
    $is_hashed_password = !empty($password_info['algo']);

    if ($is_hashed_password) {
        $verified = password_verify($input_password, $stored_password);

        return [
            'verified' => $verified,
            'needs_rehash' => $verified ? password_needs_rehash($stored_password, PASSWORD_DEFAULT) : false,
            'is_legacy_plain' => false,
        ];
    }

    $verified_plain = hash_equals($stored_password, $input_password);

    return [
        'verified' => $verified_plain,
        'needs_rehash' => $verified_plain,
        'is_legacy_plain' => $verified_plain,
    ];
}
