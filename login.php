<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/auth_bootstrap.php';

ensureAuthTablesAndSeedAdmin($conn);

redirectIfLoggedIn('index.php');

$role_filter = strtolower(trim($_GET['role'] ?? ''));
if (!in_array($role_filter, ['admin', 'kasir'], true)) {
    $role_filter = '';
}

$error_message = '';
$username_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_value = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username_value === '' || $password === '') {
        $error_message = 'Username dan password wajib diisi.';
    } else {
        $safe_username = $conn->real_escape_string($username_value);
        $query = "SELECT id, username, nama_lengkap, role, password FROM users WHERE username = '$safe_username' AND is_active = 1";
        if ($role_filter !== '') {
            $safe_role = $conn->real_escape_string($role_filter);
            $query .= " AND role = '$safe_role'";
        }
        $query .= " LIMIT 1";
        $result = $conn->query($query);

        if ($result && $result->num_rows === 1) {
            $cashier = $result->fetch_assoc();
            if (!empty($cashier['password']) && password_verify($password, $cashier['password'])) {
                loginCashierSession($cashier);
                header('Location: index.php');
                exit;
            }
        }

        $error_message = 'Username atau password tidak valid.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kasir Pintar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-card login-card">
        <div class="brand-title">Kasir Pintar</div>
        <div class="brand-subtitle">
            <?php echo $role_filter === 'admin' ? 'Login Administrator' : ($role_filter === 'kasir' ? 'Login Kasir' : 'Login'); ?>
        </div>

        <?php if ($error_message !== '') { ?>
            <div class="alert alert-danger py-2" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php } ?>

        <form method="POST" action="login.php" novalidate>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username_value); ?>" autocomplete="username" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-login">
                <i class="fas fa-right-to-bracket me-2"></i>Masuk
            </button>
        </form>

        <?php if ($role_filter === '') { ?>
            <div class="login-hint">
                Demo default: <strong>admin</strong> / <strong>123456</strong>
            </div>
        <?php } ?>
    </div>
</body>
</html>
