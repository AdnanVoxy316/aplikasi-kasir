<?php
/**
 * Products Management Page
 * Display, add, edit, and delete products
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../libraries/BarcodeGenerator.php';

// Auto-create products table if it doesn't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'products'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS `products` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(50) NOT NULL COMMENT 'Product code/SKU',
        `name` varchar(255) NOT NULL COMMENT 'Product name',
        `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Product price',
        `stock` int(11) NOT NULL DEFAULT 0 COMMENT 'Product stock quantity',
        `image` varchar(255) DEFAULT NULL COMMENT 'Product image filename',
        `description` text DEFAULT NULL COMMENT 'Product description',
        `category` varchar(100) DEFAULT NULL COMMENT 'Product category',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`),
        KEY `idx_code` (`code`),
        KEY `idx_name` (`name`),
        KEY `idx_category` (`category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Products inventory'");
}

$is_guest_mode = !isLoggedIn();

// Initialize Barcode Generator
$barcodeGen = new BarcodeGenerator();

$low_stock_threshold = 10;

/**
 * Build validated filter values from query string.
 */
function getProductFiltersFromRequest() {
    $allowed_stock = ['all', 'low', 'out'];
    $allowed_sort = [
        'newest',
        'name_asc',
        'name_desc',
        'price_asc',
        'price_desc',
        'code_asc',
        'code_desc'
    ];

    $search = trim($_GET['search'] ?? '');
    if (strlen($search) > 100) {
        $search = substr($search, 0, 100);
    }

    $category = trim($_GET['category'] ?? '');
    if (strlen($category) > 100) {
        $category = substr($category, 0, 100);
    }

    $stock_status = $_GET['stock_status'] ?? 'all';
    if (!in_array($stock_status, $allowed_stock, true)) {
        $stock_status = 'all';
    }

    $sort_by = $_GET['sort_by'] ?? 'newest';
    if (!in_array($sort_by, $allowed_sort, true)) {
        $sort_by = 'newest';
    }

    return [
        'search' => $search,
        'category' => $category,
        'stock_status' => $stock_status,
        'sort_by' => $sort_by,
    ];
}

/**
 * Return SQL ORDER BY snippet based on selected sort option.
 */
function getProductsSortSql($sort_by) {
    $map = [
        'newest' => 'id DESC',
        'name_asc' => 'name ASC, id DESC',
        'name_desc' => 'name DESC, id DESC',
        'price_asc' => 'price ASC, id DESC',
        'price_desc' => 'price DESC, id DESC',
        'code_asc' => 'code ASC, id DESC',
        'code_desc' => 'code DESC, id DESC',
    ];

    return $map[$sort_by] ?? $map['newest'];
}

/**
 * Bind prepared statement parameters dynamically.
 */
function bindDynamicParams($stmt, $types, &$params) {
    if ($types === '' || empty($params)) {
        return;
    }

    $bind_params = [$types];
    foreach ($params as $key => $value) {
        $bind_params[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind_params);
}

/**
 * Fetch products using combined filters and sorting.
 */
function fetchProducts($conn, $filters, $low_stock_threshold) {
    $conditions = [];
    $types = '';
    $params = [];

    if ($filters['search'] !== '') {
        $search_term = '%' . $filters['search'] . '%';
        $conditions[] = '(name LIKE ? OR code LIKE ?)';
        $types .= 'ss';
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if ($filters['category'] !== '') {
        $conditions[] = 'category = ?';
        $types .= 's';
        $params[] = $filters['category'];
    }

    if ($filters['stock_status'] === 'low') {
        $conditions[] = 'stock > 0 AND stock < ?';
        $types .= 'i';
        $params[] = (int) $low_stock_threshold;
    } elseif ($filters['stock_status'] === 'out') {
        $conditions[] = 'stock <= 0';
    }

    $query = 'SELECT id, code, name, category, description, price, stock, image, created_at FROM products';
    if (!empty($conditions)) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $query .= ' ORDER BY ' . getProductsSortSql($filters['sort_by']);

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }

    bindDynamicParams($stmt, $types, $params);

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $stmt->close();
    return $products;
}

/**
 * Build category options for filter dropdown.
 */
function fetchAvailableCategories($conn) {
    $categories = [];
    $query = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC";
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }

    return $categories;
}

/**
 * Render rows for product table.
 */
function renderProductsTableRows($products, $barcodeGen, $low_stock_threshold, $is_guest_mode) {
    ob_start();

    foreach ($products as $index => $product) {
        $stock_value = (int) $product['stock'];

        if ($stock_value <= 0) {
            $stock_class = 'stock-low';
            $stock_text = 'Out of Stock';
        } elseif ($stock_value < $low_stock_threshold) {
            $stock_class = 'stock-low';
            $stock_text = 'Low Stock';
        } else {
            $stock_class = 'stock-good';
            $stock_text = 'In Stock';
        }
        ?>
        <tr>
            <td><?php echo $index + 1; ?></td>
            <td>
                <?php if (!empty($product['image'])) { ?>
                    <img src="../assets/img/<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         class="product-image">
                <?php } else { ?>
                    <div class="product-image-placeholder">
                        <i class="fas fa-image"></i>
                    </div>
                <?php } ?>
            </td>
            <td class="product-barcode-cell">
                <div class="product-barcode-wrapper">
                    <?php echo $barcodeGen->generateHTML($product['code'], 700, 170); ?>
                </div>
            </td>
            <td>
                <span class="product-code-text">
                    <?php echo htmlspecialchars($product['code']); ?>
                </span>
            </td>
            <td>
                <div class="product-name-text">
                    <?php echo htmlspecialchars($product['name']); ?>
                </div>
                <small class="product-description-text">
                    <?php echo !empty($product['description']) ? htmlspecialchars(substr($product['description'], 0, 50)) : '-'; ?>
                </small>
            </td>
            <td>
                <span class="product-category-text">
                    <?php echo !empty($product['category']) ? htmlspecialchars($product['category']) : '-'; ?>
                </span>
            </td>
            <td>
                <span class="product-price-text">
                    Rp <?php echo number_format($product['price'], 0, ',', '.'); ?>
                </span>
            </td>
            <td>
                <span class="<?php echo $stock_class; ?>">
                    <?php echo $stock_value . ' ' . $stock_text; ?>
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline-success <?php echo $is_guest_mode ? 'guest-action-locked' : ''; ?>" 
                            onclick="<?php echo $is_guest_mode ? 'return showGuestAccessMessage(event);' : 'downloadLabel(' . (int) $product['id'] . ')'; ?>"
                            title="Download price tag/label">
                        <i class="fas fa-tag"></i> Label
                    </button>
                    <button class="btn btn-sm btn-outline-primary <?php echo $is_guest_mode ? 'guest-action-locked' : ''; ?>" 
                            onclick="<?php echo $is_guest_mode ? 'return showGuestAccessMessage(event);' : 'editProduct(' . (int) $product['id'] . ')'; ?>">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger <?php echo $is_guest_mode ? 'guest-action-locked' : ''; ?>" 
                            onclick="<?php echo $is_guest_mode ? 'return showGuestAccessMessage(event);' : 'deleteProduct(' . (int) $product['id'] . ')'; ?>">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </td>
        </tr>
        <?php
    }

    return ob_get_clean();
}

/**
 * Render either product table or empty state.
 */
function renderProductsTableSection($products, $barcodeGen, $low_stock_threshold, $is_guest_mode) {
    if (count($products) > 0) {
        $rows = renderProductsTableRows($products, $barcodeGen, $low_stock_threshold, $is_guest_mode);

        ob_start();
        ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th class="table-col-id">#</th>
                        <th class="table-col-image">Image</th>
                        <th class="table-col-barcode">Barcode</th>
                        <th class="table-col-code">Code</th>
                        <th class="table-col-name">Name</th>
                        <th class="table-col-category">Category</th>
                        <th class="table-col-price">Price</th>
                        <th class="table-col-stock">Stock</th>
                        <th class="table-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $rows; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start();
    ?>
    <div class="stat-card">
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-box-open"></i>
            </div>
            <p class="empty-state-text">No products found</p>
            <button class="btn btn-add-product <?php echo $is_guest_mode ? 'guest-action-locked' : ''; ?>" <?php echo $is_guest_mode ? 'onclick="return showGuestAccessMessage(event);"' : 'data-bs-toggle="modal" data-bs-target="#addProductModal"'; ?>>
                <i class="fas fa-plus me-2"></i>Add Your First Product
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

$filters = getProductFiltersFromRequest();
$available_categories = fetchAvailableCategories($conn);
$products = fetchProducts($conn, $filters, $low_stock_threshold);

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    echo json_encode([
        'success' => true,
        'html' => renderProductsTableSection($products, $barcodeGen, $low_stock_threshold, $is_guest_mode),
        'total' => count($products),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Kasir Pintar</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body id="productsPageRoot" data-is-guest-mode="<?php echo $is_guest_mode ? '1' : '0'; ?>">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php include __DIR__ . '/../includes/header.php'; ?>

        <!-- Content Area -->
        <div class="content">
            <!-- Page Title & Add Button -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h3 class="products-title">Products</h3>
                    <p class="products-subtitle">Manage your product inventory</p>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-add-product <?php echo $is_guest_mode ? 'guest-action-locked' : ''; ?>" <?php echo $is_guest_mode ? 'onclick="return showGuestAccessMessage(event);"' : 'data-bs-toggle="modal" data-bs-target="#addProductModal"'; ?>>
                        <i class="fas fa-plus me-2"></i>Add Product
                    </button>
                </div>
            </div>

            <!-- Filter & Sort Bar -->
            <div class="filter-card">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label for="filterSearch" class="form-label"><i class="bi bi-search"></i> Search</label>
                        <input type="text" id="filterSearch" class="form-control" placeholder="Name or Code" value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label for="filterCategory" class="form-label"><i class="bi bi-grid"></i> Category</label>
                        <select id="filterCategory" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($available_categories as $category) { ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filters['category'] === $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label for="filterStockStatus" class="form-label"><i class="bi bi-box-seam"></i> Stock Status</label>
                        <select id="filterStockStatus" class="form-select">
                            <option value="all" <?php echo $filters['stock_status'] === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="low" <?php echo $filters['stock_status'] === 'low' ? 'selected' : ''; ?>>Low Stock (&lt; <?php echo $low_stock_threshold; ?>)</option>
                            <option value="out" <?php echo $filters['stock_status'] === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label for="filterSortBy" class="form-label"><i class="bi bi-sort-down"></i> Sort By</label>
                        <select id="filterSortBy" class="form-select">
                            <option value="newest" <?php echo $filters['sort_by'] === 'newest' ? 'selected' : ''; ?>>Newest</option>
                            <option value="name_asc" <?php echo $filters['sort_by'] === 'name_asc' ? 'selected' : ''; ?>>Name: A-Z</option>
                            <option value="name_desc" <?php echo $filters['sort_by'] === 'name_desc' ? 'selected' : ''; ?>>Name: Z-A</option>
                            <option value="price_asc" <?php echo $filters['sort_by'] === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc" <?php echo $filters['sort_by'] === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="code_asc" <?php echo $filters['sort_by'] === 'code_asc' ? 'selected' : ''; ?>>Code: Ascending</option>
                            <option value="code_desc" <?php echo $filters['sort_by'] === 'code_desc' ? 'selected' : ''; ?>>Code: Descending</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6 d-grid">
                        <button type="button" id="resetFilters" class="btn btn-reset-filter">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="filter-summary" id="filterSummary">
                        <i class="bi bi-funnel me-1"></i> Showing <?php echo count($products); ?> products
                    </small>
                    <span id="filterLoading" class="filter-loading">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Updating...
                    </span>
                </div>
            </div>

            <div id="productsTableSection">
                <?php echo renderProductsTableSection($products, $barcodeGen, $low_stock_threshold, $is_guest_mode); ?>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductLabel">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addProductForm" class="product-modal-form" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="productCategory" class="form-label">Kategori Produk *</label>
                                    <select class="form-select" id="productCategory" name="category" required>
                                        <option value="">-- Select Category --</option>
                                        <option value="Makanan">Makanan (MK-)</option>
                                        <option value="Minuman">Minuman (MN-)</option>
                                        <option value="Snack">Snack (SN-)</option>
                                        <option value="Es Krim">Es Krim (EK-)</option>
                                        <option value="Bumbu Dapur">Bumbu Dapur (BM-)</option>
                                        <option value="Bakery">Bakery (BK-)</option>
                                        <option value="Peralatan & Perkakas">Peralatan & Perkakas (PL-)</option>
                                        <option value="Kebutuhan Bayi">Kebutuhan Bayi (BY-)</option>
                                        <option value="Pembersih & Sabun">Pembersih & Sabun (PB-)</option>
                                        <option value="Lain-lain">Lain-lain (LL-)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="productCode" class="form-label">Kode Produk *</label>
                                    <input type="text" class="form-control" id="productCode" name="code" 
                                           placeholder="e.g., MK-001" inputmode="numeric" autocomplete="off" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="productName" class="form-label">Nama Produk *</label>
                                    <input type="text" class="form-control" id="productName" name="name" 
                                           placeholder="e.g., Indomie Goreng" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="productPrice" class="form-label">Harga (Rp) *</label>
                                    <input type="number" class="form-control" id="productPrice" name="price" 
                                           placeholder="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="productStock" class="form-label">Stok *</label>
                                    <input type="number" class="form-control" id="productStock" name="stock" 
                                           placeholder="0" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="productDescription" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="productDescription" name="description" 
                                      rows="3" placeholder="Product description..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="productImage" class="form-label">Gambar Produk</label>
                            <input type="file" class="form-control" id="productImage" name="image" 
                                   accept="image/*">
                            <small class="text-muted">Max 2MB. Allowed: JPG, PNG, GIF, WebP</small>
                            <img id="imagePreview" class="image-preview" alt="Preview">
                        </div>

                        <input type="hidden" name="action" value="add">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-product-save" id="addSubmitBtn">
                        <i class="fas fa-save me-2"></i>Simpan Produk
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProductLabel">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="loading" id="editLoading">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <form id="editProductForm" class="product-modal-form hidden" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="editProductCategory" class="form-label">Kategori Produk *</label>
                                    <select class="form-select" id="editProductCategory" name="category" required>
                                        <option value="">-- Select Category --</option>
                                        <option value="Makanan">Makanan (MK-)</option>
                                        <option value="Minuman">Minuman (MN-)</option>
                                        <option value="Snack">Snack (SN-)</option>
                                        <option value="Es Krim">Es Krim (EK-)</option>
                                        <option value="Bumbu Dapur">Bumbu Dapur (BM-)</option>
                                        <option value="Bakery">Bakery (BK-)</option>
                                        <option value="Peralatan & Perkakas">Peralatan & Perkakas (PL-)</option>
                                        <option value="Kebutuhan Bayi">Kebutuhan Bayi (BY-)</option>
                                        <option value="Pembersih & Sabun">Pembersih & Sabun (PB-)</option>
                                        <option value="Lain-lain">Lain-lain (LL-)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editProductCode" class="form-label">Kode Produk *</label>
                                    <input type="text" class="form-control" id="editProductCode" name="code" inputmode="numeric" autocomplete="off" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editProductName" class="form-label">Nama Produk *</label>
                                    <input type="text" class="form-control" id="editProductName" name="name" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editProductPrice" class="form-label">Harga (Rp) *</label>
                                    <input type="number" class="form-control" id="editProductPrice" name="price" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editProductStock" class="form-label">Stok *</label>
                                    <input type="number" class="form-control" id="editProductStock" name="stock" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editProductDescription" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="editProductDescription" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="editProductImage" class="form-label">Gambar Produk</label>
                            <div id="currentImage"></div>
                            <input type="file" class="form-control mt-2" id="editProductImage" name="image" 
                                   accept="image/*">
                            <small class="text-muted">Max 2MB. Allowed: JPG, PNG, GIF, WebP</small>
                            <img id="editImagePreview" class="image-preview" alt="Preview">
                        </div>

                        <input type="hidden" name="action" value="update">
                        <input type="hidden" id="editProductId" name="id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-product-save" id="editSubmitBtn">
                        <i class="fas fa-save me-2"></i>Update Produk
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>

</body>
</html>
