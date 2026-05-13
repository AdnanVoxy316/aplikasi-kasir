<?php
/**
 * Product Management API
 * Handles all product CRUD operations (INSERT, UPDATE, DELETE)
 */

// Log all backend issues, but do not print PHP warnings/notices into AJAX JSON responses.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../libraries/BarcodeGenerator.php';

if (!isLoggedIn()) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized session. Please login again.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get request method
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is not available.');
    }

    ensureMediaDirectories();

    switch ($action) {
        case 'add':
            $response = addProduct($conn);
            break;
        
        case 'update':
            $response = updateProduct($conn);
            break;
        
        case 'delete':
            $response = deleteProduct($conn);
            break;
        
        case 'get':
            $response = getProduct($conn);
            break;
        
        default:
            $response['message'] = 'Invalid action';
    }
} catch (Throwable $e) {
    error_log('Product API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $response['message'] = 'A server error occurred while processing the product.';
}

$unexpected_output = trim(ob_get_clean());
if ($unexpected_output !== '') {
    error_log('Product API unexpected output suppressed: ' . $unexpected_output);
}

if (!headers_sent()) {
    header('Content-Type: application/json');
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

/**
 * Add Product
 */
function addProduct($conn) {
    $response = [
        'success' => false,
        'message' => 'Failed to add product',
        'data' => []
    ];
    
    // Validate input
    if (!isset($_POST['code'], $_POST['name'], $_POST['price'], $_POST['stock']) ||
        trim((string) $_POST['code']) === '' ||
        trim((string) $_POST['name']) === '' ||
        trim((string) $_POST['price']) === '' ||
        trim((string) $_POST['stock']) === '') {
        $response['message'] = 'All fields are required';
        return $response;
    }
    
    $code = sanitize($conn, $_POST['code']);
    $name = sanitize($conn, $_POST['name']);
    $category = sanitize($conn, $_POST['category'] ?? '');
    $description = sanitize($conn, $_POST['description'] ?? '');
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $image = '';
    
    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $image_result = uploadImage();
        if (!$image_result['success']) {
            $response['message'] = $image_result['message'];
            return $response;
        }
        $image = $image_result['filename'];
    }
    
    // Check if code already exists
    $check_query = "SELECT id FROM products WHERE code = '$code' LIMIT 1";
    $check_result = $conn->query($check_query);
    if (!$check_result) {
        $response['message'] = 'Database error: ' . $conn->error;
        return $response;
    }

    if ($check_result->num_rows > 0) {
        $response['message'] = 'Product code already exists';
        return $response;
    }
    
    // Insert product
    $query = "INSERT INTO products (code, name, category, description, price, stock, image) 
              VALUES ('$code', '$name', '$category', '$description', $price, $stock, '$image')";
    
    if ($conn->query($query) === TRUE) {
        // Generate barcode after the product is saved. Barcode failure is logged only;
        // it must never block INSERT success.
        safeGenerateBarcode($code);
        
        $response['success'] = true;
        $response['message'] = 'Product added successfully';
        $response['data'] = [
            'id' => $conn->insert_id,
            'code' => $code,
            'name' => $name
        ];
        $response['redirect'] = buildProductsRedirectUrl($response['message']);
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }
    
    return $response;
}

/**
 * Update Product
 */
function updateProduct($conn) {
    $response = [
        'success' => false,
        'message' => 'Failed to update product',
        'data' => []
    ];
    
    // Validate input
    if (empty($_POST['id'])) {
        $response['message'] = 'Product ID is required';
        return $response;
    }
    
    $id = intval($_POST['id']);
    $current_product = getProductById($conn, $id);

    if (!$current_product) {
        $response['message'] = 'Product not found';
        return $response;
    }

    $updates = [];
    $new_code = $current_product['code'];
    $code_changed = false;
    
    // Build update query dynamically
    if (isset($_POST['code']) && trim((string) $_POST['code']) !== '') {
        $code = sanitize($conn, $_POST['code']);

        if ($code !== $current_product['code']) {
            $duplicate_query = "SELECT id FROM products WHERE code = '$code' AND id <> $id LIMIT 1";
            $duplicate_result = $conn->query($duplicate_query);
            if (!$duplicate_result) {
                $response['message'] = 'Database error: ' . $conn->error;
                return $response;
            }

            if ($duplicate_result->num_rows > 0) {
                $response['message'] = 'Product code already exists';
                return $response;
            }

            $code_changed = true;
        }

        $new_code = $code;
        $updates[] = "code = '$code'";
    }
    
    if (!empty($_POST['name'])) {
        $name = sanitize($conn, $_POST['name']);
        $updates[] = "name = '$name'";
    }
    
    if (!empty($_POST['category'])) {
        $category = sanitize($conn, $_POST['category']);
        $updates[] = "category = '$category'";
    }
    
    if (!empty($_POST['description'])) {
        $description = sanitize($conn, $_POST['description']);
        $updates[] = "description = '$description'";
    }
    
    if (isset($_POST['price'])) {
        $price = floatval($_POST['price']);
        $updates[] = "price = $price";
    }
    
    if (isset($_POST['stock'])) {
        $stock = intval($_POST['stock']);
        $updates[] = "stock = $stock";
    }
    
    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $image_result = uploadImage();
        if (!$image_result['success']) {
            $response['message'] = $image_result['message'];
            return $response;
        }
        
        // Delete old product image safely. Failure is logged only and does not block update.
        if (!empty($current_product['image'])) {
            safeDeleteFile('assets/img/' . basename($current_product['image']));
        }
        
        $updates[] = "image = '" . $image_result['filename'] . "'";
    }
    
    if (empty($updates)) {
        $response['message'] = 'No fields to update';
        return $response;
    }
    
    $query = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = $id";
    
    if ($conn->query($query) === TRUE) {
        // Barcode/QR cleanup and regeneration is non-blocking.
        if ($code_changed) {
            safeDeleteProductCodeAssets($current_product['code']);
            safeGenerateBarcode($new_code);
        } elseif (!empty($new_code)) {
            safeGenerateBarcode($new_code);
        }
        
        $response['success'] = true;
        $response['message'] = 'Product updated successfully';
        $response['redirect'] = buildProductsRedirectUrl($response['message']);
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }
    
    return $response;
}

/**
 * Delete Product
 */
function deleteProduct($conn) {
    $response = [
        'success' => false,
        'message' => 'Failed to delete product',
        'data' => []
    ];
    
    // Validate input
    if (empty($_POST['id'])) {
        $response['message'] = 'Product ID is required';
        return $response;
    }
    
    $id = intval($_POST['id']);
    $product = getProductById($conn, $id);

    if (!$product) {
        $response['message'] = 'Product not found';
        return $response;
    }
    
    // Delete product
    $query = "DELETE FROM products WHERE id = $id LIMIT 1";
    
    if ($conn->query($query) === TRUE) {
        // Clean up product image, barcode SVG, and legacy QR PNG after successful DB delete.
        // File deletion errors are logged only and will not turn the delete into a failure.
        if (!empty($product['image'])) {
            safeDeleteFile('assets/img/' . basename($product['image']));
        }

        if (!empty($product['code'])) {
            safeDeleteProductCodeAssets($product['code']);
        }

        $response['success'] = true;
        $response['message'] = 'Product deleted successfully';
        $response['redirect'] = buildProductsRedirectUrl($response['message']);
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }
    
    return $response;
}

/**
 * Get Single Product
 */
function getProduct($conn) {
    $response = [
        'success' => false,
        'message' => 'Product not found'
    ];
    
    if (empty($_GET['id'])) {
        $response['message'] = 'Product ID is required';
        return $response;
    }
    
    $id = intval($_GET['id']);
    $query = "SELECT * FROM products WHERE id = $id LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Product found';
        $response['data'] = $result->fetch_assoc();
    }
    
    return $response;
}

/**
 * Upload Image
 */
function uploadImage() {
    $response = [
        'success' => false,
        'message' => 'Image upload failed',
        'filename' => ''
    ];
    
    $upload_dir = 'assets/img/';
    
    // Create directory if it doesn't exist and verify write access
    if (!ensureDirectory($upload_dir)) {
        $response['message'] = 'Image upload folder is not writable';
        return $response;
    }
    
    // Allowed extensions
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $file = $_FILES['image'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'Upload error: ' . $file['error'];
        return $response;
    }
    
    // Check file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        $response['message'] = 'File size must be less than 2MB';
        return $response;
    }
    
    // Check file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_ext)) {
        $response['message'] = 'Invalid file type. Allowed: ' . implode(', ', $allowed_ext);
        return $response;
    }
    
    // Generate unique filename
    $filename = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
    $filepath = projectPath($upload_dir . $filename);
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $response['success'] = true;
        $response['message'] = 'Image uploaded successfully';
        $response['filename'] = $filename;
    } else {
        $response['message'] = 'Failed to move uploaded file';
    }
    
    return $response;
}

/**
 * Sanitize input to prevent SQL injection
 */
function sanitize($conn, $input) {
    return $conn->real_escape_string(trim($input));
}

/**
 * Fetch product by ID for update/delete workflows.
 */
function getProductById($conn, $id) {
    $id = intval($id);
    $query = "SELECT * FROM products WHERE id = $id LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

/**
 * Ensure all product media folders exist and are writable.
 */
function ensureMediaDirectories() {
    ensureDirectory('assets/img/');
    ensureDirectory('assets/img/barcodes/');
    ensureDirectory('assets/img/qrcodes/');
}

/**
 * Create/verify a directory under the project root.
 */
function ensureDirectory($relative_dir) {
    $dir = projectPath($relative_dir);

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        error_log('Unable to create directory: ' . $dir);
        return false;
    }

    if (!is_writable($dir)) {
        error_log('Directory is not writable: ' . $dir);
        return false;
    }

    return true;
}

/**
 * Generate barcode safely after DB writes. Failure should never block add/edit.
 */
function safeGenerateBarcode($code) {
    if (trim((string) $code) === '') {
        return false;
    }

    try {
        ensureDirectory('assets/img/barcodes/');
        $barcode = new BarcodeGenerator();
        $result = $barcode->generate($code);

        if ($result === false) {
            error_log('Barcode generation returned false for product code: ' . $code);
            return false;
        }

        return true;
    } catch (Throwable $e) {
        error_log('Barcode generation failed for product code ' . $code . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete barcode SVG and legacy QR PNG for a product code safely.
 */
function safeDeleteProductCodeAssets($code) {
    $sanitized_code = preg_replace('/[^a-zA-Z0-9-_]/', '_', (string) $code);

    if ($sanitized_code === '') {
        return;
    }

    safeDeleteFile('assets/img/barcodes/barcode_' . $sanitized_code . '.svg');
    safeDeleteFile('assets/img/qrcodes/qr_' . $sanitized_code . '.png');
}

/**
 * Delete a file under the project root without throwing or blocking DB operations.
 */
function safeDeleteFile($relative_path) {
    $filepath = projectPath($relative_path);

    if (file_exists($filepath) && is_file($filepath) && !@unlink($filepath)) {
        error_log('Failed to delete file: ' . $filepath);
        return false;
    }

    return true;
}

/**
 * Resolve a safe filesystem path under this project.
 */
function projectPath($relative_path) {
    $normalized = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $relative_path), DIRECTORY_SEPARATOR);
    return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $normalized;
}

/**
 * Build current-tab redirect URL for clean success messages.
 */
function buildProductsRedirectUrl($message, $type = 'success') {
    return 'products.php?' . urlencode($type) . '=' . urlencode($message);
}
?>
