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
        $contact_number = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $cashier_id_input = preg_replace('/\D/', '', (string) ($_POST['cashier_id'] ?? ''));
        $profile_photo_file = $_FILES['profile_photo'] ?? null;

        if ($nama_lengkap === '') {
            $error_message = 'Nama lengkap wajib diisi.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Alamat email tidak valid.';
        } else {
            $safe_name = $conn->real_escape_string($nama_lengkap);
            $contact_sql = $contact_number !== ''
                ? "'" . $conn->real_escape_string($contact_number) . "'"
                : 'NULL';
            $email_sql = $email !== ''
                ? "'" . $conn->real_escape_string($email) . "'"
                : 'NULL';
            $updates = [
                "nama_lengkap = '$safe_name'",
                "contact_number = $contact_sql",
                "email = $email_sql"
            ];

            if ($profile_photo_file && ($profile_photo_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if (($profile_photo_file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    $error_message = 'Gagal mengunggah foto profil.';
                } else {
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    $original_name = (string) ($profile_photo_file['name'] ?? '');
                    $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    $tmp_name = (string) ($profile_photo_file['tmp_name'] ?? '');
                    $image_info = $tmp_name !== '' ? @getimagesize($tmp_name) : false;

                    if (!in_array($file_extension, $allowed_extensions, true) || $image_info === false) {
                        $error_message = 'File foto profil harus berupa gambar yang valid.';
                    } else {
                        $existing_photo_result = $conn->query("SELECT profile_photo FROM users WHERE id = $current_user_id LIMIT 1");
                        $existing_photo = '';
                        if ($existing_photo_result && $existing_photo_result->num_rows === 1) {
                            $existing_photo = (string) ($existing_photo_result->fetch_assoc()['profile_photo'] ?? '');
                        }

                        $new_profile_photo = sprintf(
                            'profile_%d_%s.%s',
                            $current_user_id,
                            bin2hex(random_bytes(4)),
                            $file_extension,
                        );
                        $target_directory = __DIR__ . '/assets/img/';
                        $target_path = $target_directory . $new_profile_photo;

                        if (!move_uploaded_file($tmp_name, $target_path)) {
                            $error_message = 'Foto profil tidak dapat disimpan.';
                        } else {
                            if ($existing_photo !== '' && $existing_photo !== $new_profile_photo) {
                                $existing_path = $target_directory . basename($existing_photo);
                                if (is_file($existing_path)) {
                                    @unlink($existing_path);
                                }
                            }

                            $safe_profile_photo = $conn->real_escape_string($new_profile_photo);
                            $updates[] = "profile_photo = '$safe_profile_photo'";
                        }
                    }
                }
            }

            if ($is_admin) {
                if (!preg_match('/^\d{11}$/', $cashier_id_input)) {
                    $error_message = 'ID Kasir harus terdiri dari 11 digit angka.';
                } else {
                    $safe_cashier_id = $conn->real_escape_string($cashier_id_input);
                    $cashier_id_exists = $conn->query("SELECT id FROM users WHERE cashier_id = '$safe_cashier_id' AND id != $current_user_id LIMIT 1");
                    if ($cashier_id_exists && $cashier_id_exists->num_rows > 0) {
                        $error_message = 'ID Kasir sudah digunakan oleh pengguna lain.';
                    } else {
                        $updates[] = "cashier_id = '$safe_cashier_id'";
                    }
                }
            }

            if ($error_message === '') {
                $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = $current_user_id LIMIT 1";
                if ($conn->query($query)) {
                    $fresh = $conn->query("SELECT id, username, nama_lengkap, role, cashier_id, contact_number, email, profile_photo FROM users WHERE id = $current_user_id LIMIT 1");
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

    if ($action === 'reset_profile_password') {
        $security_old_password = $_POST['security_old_password'] ?? '';
        $security_new_password = $_POST['security_new_password'] ?? '';
        $security_confirm_password = $_POST['security_confirm_password'] ?? '';

        if ($security_old_password === '') {
            $error_message = 'Password Lama wajib diisi untuk verifikasi.';
        } elseif ($security_new_password === '' || $security_confirm_password === '') {
            $error_message = 'Password baru dan konfirmasi password wajib diisi.';
        } elseif (strlen($security_new_password) < 6) {
            $error_message = 'Password baru minimal 6 karakter.';
        } elseif (!hash_equals($security_new_password, $security_confirm_password)) {
            $error_message = 'Konfirmasi password tidak cocok.';
        } else {
            $current_user_result = $conn->query("SELECT password FROM users WHERE id = $current_user_id LIMIT 1");
            if (!$current_user_result || $current_user_result->num_rows === 0) {
                $error_message = 'Pengguna tidak ditemukan.';
            } else {
                $current_user_data = $current_user_result->fetch_assoc();
                $stored_password = (string) ($current_user_data['password'] ?? '');
                
                if (!password_verify($security_old_password, $stored_password)) {
                    $error_message = 'Password Lama tidak sesuai.';
                } else {
                    $safe_pass = $conn->real_escape_string(password_hash($security_new_password, PASSWORD_DEFAULT));
                    if ($conn->query("UPDATE users SET password = '$safe_pass' WHERE id = $current_user_id LIMIT 1")) {
                        $fresh = $conn->query("SELECT id, username, nama_lengkap, role, cashier_id, contact_number, email, profile_photo FROM users WHERE id = $current_user_id LIMIT 1");
                        if ($fresh && $fresh->num_rows === 1) {
                            loginCashierSession($fresh->fetch_assoc());
                            $current_user = getCurrentCashier();
                        }
                        $success_message = 'Password akun berhasil diperbarui.';
                    } else {
                        $error_message = 'Gagal memperbarui password akun.';
                    }
                }
            }
        }
    }

    if ($action === 'reset_primary_security_question') {
        $security_old_password = $_POST['security_old_password_primary'] ?? '';
        $primary_security_question = trim($_POST['primary_security_question'] ?? '');
        $primary_security_answer = trim($_POST['primary_security_answer'] ?? '');

        if ($security_old_password === '') {
            $error_message = 'Password Lama wajib diisi untuk verifikasi.';
        } elseif (!in_array($primary_security_question, $security_question_options, true)) {
            $error_message = 'Pertanyaan keamanan utama tidak valid.';
        } elseif ($primary_security_answer === '') {
            $error_message = 'Jawaban pertanyaan keamanan utama wajib diisi.';
        } else {
            $current_user_result = $conn->query("SELECT password FROM users WHERE id = $current_user_id LIMIT 1");
            if (!$current_user_result || $current_user_result->num_rows === 0) {
                $error_message = 'Pengguna tidak ditemukan.';
            } else {
                $current_user_data = $current_user_result->fetch_assoc();
                $stored_password = (string) ($current_user_data['password'] ?? '');
                
                if (!password_verify($security_old_password, $stored_password)) {
                    $error_message = 'Password Lama tidak sesuai.';
                } else {
                    $normalized_answer = mb_strtolower($primary_security_answer, 'UTF-8');
                    $safe_question = $conn->real_escape_string($primary_security_question);
                    $safe_answer_hash = $conn->real_escape_string(password_hash($normalized_answer, PASSWORD_DEFAULT));

                    if ($conn->query("UPDATE users SET security_question = '$safe_question', security_answer = '$safe_answer_hash' WHERE id = $current_user_id LIMIT 1")) {
                        $success_message = 'Pertanyaan keamanan utama berhasil diperbarui.';
                    } else {
                        $error_message = 'Gagal memperbarui pertanyaan keamanan utama.';
                    }
                }
            }
        }
    }

    if ($action === 'update_additional_security_questions') {
        $security_old_password = $_POST['security_old_password_additional'] ?? '';
        $secondary_question = trim($_POST['security_question_secondary'] ?? '');
        $secondary_answer = trim($_POST['security_answer_secondary'] ?? '');
        $tertiary_question = trim($_POST['security_question_tertiary'] ?? '');
        $tertiary_answer = trim($_POST['security_answer_tertiary'] ?? '');

        if ($security_old_password === '') {
            $error_message = 'Password Lama wajib diisi untuk verifikasi.';
        } else {
            $current_user_result = $conn->query("SELECT password FROM users WHERE id = $current_user_id LIMIT 1");
            if (!$current_user_result || $current_user_result->num_rows === 0) {
                $error_message = 'Pengguna tidak ditemukan.';
            } else {
                $current_user_data = $current_user_result->fetch_assoc();
                $stored_password = (string) ($current_user_data['password'] ?? '');
                
                if (!password_verify($security_old_password, $stored_password)) {
                    $error_message = 'Password Lama tidak sesuai.';
                } else {
                    $security_state_result = $conn->query("SELECT security_question, security_question_secondary, security_answer_secondary, security_question_tertiary, security_answer_tertiary FROM users WHERE id = $current_user_id LIMIT 1");
                    $security_state = $security_state_result && $security_state_result->num_rows === 1
                        ? $security_state_result->fetch_assoc()
                        : [];

                    $current_primary_question = trim((string) ($security_state['security_question'] ?? ''));
                    $current_secondary_question = trim((string) ($security_state['security_question_secondary'] ?? ''));
                    $current_secondary_answer = trim((string) ($security_state['security_answer_secondary'] ?? ''));
                    $current_tertiary_question = trim((string) ($security_state['security_question_tertiary'] ?? ''));
                    $current_tertiary_answer = trim((string) ($security_state['security_answer_tertiary'] ?? ''));

                    $normalized_slots = [];
                    $question_registry = [];
                    if ($current_primary_question !== '') {
                        $question_registry[] = $current_primary_question;
                    }

                    $slot_payloads = [
                        'secondary' => [
                            'question' => $secondary_question,
                            'answer' => $secondary_answer,
                            'current_question' => $current_secondary_question,
                            'current_answer' => $current_secondary_answer,
                        ],
                        'tertiary' => [
                            'question' => $tertiary_question,
                            'answer' => $tertiary_answer,
                            'current_question' => $current_tertiary_question,
                            'current_answer' => $current_tertiary_answer,
                        ],
                    ];

                    foreach ($slot_payloads as $slot_name => $slot) {
                        $question = trim((string) ($slot['question'] ?? ''));
                        $answer = trim((string) ($slot['answer'] ?? ''));
                        $current_question = trim((string) ($slot['current_question'] ?? ''));
                        $current_answer = trim((string) ($slot['current_answer'] ?? ''));

                        if ($question === '' && $answer === '') {
                            $normalized_slots[$slot_name] = [
                                'question_sql' => 'NULL',
                                'answer_sql' => 'NULL',
                            ];
                            continue;
                        }

                        if (!in_array($question, $security_question_options, true)) {
                            $error_message = 'Pertanyaan keamanan tambahan tidak valid.';
                            break;
                        }

                        if (in_array($question, $question_registry, true) && $question !== $current_question) {
                            $error_message = 'Setiap pertanyaan keamanan harus berbeda.';
                            break;
                        }

                        if ($answer === '') {
                            if ($question === $current_question && $current_answer !== '') {
                                $normalized_slots[$slot_name] = [
                                    'question_sql' => "'" . $conn->real_escape_string($question) . "'",
                                    'answer_sql' => "'" . $conn->real_escape_string($current_answer) . "'",
                                ];
                                $question_registry[] = $question;
                                continue;
                            }

                            $error_message = 'Jawaban untuk pertanyaan keamanan tambahan wajib diisi.';
                            break;
                        }

                        $normalized_answer = mb_strtolower($answer, 'UTF-8');
                        $normalized_slots[$slot_name] = [
                            'question_sql' => "'" . $conn->real_escape_string($question) . "'",
                            'answer_sql' => "'" . $conn->real_escape_string(password_hash($normalized_answer, PASSWORD_DEFAULT)) . "'",
                        ];
                        $question_registry[] = $question;
                    }

                    if ($error_message === '') {
                        $query = "UPDATE users SET
                                    security_question_secondary = " . $normalized_slots['secondary']['question_sql'] . ",
                                    security_answer_secondary = " . $normalized_slots['secondary']['answer_sql'] . ",
                                    security_question_tertiary = " . $normalized_slots['tertiary']['question_sql'] . ",
                                    security_answer_tertiary = " . $normalized_slots['tertiary']['answer_sql'] . "
                      WHERE id = $current_user_id LIMIT 1";

            if ($conn->query($query)) {
                $success_message = 'Pertanyaan keamanan tambahan berhasil diperbarui.';
            } else {
                $error_message = 'Gagal memperbarui pertanyaan keamanan tambahan.';
            }
        }
    }

    if ($action === 'forgot_password_verify_question') {
        $security_question_answer = trim($_POST['security_question_answer'] ?? '');
        
        if ($security_question_answer === '') {
            $error_message = 'Jawaban pertanyaan keamanan wajib diisi.';
        } else {
            $security_result = $conn->query("SELECT security_question, security_answer FROM users WHERE id = $current_user_id LIMIT 1");
            if (!$security_result || $security_result->num_rows === 0) {
                $error_message = 'Pengguna tidak ditemukan.';
            } else {
                $security_data = $security_result->fetch_assoc();
                $stored_answer_hash = (string) ($security_data['security_answer'] ?? '');
                $normalized_input = mb_strtolower($security_question_answer, 'UTF-8');
                
                if (empty($stored_answer_hash) || !password_verify($normalized_input, $stored_answer_hash)) {
                    $error_message = 'Jawaban pertanyaan keamanan tidak sesuai.';
                } else {
                    $_SESSION['forgot_password_verified'] = true;
                    $_SESSION['forgot_password_verified_user_id'] = $current_user_id;
                    $success_message = 'Jawaban benar! Silakan atur password baru Anda.';
                }
            }
        }
    }

    if ($action === 'forgot_password_set_new' && isset($_SESSION['forgot_password_verified']) && $_SESSION['forgot_password_verified'] === true) {
        $new_password = $_POST['forgot_password_new'] ?? '';
        $confirm_password = $_POST['forgot_password_confirm'] ?? '';
        
        if ($new_password === '' || $confirm_password === '') {
            $error_message = 'Password baru dan konfirmasi password wajib diisi.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'Password baru minimal 6 karakter.';
        } elseif (!hash_equals($new_password, $confirm_password)) {
            $error_message = 'Konfirmasi password tidak cocok.';
        } else {
            $safe_pass = $conn->real_escape_string(password_hash($new_password, PASSWORD_DEFAULT));
            if ($conn->query("UPDATE users SET password = '$safe_pass' WHERE id = $current_user_id LIMIT 1")) {
                unset($_SESSION['forgot_password_verified']);
                unset($_SESSION['forgot_password_verified_user_id']);
                $fresh = $conn->query("SELECT id, username, nama_lengkap, role, cashier_id, contact_number, email, profile_photo FROM users WHERE id = $current_user_id LIMIT 1");
                if ($fresh && $fresh->num_rows === 1) {
                    loginCashierSession($fresh->fetch_assoc());
                    $current_user = getCurrentCashier();
                }
                $success_message = 'Password berhasil direset. Silakan login kembali dengan password baru Anda.';
            } else {
                $error_message = 'Gagal mereset password.';
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
$current_user_profile = [
    'username' => (string) ($current_user['username'] ?? ''),
    'nama_lengkap' => (string) ($current_user['name'] ?? ''),
    'cashier_id' => (string) ($current_user['cashier_id'] ?? ''),
    'contact_number' => (string) ($current_user['contact_number'] ?? ''),
    'email' => (string) ($current_user['email'] ?? ''),
    'profile_photo' => (string) ($current_user['profile_photo'] ?? ''),
];
$current_user_security = [
    'primary_question' => $security_question_options[0],
    'secondary_question' => '',
    'tertiary_question' => '',
];

if ($is_logged_in) {
    $user_profile_result = $conn->query("SELECT username, nama_lengkap, cashier_id, contact_number, email, profile_photo, security_question, security_question_secondary, security_question_tertiary FROM users WHERE id = $current_user_id LIMIT 1");
    if ($user_profile_result && $user_profile_result->num_rows === 1) {
        $user_profile_row = $user_profile_result->fetch_assoc();
        $current_user_profile['username'] = (string) ($user_profile_row['username'] ?? $current_user_profile['username']);
        $current_user_profile['nama_lengkap'] = (string) ($user_profile_row['nama_lengkap'] ?? $current_user_profile['nama_lengkap']);
        $current_user_profile['cashier_id'] = (string) ($user_profile_row['cashier_id'] ?? '');
        $current_user_profile['contact_number'] = (string) ($user_profile_row['contact_number'] ?? '');
        $current_user_profile['email'] = (string) ($user_profile_row['email'] ?? '');
        $current_user_profile['profile_photo'] = (string) ($user_profile_row['profile_photo'] ?? '');
        $saved_question = trim((string) ($user_profile_row['security_question'] ?? ''));
        if (in_array($saved_question, $security_question_options, true)) {
            $selected_security_question = $saved_question;
        }
        $current_user_security['primary_question'] = $selected_security_question;

        $secondary_question = trim((string) ($user_profile_row['security_question_secondary'] ?? ''));
        if (in_array($secondary_question, $security_question_options, true)) {
            $current_user_security['secondary_question'] = $secondary_question;
        }

        $tertiary_question = trim((string) ($user_profile_row['security_question_tertiary'] ?? ''));
        if (in_array($tertiary_question, $security_question_options, true)) {
            $current_user_security['tertiary_question'] = $tertiary_question;
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
                <a href="#my-profile" class="menu-card menu-card-profile" data-settings-toggle="my-profile">
                    <span class="menu-card-icon"><i class="fas fa-id-badge"></i></span>
                    <div class="title">My Profile</div>
                    <div class="desc">Kelola identitas akun admin, kontak, email, dan ID Kasir.</div>
                </a>

                <a href="#user-management" class="menu-card menu-card-profile" data-settings-toggle="user-management">
                    <span class="menu-card-icon"><i class="fas fa-users-cog"></i></span>
                    <div class="title">User Management</div>
                    <div class="desc">Lihat akun kasir, hash password, jam kerja dan status Online/Offline.</div>
                </a>

                <a href="#store-profile" class="menu-card menu-card-attendance" data-settings-toggle="store-profile">
                    <span class="menu-card-icon"><i class="fas fa-store"></i></span>
                    <div class="title">Store Profile</div>
                    <div class="desc">Ubah nama dan alamat toko untuk struk.</div>
                </a>

                <a href="#attendance-log" class="menu-card menu-card-attendance" data-settings-toggle="attendance-log">
                    <span class="menu-card-icon"><i class="fas fa-clipboard-list"></i></span>
                    <div class="title">Attendance Log</div>
                    <div class="desc">Pantau jam Absen Masuk/Keluar semua kasir.</div>
                </a>
            <?php } elseif ($is_cashier) { ?>
                <a href="#my-profile" class="menu-card menu-card-profile" data-settings-toggle="my-profile">
                    <span class="menu-card-icon"><i class="fas fa-id-badge"></i></span>
                    <div class="title">My Profile</div>
                    <div class="desc">Ganti nama lengkap dan password Anda.</div>
                </a>

                <a href="#attendance" class="menu-card menu-card-attendance" data-settings-toggle="attendance">
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

        <?php if ($success_message !== '' || $error_message !== '') { ?>
            <div class="settings-feedback-stack">
                <?php if ($success_message !== '') { ?>
                    <div class="alert alert-success auto-hide-alert mb-0" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php } ?>

                <?php if ($error_message !== '') { ?>
                    <div class="alert alert-danger mb-0" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($is_logged_in) { ?>
            <div class="panel settings-panel settings-panel--profile settings-panel--profile-shell" id="my-profile" data-settings-panel="my-profile" aria-hidden="true">
                <div class="settings-profile-view settings-profile-view--main is-active" data-profile-view="main">
                    <form method="POST" action="settings.php" class="settings-form settings-profile-form" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_my_profile">

                        <div class="settings-profile-heading settings-profile-heading--top">
                            <h5>My Profile</h5>
                            <small>Perbarui identitas akun, informasi kontak, dan keamanan profil.</small>
                        </div>

                        <div class="settings-profile-photo-block">
                            <div class="settings-profile-avatar">
                                <?php if (!empty($current_user_profile['profile_photo'])) { ?>
                                    <img src="assets/img/<?php echo htmlspecialchars($current_user_profile['profile_photo']); ?>" alt="Foto Profil" class="settings-profile-avatar-image" id="settingsProfileAvatarPreview">
                                    <div class="settings-profile-avatar-placeholder hidden" id="settingsProfileAvatarPlaceholder" aria-hidden="true">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php } else { ?>
                                    <img src="" alt="Foto Profil" class="settings-profile-avatar-image hidden" id="settingsProfileAvatarPreview">
                                    <div class="settings-profile-avatar-placeholder" id="settingsProfileAvatarPlaceholder" aria-hidden="true">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php } ?>

                                <label for="profilePhotoInput" class="settings-profile-avatar-upload" aria-label="Ubah foto profil">
                                    <i class="fas fa-camera"></i>
                                </label>
                            </div>
                            <input type="file" class="hidden" id="profilePhotoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif">
                        </div>

                        <div class="settings-profile-id-wrap">
                            <label class="form-label">ID Kasir</label>
                            <input type="text" class="form-control" name="cashier_id" inputmode="numeric" maxlength="11" pattern="\d{11}" value="<?php echo htmlspecialchars($current_user_profile['cashier_id']); ?>" readonly>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input class="form-control" value="<?php echo htmlspecialchars($current_user_profile['username']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama Lengkap</label>
                                <input class="form-control" name="nama_lengkap" value="<?php echo htmlspecialchars($current_user_profile['nama_lengkap']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nomor Kontak</label>
                                <input type="text" class="form-control" name="contact_number" inputmode="tel" placeholder="08xxxxxxxxxx" value="<?php echo htmlspecialchars($current_user_profile['contact_number']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Alamat Email</label>
                                <input type="email" class="form-control" name="email" placeholder="nama@email.com" value="<?php echo htmlspecialchars($current_user_profile['email']); ?>">
                            </div>
                        </div>

                        <div class="settings-profile-actions">
                            <button class="btn btn-profile-outline-security" type="button" data-open-profile-security="1">Keamanan Akun</button>
                            <button class="btn btn-profile-outline-save" type="submit">Simpan Profil</button>
                        </div>
                    </form>
                </div>

                <div class="settings-profile-view settings-profile-view--security" data-profile-view="security">
                    <div class="settings-profile-heading settings-profile-heading--top settings-profile-heading--security">
                        <h5>Keamanan Akun</h5>
                        <small>Pilih alur keamanan yang ingin Anda kelola dari menu berikut.</small>
                    </div>

                    <div class="settings-security-menu">
                        <button type="button" class="settings-security-menu-btn is-active" data-security-pane-target="reset-password">Reset Password</button>
                        <button type="button" class="settings-security-menu-btn" data-security-pane-target="reset-primary-question">Reset Pertanyaan Keamanan</button>
                        <button type="button" class="settings-security-menu-btn" data-security-pane-target="additional-questions">Menambahkan Pertanyaan Keamanan</button>
                        <button type="button" class="settings-security-menu-btn settings-security-menu-btn--back" data-return-profile-main="1">Kembali ke Settings</button>
                    </div>

                    <div class="settings-security-pane is-active" data-security-pane="reset-password">
                        <form method="POST" action="settings.php" class="settings-form settings-security-form" data-security-submit-pane="reset-password">
                            <input type="hidden" name="action" value="reset_profile_password">
                            <div class="settings-security-pane-card">
                                <div class="settings-security-pane-title">Reset Password</div>
                                <div class="settings-security-pane-desc">Perbarui password akun Anda dengan kombinasi baru yang lebih aman.</div>

                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="security_old_password" placeholder="Masukkan password lama Anda" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Password Baru</label>
                                        <input type="password" class="form-control" name="security_new_password" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Konfirmasi Password Baru</label>
                                        <input type="password" class="form-control" name="security_confirm_password" required>
                                    </div>
                                </div>

                                <div class="settings-profile-actions settings-profile-actions--security">
                                    <button class="btn btn-profile-outline-save" type="submit">Simpan Password</button>
                                </div>
                            </div>
                        </form>
                        <div class="settings-security-forgot-link">
                            <button type="button" class="settings-security-forgot-btn" data-security-pane-target="forgot-password">Lupa Password?</button>
                        </div>
                    </div>

                    <div class="settings-security-pane" data-security-pane="reset-primary-question">
                        <form method="POST" action="settings.php" class="settings-form settings-security-form" data-security-submit-pane="reset-primary-question">
                            <input type="hidden" name="action" value="reset_primary_security_question">
                            <div class="settings-security-pane-card">
                                <div class="settings-security-pane-title">Reset Pertanyaan Keamanan</div>
                                <div class="settings-security-pane-desc">Atur ulang pertanyaan keamanan utama beserta jawaban barunya.</div>

                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="security_old_password_primary" placeholder="Masukkan password Anda untuk verifikasi" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Pertanyaan Keamanan Utama</label>
                                        <select class="form-select" name="primary_security_question" required>
                                            <?php foreach ($security_question_options as $question_option) { ?>
                                                <option value="<?php echo htmlspecialchars($question_option); ?>" <?php echo $current_user_security['primary_question'] === $question_option ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($question_option); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Jawaban Baru</label>
                                        <input type="text" class="form-control" name="primary_security_answer" required>
                                    </div>
                                </div>

                                <div class="settings-profile-actions settings-profile-actions--security">
                                    <button class="btn btn-profile-outline-save" type="submit">Simpan Pertanyaan Utama</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="settings-security-pane" data-security-pane="additional-questions">
                        <form method="POST" action="settings.php" class="settings-form settings-security-form" data-security-submit-pane="additional-questions">
                            <input type="hidden" name="action" value="update_additional_security_questions">
                            <div class="settings-security-pane-card">
                                <div class="settings-security-pane-title">Menambahkan Pertanyaan Keamanan</div>
                                <div class="settings-security-pane-desc">Kelola pertanyaan keamanan tambahan agar akun memiliki hingga 3 lapis verifikasi.</div>

                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="security_old_password_additional" placeholder="Masukkan password Anda untuk verifikasi" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Pertanyaan Keamanan 2</label>
                                        <select class="form-select" name="security_question_secondary">
                                            <option value="">Tidak digunakan</option>
                                            <?php foreach ($security_question_options as $question_option) { ?>
                                                <option value="<?php echo htmlspecialchars($question_option); ?>" <?php echo $current_user_security['secondary_question'] === $question_option ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($question_option); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Jawaban Pertanyaan 2</label>
                                        <input type="text" class="form-control" name="security_answer_secondary" placeholder="Isi jika ingin menambah/memperbarui">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Pertanyaan Keamanan 3</label>
                                        <select class="form-select" name="security_question_tertiary">
                                            <option value="">Tidak digunakan</option>
                                            <?php foreach ($security_question_options as $question_option) { ?>
                                                <option value="<?php echo htmlspecialchars($question_option); ?>" <?php echo $current_user_security['tertiary_question'] === $question_option ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($question_option); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Jawaban Pertanyaan 3</label>
                                        <input type="text" class="form-control" name="security_answer_tertiary" placeholder="Isi jika ingin menambah/memperbarui">
                                    </div>
                                </div>

                                <div class="settings-profile-actions settings-profile-actions--security">
                                    <button class="btn btn-profile-outline-save" type="submit">Simpan Pertanyaan Tambahan</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="settings-security-pane" data-security-pane="forgot-password">
                        <div class="settings-security-pane-card">
                            <div class="settings-security-pane-title">Lupa Password?</div>
                            <div class="settings-security-pane-desc">Jawab pertanyaan keamanan Anda untuk mengatur ulang password.</div>

                            <div id="forgotPasswordStage1">
                                <form method="POST" action="settings.php" class="settings-form settings-security-form">
                                    <input type="hidden" name="action" value="forgot_password_verify_question">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Pertanyaan Keamanan Anda</label>
                                            <div class="form-control-static">
                                                <?php 
                                                    $primary_q = $current_user_security['primary_question'] ?? 'Tidak ada pertanyaan yang ditetapkan';
                                                    echo htmlspecialchars($primary_q);
                                                ?>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Jawaban Anda</label>
                                            <input type="text" class="form-control" name="security_question_answer" placeholder="Ketik jawaban Anda" required>
                                        </div>
                                    </div>

                                    <div class="settings-profile-actions settings-profile-actions--security">
                                        <button class="btn btn-profile-outline-save" type="submit">Verifikasi Jawaban</button>
                                    </div>
                                </form>
                            </div>

                            <div id="forgotPasswordStage2" style="display: none;">
                                <form method="POST" action="settings.php" class="settings-form settings-security-form">
                                    <input type="hidden" name="action" value="forgot_password_set_new">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Password Baru</label>
                                            <input type="password" class="form-control" name="forgot_password_new" placeholder="Masukkan password baru" required>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Konfirmasi Password Baru</label>
                                            <input type="password" class="form-control" name="forgot_password_confirm" placeholder="Konfirmasi password baru" required>
                                        </div>
                                    </div>

                                    <div class="settings-profile-actions settings-profile-actions--security">
                                        <button class="btn btn-profile-outline-save" type="submit">Simpan Password Baru</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($is_admin) { ?>
            <div class="panel settings-panel settings-panel--attendance" id="store-profile" data-settings-panel="store-profile" aria-hidden="true">
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

            <div class="panel settings-panel settings-panel--profile" id="user-management" data-settings-panel="user-management" aria-hidden="true">
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

            <div class="panel settings-panel settings-panel--attendance" id="attendance-log" data-settings-panel="attendance-log" aria-hidden="true">
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
            <div class="panel settings-panel settings-panel--attendance" id="attendance" data-settings-panel="attendance" aria-hidden="true">
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
