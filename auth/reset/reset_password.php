<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/auth_bootstrap.php';

ensureAuthTablesAndSeedAdmin($conn);

$error_message = '';
$username_value = '';
$question_options = [
    'Siapa nama ibu kandung Anda?',
    'Apa nama hewan peliharaan pertama Anda?',
    'Di kota mana Anda lahir?'
];
$question_value = $question_options[0];
$answer_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_value = trim($_POST['username'] ?? '');
    $question_value = trim($_POST['security_question'] ?? '');
    $answer_value = trim($_POST['security_answer'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if ($username_value === '' || $question_value === '' || $answer_value === '' || $new_password === '') {
        $error_message = 'Username, pertanyaan keamanan, jawaban keamanan, dan password baru wajib diisi.';
    } elseif (!in_array($question_value, $question_options, true)) {
        $error_message = 'Pertanyaan keamanan tidak valid.';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'Password baru minimal 6 karakter.';
    } else {
        $safe_username = $conn->real_escape_string($username_value);
        $query = "SELECT id, security_question, security_answer FROM users WHERE username = '$safe_username' AND role = 'kasir' AND is_active = 1 LIMIT 1";
        $result = $conn->query($query);

        if (!$result || $result->num_rows !== 1) {
            $error_message = 'Username, Pertanyaan, atau Jawaban salah!';
        } else {
            $cashier = $result->fetch_assoc();
            $saved_question = trim((string) ($cashier['security_question'] ?? ''));
            $db_answer = trim((string) ($cashier['security_answer'] ?? ''));

            $is_question_match = $saved_question !== '' && $saved_question === $question_value;

            $input_normalized = mb_strtolower($answer_value, 'UTF-8');
            $is_answer_match = false;
            if ($db_answer !== '') {
                if (password_get_info($db_answer)['algo'] !== null) {
                    $is_answer_match = password_verify($input_normalized, $db_answer);
                } else {
                    $is_answer_match = strcasecmp($db_answer, $answer_value) === 0;
                }
            }

            if (!$is_question_match || !$is_answer_match) {
                $error_message = 'Username, Pertanyaan, atau Jawaban salah!';
            } else {
                $user_id = (int) ($cashier['id'] ?? 0);
                $new_hash = $conn->real_escape_string(password_hash($new_password, PASSWORD_DEFAULT));
                if ($conn->query("UPDATE users SET password = '$new_hash' WHERE id = $user_id LIMIT 1")) {
                    header('Location: /aplikasi-kasir-copy/auth/login/login_cashier.php?reset=success');
                    exit;
                }
                $error_message = 'Gagal mengubah password. Coba lagi.';
            }
        }
    }
}

if (!in_array($question_value, $question_options, true)) {
    $question_value = $question_options[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password Kasir - Kasir Pintar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="auth-page">
    <a href="../login/login_cashier.php" class="page-back">
        <i class="fas fa-arrow-left"></i>Kembali
    </a>

    <div class="auth-card login-card">
        <div class="brand-title">Kasir Pintar</div>
        <div class="brand-subtitle">Reset Password Kasir</div>

        <?php if ($error_message !== '') { ?>
            <div class="alert alert-danger py-2" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php } ?>

        <form method="POST" action="reset_password.php" novalidate>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username_value); ?>" autocomplete="username" required>
            </div>

            <div class="mb-3">
                <label class="form-label" for="security_question">Pertanyaan Keamanan</label>
                <select class="form-select" id="security_question" name="security_question" required>
                    <?php foreach ($question_options as $question_option) { ?>
                        <option value="<?php echo htmlspecialchars($question_option); ?>" <?php echo $question_value === $question_option ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($question_option); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="security_answer">Jawaban Keamanan</label>
                <input type="text" class="form-control" id="security_answer" name="security_answer" value="<?php echo htmlspecialchars($answer_value); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label" for="new_password">Password Baru</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fas fa-key me-2"></i>Reset Password
            </button>
        </form>
    </div>
</body>
</html>
