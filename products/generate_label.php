<?php
/**
 * Generate Product Label/Price Tag with Barcode
 * Creates a printable label with product information
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../libraries/BarcodeGenerator.php';

requireLogin('/aplikasi-kasir-copy/auth/login/login_cashier.php');

// Get product ID from request
$product_id = intval($_GET['id'] ?? 0);

if (empty($product_id)) {
    die('Invalid product ID');
}

// Fetch product from database
$query = "SELECT * FROM products WHERE id = $product_id LIMIT 1";
$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    die('Product not found');
}

$product = $result->fetch_assoc();

// Generate barcode
$barcode = new BarcodeGenerator();
$barcode_result = $barcode->generate($product['code']);
$barcode_filename = $barcode_result ? $barcode_result['filename'] : null;

// Format price
$price_formatted = 'Rp ' . number_format($product['price'], 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Label - <?php echo htmlspecialchars($product['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="label-page" id="labelPageRoot"
      data-barcode-filename="<?php echo htmlspecialchars((string)($barcode_filename ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
      data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
      data-price-formatted="<?php echo htmlspecialchars($price_formatted, ENT_QUOTES, 'UTF-8'); ?>"
      data-product-code="<?php echo htmlspecialchars($product['code'], ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="top-nav-title">
            <h2><i class="fas fa-barcode"></i> Print Labels</h2>
            <span class="top-nav-subtitle">Professional printable price-tag sheet</span>
        </div>
        <div class="nav-actions">
            <div class="quantity-selector">
                <label for="labelCount">Labels:</label>
                <input type="number" id="labelCount" min="1" max="100" value="8">
            </div>
            <button class="btn-download" onclick="generatePDF()" type="button">
                <i class="fas fa-print"></i> Print/Download
            </button>
            <a href="products.php" class="btn-back-products" aria-label="Back to Products">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
    </div>

    <div class="page-content">
        <!-- Product Information -->
        <div class="product-info">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <p><strong>Code:</strong> <span class="code-badge"><?php echo htmlspecialchars($product['code']); ?></span></p>
            <p><strong>Price:</strong> <?php echo $price_formatted; ?></p>
            <p><strong>Category:</strong> <?php echo !empty($product['category']) ? htmlspecialchars($product['category']) : '-'; ?></p>
            <p><strong>Stock:</strong> <?php echo $product['stock']; ?> units</p>
        </div>

        <!-- Label Container -->
        <div class="label-container" id="labelContainer">
            <!-- Labels will be generated here -->
        </div>
    </div><!-- Close page-content -->

    <!-- Hidden Font Awesome for Print -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="../assets/js/script.js"></script>
</body>
</html>
