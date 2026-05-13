<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/auth_bootstrap.php';

ensureAuthTablesAndSeedAdmin($conn);
redirectIfLoggedIn('/aplikasi-kasir-copy/dashboard.php');

$error_message = '';
$success_message = '';
$username_value = '';

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success_message = 'Password berhasil direset. Silakan login dengan password baru.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_value = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username_value === '' || $password === '') {
        $error_message = 'Username dan password wajib diisi.';
    } else {
        $safe_username = $conn->real_escape_string($username_value);
        $query = "SELECT id, username, nama_lengkap, role, password FROM users WHERE username = '$safe_username' AND role = 'kasir' AND is_active = 1 LIMIT 1";
        $result = $conn->query($query);

        if ($result && $result->num_rows === 1) {
            $cashier = $result->fetch_assoc();
            $password_check = verifyUserPasswordCompatibility($password, $cashier['password'] ?? '');

            if ($password_check['verified']) {
                if (!empty($password_check['needs_rehash'])) {
                    $new_hash = $conn->real_escape_string(password_hash($password, PASSWORD_DEFAULT));
                    $cashier_id = (int) ($cashier['id'] ?? 0);
                    if ($cashier_id > 0) {
                        $conn->query("UPDATE users SET password = '$new_hash' WHERE id = $cashier_id LIMIT 1");
                    }
                }

                loginCashierSession($cashier);
                header('Location: /aplikasi-kasir-copy/dashboard.php');
                exit;
            }
        }

        $error_message = 'Username kasir atau password tidak valid.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kasir - Kasir Pintar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="auth-page">
    <a href="../../settings.php" class="page-back">
        <i class="fas fa-arrow-left"></i>Kembali
    </a>

    <div class="auth-card login-card">
        <div class="brand-title">Kasir Pintar</div>
        <div class="brand-subtitle">Login Kasir</div>

        <?php if ($success_message !== '') { ?>
            <div class="alert alert-success py-2" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php } ?>

        <?php if ($error_message !== '') { ?>
            <div class="alert alert-danger py-2" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php } ?>

        <form method="POST" action="login_cashier.php" novalidate>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username_value); ?>" autocomplete="username" required>
            </div>
            <div class="mb-2">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
            </div>
            <div class="text-end mb-3">
                <a href="../reset/reset_password.php" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fas fa-right-to-bracket me-2"></i>Masuk
            </button>
        </form>
    </div>
</body>
</html>
