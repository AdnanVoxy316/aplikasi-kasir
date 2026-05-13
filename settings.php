<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/auth_bootstrap.php';

ensureAuthTablesAndSeedAdmin($conn);

$is_logged_in = isLoggedIn();
$current_user = $is_logged_in ? getCurrentCashier() : null;
$current_user_id = (int) ($current_user['id'] ?? 0);
$current_role = $is_logged_in ? getCurrentUserRole() : 'guest';
$is_admin = $current_role === 'admin';
$is_cashier = $current_role === 'kasir';

$security_question_options = [
    'Siapa nama ibu kandung Anda?',
    'Apa nama hewan peliharaan pertama Anda?',
    'Di kota mana Anda lahir?'
];

$success_message = '';
$error_message = '';

function settingsRespondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function isSettingsAjaxRequest(): bool
{
    return (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (isset($_POST['ajax']) && $_POST['ajax'] === '1');
}

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($is_cashier && in_array($action, ['clock_in', 'clock_out'], true) && isSettingsAjaxRequest()) {
        if ($action === 'clock_in') {
            $open = $conn->query("SELECT id FROM attendance_logs WHERE user_id = $current_user_id AND clock_out_at IS NULL ORDER BY id DESC LIMIT 1");
            if ($open && $open->num_rows > 0) {
                settingsRespondJson([
                    'success' => false,
                    'message' => 'Gagal mencatat absen',
                    'status' => 'Online',
                ], 409);
            }

            if ($conn->query("INSERT INTO attendance_logs (user_id, clock_in_at) VALUES ($current_user_id, NOW())")) {
                settingsRespondJson([
                    'success' => true,
                    'action' => 'clock_in',
                    'message' => 'Absen Masuk Success',
                    'status' => 'Online',
                    'clock_in_at' => date('Y-m-d H:i:s'),
                    'clock_out_at' => null,
                ]);
            }

            settingsRespondJson([
                'success' => false,
                'message' => 'Gagal mencatat absen',
                'status' => 'Offline',
            ], 500);
        }

        if ($action === 'clock_out') {
            $open = $conn->query("SELECT id, clock_in_at FROM attendance_logs WHERE user_id = $current_user_id AND clock_out_at IS NULL ORDER BY id DESC LIMIT 1");
            if (!$open || $open->num_rows === 0) {
                settingsRespondJson([
                    'success' => false,
                    'message' => 'Gagal mencatat absen',
                    'status' => 'Offline',
                ], 409);
            }

            $open_row = $open->fetch_assoc();
            $attendance_id = (int) ($open_row['id'] ?? 0);
            $clock_in_at = (string) ($open_row['clock_in_at'] ?? '');

            if ($attendance_id > 0 && $conn->query("UPDATE attendance_logs SET clock_out_at = NOW() WHERE id = $attendance_id LIMIT 1")) {
                settingsRespondJson([
                    'success' => true,
                    'action' => 'clock_out',
                    'message' => 'Absen Keluar Success',
                    'status' => 'Offline',
                    'clock_in_at' => $clock_in_at,
                    'clock_out_at' => date('Y-m-d H:i:s'),
                ]);
            }

            settingsRespondJson([
                'success' => false,
                'message' => 'Gagal mencatat absen',
                'status' => 'Online',
            ], 500);
        }
    }

    if ($action === 'update_my_profile') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $security_question = trim($_POST['security_question'] ?? '');
        $security_answer = trim($_POST['security_answer'] ?? '');
        $new_password = $_POST['new_password'] ?? '';

        if ($nama_lengkap === '') {
            $error_message = 'Nama lengkap wajib diisi.';
        } elseif (!in_array($security_question, $security_question_options, true)) {
            $error_message = 'Pertanyaan keamanan tidak valid.';
        } elseif ($security_answer === '') {
            $error_message = 'Jawaban keamanan wajib diisi.';
        } else {
            $safe_name = $conn->real_escape_string($nama_lengkap);
            $safe_question = $conn->real_escape_string($security_question);
            $normalized_answer = mb_strtolower($security_answer, 'UTF-8');
            $safe_answer_hash = $conn->real_escape_string(password_hash($normalized_answer, PASSWORD_DEFAULT));
            $updates = [
                "nama_lengkap = '$safe_name'",
                "security_question = '$safe_question'",
                "security_answer = '$safe_answer_hash'"
            ];

            if ($new_password !== '') {
                if (strlen($new_password) < 6) {
                    $error_message = 'Password baru minimal 6 karakter.';
                } else {
                    $safe_pass = $conn->real_escape_string(password_hash($new_password, PASSWORD_DEFAULT));
                    $updates[] = "password = '$safe_pass'";
                }
            }

            if ($error_message === '') {
                $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = $current_user_id LIMIT 1";
                if ($conn->query($query)) {
                    $fresh = $conn->query("SELECT id, username, nama_lengkap, role FROM users WHERE id = $current_user_id LIMIT 1");
                    if ($fresh && $fresh->num_rows === 1) {
                        loginCashierSession($fresh->fetch_assoc());
                        $current_user = getCurrentCashier();
                    }
                    $success_message = 'Profil berhasil diperbarui.';
                } else {
                    $error_message = 'Gagal memperbarui profil.';
                }
            }
        }
    }

    if ($is_admin && $action === 'update_store_profile') {
        $store_name = trim($_POST['store_name'] ?? '');
        $store_address = trim($_POST['store_address'] ?? '');

        if ($store_name === '') {
            $error_message = 'Nama toko wajib diisi.';
        } else {
            $safe_name = $conn->real_escape_string($store_name);
            $safe_address = $conn->real_escape_string($store_address);

            $row = $conn->query("SELECT id FROM store_profile LIMIT 1");
            if ($row && $row->num_rows > 0) {
                $id = (int) ($row->fetch_assoc()['id'] ?? 0);
                $query = "UPDATE store_profile SET store_name = '$safe_name', store_address = '$safe_address' WHERE id = $id";
            } else {
                $query = "INSERT INTO store_profile (store_name, store_address) VALUES ('$safe_name', '$safe_address')";
            }

            if ($conn->query($query)) {
                $success_message = 'Profil toko berhasil diperbarui.';
            } else {
                $error_message = 'Gagal menyimpan profil toko.';
            }
        }
    }

    if ($is_admin && $action === 'add_cashier') {
        $username = trim($_POST['username'] ?? '');
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $nama_lengkap === '' || $password === '') {
            $error_message = 'Username, nama lengkap, dan password wajib diisi.';
        } elseif (strlen($password) < 6) {
            $error_message = 'Password minimal 6 karakter.';
        } else {
            $safe_user = $conn->real_escape_string($username);
            $check = $conn->query("SELECT id FROM users WHERE username = '$safe_user' LIMIT 1");
            if ($check && $check->num_rows > 0) {
                $error_message = 'Username sudah digunakan.';
            } else {
                $safe_name = $conn->real_escape_string($nama_lengkap);
                $safe_pass = $conn->real_escape_string(password_hash($password, PASSWORD_DEFAULT));
                $query = "INSERT INTO users (username, password, role, nama_lengkap, is_active) VALUES ('$safe_user', '$safe_pass', 'kasir', '$safe_name', 1)";
                if ($conn->query($query)) {
                    $success_message = 'Akun kasir berhasil ditambahkan.';
                } else {
                    $error_message = 'Gagal menambah akun kasir.';
                }
            }
        }
    }

    if ($is_admin && $action === 'update_cashier') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $password = $_POST['password'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($user_id <= 0 || $nama_lengkap === '') {
            $error_message = 'Data kasir tidak lengkap.';
        } else {
            $safe_name = $conn->real_escape_string($nama_lengkap);
            $updates = ["nama_lengkap = '$safe_name'", "is_active = $is_active"];

            if ($password !== '') {
                if (strlen($password) < 6) {
                    $error_message = 'Password minimal 6 karakter.';
                } else {
                    $safe_pass = $conn->real_escape_string(password_hash($password, PASSWORD_DEFAULT));
                    $updates[] = "password = '$safe_pass'";
                }
            }

            if ($error_message === '') {
                $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = $user_id AND role = 'kasir' LIMIT 1";
                if ($conn->query($query)) {
                    $success_message = 'Data kasir berhasil diperbarui.';
                } else {
                    $error_message = 'Gagal memperbarui kasir.';
                }
            }
        }
    }

    if ($is_cashier && $action === 'clock_in') {
        $open = $conn->query("SELECT id FROM attendance_logs WHERE user_id = $current_user_id AND clock_out_at IS NULL ORDER BY id DESC LIMIT 1");
        if ($open && $open->num_rows > 0) {
            $error_message = 'Anda sudah melakukan Absen Masuk.';
        } else {
            if ($conn->query("INSERT INTO attendance_logs (user_id, clock_in_at) VALUES ($current_user_id, NOW())")) {
                $success_message = 'Absen Masuk berhasil dicatat.';
            } else {
                $error_message = 'Gagal mencatat Absen Masuk.';
            }
        }
    }

    if ($is_cashier && $action === 'clock_out') {
        $open = $conn->query("SELECT id FROM attendance_logs WHERE user_id = $current_user_id AND clock_out_at IS NULL ORDER BY id DESC LIMIT 1");
        if (!$open || $open->num_rows === 0) {
            $error_message = 'Belum ada Absen Masuk aktif.';
        } else {
            $attendance_id = (int) ($open->fetch_assoc()['id'] ?? 0);
            if ($conn->query("UPDATE attendance_logs SET clock_out_at = NOW() WHERE id = $attendance_id LIMIT 1")) {
                $success_message = 'Absen Keluar berhasil dicatat.';
            } else {
                $error_message = 'Gagal mencatat Absen Keluar.';
            }
        }
    }
}

$store_profile = ['store_name' => 'Kasir Pintar Store', 'store_address' => ''];
$cashiers = [];
$attendance_logs = [];
$my_attendance_logs = [];
$current_shift_open = false;

$store_result = $conn->query("SELECT store_name, store_address FROM store_profile LIMIT 1");
if ($store_result && $store_result->num_rows > 0) {
    $store_profile = $store_result->fetch_assoc();
}

if ($is_admin) {
    $cashier_query = "
        SELECT
            u.id,
            u.username,
            u.password,
            u.nama_lengkap,
            u.is_active,
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM attendance_logs a
                    WHERE a.user_id = u.id
                      AND DATE(a.clock_in_at) = CURDATE()
                      AND a.clock_out_at IS NULL
                ) THEN 'Online'
                ELSE 'Offline'
            END AS shift_status,
            SEC_TO_TIME(COALESCE(SUM(
                CASE
                    WHEN DATE(al.clock_in_at) = CURDATE() THEN TIMESTAMPDIFF(SECOND, al.clock_in_at, COALESCE(al.clock_out_at, NOW()))
                    ELSE 0
                END
            ), 0)) AS working_hours_today
        FROM users u
        LEFT JOIN attendance_logs al ON al.user_id = u.id
        WHERE u.role = 'kasir'
        GROUP BY u.id
        ORDER BY u.id DESC
    ";

    $cashier_result = $conn->query($cashier_query);
    if ($cashier_result) {
        while ($row = $cashier_result->fetch_assoc()) {
            $cashiers[] = $row;
        }
    }

    $log_query = "
        SELECT a.id, u.username, u.nama_lengkap, a.clock_in_at, a.clock_out_at
        FROM attendance_logs a
        INNER JOIN users u ON u.id = a.user_id
        ORDER BY a.clock_in_at DESC
        LIMIT 100
    ";

    $log_result = $conn->query($log_query);
    if ($log_result) {
        while ($row = $log_result->fetch_assoc()) {
            $attendance_logs[] = $row;
        }
    }
}

if ($is_cashier) {
    $open_result = $conn->query("SELECT id FROM attendance_logs WHERE user_id = $current_user_id AND clock_out_at IS NULL ORDER BY id DESC LIMIT 1");
    $current_shift_open = $open_result && $open_result->num_rows > 0;

    $my_log_result = $conn->query("SELECT clock_in_at, clock_out_at FROM attendance_logs WHERE user_id = $current_user_id ORDER BY clock_in_at DESC LIMIT 10");
    if ($my_log_result) {
        while ($row = $my_log_result->fetch_assoc()) {
            $my_attendance_logs[] = $row;
        }
    }
}

$selected_security_question = $security_question_options[0];
if ($is_logged_in) {
    $user_profile_result = $conn->query("SELECT security_question FROM users WHERE id = $current_user_id LIMIT 1");
    if ($user_profile_result && $user_profile_result->num_rows === 1) {
        $user_profile_row = $user_profile_result->fetch_assoc();
        $saved_question = trim((string) ($user_profile_row['security_question'] ?? ''));
        if (in_array($saved_question, $security_question_options, true)) {
            $selected_security_question = $saved_question;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings Control Center - Kasir Pintar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?php echo $is_logged_in ? 'logged-in' : 'guest-mode'; ?>" data-role="<?php echo htmlspecialchars($current_role); ?>">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/header.php'; ?>
    <div class="content settings-content">
        <div class="settings-hero">
            <div class="settings-hero-copy">
                <h3 class="settings-title">Settings Control Center</h3>
                <small class="settings-subtitle">Pusat kendali akun dan aktivitas kehadiran</small>
            </div>
            <span class="settings-role-badge"><?php echo htmlspecialchars($current_role); ?></span>
        </div>
        <div class="menu-grid <?php echo !$is_logged_in ? 'menu-grid--guest' : ''; ?>">
            <?php if (!$is_logged_in) { ?>
                <a href="auth/login/login_cashier.php" class="menu-card menu-card-attendance">
                    <span class="menu-card-icon"><i class="fas fa-user-clock"></i></span>
                    <div class="title">Login as Cashier</div>
                    <div class="desc">Masuk sebagai kasir untuk mulai transaksi dan absensi.</div>
                </a>

                <a href="auth/login/login_admin.php" class="menu-card menu-card-profile">
                    <span class="menu-card-icon"><i class="fas fa-user-shield"></i></span>
                    <div class="title">Login as Administrator</div>
                    <div class="desc">Masuk sebagai admin untuk kelola sistem. Reset password admin via phpMyAdmin.</div>
                </a>
            <?php } elseif ($is_admin) { ?>
                <a href="#user-management" class="menu-card menu-card-profile">
                    <span class="menu-card-icon"><i class="fas fa-users-cog"></i></span>
                    <div class="title">User Management</div>
                    <div class="desc">Lihat akun kasir, hash password, jam kerja dan status Online/Offline.</div>
                </a>

                <a href="#store-profile" class="menu-card menu-card-attendance">
                    <span class="menu-card-icon"><i class="fas fa-store"></i></span>
                    <div class="title">Store Profile</div>
                    <div class="desc">Ubah nama dan alamat toko untuk struk.</div>
                </a>

                <a href="#attendance-log" class="menu-card menu-card-attendance">
                    <span class="menu-card-icon"><i class="fas fa-clipboard-list"></i></span>
                    <div class="title">Attendance Log</div>
                    <div class="desc">Pantau jam Absen Masuk/Keluar semua kasir.</div>
                </a>
            <?php } elseif ($is_cashier) { ?>
                <a href="#my-profile" class="menu-card menu-card-profile">
                    <span class="menu-card-icon"><i class="fas fa-id-badge"></i></span>
                    <div class="title">My Profile</div>
                    <div class="desc">Ganti nama lengkap dan password Anda.</div>
                </a>

                <a href="#attendance" class="menu-card menu-card-attendance">
                    <span class="menu-card-icon"><i class="fas fa-fingerprint"></i></span>
                    <div class="title">Attendance</div>
                    <div class="desc">Lakukan Absen Masuk / Absen Keluar.</div>
                </a>

                <a href="logout.php" class="menu-card menu-card-switch">
                    <span class="menu-card-icon"><i class="fas fa-right-from-bracket"></i></span>
                    <div class="title">Switch Account</div>
                    <div class="desc">Logout lalu kembali ke pilihan login.</div>
                </a>
            <?php } ?>
        </div>

        <?php if ($is_admin) { ?>
            <div class="panel" id="store-profile">
                <h5><i class="fas fa-store me-2"></i>Store Profile</h5>
                <form method="POST" action="settings.php">
                    <input type="hidden" name="action" value="update_store_profile">
                    <div class="mb-3">
                        <label class="form-label">Nama Toko</label>
                        <input type="text" class="form-control" name="store_name" value="<?php echo htmlspecialchars($store_profile['store_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat Toko</label>
                        <textarea class="form-control" name="store_address" rows="3"><?php echo htmlspecialchars($store_profile['store_address'] ?? ''); ?></textarea>
                    </div>
                    <button class="btn btn-success" type="submit">Simpan Profil Toko</button>
                </form>
            </div>

            <div class="panel" id="user-management">
                <h5><i class="fas fa-users-cog me-2"></i>User Management (Kasir)</h5>
                <form method="POST" action="settings.php" class="row g-2 mb-3">
                    <input type="hidden" name="action" value="add_cashier">
                    <div class="col-md-3"><input name="username" class="form-control" placeholder="Username" required></div>
                    <div class="col-md-4"><input name="nama_lengkap" class="form-control" placeholder="Nama Lengkap" required></div>
                    <div class="col-md-3"><input name="password" type="password" class="form-control" placeholder="Password" required></div>
                    <div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit">Tambah Kasir</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead><tr><th>Username</th><th>Nama</th><th>Password (Hash)</th><th>Working Hours (Today)</th><th>Shift</th><th>Aktif</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php if (count($cashiers) === 0) { ?>
                            <tr><td colspan="7" class="text-center text-muted">Belum ada akun kasir.</td></tr>
                        <?php } else { foreach ($cashiers as $cashier) { ?>
                            <tr>
                                <form method="POST" action="settings.php">
                                    <input type="hidden" name="action" value="update_cashier">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $cashier['id']; ?>">
                                    <td><?php echo htmlspecialchars($cashier['username']); ?></td>
                                    <td><input class="form-control" name="nama_lengkap" value="<?php echo htmlspecialchars($cashier['nama_lengkap']); ?>" required></td>
                                    <td class="password-hash-cell"><?php echo htmlspecialchars($cashier['password']); ?></td>
                                    <td><?php echo htmlspecialchars($cashier['working_hours_today'] ?? '00:00:00'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $cashier['shift_status'] === 'Online' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo htmlspecialchars($cashier['shift_status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><input type="checkbox" name="is_active" <?php echo (int) $cashier['is_active'] === 1 ? 'checked' : ''; ?>></td>
                                    <td>
                                        <input type="password" class="form-control mb-2" name="password" placeholder="Password baru (opsional)">
                                        <button class="btn btn-sm btn-success" type="submit">Update</button>
                                    </td>
                                </form>
                            </tr>
                        <?php }} ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel" id="attendance-log">
                <h5><i class="fas fa-clipboard-list me-2"></i>Attendance Log</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead><tr><th>Kasir</th><th>Username</th><th>Absen Masuk</th><th>Absen Keluar</th></tr></thead>
                        <tbody>
                        <?php if (count($attendance_logs) === 0) { ?>
                            <tr><td colspan="4" class="text-center text-muted">Belum ada log absensi.</td></tr>
                        <?php } else { foreach ($attendance_logs as $log) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['nama_lengkap']); ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td><?php echo htmlspecialchars($log['clock_in_at']); ?></td>
                                <td><?php echo $log['clock_out_at'] ? htmlspecialchars($log['clock_out_at']) : '<span class="badge bg-warning text-dark">Belum Keluar</span>'; ?></td>
                            </tr>
                        <?php }} ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>

        <?php if ($is_cashier) { ?>
            <div class="panel" id="my-profile">
                <h5><i class="fas fa-id-badge me-2"></i>My Profile</h5>
                <form method="POST" action="settings.php" class="settings-form settings-profile-form">
                    <input type="hidden" name="action" value="update_my_profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input class="form-control" value="<?php echo htmlspecialchars($current_user['username'] ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap</label>
                            <input class="form-control" name="nama_lengkap" value="<?php echo htmlspecialchars($current_user['name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password Baru</label>
                            <input type="password" class="form-control" name="new_password" placeholder="Opsional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pertanyaan Keamanan</label>
                            <select class="form-select" name="security_question" required>
                                <?php foreach ($security_question_options as $question_option) { ?>
                                    <option value="<?php echo htmlspecialchars($question_option); ?>" <?php echo $selected_security_question === $question_option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($question_option); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jawaban Keamanan</label>
                            <input type="text" class="form-control" name="security_answer" placeholder="Masukkan jawaban keamanan" required>
                            <small class="text-muted">Jawaban tidak membedakan huruf besar/kecil.</small>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit">Simpan Profil</button>
                </form>
            </div>

            <div class="panel" id="attendance">
                <h5><i class="fas fa-fingerprint me-2"></i>Attendance</h5>
                <p class="text-muted">Status shift saat ini:
                    <span id="settingsAttendanceStatusBadge" class="badge <?php echo $current_shift_open ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $current_shift_open ? 'Online' : 'Offline'; ?></span>
                </p>
                <form method="POST" action="settings.php" class="d-flex gap-2 mb-3" id="settingsAttendanceForm">
                    <button class="btn btn-success" type="submit" name="action" value="clock_in" id="settingsClockInBtn" <?php echo $current_shift_open ? 'disabled' : ''; ?>>Absen Masuk</button>
                    <button class="btn btn-danger" type="submit" name="action" value="clock_out" id="settingsClockOutBtn" <?php echo !$current_shift_open ? 'disabled' : ''; ?>>Absen Keluar</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Absen Masuk</th><th>Absen Keluar</th></tr></thead>
                        <tbody id="settingsAttendanceHistoryBody">
                        <?php if (count($my_attendance_logs) === 0) { ?>
                            <tr><td colspan="2" class="text-center text-muted">Belum ada riwayat absensi.</td></tr>
                        <?php } else { foreach ($my_attendance_logs as $log) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['clock_in_at']); ?></td>
                                <td><?php echo $log['clock_out_at'] ? htmlspecialchars($log['clock_out_at']) : '<span class="badge bg-warning text-dark">Belum Keluar</span>'; ?></td>
                            </tr>
                        <?php }} ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<script src="assets/js/script.js"></script>
</body>
</html>
