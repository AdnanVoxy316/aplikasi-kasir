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

/* ─── REAL DATA FROM DB ─── */
$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');

$dbRevenueToday    = 0;
$dbTxCountToday    = 0;
$dbBestProductName = '-';
$dbBestProductQty  = 0;

$stmtRev = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS r FROM transactions WHERE transaction_date BETWEEN ? AND ?");
if ($stmtRev) {
    $stmtRev->bind_param('ss', $todayStart, $todayEnd);
    $stmtRev->execute();
    $r = $stmtRev->get_result()->fetch_assoc();
    $dbRevenueToday = (float) ($r['r'] ?? 0);
    $stmtRev->close();
}

$stmtCnt = $conn->prepare("SELECT COUNT(*) AS c FROM transactions WHERE transaction_date BETWEEN ? AND ?");
if ($stmtCnt) {
    $stmtCnt->bind_param('ss', $todayStart, $todayEnd);
    $stmtCnt->execute();
    $c = $stmtCnt->get_result()->fetch_assoc();
    $dbTxCountToday = (int) ($c['c'] ?? 0);
    $stmtCnt->close();
}

$stmtBest = $conn->prepare("
    SELECT p.name, SUM(td.qty) AS qty
    FROM transaction_details td
    INNER JOIN transactions t ON t.id = td.transaction_id
    INNER JOIN products p ON p.id = td.product_id
    WHERE t.transaction_date BETWEEN ? AND ?
    GROUP BY td.product_id, p.name
    ORDER BY qty DESC LIMIT 1
");
if ($stmtBest) {
    $stmtBest->bind_param('ss', $todayStart, $todayEnd);
    $stmtBest->execute();
    $b = $stmtBest->get_result()->fetch_assoc();
    if ($b) {
        $dbBestProductName = $b['name'];
        $dbBestProductQty  = (int) $b['qty'];
    }
    $stmtBest->close();
}

/* ─── DUMMY DATA ─── */

/* Menu grid sections */
$reportMenuSections = $is_logged_in ? [
    ['id' => 'report-overview',      'icon' => 'fa-chart-pie',    'title' => 'Overview',           'desc' => 'Total pendapatan, transaksi & produk terlaris.', 'badge' => 'Summary'],
    ['id' => 'transaction-history',  'icon' => 'fa-history',       'title' => 'Riwayat Transaksi',  'desc' => 'Lihat semua transaksi harian & rincian item.',     'badge' => '100+ Records'],
    ['id' => 'daily-summary',        'icon' => 'fa-calendar-check', 'title' => 'Ringkasan Harian',   'desc' => 'Rekap pendapatan & jumlah transaksi per hari.',   'badge' => '7 Hari Terakhir'],
    ['id' => 'best-sellers',         'icon' => 'fa-trophy',         'title' => 'Produk Terlaris',    'desc' => 'Peringkat produk paling banyak terjual.',         'badge' => 'Top 10'],
    ['id' => 'cashier-performance',   'icon' => 'fa-user-check',     'title' => 'Performa Kasir',     'desc' => 'Statistik kasir: jumlah tx & rata-rata belanja.', 'badge' => 'Live'],
] : [];

/* ─── Build detail map from DB ─── */
$reportRows = [];
$res = $conn->query("
    SELECT id, transaction_date, transaction_number, cashier_name,
           total_amount, payment_amount, change_amount, payment_method
    FROM transactions ORDER BY transaction_date DESC, id DESC LIMIT 100
");
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) $reportRows[] = $row;
}

$trxDetailsMap = [];
if (!empty($reportRows)) {
    $ids = array_filter(array_map(fn($r) => (int) ($r['id'] ?? 0), $reportRows));
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $tp = str_repeat('i', count($ids));
        $st = $conn->prepare("
            SELECT td.transaction_id, p.code, p.name, td.qty, td.subtotal, p.price
            FROM transaction_details td
            INNER JOIN products p ON p.id = td.product_id
            WHERE td.transaction_id IN ($ph) ORDER BY td.transaction_id, td.id
        ");
        if ($st) {
            $bp = [$tp];
            foreach ($ids as $k => $v) $bp[] = &$ids[$k];
            call_user_func_array([$st, 'bind_param'], $bp);
            $st->execute();
            $dr = $st->get_result();
            while ($d = $dr->fetch_assoc()) {
                $tid = (int) $d['transaction_id'];
                if (!isset($trxDetailsMap[$tid])) $trxDetailsMap[$tid] = [];
                $trxDetailsMap[$tid][] = [
                    'code'    => (string) ($d['code'] ?? ''),
                    'name'    => (string) ($d['name'] ?? ''),
                    'qty'     => (int)    ($d['qty'] ?? 0),
                    'price'   => (float)  ($d['price'] ?? 0),
                    'subtotal'=> (float)  ($d['subtotal'] ?? 0),
                ];
            }
            $st->close();
        }
    }
}

/* DUMMY transaction rows (blended with real DB data) */
$dummyHistoryRows = [
    ['id' => 999, 'transaction_date' => date('Y-m-d H:i:s'),        'transaction_number' => 'TRX-' . date('ymd') . '-001', 'cashier_name' => $current_user['name'] ?? 'Kasir Satu', 'total_amount' => 125000,  'payment_amount' => 150000, 'change_amount' => 25000,  'payment_method' => 'Cash', 'is_dummy' => true],
    ['id' => 998, 'transaction_date' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'transaction_number' => 'TRX-' . date('ymd') . '-002', 'cashier_name' => $current_user['name'] ?? 'Kasir Satu', 'total_amount' => 43500,   'payment_amount' =>  50000, 'change_amount' =>  6500, 'payment_method' => 'Cash', 'is_dummy' => true],
    ['id' => 997, 'transaction_date' => date('Y-m-d H:i:s', strtotime('-5 hours')), 'transaction_number' => 'TRX-' . date('ymd') . '-003', 'cashier_name' => 'Kasir Dua',  'total_amount' => 289000,  'payment_amount' => 300000, 'change_amount' => 11000, 'payment_method' => 'Cash', 'is_dummy' => true],
    ['id' => 996, 'transaction_date' => date('Y-m-d H:i:s', strtotime('-7 hours')), 'transaction_number' => 'TRX-' . date('ymd') . '-004', 'cashier_name' => $current_user['name'] ?? 'Kasir Satu', 'total_amount' =>  75000,  'payment_amount' => 100000, 'change_amount' => 25000, 'payment_method' => 'Cash', 'is_dummy' => true],
    ['id' => 995, 'transaction_date' => date('Y-m-d H:i:s', strtotime('-1 day')),   'transaction_number' => 'TRX-' . date('ymd', strtotime('-1 day')) . '-001', 'cashier_name' => 'Kasir Dua',  'total_amount' => 198000, 'payment_amount' => 200000, 'change_amount' =>  2000, 'payment_method' => 'Cash', 'is_dummy' => true],
    ['id' => 994, 'transaction_date' => date('Y-m-d H:i:s', strtotime('-1 day')),   'transaction_number' => 'TRX-' . date('ymd', strtotime('-1 day')) . '-002', 'cashier_name' => $current_user['name'] ?? 'Kasir Satu', 'total_amount' =>  65000, 'payment_amount' => 100000, 'change_amount' => 35000, 'payment_method' => 'Cash', 'is_dummy' => true],
    ['id' => 993, 'transaction_date' => date('Y-m-d H:i:s', strtotime('-2 days')), 'transaction_number' => 'TRX-' . date('ymd', strtotime('-2 days')) . '-001', 'cashier_name' => 'Kasir Tiga', 'total_amount' => 345000, 'payment_amount' => 350000, 'change_amount' =>  5000, 'payment_method' => 'Cash', 'is_dummy' => true],
];

$allHistoryRows = array_merge($reportRows, $dummyHistoryRows);

/* ─── PANEL B — RINGKASAN HARIAN (7 hari) ─── */
$dummyDailySummary = [
    ['date_full' => date('l, d F Y'),               'date_short' => date('d/m'), 'revenue' => 12750000, 'tx_count' => 127,  'avg_basket' => 100394, 'top_product' => 'Indomie Goreng'],
    ['date_full' => date('l, d F Y', strtotime('-1 day')),  'date_short' => date('d/m', strtotime('-1 day')),  'revenue' =>  9320000, 'tx_count' => 94,   'avg_basket' =>  99149, 'top_product' => 'Aqua 240ml'],
    ['date_full' => date('l, d F Y', strtotime('-2 days')), 'date_short' => date('d/m', strtotime('-2 days')), 'revenue' => 11180000, 'tx_count' => 108,  'avg_basket' => 103519, 'top_product' => 'Kopi Sachet'],
    ['date_full' => date('l, d F Y', strtotime('-3 days')), 'date_short' => date('d/m', strtotime('-3 days')), 'revenue' =>  8450000, 'tx_count' => 82,   'avg_basket' => 102927, 'top_product' => 'Indomie Goreng'],
    ['date_full' => date('l, d F Y', strtotime('-4 days')), 'date_short' => date('d/m', strtotime('-4 days')), 'revenue' => 13600000, 'tx_count' => 135,  'avg_basket' => 100741, 'top_product' => 'Rokok 12'],
    ['date_full' => date('l, d F Y', strtotime('-5 days')), 'date_short' => date('d/m', strtotime('-5 days')), 'revenue' => 10900000, 'tx_count' => 110,  'avg_basket' =>  99091, 'top_product' => 'Teh Kotak'],
    ['date_full' => date('l, d F Y', strtotime('-6 days')), 'date_short' => date('d/m', strtotime('-6 days')), 'revenue' =>  7800000, 'tx_count' => 78,   'avg_basket' => 100000, 'top_product' => 'Indomie Goreng'],
];

/* ─── PANEL C — PRODUK TERLARIS (top 10) ─── */
$dummyBestSellers = [
    ['rank' =>  1, 'name' => 'Indomie Goreng',   'category' => 'Makanan',  'qty' => 248, 'revenue' => 2976000],
    ['rank' =>  2, 'name' => 'Aqua 240ml',        'category' => 'Minuman',  'qty' => 215, 'revenue' =>  645000],
    ['rank' =>  3, 'name' => 'Kopi Sachet',       'category' => 'Minuman',  'qty' => 198, 'revenue' =>  396000],
    ['rank' =>  4, 'name' => 'Rokok 12',          'category' => 'Lain-Lain','qty' => 175, 'revenue' => 3500000],
    ['rank' =>  5, 'name' => 'Teh Kotak',         'category' => 'Minuman',  'qty' => 162, 'revenue' =>  486000],
    ['rank' =>  6, 'name' => 'Mie Sedaap',        'category' => 'Makanan',  'qty' => 140, 'revenue' =>  700000],
    ['rank' =>  7, 'name' => 'Beras 1kg',         'category' => 'Makanan',  'qty' =>  89, 'revenue' =>  890000],
    ['rank' =>  8, 'name' => 'Sabun Cuci Piring', 'category' => 'Pembersih','qty' =>  75, 'revenue' =>  375000],
    ['rank' =>  9, 'name' => 'Popsicle Es Krim',  'category' => 'Es Krim', 'qty' =>  68, 'revenue' =>  340000],
    ['rank' => 10, 'name' => 'Pasta Gigi',         'category' => 'Pembersih','qty' =>  55, 'revenue' =>  275000],
];

/* ─── PANEL D — PERFORMA KASIR ─── */
$dummyCashierPerformance = [
    ['name' => $current_user['name'] ?? 'Kasir Satu', 'username' => $current_user['username'] ?? 'kasir1', 'today_tx' => 42, 'today_rev' => 4215000, 'avg_basket' => 100357],
    ['name' => 'Kasir Dua',  'username' => 'kasir2', 'today_tx' => 38, 'today_rev' => 3810000, 'avg_basket' => 100263],
    ['name' => 'Kasir Tiga', 'username' => 'kasir3', 'today_tx' => 35, 'today_rev' => 3505000, 'avg_basket' => 100143],
    ['name' => 'Kasir Empat','username' => 'kasir4', 'today_tx' => 30, 'today_rev' => 3010000, 'avg_basket' => 100333],
    ['name' => 'Kasir Lima', 'username' => 'kasir5', 'today_tx' => 25, 'today_rev' => 2505000, 'avg_basket' => 100200],
];

/* ─── DUMMY DETAIL ITEMS (for modal) ─── */
$dummyDetailItems = [
    ['code' => 'MK-0001', 'name' => 'Indomie Goreng',      'qty' => 2, 'price' => 12000, 'subtotal' => 24000],
    ['code' => 'MN-0001', 'name' => 'Aqua 240ml',          'qty' => 1, 'price' =>  3500, 'subtotal' =>  3500],
    ['code' => 'MN-0002', 'name' => 'Kopi Sachet',         'qty' => 3, 'price' =>  2000, 'subtotal' =>  6000],
    ['code' => 'LL-0003', 'name' => 'Rokok 12',            'qty' => 1, 'price' => 85000, 'subtotal' => 85000],
    ['code' => 'SN-0001', 'name' => 'Chips Mozambique',   'qty' => 1, 'price' =>  6000, 'subtotal' =>  6000],
];

function fmt($val) {
    return 'Rp ' . number_format((float) $val, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Control Center - Kasir Pintar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?php echo $is_logged_in ? 'logged-in' : 'guest-mode'; ?>" data-role="<?php echo htmlspecialchars($current_role); ?>">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/header.php'; ?>
    <div class="content reports-content">

        <!-- ═══ HERO ═══ -->
        <div class="reports-hero">
            <div class="reports-hero-copy">
                <h3 class="reports-title">Reports Control Center</h3>
                <small class="reports-subtitle">Pusat kendali laporan, ringkasan, dan statistik kasir.</small>
            </div>
            <span class="reports-role-badge"><?php echo htmlspecialchars($current_role); ?></span>
        </div>

        <!-- ═══ MENU GRID ═══ -->
        <?php if (!empty($reportMenuSections)): ?>
        <div class="menu-grid">
            <?php foreach ($reportMenuSections as $item): ?>
                <a href="#<?php echo $item['id']; ?>"
                   class="menu-card menu-card-report"
                   data-reports-toggle="<?php echo $item['id']; ?>">
                    <span class="menu-card-icon"><i class="fas <?php echo $item['icon']; ?>"></i></span>
                    <div class="title"><?php echo $item['title']; ?></div>
                    <div class="desc"><?php echo $item['desc']; ?></div>
                    <span class="menu-card-badge"><?php echo $item['badge']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════
             PANEL A — TOTAL PENDAPATAN + TOTAL TRANSAKSI + PRODUK TERLARIS
             ══════════════════��═══════════════════════════ -->
        <div class="panel reports-panel" id="report-overview"
             data-reports-panel="report-overview" aria-hidden="true">

            <!-- ── Total Pendapatan ── -->
            <div class="reports-section">
                <div class="reports-section-header">
                    <div class="reports-section-header-left">
                        <h5 class="reports-section-title">
                            <i class="fas fa-wallet me-1"></i> Total Pendapatan
                        </h5>
                        <p class="reports-section-subtitle">Akumulasi semua checkout hari ini.</p>
                    </div>
                    <div class="reports-section-header-right">
                        <span class="reports-period-badge"><?php echo date('l, d F Y'); ?></span>
                    </div>
                </div>
                <div class="reports-kpi-grid">
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-wallet"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Total Pendapatan Hari Ini</div>
                            <div class="reports-kpi-value">
                                <?php echo $dbRevenueToday > 0
                                    ? fmt($dbRevenueToday)
                                    : '<span class="reports-kpi-dummy-flag">Rp 12.750.000</span>'; ?>
                            </div>
                        </div>
                        <div class="reports-kpi-meta">
                            <i class="fas fa-calendar-day"></i> Hari Ini &bull; Semua metode bayar
                        </div>
                    </div>
                    <div class="reports-kpi-card">
                        <div class="reports-kpi-label-sm">Cash</div>
                        <div class="reports-kpi-value-sm"><?php echo $dbRevenueToday > 0 ? fmt($dbRevenueToday) : 'Rp 9.562.500'; ?></div>
                        <div class="reports-kpi-bar-wrap"><div class="reports-kpi-bar" style="width:75%"></div></div>
                    </div>
                    <div class="reports-kpi-card">
                        <div class="reports-kpi-label-sm">QRIS</div>
                        <div class="reports-kpi-value-sm">Rp 0</div>
                        <div class="reports-kpi-bar-wrap"><div class="reports-kpi-bar" style="width:0%"></div></div>
                    </div>
                    <div class="reports-kpi-card">
                        <div class="reports-kpi-label-sm">Debit Card</div>
                        <div class="reports-kpi-value-sm">Rp 3.187.500</div>
                        <div class="reports-kpi-bar-wrap"><div class="reports-kpi-bar" style="width:25%"></div></div>
                    </div>
                </div>
            </div>

            <!-- ── Total Transaksi ── -->
            <div class="reports-section">
                <div class="reports-section-header">
                    <div class="reports-section-header-left">
                        <h5 class="reports-section-title">
                            <i class="fas fa-cash-register me-1"></i> Total Transaksi
                        </h5>
                        <p class="reports-section-subtitle">Jumlah checkout hari ini.</p>
                    </div>
                    <div class="reports-section-header-right">
                        <span class="reports-period-badge"><?php echo date('l, d F Y'); ?></span>
                    </div>
                </div>
                <div class="reports-kpi-grid">
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-receipt"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Total Transaksi Hari Ini</div>
                            <div class="reports-kpi-value">
                                <?php echo $dbTxCountToday > 0
                                    ? number_format($dbTxCountToday, 0, ',', '.')
                                    : '<span class="reports-kpi-dummy-flag">127</span>'; ?>
                            </div>
                        </div>
                        <div class="reports-kpi-meta">
                            <i class="fas fa-calendar-day"></i> Hari Ini &bull; Semua kasir
                        </div>
                    </div>
                    <div class="reports-kpi-card">
                        <div class="reports-kpi-label-sm">Rata-rata Belanja</div>
                        <div class="reports-kpi-value-sm"><?php echo $dbRevenueToday > 0 && $dbTxCountToday > 0 ? fmt($dbRevenueToday / $dbTxCountToday) : 'Rp 100.394'; ?></div>
                    </div>
                    <div class="reports-kpi-card">
                        <div class="reports-kpi-label-sm">Transaksi Tertinggi</div>
                        <div class="reports-kpi-value-sm">Rp 2.850.000</div>
                    </div>
                    <div class="reports-kpi-card">
                        <div class="reports-kpi-label-sm">Transaksi Terendah</div>
                        <div class="reports-kpi-value-sm">Rp 5.000</div>
                    </div>
                </div>
            </div>

            <!-- ── Produk Terlaris ── -->
            <div class="reports-section">
                <div class="reports-section-header">
                    <div class="reports-section-header-left">
                        <h5 class="reports-section-title">
                            <i class="fas fa-star me-1"></i> Produk Terlaris
                        </h5>
                        <p class="reports-section-subtitle">Produk dengan jumlah penjualan tertinggi hari ini.</p>
                    </div>
                    <div class="reports-section-header-right">
                        <span class="reports-period-badge"><?php echo date('l, d F Y'); ?></span>
                    </div>
                </div>
                <div class="reports-kpi-grid">
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon reports-kpi-icon--gold"><i class="fas fa-medal"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Produk Terlaris Hari Ini</div>
                            <div class="reports-kpi-value">
                                <?php echo $dbBestProductName !== '-'
                                    ? htmlspecialchars($dbBestProductName)
                                    : '<span class="reports-kpi-dummy-flag">Indomie Goreng</span>'; ?>
                            </div>
                        </div>
                        <div class="reports-kpi-meta">
                            <i class="fas fa-box"></i> Terjual
                            <?php echo $dbBestProductQty > 0
                                ? number_format($dbBestProductQty, 0, ',', '.') . ' pcs'
                                : '48 pcs'; ?> hari ini
                        </div>
                    </div>
                </div>
                <div class="reports-table-wrap">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th class="col-rank"><i class="fas fa-trophy me-1 text-muted"></i>Peringkat</th>
                                <th class="col-product"><i class="fas fa-box me-1 text-muted"></i>Produk</th>
                                <th class="col-category"><i class="fas fa-tag me-1 text-muted"></i>Kategori</th>
                                <th class="col-qty text-center"><i class="fas fa-shopping-cart me-1 text-muted"></i>Terjual</th>
                                <th class="col-revenue text-end"><i class="fas fa-coins me-1 text-muted"></i>Pendapatan</th>
                                <th class="col-chart"><i class="fas fa-chart-bar me-1 text-muted"></i>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dummyBestSellers as $bs):
                                $maxQty = 248;
                                $pct    = round(($bs['qty'] / $maxQty) * 100);
                                $isTop  = $bs['rank'] <= 3;
                            ?>
                            <tr class="<?php echo $isTop ? 'reports-tr-top' : ''; ?>">
                                <td class="col-rank">
                                    <?php if ($bs['rank'] === 1): ?>
                                        <span class="reports-rank-badge gold"><i class="fas fa-medal"></i> 1</span>
                                    <?php elseif ($bs['rank'] === 2): ?>
                                        <span class="reports-rank-badge silver"><i class="fas fa-medal"></i> 2</span>
                                    <?php elseif ($bs['rank'] === 3): ?>
                                        <span class="reports-rank-badge bronze"><i class="fas fa-medal"></i> 3</span>
                                    <?php else: ?>
                                        <span class="reports-rank-badge"><?php echo $bs['rank']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-product fw-bold"><?php echo htmlspecialchars($bs['name']); ?></td>
                                <td class="col-category"><span class="reports-cat-badge"><?php echo htmlspecialchars($bs['category']); ?></span></td>
                                <td class="col-qty text-center fw-bold"><?php echo number_format($bs['qty'], 0, ',', '.'); ?> <small class="text-muted">pcs</small></td>
                                <td class="col-revenue text-end fw-bold"><?php echo fmt($bs['revenue']); ?></td>
                                <td class="col-chart">
                                    <div class="reports-trend-bar-wrap">
                                        <div class="reports-trend-bar" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
             PANEL B — RINGKASAN HARIAN (tabel)
             ══════════════════════════════════════════════ -->
        <div class="panel reports-panel" id="daily-summary"
             data-reports-panel="daily-summary" aria-hidden="true">
            <div class="reports-section">
                <div class="reports-section-header">
                    <div class="reports-section-header-left">
                        <h5 class="reports-section-title">
                            <i class="fas fa-calendar-check me-1"></i> Ringkasan Harian
                        </h5>
                        <p class="reports-section-subtitle">Rekap pendapatan &amp; jumlah transaksi 7 hari terakhir.</p>
                    </div>
                    <div class="reports-section-header-right">
                        <button type="button" class="btn-reports-outline btn-reports-outline-sm" data-reports-panel-close="1">
                            <i class="fas fa-times me-1"></i> Tutup
                        </button>
                    </div>
                </div>

                <!-- ── KPI row ── -->
                <div class="reports-kpi-grid">
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-wallet"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Total 7 Hari</div>
                            <div class="reports-kpi-value">Rp 75.580.000</div>
                        </div>
                    </div>
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-receipt"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Total Tx 7 Hari</div>
                            <div class="reports-kpi-value">734</div>
                        </div>
                    </div>
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Rata-rata / Hari</div>
                            <div class="reports-kpi-value">Rp 10.797.143</div>
                        </div>
                    </div>
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-trophy"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Best Day</div>
                            <div class="reports-kpi-value">Jumat</div>
                        </div>
                    </div>
                </div>

                <!-- ── Tabel ringkasan harian ── -->
                <div class="reports-table-wrap">
                    <table class="reports-table" id="dailySummaryTable">
                        <thead>
                            <tr>
                                <th class="col-day"><i class="fas fa-calendar me-1 text-muted"></i>Tanggal</th>
                                <th class="col-rev text-end"><i class="fas fa-coins me-1 text-muted"></i>Total Pendapatan</th>
                                <th class="col-tx text-center"><i class="fas fa-receipt me-1 text-muted"></i>Jumlah Tx</th>
                                <th class="col-avg text-end"><i class="fas fa-calculator me-1 text-muted"></i>Rata-rata Belanja</th>
                                <th class="col-top text-start"><i class="fas fa-star me-1 text-muted"></i>Produk Terlaris</th>
                                <th class="col-bar"><i class="fas fa-chart-area me-1 text-muted"></i>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $maxRev = 13600000;
                            foreach ($dummyDailySummary as $day):
                                $pct = round(($day['revenue'] / $maxRev) * 100);
                            ?>
                            <tr>
                                <td class="col-day fw-bold"><?php echo htmlspecialchars($day['date_full']); ?></td>
                                <td class="col-rev text-end fw-bold"><?php echo fmt($day['revenue']); ?></td>
                                <td class="col-tx text-center"><?php echo number_format($day['tx_count'], 0, ',', '.'); ?></td>
                                <td class="col-avg text-end"><?php echo fmt($day['avg_basket']); ?></td>
                                <td class="col-top">
                                    <span class="reports-top-product">
                                        <i class="fas fa-star text-warning"></i>
                                        <?php echo htmlspecialchars($day['top_product']); ?>
                                    </span>
                                </td>
                                <td class="col-bar">
                                    <div class="reports-trend-bar-wrap">
                                        <div class="reports-trend-bar reports-trend-bar--fill" style="width:<?php echo $pct; ?>%"></div>
                                        <span class="reports-trend-pct"><?php echo $pct; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
             PANEL C — RIWAYAT TRANSAKSI (tabel)
             ══════════════════════════════════════════════ -->
        <div class="panel reports-panel" id="transaction-history"
             data-reports-panel="transaction-history" aria-hidden="true">
            <div class="reports-section">
                <div class="reports-section-header">
                    <div class="reports-section-header-left">
                        <h5 class="reports-section-title">
                            <i class="fas fa-history me-1"></i> Riwayat Transaksi
                        </h5>
                        <p class="reports-section-subtitle">Semua transaksi harian dengan detail item. Klik untuk lihat rincian.</p>
                    </div>
                    <div class="reports-section-header-right">
                        <a href="reports.php?export=csv" class="btn-reports-outline btn-reports-outline-sm">
                            <i class="fas fa-download me-1"></i> Export CSV
                        </a>
                        <button type="button" class="btn-reports-outline btn-reports-outline-sm" data-reports-panel-close="1">
                            <i class="fas fa-times me-1"></i> Tutup
                        </button>
                    </div>
                </div>

                <!-- Filter bar -->
                <div class="reports-filter-bar">
                    <div class="reports-filter-group">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" id="reportFilterDate" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="reports-filter-group">
                        <label class="form-label">Kasir</label>
                        <select class="form-select" id="reportFilterCashier">
                            <option value="">Semua Kasir</option>
                            <option value="kasir1" selected>Kasir Satu</option>
                            <option value="kasir2">Kasir Dua</option>
                            <option value="kasir3">Kasir Tiga</option>
                        </select>
                    </div>
                    <div class="reports-filter-group">
                        <label class="form-label">Cari</label>
                        <input type="text" class="form-control" id="reportSearchTx" placeholder="Ketik ID transaksi...">
                    </div>
                    <div class="reports-filter-group reports-filter-group--range">
                        <label class="form-label">Rentang Total</label>
                        <div class="input-group">
                            <input type="number" class="form-control" placeholder="Min" id="reportFilterMin" value="0">
                            <span class="input-group-text">—</span>
                            <input type="number" class="form-control" placeholder="Max" id="reportFilterMax" value="500000">
                        </div>
                    </div>
                </div>

                <!-- Tabel riwayat transaksi -->
                <div class="reports-table-wrap">
                    <table class="reports-table" id="reportsHistoryTable">
                        <thead>
                            <tr>
                                <th class="col-no">No</th>
                                <th class="col-datetime"><i class="fas fa-calendar-alt me-1 text-muted"></i>Tanggal &amp; Waktu</th>
                                <th class="col-trxid"><i class="fas fa-hashtag me-1 text-muted"></i>Transaction ID</th>
                                <th class="col-cashier"><i class="fas fa-user me-1 text-muted"></i>Nama Kasir</th>
                                <th class="col-items text-center"><i class="fas fa-box-open me-1 text-muted"></i>Item</th>
                                <th class="col-total text-end"><i class="fas fa-shopping-cart me-1 text-muted"></i>Total Belanja</th>
                                <th class="col-paid text-end"><i class="fas fa-money-bill me-1 text-muted"></i>Uang Bayar</th>
                                <th class="col-change text-end"><i class="fas fa-wallet me-1 text-muted"></i>Kembalian</th>
                                <th class="col-method"><i class="fas fa-credit-card me-1 text-muted"></i>Metode</th>
                                <th class="col-action"><i class="fas fa-cog me-1 text-muted"></i>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="reportsHistoryBody">
                            <?php
                            $rowNum = 1;
                            foreach ($allHistoryRows as $trx):
                                $trxId   = (int) ($trx['id'] ?? 0);
                                $isDummy = !empty($trx['is_dummy']);
                                $details = $isDummy ? $dummyDetailItems : ($trxDetailsMap[$trxId] ?? []);
                                $detailJson = htmlspecialchars(json_encode($details, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                $totalAmt  = (float) ($trx['total_amount'] ?? 0);
                                $paidAmt   = (float) ($trx['payment_amount'] ?? 0);
                                $changeAmt = (float) ($trx['change_amount'] ?? 0);
                                $cashierNm = htmlspecialchars((string) ($trx['cashier_name'] ?? '-'));
                                $trxNum    = htmlspecialchars((string) ($trx['transaction_number'] ?? '-'));
                                $dateFmt   = date('d/m/Y H:i', strtotime((string) ($trx['transaction_date'] ?? time())));
                                $method    = htmlspecialchars((string) ($trx['payment_method'] ?? 'Cash'));
                                $itemCount = count($details);
                            ?>
                            <tr class="reports-trx-row" data-trx-id="<?php echo $trxId; ?>" data-is-dummy="<?php echo $isDummy ? '1' : '0'; ?>">
                                <td class="col-no"><?php echo $rowNum++; ?></td>
                                <td class="col-datetime"><?php echo $dateFmt; ?></td>
                                <td class="col-trxid">
                                    <button type="button" class="report-trx-number-btn"
                                            data-trx-id="<?php echo $trxId; ?>"
                                            data-trx-num="<?php echo $trxNum; ?>"
                                            data-trx-cashier="<?php echo $cashierNm; ?>"
                                            data-trx-total="<?php echo fmt($totalAmt); ?>"
                                            data-trx-paid="<?php echo fmt($paidAmt); ?>"
                                            data-trx-change="<?php echo fmt($changeAmt); ?>"
                                            data-trx-method="<?php echo $method; ?>"
                                            data-trx-items='<?php echo $detailJson; ?>'>
                                        <?php echo $trxNum; ?>
                                    </button>
                                </td>
                                <td class="col-cashier"><?php echo $cashierNm; ?></td>
                                <td class="col-items text-center">
                                    <span class="reports-item-count"><?php echo $itemCount; ?> item</span>
                                </td>
                                <td class="col-total text-end fw-bold"><?php echo fmt($totalAmt); ?></td>
                                <td class="col-paid text-end"><?php echo fmt($paidAmt); ?></td>
                                <td class="col-change text-end"><?php echo fmt($changeAmt); ?></td>
                                <td class="col-method">
                                    <span class="reports-method-badge"><?php echo $method; ?></span>
                                </td>
                                <td class="col-action">
                                    <button type="button" class="btn-reports-detail"
                                            data-trx-id="<?php echo $trxId; ?>"
                                            title="Lihat detail transaksi">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($allHistoryRows)): ?>
                            <tr>
                                <td colspan="10" class="reports-empty-cell">
                                    <i class="fas fa-inbox"></i>
                                    <span>Belum ada transaksi tercatat.</span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="reports-pagination">
                    <span class="reports-pagination-info">
                        Menampilkan <strong>1–<?php echo min(10, count($allHistoryRows)); ?></strong>
                        dari <strong><?php echo count($allHistoryRows); ?></strong> transaksi
                    </span>
                    <nav class="reports-pagination-nav">
                        <button class="btn-pagination" disabled>&laquo; Prev</button>
                        <button class="btn-pagination active">1</button>
                        <button class="btn-pagination">2</button>
                        <button class="btn-pagination">3</button>
                        <button class="btn-pagination">Next &raquo;</button>
                    </nav>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
             PANEL D — PRODUK TERLARIS (tabel lengkap)
             ══════════════════════════════════════════════ -->
        <div class="panel reports-panel" id="best-sellers"
             data-reports-panel="best-sellers" aria-hidden="true">
            <div class="reports-section">
                <div class="reports-section-header">
                    <div class="reports-section-header-left">
                        <h5 class="reports-section-title">
                            <i class="fas fa-trophy me-1"></i> Produk Terlaris
                        </h5>
                        <p class="reports-section-subtitle">Peringkat 10 produk paling banyak terjual hari ini.</p>
                    </div>
                    <div class="reports-section-header-right">
                        <button type="button" class="btn-reports-outline btn-reports-outline-sm" data-reports-panel-close="1">
                            <i class="fas fa-times me-1"></i> Tutup
                        </button>
                    </div>
                </div>

                <!-- KPI Row -->
                <div class="reports-kpi-grid">
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-fire"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Total Unit Terjual</div>
                            <div class="reports-kpi-value">1.425 pcs</div>
                        </div>
                    </div>
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-coins"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Total Revenue Top 10</div>
                            <div class="reports-kpi-value">Rp 10.083.000</div>
                        </div>
                    </div>
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon reports-kpi-icon--gold"><i class="fas fa-medal"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Best Seller</div>
                            <div class="reports-kpi-value">Indomie Goreng</div>
                        </div>
                    </div>
                </div>

                <!-- Tabel best sellers -->
                <div class="reports-table-wrap">
                    <table class="reports-table" id="bestSellersTable">
                        <thead>
                            <tr>
                                <th class="col-rank"><i class="fas fa-trophy me-1 text-muted"></i>Peringkat</th>
                                <th class="col-product"><i class="fas fa-box me-1 text-muted"></i>Produk</th>
                                <th class="col-category"><i class="fas fa-tag me-1 text-muted"></i>Kategori</th>
                                <th class="col-qty text-center"><i class="fas fa-shopping-cart me-1 text-muted"></i>Terjual (pcs)</th>
                                <th class="col-revenue text-end"><i class="fas fa-coins me-1 text-muted"></i>Total Revenue</th>
                                <th class="col-chart"><i class="fas fa-chart-bar me-1 text-muted"></i>Market Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $maxQty = 248;
                            $totalQty = array_sum(array_column($dummyBestSellers, 'qty'));
                            foreach ($dummyBestSellers as $bs):
                                $pct    = round(($bs['qty'] / $maxQty) * 100);
                                $share  = round(($bs['qty'] / $totalQty) * 100);
                                $isTop  = $bs['rank'] <= 3;
                            ?>
                            <tr class="<?php echo $isTop ? 'reports-tr-top' : ''; ?>">
                                <td class="col-rank">
                                    <?php if ($bs['rank'] === 1): ?>
                                        <span class="reports-rank-badge gold"><i class="fas fa-medal"></i> #1</span>
                                    <?php elseif ($bs['rank'] === 2): ?>
                                        <span class="reports-rank-badge silver"><i class="fas fa-medal"></i> #2</span>
                                    <?php elseif ($bs['rank'] === 3): ?>
                                        <span class="reports-rank-badge bronze"><i class="fas fa-medal"></i> #3</span>
                                    <?php else: ?>
                                        <span class="reports-rank-badge">#<?php echo $bs['rank']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-product">
                                    <span class="fw-bold"><?php echo htmlspecialchars($bs['name']); ?></span>
                                </td>
                                <td class="col-category">
                                    <span class="reports-cat-badge"><?php echo htmlspecialchars($bs['category']); ?></span>
                                </td>
                                <td class="col-qty text-center">
                                    <span class="fw-bold"><?php echo number_format($bs['qty'], 0, ',', '.'); ?></span>
                                    <small class="text-muted"> pcs</small>
                                </td>
                                <td class="col-revenue text-end fw-bold"><?php echo fmt($bs['revenue']); ?></td>
                                <td class="col-chart">
                                    <div class="reports-trend-bar-wrap">
                                        <div class="reports-trend-bar <?php echo $isTop ? 'reports-trend-bar--fill' : 'reports-trend-bar--fill-2'; ?>"
                                             style="width:<?php echo $pct; ?>%"></div>
                                        <span class="reports-trend-pct"><?php echo $share; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
             PANEL E — PERFORMA KASIR (tabel)
             ══════════════════════════════════════════════ -->
        <div class="panel reports-panel" id="cashier-performance"
             data-reports-panel="cashier-performance" aria-hidden="true">
            <div class="reports-section">
                <div class="reports-section-header">
                    <div class="reports-section-header-left">
                        <h5 class="reports-section-title">
                            <i class="fas fa-user-check me-1"></i> Performa Kasir
                        </h5>
                        <p class="reports-section-subtitle">Statistik kasir hari ini: jumlah transaksi, pendapatan, dan rata-rata belanja.</p>
                    </div>
                    <div class="reports-section-header-right">
                        <button type="button" class="btn-reports-outline btn-reports-outline-sm" data-reports-panel-close="1">
                            <i class="fas fa-times me-1"></i> Tutup
                        </button>
                    </div>
                </div>

                <!-- KPI Row -->
                <div class="reports-kpi-grid">
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-users"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Total Kasir Aktif</div>
                            <div class="reports-kpi-value"><?php echo count($dummyCashierPerformance); ?> Orang</div>
                        </div>
                    </div>
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-receipt"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Total Tx Semua Kasir</div>
                            <div class="reports-kpi-value">170 Tx</div>
                        </div>
                    </div>
                    <div class="reports-kpi-card reports-kpi-card--primary">
                        <div class="reports-kpi-icon"><i class="fas fa-star"></i></div>
                        <div class="reports-kpi-body">
                            <div class="reports-kpi-label">Kasir Terbaik</div>
                            <div class="reports-kpi-value"><?php echo htmlspecialchars($current_user['name'] ?? 'Kasir Satu'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Tabel performa kasir -->
                <div class="reports-table-wrap">
                    <table class="reports-table" id="cashierPerfTable">
                        <thead>
                            <tr>
                                <th class="col-cashier-rank"><i class="fas fa-hashtag me-1 text-muted"></i>#</th>
                                <th class="col-cashier-info"><i class="fas fa-user me-1 text-muted"></i>Nama Kasir</th>
                                <th class="col-cashier-uname"><i class="fas fa-at me-1 text-muted"></i>Username</th>
                                <th class="col-cashier-tx text-center"><i class="fas fa-receipt me-1 text-muted"></i>Tx Hari Ini</th>
                                <th class="col-cashier-rev text-end"><i class="fas fa-coins me-1 text-muted"></i>Pendapatan</th>
                                <th class="col-cashier-avg text-end"><i class="fas fa-calculator me-1 text-muted"></i>Rata-rata Belanja</th>
                                <th class="col-cashier-bar"><i class="fas fa-chart-line me-1 text-muted"></i>Contribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $maxTx = 42;
                            $totalTxAll = array_sum(array_column($dummyCashierPerformance, 'today_tx'));
                            $rank = 1;
                            foreach ($dummyCashierPerformance as $cp):
                                $pct   = round(($cp['today_tx'] / $maxTx) * 100);
                                $share = $totalTxAll > 0 ? round(($cp['today_tx'] / $totalTxAll) * 100) : 0;
                                $isTop = $rank === 1;
                            ?>
                            <tr class="<?php echo $isTop ? 'reports-tr-top' : ''; ?>">
                                <td class="col-cashier-rank">
                                    <span class="reports-rank-badge <?php echo $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : '')); ?>">
                                        <?php echo $rank++; ?>
                                    </span>
                                </td>
                                <td class="col-cashier-info">
                                    <div class="reports-cashier-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <span class="fw-bold"><?php echo htmlspecialchars($cp['name']); ?></span>
                                </td>
                                <td class="col-cashier-uname text-muted">@<?php echo htmlspecialchars($cp['username']); ?></td>
                                <td class="col-cashier-tx text-center">
                                    <span class="fw-bold"><?php echo $cp['today_tx']; ?></span>
                                    <small class="text-muted"> tx</small>
                                </td>
                                <td class="col-cashier-rev text-end fw-bold"><?php echo fmt($cp['today_rev']); ?></td>
                                <td class="col-cashier-avg text-end"><?php echo fmt($cp['avg_basket']); ?></td>
                                <td class="col-cashier-bar">
                                    <div class="reports-trend-bar-wrap">
                                        <div class="reports-trend-bar <?php echo $isTop ? 'reports-trend-bar--fill' : 'reports-trend-bar--fill-2'; ?>"
                                             style="width:<?php echo $pct; ?>%"></div>
                                        <span class="reports-trend-pct"><?php echo $share; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ═══ MODAL DETAIL TRANSAKSI ═══ -->
<div class="modal fade" id="reportDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Detail Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="report-detail-meta-grid">
                    <div class="report-detail-meta-item">
                        <span class="report-detail-meta-label">ID Transaksi</span>
                        <span class="report-detail-meta-value" id="rDetailId">-</span>
                    </div>
                    <div class="report-detail-meta-item">
                        <span class="report-detail-meta-label">Kasir</span>
                        <span class="report-detail-meta-value" id="rDetailCashier">-</span>
                    </div>
                    <div class="report-detail-meta-item">
                        <span class="report-detail-meta-label">Metode Bayar</span>
                        <span class="report-detail-meta-value" id="rDetailMethod">-</span>
                    </div>
                    <div class="report-detail-meta-item">
                        <span class="report-detail-meta-label">Total Belanja</span>
                        <span class="report-detail-meta-value" id="rDetailTotal">-</span>
                    </div>
                    <div class="report-detail-meta-item">
                        <span class="report-detail-meta-label">Uang Bayar</span>
                        <span class="report-detail-meta-value" id="rDetailPaid">-</span>
                    </div>
                    <div class="report-detail-meta-item">
                        <span class="report-detail-meta-label">Kembalian</span>
                        <span class="report-detail-meta-value" id="rDetailChange">-</span>
                    </div>
                </div>

                <h6 class="report-detail-items-title">
                    <i class="fas fa-list me-1"></i>Rincian Item
                </h6>
                <table class="table report-detail-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Produk</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Harga</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="rDetailItemsBody">
                        <tr><td colspan="5" class="text-center text-muted">Tidak ada item.</td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="report-detail-total-row">
                            <td colspan="4" class="text-end fw-bold">TOTAL</td>
                            <td class="text-end fw-bold" id="rDetailTotalValue">-</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-reports-outline" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Tutup
                </button>
                <button type="button" class="btn-reports-print" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Cetak Struk
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/script.js"></script>
</body>
</html>