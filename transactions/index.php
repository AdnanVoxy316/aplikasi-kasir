<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin('/aplikasi-kasir-copy/settings.php');

if (!isset($_SESSION['transaction_cart']) || !is_array($_SESSION['transaction_cart'])) {
    $_SESSION['transaction_cart'] = [];
}

function transactionTableExists(mysqli $conn, $tableName)
{
    $query = "SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return ((int) ($row['total'] ?? 0)) > 0;
}

function transactionColumnExists(mysqli $conn, $tableName, $columnName)
{
    $query = "SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return ((int) ($row['total'] ?? 0)) > 0;
}

function ensureTransactionSchema(mysqli $conn)
{
    if (!transactionTableExists($conn, 'transaction_details')) {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS transaction_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transaction_id INT NOT NULL,
                product_id INT NOT NULL,
                qty INT NOT NULL,
                subtotal DECIMAL(12,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_transaction_id (transaction_id),
                INDEX idx_product_id (product_id),
                CONSTRAINT fk_transaction_details_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
                CONSTRAINT fk_transaction_details_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!transactionColumnExists($conn, 'transactions', 'total_price')) {
        $conn->query("ALTER TABLE transactions ADD COLUMN total_price DECIMAL(12,2) NULL AFTER total_amount");
    }

    if (!transactionColumnExists($conn, 'transactions', 'payment_amount')) {
        $conn->query("ALTER TABLE transactions ADD COLUMN payment_amount DECIMAL(12,2) NULL AFTER total_price");
    }

    if (!transactionColumnExists($conn, 'transactions', 'change_amount')) {
        $conn->query("ALTER TABLE transactions ADD COLUMN change_amount DECIMAL(12,2) NULL AFTER payment_amount");
    }

    if (!transactionColumnExists($conn, 'transactions', 'date_time')) {
        $conn->query("ALTER TABLE transactions ADD COLUMN date_time DATETIME NULL AFTER transaction_date");
    }
}

ensureTransactionSchema($conn);

function transactionJsonResponse(array $payload)
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getTransactionCartSummary(array $cart, float $taxRate = 0.11)
{
    $items = [];
    $subtotal = 0;

    foreach ($cart as $productId => $item) {
        $qty = (int) ($item['qty'] ?? 0);
        $price = (float) ($item['price'] ?? 0);
        $lineSubtotal = $qty * $price;
        $subtotal += $lineSubtotal;

        $items[] = [
            'product_id' => (int) $productId,
            'code' => (string) ($item['code'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'price' => $price,
            'qty' => $qty,
            'stock' => (int) ($item['stock'] ?? 0),
            'line_subtotal' => $lineSubtotal,
        ];
    }

    $tax = $subtotal * $taxRate;
    $total = $subtotal + $tax;

    return [
        'items' => $items,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total,
        'tax_rate' => $taxRate,
        'item_count' => count($items),
    ];
}

function createTransactionNumber(mysqli $conn)
{
    $prefix = 'TRX-' . date('Ymd');
    $query = "SELECT COUNT(*) AS total FROM transactions WHERE transaction_number LIKE ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return $prefix . '-001';
    }

    $likePrefix = $prefix . '%';
    $stmt->bind_param('s', $likePrefix);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $sequence = ((int) ($row['total'] ?? 0)) + 1;
    return sprintf('%s-%03d', $prefix, $sequence);
}

function fetchProductByCode(mysqli $conn, $code)
{
    $query = "SELECT id, code, name, price, stock FROM products WHERE code = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $product;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'add_by_code') {
        $code = trim((string) ($_POST['code'] ?? ''));
        if ($code === '') {
            transactionJsonResponse([
                'success' => false,
                'message' => 'Kode produk wajib diisi.',
            ]);
        }

        $product = fetchProductByCode($conn, $code);
        if (!$product) {
            transactionJsonResponse([
                'success' => false,
                'message' => 'Produk dengan kode tersebut tidak ditemukan.',
            ]);
        }

        $productId = (int) $product['id'];
        $currentQty = (int) ($_SESSION['transaction_cart'][$productId]['qty'] ?? 0);
        $stock = (int) $product['stock'];
        if ($currentQty + 1 > $stock) {
            transactionJsonResponse([
                'success' => false,
                'message' => 'Stok tidak mencukupi untuk menambahkan item ini.',
            ]);
        }

        $_SESSION['transaction_cart'][$productId] = [
            'id' => $productId,
            'code' => (string) $product['code'],
            'name' => (string) $product['name'],
            'price' => (float) $product['price'],
            'stock' => $stock,
            'qty' => $currentQty + 1,
        ];

        transactionJsonResponse([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan ke keranjang.',
            'cart' => getTransactionCartSummary($_SESSION['transaction_cart']),
        ]);
    }

    if ($action === 'remove_item') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId > 0 && isset($_SESSION['transaction_cart'][$productId])) {
            unset($_SESSION['transaction_cart'][$productId]);
        }

        transactionJsonResponse([
            'success' => true,
            'message' => 'Item berhasil dihapus dari keranjang.',
            'cart' => getTransactionCartSummary($_SESSION['transaction_cart']),
        ]);
    }

    if ($action === 'clear_cart') {
        $_SESSION['transaction_cart'] = [];
        transactionJsonResponse([
            'success' => true,
            'message' => 'Keranjang berhasil dikosongkan.',
            'cart' => getTransactionCartSummary($_SESSION['transaction_cart']),
        ]);
    }

    if ($action === 'checkout') {
        $cart = $_SESSION['transaction_cart'];
        if (empty($cart)) {
            transactionJsonResponse([
                'success' => false,
                'message' => 'Keranjang masih kosong.',
            ]);
        }

        $summary = getTransactionCartSummary($cart);
        $cashier = getCurrentCashier();
        $cashierId = (int) ($cashier['id'] ?? 0);
        $cashierName = getActiveCashierName();
        $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'Cash'));
        $paymentAmount = (float) ($_POST['payment_amount'] ?? 0);
        if (strcasecmp($paymentMethod, 'Cash') !== 0) {
            transactionJsonResponse([
                'success' => false,
                'message' => 'Metode pembayaran belum didukung. Gunakan Cash.',
            ]);
        }

        $totalPay = (float) $summary['total'];
        if ($paymentAmount < $totalPay) {
            transactionJsonResponse([
                'success' => false,
                'message' => 'Uang bayar kurang dari total pembayaran.',
            ]);
        }

        $changeAmount = $paymentAmount - $totalPay;
        $notes = trim((string) ($_POST['notes'] ?? ''));

        $conn->begin_transaction();

        try {
            foreach ($cart as $productId => $item) {
                $queryStock = "SELECT stock FROM products WHERE id = ? FOR UPDATE";
                $stmtStock = $conn->prepare($queryStock);
                if (!$stmtStock) {
                    throw new Exception('Gagal mengunci data stok produk.');
                }

                $pid = (int) $productId;
                $stmtStock->bind_param('i', $pid);
                $stmtStock->execute();
                $resultStock = $stmtStock->get_result();
                $rowStock = $resultStock ? $resultStock->fetch_assoc() : null;
                $stmtStock->close();

                $availableStock = (int) ($rowStock['stock'] ?? 0);
                if ($availableStock < (int) $item['qty']) {
                    throw new Exception('Stok tidak cukup untuk produk: ' . $item['name']);
                }
            }

            $transactionNumber = createTransactionNumber($conn);

            $queryInsertTransaction = "
                INSERT INTO transactions (
                    transaction_number,
                    user_id,
                    cashier_name,
                    total_amount,
                    total_price,
                    payment_amount,
                    change_amount,
                    payment_method,
                    transaction_date,
                    date_time,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
            ";
            $stmtTransaction = $conn->prepare($queryInsertTransaction);
            if (!$stmtTransaction) {
                throw new Exception('Gagal menyimpan transaksi.');
            }

            $totalAmount = (float) $summary['total'];
            $stmtTransaction->bind_param(
                'sisddddss',
                $transactionNumber,
                $cashierId,
                $cashierName,
                $totalAmount,
                $totalAmount,
                $paymentAmount,
                $changeAmount,
                $paymentMethod,
                $notes
            );
            if (!$stmtTransaction->execute()) {
                $stmtTransaction->close();
                throw new Exception('Gagal mengeksekusi transaksi.');
            }

            $transactionId = (int) $stmtTransaction->insert_id;
            $stmtTransaction->close();

            $queryInsertItem = "
                INSERT INTO transaction_items (transaction_id, product_id, quantity, price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ";
            $stmtItem = $conn->prepare($queryInsertItem);
            if (!$stmtItem) {
                throw new Exception('Gagal menyimpan detail transaksi.');
            }

            $queryUpdateStock = "UPDATE products SET stock = stock - ? WHERE id = ?";
            $stmtUpdateStock = $conn->prepare($queryUpdateStock);
            if (!$stmtUpdateStock) {
                $stmtItem->close();
                throw new Exception('Gagal memperbarui stok produk.');
            }

            $queryInsertDetail = "
                INSERT INTO transaction_details (transaction_id, product_id, qty, subtotal)
                VALUES (?, ?, ?, ?)
            ";
            $stmtDetail = $conn->prepare($queryInsertDetail);
            if (!$stmtDetail) {
                $stmtItem->close();
                $stmtUpdateStock->close();
                throw new Exception('Gagal menyimpan transaction_details.');
            }

            foreach ($cart as $productId => $item) {
                $qty = (int) $item['qty'];
                $price = (float) $item['price'];
                $lineSubtotal = $qty * $price;
                $pid = (int) $productId;

                $stmtItem->bind_param('iiidd', $transactionId, $pid, $qty, $price, $lineSubtotal);
                if (!$stmtItem->execute()) {
                    throw new Exception('Gagal menyimpan salah satu item transaksi.');
                }

                $stmtUpdateStock->bind_param('ii', $qty, $pid);
                if (!$stmtUpdateStock->execute()) {
                    throw new Exception('Gagal mengurangi stok produk.');
                }

                $stmtDetail->bind_param('iiid', $transactionId, $pid, $qty, $lineSubtotal);
                if (!$stmtDetail->execute()) {
                    throw new Exception('Gagal menyimpan transaction_details item.');
                }
            }

            $stmtItem->close();
            $stmtUpdateStock->close();
            $stmtDetail->close();

            $conn->commit();
            $_SESSION['transaction_cart'] = [];

            transactionJsonResponse([
                'success' => true,
                'message' => 'Transaksi berhasil disimpan.',
                'transaction_number' => $transactionNumber,
                'payment_amount' => $paymentAmount,
                'change_amount' => $changeAmount,
                'total' => $totalAmount,
                'cart' => getTransactionCartSummary($_SESSION['transaction_cart']),
            ]);
        } catch (Throwable $e) {
            $conn->rollback();
            transactionJsonResponse([
                'success' => false,
                'message' => 'Checkout gagal: ' . $e->getMessage(),
            ]);
        }
    }

    transactionJsonResponse([
        'success' => false,
        'message' => 'Aksi tidak dikenal.',
    ]);
}

$cartSummary = getTransactionCartSummary($_SESSION['transaction_cart']);
$activeCashier = getActiveCashierName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Kasir Pintar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body id="transactionPageRoot" data-cashier-name="<?php echo htmlspecialchars($activeCashier); ?>">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>

        <div class="content transaction-content">
            <div class="transaction-topbar">
                <div>
                    <h3 class="transaction-title">Transaction Module</h3>
                    <p class="transaction-subtitle">Hybrid input: Manual kode, USB scanner, dan kamera scanner.</p>
                </div>
                <div class="transaction-cashier-meta">
                    <div><span class="meta-label">Kasir:</span> <span id="transactionCashierName"><?php echo htmlspecialchars($activeCashier); ?></span></div>
                    <div><span class="meta-label">Waktu:</span> <span id="transactionRealtimeClock">--:--:--</span></div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-7">
                    <div class="stat-card transaction-input-card">
                        <label for="transactionCodeInput" class="form-label">Kode Produk</label>
                        <div class="transaction-code-input-wrap">
                            <input
                                type="text"
                                id="transactionCodeInput"
                                class="form-control"
                                placeholder="Scan barcode atau ketik kode produk lalu Enter"
                                autocomplete="off"
                            >
                            <button type="button" class="btn btn-minimalist-success" id="transactionAddBtn">
                                <i class="fas fa-plus me-1"></i> Tambah
                            </button>
                            <button type="button" class="btn btn-minimalist-primary" id="transactionCameraScanBtn">
                                <i class="fas fa-camera me-1"></i> Scan
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="stat-card transaction-scanner-card">
                        <div class="transaction-scanner-header">
                            <h6 class="mb-0"><i class="fas fa-barcode me-2"></i>Camera Scanner</h6>
                            <button type="button" class="btn btn-minimalist-danger btn-minimalist-sm" id="transactionStopScanBtn" disabled>Stop</button>
                        </div>
                        <div id="transactionScannerArea" class="transaction-scanner-area"></div>
                    </div>
                </div>
            </div>

            <div class="transaction-cart-panel">
                <div class="transaction-cart-hero">
                    <h3 class="transaction-cart-title">Keranjang Belanja</h3>
                    <button type="button" class="btn btn-minimalist-danger btn-minimalist-sm" id="transactionClearCartBtn">
                        <i class="fas fa-trash me-1"></i>Kosongkan
                    </button>
                </div>

                <div class="transaction-cart-table-wrap" id="transactionCartTableWrap">
                    <table class="transaction-cart-table" id="transactionCartTable">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Produk</th>
                                <th class="text-end">Harga</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="transactionCartBody">
                            <?php if (!empty($cartSummary['items'])) { ?>
                                <?php foreach ($cartSummary['items'] as $item) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['code']); ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td class="text-end">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                        <td class="text-center"><?php echo (int) $item['qty']; ?></td>
                                        <td class="text-end">Rp <?php echo number_format($item['line_subtotal'], 0, ',', '.'); ?></td>
                                        <td class="text-center">
                                            <button
                                                type="button"
                                                class="btn btn-minimalist-danger btn-minimalist-xs transaction-remove-item-btn"
                                                data-product-id="<?php echo (int) $item['product_id']; ?>"
                                            >
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr class="transaction-empty-row">
                                    <td colspan="6" class="text-center">Belum ada item di keranjang.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="transaction-cart-footer">
                    <div class="transaction-summary-panel">
                        <div class="transaction-summary-item">
                            <span class="transaction-summary-label">Subtotal</span>
                            <span class="transaction-summary-value" id="transactionSubtotal">Rp <?php echo number_format($cartSummary['subtotal'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="transaction-summary-item">
                            <span class="transaction-summary-label">Pajak (11%)</span>
                            <span class="transaction-summary-value" id="transactionTax">Rp <?php echo number_format($cartSummary['tax'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="transaction-summary-item transaction-summary-item--total">
                            <span class="transaction-summary-label">Total Bayar</span>
                            <span class="transaction-summary-value" id="transactionTotal">Rp <?php echo number_format($cartSummary['total'], 0, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="transaction-payment-form">
                        <div class="transaction-payment-header">
                            <span class="transaction-payment-title"><i class="fas fa-credit-card me-2"></i>Metode Pembayaran</span>
                        </div>
                        <div class="transaction-payment-fields">
                            <div class="transaction-payment-field">
                                <label class="transaction-field-label">Metode</label>
                                <select id="transactionPaymentMethod" class="transaction-field-input">
                                    <option value="Cash" selected>Cash</option>
                                    <option value="QRIS" disabled>QRIS (Segera Hadir)</option>
                                </select>
                            </div>
                            <div class="transaction-payment-field">
                                <label class="transaction-field-label">Uang Bayar</label>
                                <input type="number" min="0" step="100" class="transaction-field-input" id="transactionPaymentAmount" placeholder="Masukkan jumlah uang">
                            </div>
                            <div class="transaction-payment-field">
                                <label class="transaction-field-label">Kembalian</label>
                                <div class="transaction-change-display" id="transactionChangeAmount">Rp 0</div>
                            </div>
                        </div>
                    </div>

                    <div class="transaction-action-bottom">
                        <div class="transaction-notes-wrap">
                            <input type="text" class="transaction-notes-input" id="transactionNotes" placeholder="Catatan transaksi (opsional)">
                        </div>
                        <button type="button" class="btn btn-product-save" id="transactionPayBtn">
                            <i class="fas fa-cash-register me-1"></i> Bayar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
