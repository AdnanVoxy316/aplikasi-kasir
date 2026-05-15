<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/auth_bootstrap.php';

ensureAuthTablesAndSeedAdmin($conn);

$is_logged_in = isLoggedIn();
$current_user = $is_logged_in ? getCurrentCashier() : null;
$current_user_id = (int) ($current_user['id'] ?? 0);
$current_role = $is_logged_in ? getCurrentUserRole() : 'guest';
$is_admin = $current_role === 'admin';
$is_cashier = $current_role === 'kasir';

function attendanceRespondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_action'])) {
    $action = (string) ($_POST['attendance_action'] ?? '');

    if (!$is_logged_in) {
        attendanceRespondJson([
            'success' => false,
            'message' => 'Anda harus login untuk mengakses attendance.',
        ], 401);
    }

    if ($action === 'toggle') {
        if (!$is_cashier) {
            attendanceRespondJson([
                'success' => false,
                'message' => 'Hanya kasir yang dapat melakukan attendance toggle.',
            ], 403);
        }

        $openLog = $conn->query("SELECT id FROM attendance_logs WHERE user_id = $current_user_id AND clock_out_at IS NULL ORDER BY id DESC LIMIT 1");
        $result = ($openLog && $openLog->num_rows > 0)
            ? attendanceClockOutUser($conn, $current_user_id)
            : attendanceClockInUser($conn, $current_user_id);

        if (empty($result['success'])) {
            attendanceRespondJson([
                'success' => false,
                'message' => (string) ($result['message'] ?? 'Gagal memproses attendance.'),
            ], (int) ($result['code'] ?? 500));
        }

        $attendancePayload = [
            'status' => (($result['status'] ?? 'Offline') === 'Online') ? 'Masuk' : 'Pulang',
            'clock_in' => $result['clock_in_at'] ?? null,
            'clock_out' => $result['clock_out_at'] ?? null,
            'total_hours' => round(((int) ($result['duration_seconds'] ?? 0)) / 3600, 2),
            'clock_in_ts' => !empty($result['clock_in_at']) ? strtotime($result['clock_in_at']) : null,
        ];

        attendanceRespondJson([
            'success' => true,
            'message' => (string) ($result['message'] ?? 'Attendance berhasil diproses.'),
            'attendance' => $attendancePayload,
        ]);
    }

    if ($action === 'status') {
        if (!$is_cashier) {
            attendanceRespondJson([
                'success' => false,
                'message' => 'Hanya kasir yang dapat mengakses status attendance pribadi.',
            ], 403);
        }

        $today = date('Y-m-d');
        $row = null;
        $stmt = $conn->prepare('SELECT id, date, clock_in, clock_out, total_hours, status FROM attendance WHERE user_id = ? AND date = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('is', $current_user_id, $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
        }

        if (!$row) {
            $openLog = $conn->query("SELECT clock_in_at, clock_out_at FROM attendance_logs WHERE user_id = $current_user_id AND DATE(clock_in_at) = '$today' ORDER BY id DESC LIMIT 1");
            if ($openLog && $openLog->num_rows > 0) {
                $logRow = $openLog->fetch_assoc();
                $clockIn = (string) ($logRow['clock_in_at'] ?? '');
                $clockOut = !empty($logRow['clock_out_at']) ? (string) $logRow['clock_out_at'] : null;
                $seconds = 0;
                if ($clockIn !== '') {
                    $seconds = max(0, (strtotime($clockOut ?: date('Y-m-d H:i:s')) ?: time()) - (strtotime($clockIn) ?: time()));
                }
                $row = [
                    'date' => $today,
                    'clock_in' => $clockIn !== '' ? $clockIn : null,
                    'clock_out' => $clockOut,
                    'total_hours' => round($seconds / 3600, 2),
                    'status' => $clockOut ? 'Pulang' : 'Masuk',
                    'clock_in_ts' => $clockIn !== '' ? strtotime($clockIn) : null,
                ];
            }
        }

        $clockInTs = !empty($row['clock_in']) ? strtotime($row['clock_in']) : null;

        attendanceRespondJson([
            'success' => true,
            'attendance' => $row ? array_merge($row, ['clock_in_ts' => $clockInTs]) : [
                'date' => $today,
                'clock_in' => null,
                'clock_out' => null,
                'total_hours' => 0,
                'status' => 'Pulang',
                'clock_in_ts' => null,
            ],
        ]);
    }

    if ($action === 'monitor') {
        if (!$is_admin) {
            attendanceRespondJson([
                'success' => false,
                'message' => 'Hanya admin yang dapat melihat live monitor attendance.',
            ], 403);
        }

        $monitorRows = [];

        $monitorSql = "
            SELECT
                u.id,
                u.nama_lengkap,
                u.username,
                u.is_active,
                al.clock_in_at,
                al.clock_out_at
            FROM users u
            LEFT JOIN attendance_logs al ON al.id = (
                SELECT al2.id
                FROM attendance_logs al2
                WHERE al2.user_id = u.id
                ORDER BY al2.clock_in_at DESC
                LIMIT 1
            )
            WHERE u.role = 'kasir'
            ORDER BY u.nama_lengkap ASC
        ";

        $result = $conn->query($monitorSql);
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $clockIn = !empty($row['clock_in_at']) ? (string) $row['clock_in_at'] : '';
                $clockOut = !empty($row['clock_out_at']) ? (string) $row['clock_out_at'] : null;
                $status = ($clockIn !== '' && $clockOut === null) ? 'Masuk' : 'Pulang';

                $durationSeconds = 0;
                if ($clockIn !== '') {
                    $durationSeconds = max(0, (strtotime($clockOut ?: date('Y-m-d H:i:s')) ?: time()) - (strtotime($clockIn) ?: time()));
                }

                $monitorRows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['nama_lengkap'] ?? 'Kasir'),
                    'username' => (string) ($row['username'] ?? ''),
                    'is_active' => (int) ($row['is_active'] ?? 0) === 1,
                    'status' => $status,
                    'clock_in' => $clockIn !== '' ? $clockIn : null,
                    'clock_out' => $clockOut,
                    'total_hours' => round($durationSeconds / 3600, 2),
                    'duration_seconds' => $durationSeconds,
                ];
            }
        }

        attendanceRespondJson([
            'success' => true,
            'cashiers' => $monitorRows,
        ]);
    }

    attendanceRespondJson([
        'success' => false,
        'message' => 'Aksi attendance tidak valid.',
    ], 400);
}

$is_guest_mode = !isLoggedIn();

$today = date('Y-m-d');
$attendanceToday = [
    'date' => $today,
    'clock_in' => null,
    'clock_out' => null,
    'total_hours' => 0,
    'status' => 'Pulang',
];

if ($is_cashier) {
    $stmtMyAttendance = $conn->prepare('SELECT date, clock_in, clock_out, total_hours, status FROM attendance WHERE user_id = ? AND date = ? LIMIT 1');
    if ($stmtMyAttendance) {
        $stmtMyAttendance->bind_param('is', $current_user_id, $today);
        $stmtMyAttendance->execute();
        $resultMyAttendance = $stmtMyAttendance->get_result();
        $rowMyAttendance = $resultMyAttendance ? $resultMyAttendance->fetch_assoc() : null;
        $stmtMyAttendance->close();

        if ($rowMyAttendance) {
            $attendanceToday = [
                'date' => (string) ($rowMyAttendance['date'] ?? $today),
                'clock_in' => $rowMyAttendance['clock_in'] ?? null,
                'clock_out' => $rowMyAttendance['clock_out'] ?? null,
                'total_hours' => (float) ($rowMyAttendance['total_hours'] ?? 0),
                'status' => (string) ($rowMyAttendance['status'] ?? 'Pulang'),
            ];
        }
    }

    if (empty($attendanceToday['clock_in'])) {
        $myLatestLog = $conn->query("SELECT clock_in_at, clock_out_at FROM attendance_logs WHERE user_id = $current_user_id ORDER BY clock_in_at DESC LIMIT 1");
        if ($myLatestLog && $myLatestLog->num_rows > 0) {
            $latestLogRow = $myLatestLog->fetch_assoc();
            $clockIn = !empty($latestLogRow['clock_in_at']) ? (string) $latestLogRow['clock_in_at'] : null;
            $clockOut = !empty($latestLogRow['clock_out_at']) ? (string) $latestLogRow['clock_out_at'] : null;
            $seconds = 0;
            if (!empty($clockIn)) {
                $seconds = max(0, (strtotime($clockOut ?: date('Y-m-d H:i:s')) ?: time()) - (strtotime($clockIn) ?: time()));
            }

            $attendanceToday = [
                'date' => !empty($clockIn) ? date('Y-m-d', strtotime($clockIn)) : $today,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'total_hours' => round($seconds / 3600, 2),
                'status' => $clockOut ? 'Pulang' : (!empty($clockIn) ? 'Masuk' : 'Pulang'),
            ];
        }
    }
}

$adminLiveCashiers = [];
if ($is_admin) {
    $monitorSql = "
        SELECT
            u.id,
            u.nama_lengkap,
            u.username,
            u.is_active,
            al.clock_in_at,
            al.clock_out_at
        FROM users u
        LEFT JOIN attendance_logs al ON al.id = (
            SELECT al2.id
            FROM attendance_logs al2
            WHERE al2.user_id = u.id
            ORDER BY al2.clock_in_at DESC
            LIMIT 1
        )
        WHERE u.role = 'kasir'
        ORDER BY u.nama_lengkap ASC
    ";

    $resultMonitor = $conn->query($monitorSql);
    if ($resultMonitor instanceof mysqli_result) {
        while ($row = $resultMonitor->fetch_assoc()) {
            $clockIn = !empty($row['clock_in_at']) ? (string) $row['clock_in_at'] : '';
            $clockOut = !empty($row['clock_out_at']) ? (string) $row['clock_out_at'] : null;
            $status = ($clockIn !== '' && $clockOut === null) ? 'Masuk' : 'Pulang';
            $durationSeconds = 0;

            if ($clockIn !== '') {
                $durationSeconds = max(0, (strtotime($clockOut ?: date('Y-m-d H:i:s')) ?: time()) - (strtotime($clockIn) ?: time()));
            }

            $adminLiveCashiers[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['nama_lengkap'] ?? 'Kasir'),
                'username' => (string) ($row['username'] ?? ''),
                'is_active' => (int) ($row['is_active'] ?? 0) === 1,
                'status' => $status,
                'clock_in' => $clockIn !== '' ? $clockIn : null,
                'clock_out' => $clockOut,
                'total_hours' => round($durationSeconds / 3600, 2),
                'duration_seconds' => $durationSeconds,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Pintar - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body
    id="dashboardPageRoot"
    data-is-logged-in="<?php echo $is_logged_in ? '1' : '0'; ?>"
    data-role="<?php echo htmlspecialchars($current_role); ?>"
>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <?php if ($is_guest_mode) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="fas fa-user-lock me-2"></i>
                    <div><strong>Welcome Guest</strong> — Anda sedang dalam mode terbatas. Silakan login via <strong>Settings</strong> untuk akses penuh.</div>
                </div>
            <?php } ?>

            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-card-icon sales"><i class="fas fa-dollar-sign"></i></div>
                        <h6 class="stat-card-title">Total Sales</h6>
                        <p class="stat-card-value"><?php echo $is_guest_mode ? '***' : 'Rp 1.250.000'; ?></p>
                        <p class="stat-card-subtitle"><?php echo $is_guest_mode ? 'Login required for full report' : '↑ 12% from last week'; ?></p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-card-icon items"><i class="fas fa-shopping-cart"></i></div>
                        <h6 class="stat-card-title">Total Items</h6>
                        <p class="stat-card-value"><?php echo $is_guest_mode ? '***' : '1,245'; ?></p>
                        <p class="stat-card-subtitle"><?php echo $is_guest_mode ? 'Restricted preview' : '↑ 8% from last week'; ?></p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-card-icon stock"><i class="fas fa-exclamation-triangle"></i></div>
                        <h6 class="stat-card-title">Low Stock</h6>
                        <p class="stat-card-value"><?php echo $is_guest_mode ? '***' : '23'; ?></p>
                        <p class="stat-card-subtitle"><?php echo $is_guest_mode ? 'Login to see stock alerts' : 'Items below threshold'; ?></p>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-card-icon customers"><i class="fas fa-users"></i></div>
                        <h6 class="stat-card-title">Customers</h6>
                        <p class="stat-card-value"><?php echo $is_guest_mode ? '***' : '342'; ?></p>
                        <p class="stat-card-subtitle"><?php echo $is_guest_mode ? 'Guest view only' : '↑ 5% from last month'; ?></p>
                    </div>
                </div>
            </div>

            <?php if ($is_cashier) { ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div
                            class="attendance-card attendance-cashier-widget"
                            id="attendanceCashierWidget"
                            data-clock-in="<?php echo htmlspecialchars((string) ($attendanceToday['clock_in'] ?? '')); ?>"
                            data-clock-out="<?php echo htmlspecialchars((string) ($attendanceToday['clock_out'] ?? '')); ?>"
                            data-status="<?php echo htmlspecialchars((string) ($attendanceToday['status'] ?? 'Pulang')); ?>"
                            data-total-hours="<?php echo htmlspecialchars((string) ($attendanceToday['total_hours'] ?? 0)); ?>"
                            data-clock-in-ts="<?php echo isset($attendanceToday['clock_in']) && $attendanceToday['clock_in'] ? strtotime($attendanceToday['clock_in']) : ''; ?>"
                        >
                            <div class="attendance-card-header">
                                <div>
                                    <h4 class="attendance-title">Advanced Attendance</h4>
                                    <p class="attendance-subtitle">Kelola absensi kerja Anda secara live dan profesional.</p>
                                </div>
                                <span id="attendanceStatusBadge" class="attendance-status-badge <?php echo (($attendanceToday['status'] ?? 'Pulang') === 'Masuk') ? 'online' : 'offline'; ?>">
                                    <?php echo (($attendanceToday['status'] ?? 'Pulang') === 'Masuk') ? 'On Duty' : 'Off Duty'; ?>
                                </span>
                            </div>

                            <div class="attendance-grid">
                                <div class="attendance-info-box">
                                    <div class="attendance-info-label">Absen Masuk</div>
                                    <div class="attendance-info-value" id="attendanceClockInValue"><?php echo !empty($attendanceToday['clock_in']) ? htmlspecialchars(date('d/m/Y H:i:s', strtotime((string) $attendanceToday['clock_in']))) : '-'; ?></div>
                                </div>
                                <div class="attendance-info-box">
                                    <div class="attendance-info-label">Absen Pulang</div>
                                    <div class="attendance-info-value" id="attendanceClockOutValue"><?php echo !empty($attendanceToday['clock_out']) ? htmlspecialchars(date('d/m/Y H:i:s', strtotime((string) $attendanceToday['clock_out']))) : '-'; ?></div>
                                </div>
                                <div class="attendance-info-box">
                                    <div class="attendance-info-label">Durasi Kerja</div>
                                    <div class="attendance-info-value" id="attendanceDurationValue">00:00:00</div>
                                </div>
                            </div>

                            <div class="attendance-actions">
                                <button type="button" id="attendanceToggleBtn"
                                        class="btn btn-minimalist <?php echo (($attendanceToday['status'] ?? 'Pulang') === 'Masuk') ? 'btn-minimalist-danger' : 'btn-minimalist-success'; ?>">
                                    <i class="fas <?php echo (($attendanceToday['status'] ?? 'Pulang') === 'Masuk') ? 'fa-sign-out-alt' : 'fa-sign-in-alt'; ?>" id="attendanceToggleIcon"></i>
                                    <span id="attendanceToggleLabel"><?php echo (($attendanceToday['status'] ?? 'Pulang') === 'Masuk') ? 'Absen Pulang' : 'Absen Masuk'; ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if ($is_admin) { ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="attendance-card attendance-admin-widget" id="attendanceAdminMonitor">
                            <div class="attendance-card-header">
                                <div>
                                    <h4 class="attendance-title">Live Cashier Monitor</h4>
                                    <p class="attendance-subtitle">Pantau status absensi seluruh kasir secara real-time.</p>
                                </div>
                                <span class="attendance-status-badge online">Live</span>
                            </div>

                            <div id="attendanceAdminGrid" class="attendance-admin-grid">
                                <?php if (count($adminLiveCashiers) === 0) { ?>
                                    <div class="attendance-empty">Belum ada data kasir untuk dimonitor.</div>
                                <?php } else { ?>
                                    <?php foreach ($adminLiveCashiers as $cashierLive) { ?>
                                        <div class="attendance-admin-item <?php echo (($cashierLive['status'] ?? 'Pulang') === 'Masuk') ? 'online' : 'offline'; ?>" data-status="<?php echo htmlspecialchars((string) ($cashierLive['status'] ?? 'Pulang')); ?>" data-duration-seconds="<?php echo (int) ($cashierLive['duration_seconds'] ?? 0); ?>">
                                            <div class="attendance-admin-top">
                                                <div class="attendance-admin-user">
                                                    <div class="attendance-admin-name"><?php echo htmlspecialchars((string) ($cashierLive['name'] ?? 'Kasir')); ?></div>
                                                    <div class="attendance-admin-username">@<?php echo htmlspecialchars((string) ($cashierLive['username'] ?? 'cashier')); ?></div>
                                                </div>
                                                <div class="attendance-live-indicator-wrap">
                                                    <span class="attendance-live-indicator"></span>
                                                </div>
                                            </div>
                                            <div class="attendance-admin-meta">
                                                <div><span>Clock In:</span> <strong><?php echo !empty($cashierLive['clock_in']) ? htmlspecialchars(date('H:i:s', strtotime((string) $cashierLive['clock_in']))) : '-'; ?></strong></div>
                                                <div><span>Clock Out:</span> <strong><?php echo !empty($cashierLive['clock_out']) ? htmlspecialchars(date('H:i:s', strtotime((string) $cashierLive['clock_out']))) : '-'; ?></strong></div>
                                                <div><span>Work Timer:</span> <strong class="attendance-admin-duration">00:00:00</strong></div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>
