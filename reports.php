<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

$summaryRevenueToday = 0;
$summaryTotalTransactionToday = 0;
$summaryBestProductName = '-';
$summaryBestProductQty = 0;

// Total pendapatan hari ini
$stmtRevenue = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total_revenue FROM transactions WHERE transaction_date BETWEEN ? AND ?");
if ($stmtRevenue) {
    $stmtRevenue->bind_param('ss', $todayStart, $todayEnd);
    $stmtRevenue->execute();
    $resultRevenue = $stmtRevenue->get_result();
    $rowRevenue = $resultRevenue ? $resultRevenue->fetch_assoc() : null;
    $summaryRevenueToday = (float) ($rowRevenue['total_revenue'] ?? 0);
    $stmtRevenue->close();
}

// Total transaksi hari ini
$stmtCount = $conn->prepare("SELECT COUNT(*) AS total_tx FROM transactions WHERE transaction_date BETWEEN ? AND ?");
if ($stmtCount) {
    $stmtCount->bind_param('ss', $todayStart, $todayEnd);
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $rowCount = $resultCount ? $resultCount->fetch_assoc() : null;
    $summaryTotalTransactionToday = (int) ($rowCount['total_tx'] ?? 0);
    $stmtCount->close();
}

// Produk terlaris (hari ini) dari transaction_details
$bestProductSql = "
    SELECT
        p.name AS product_name,
        SUM(td.qty) AS total_qty
    FROM transaction_details td
    INNER JOIN transactions t ON t.id = td.transaction_id
    INNER JOIN products p ON p.id = td.product_id
    WHERE t.transaction_date BETWEEN ? AND ?
    GROUP BY td.product_id, p.name
    ORDER BY total_qty DESC, p.name ASC
    LIMIT 1
";

$stmtBestProduct = $conn->prepare($bestProductSql);
if ($stmtBestProduct) {
    $stmtBestProduct->bind_param('ss', $todayStart, $todayEnd);
    $stmtBestProduct->execute();
    $resultBestProduct = $stmtBestProduct->get_result();
    $rowBestProduct = $resultBestProduct ? $resultBestProduct->fetch_assoc() : null;
    if ($rowBestProduct) {
        $summaryBestProductName = (string) ($rowBestProduct['product_name'] ?? '-');
        $summaryBestProductQty = (int) ($rowBestProduct['total_qty'] ?? 0);
    }
    $stmtBestProduct->close();
}

$reportRows = [];
$query = "
    SELECT
        id,
        transaction_date,
        transaction_number,
        cashier_name,
        total_amount,
        payment_method
    FROM transactions
    ORDER BY transaction_date DESC, id DESC
    LIMIT 100
";

$result = $conn->query($query);
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $reportRows[] = $row;
    }
}

$transactionDetailsMap = [];
if (!empty($reportRows)) {
    $transactionIds = array_map(static fn($row) => (int) ($row['id'] ?? 0), $reportRows);
    $transactionIds = array_values(array_filter($transactionIds, static fn($id) => $id > 0));

    if (!empty($transactionIds)) {
        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $types = str_repeat('i', count($transactionIds));

        $detailSql = "
            SELECT
                td.transaction_id,
                p.code AS product_code,
                p.name AS product_name,
                td.qty,
                td.subtotal
            FROM transaction_details td
            INNER JOIN products p ON p.id = td.product_id
            WHERE td.transaction_id IN ($placeholders)
            ORDER BY td.transaction_id ASC, td.id ASC
        ";

        $stmtDetails = $conn->prepare($detailSql);
        if ($stmtDetails) {
            $bindParams = [$types];
            foreach ($transactionIds as $k => $idValue) {
                $bindParams[] = &$transactionIds[$k];
            }
            call_user_func_array([$stmtDetails, 'bind_param'], $bindParams);
            $stmtDetails->execute();
            $resultDetails = $stmtDetails->get_result();
            if ($resultDetails instanceof mysqli_result) {
                while ($detailRow = $resultDetails->fetch_assoc()) {
                    $trxId = (int) ($detailRow['transaction_id'] ?? 0);
                    if (!isset($transactionDetailsMap[$trxId])) {
                        $transactionDetailsMap[$trxId] = [];
                    }
                    $transactionDetailsMap[$trxId][] = [
                        'product_code' => (string) ($detailRow['product_code'] ?? ''),
                        'product_name' => (string) ($detailRow['product_name'] ?? ''),
                        'qty' => (int) ($detailRow['qty'] ?? 0),
                        'subtotal' => (float) ($detailRow['subtotal'] ?? 0),
                    ];
                }
            }
            $stmtDetails->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Kasir Pintar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body id="reportsPageRoot">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="row g-3 mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="stat-card report-highlight-card">
                        <div class="report-highlight-icon revenue"><i class="fas fa-wallet"></i></div>
                        <div class="report-highlight-title">Total Pendapatan Hari Ini</div>
                        <div class="report-highlight-value">Rp <?php echo number_format($summaryRevenueToday, 0, ',', '.'); ?></div>
                        <div class="report-highlight-note">Akumulasi semua checkout hari ini</div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="stat-card report-highlight-card">
                        <div class="report-highlight-icon tx"><i class="fas fa-cash-register"></i></div>
                        <div class="report-highlight-title">Total Transaksi</div>
                        <div class="report-highlight-value"><?php echo number_format($summaryTotalTransactionToday, 0, ',', '.'); ?></div>
                        <div class="report-highlight-note">Jumlah checkout hari ini</div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-12">
                    <div class="stat-card report-highlight-card">
                        <div class="report-highlight-icon best"><i class="fas fa-star"></i></div>
                        <div class="report-highlight-title">Produk Terlaris</div>
                        <div class="report-highlight-value report-best-product-name"><?php echo htmlspecialchars($summaryBestProductName); ?></div>
                        <div class="report-highlight-note">Terjual <?php echo number_format($summaryBestProductQty, 0, ',', '.'); ?> pcs hari ini</div>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <h3><i class="fas fa-file-alt me-2"></i>Reports</h3>
                <p class="text-muted mb-3">Riwayat transaksi terbaru (otomatis update setelah checkout sukses).</p>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal &amp; Waktu</th>
                                <th>Transaction ID</th>
                                <th>Nama Kasir</th>
                                <th class="text-end">Total Belanja</th>
                                <th>Metode Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reportRows)) { ?>
                                <?php foreach ($reportRows as $trx) { ?>
                                    <?php
                                        $trxId = (int) ($trx['id'] ?? 0);
                                        $trxDetails = $transactionDetailsMap[$trxId] ?? [];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime((string) $trx['transaction_date']))); ?></td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn report-transaction-id-btn"
                                                data-trx-number="<?php echo htmlspecialchars((string) $trx['transaction_number']); ?>"
                                                data-trx-cashier="<?php echo htmlspecialchars((string) ($trx['cashier_name'] ?? '-')); ?>"
                                                data-trx-total="Rp <?php echo number_format((float) ($trx['total_amount'] ?? 0), 0, ',', '.'); ?>"
                                                data-trx-items='<?php echo htmlspecialchars(json_encode($trxDetails, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
                                            >
                                                <?php echo htmlspecialchars((string) $trx['transaction_number']); ?>
                                            </button>
                                        </td>
                                        <td><?php echo htmlspecialchars((string) ($trx['cashier_name'] ?? '-')); ?></td>
                                        <td class="text-end">Rp <?php echo number_format((float) ($trx['total_amount'] ?? 0), 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($trx['payment_method'] ?? 'Cash')); ?></td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Belum ada transaksi.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reportTransactionDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Detail Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="report-detail-meta mb-3">
                        <div><strong>ID:</strong> <span id="reportDetailTrxNumber">-</span></div>
                        <div><strong>Kasir:</strong> <span id="reportDetailCashier">-</span></div>
                        <div><strong>Total:</strong> <span id="reportDetailTotal">-</span></div>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Produk</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="reportDetailItemsBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Belum ada detail.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
