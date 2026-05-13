# QR Code & Product Labels Feature Documentation

## 📦 Overview

The Kasir Pintar system now includes comprehensive QR Code functionality integrated with the Products Management system. Every product automatically gets a QR code generated based on its product code, enabling easy tracking and professional price tag generation.

## ✨ Features Implemented

### 1. **QR Code Generation**

- Automatically generates unique QR codes for each product
- QR codes are created based on the product's SKU/code
- Uses QR Server API for high-quality QR code generation
- Fallback local generation if API unavailable
- QR codes stored in `assets/img/qrcodes/` directory

### 2. **QR Code Display in Products Table**

- New "QR Code" column added to the products table
- Shows small preview (40x40px) of generated QR code
- Placeholder icon if QR code not yet generated
- Professional styling with border and border-radius

### 3. **Dynamic QR Code Updates**

- QR codes automatically regenerate when product code is edited
- Old QR code files are automatically deleted
- New QR code generated and saved immediately
- No manual intervention required

### 4. **Professional Price Tag Generation**

- Interactive label generator at `generate_label.php`
- Shows product information: Name, Code, Price, Category, Stock
- Includes QR code display
- Adjustable quantity selector (1-100 labels)
- Professional layout designed for printing

### 5. **Download & Print Functionality**

- **Download Label Button**: Each product has a "Label" button in the actions column
- Opens new window with print-ready labels
- Support for multiple label copies
- Print directly to thermal printer or regular paper
- Browser's print dialog for PDF export

## 📁 New Files & Directories

```
aplikasi-kasir/
├── libraries/
│   └── QRCodeGenerator.php          # QR code generation class
├── assets/img/
│   └── qrcodes/                     # Stores generated QR code images
└── generate_label.php               # Price tag/label generator page
```

## 🔧 Technical Implementation

### QRCodeGenerator Class

Located in `libraries/QRCodeGenerator.php`, provides:

```php
$qrCode = new QRCodeGenerator();

// Generate QR code
$result = $qrCode->generate($product_code);

// Delete QR code
$qrCode->deleteByCode($product_code);

// Get filename
$filename = $qrCode->getFilename($product_code);
```

**Methods**:

- `generate($code)` - Creates QR code from product code
- `delete($filename)` - Delete QR code by filename
- `deleteByCode($code)` - Delete QR code by product code
- `getFilename($code)` - Get QR code filename from code

### Integration Points

#### In `process_product.php`:

**ADD Product**:

```php
$qrCode->generate($code);  // Auto-generates QR after insert
```

**UPDATE Product**:

```php
// If code changed:
$qrCode->deleteByCode($old_code);
$qrCode->generate($new_code);
```

**DELETE Product**:

```php
$qrCode->deleteByCode($product['code']);  // Cleans up QR file
```

#### In `products.php`:

**Display QR in Table**:

```php
<?php
    $qr_filename = 'qr_' . preg_replace('/[^a-zA-Z0-9-_]/', '_', $product['code']) . '.png';
    $qr_filepath = 'assets/img/qrcodes/' . $qr_filename;
    if (file_exists($qr_filepath)) {
        echo "<img src='$qr_filepath' alt='QR Code'>";
    }
?>
```

**Download Label Button**:

```php
<button onclick="downloadLabel(<?php echo $product['id']; ?>)">
    <i class="fas fa-tag"></i> Label
</button>
```

## 🖨️ Label/Price Tag Design

The generated labels feature:

- **Dimensions**: 200x280px (printable label size)
- **Content**: Product name, QR code, price, product code
- **Design**: Professional white label with dark blue border
- **Styling**: Responsive, mobile-friendly
- **Print Support**: Optimized for both digital and physical printing

### Label Layout:

```
┌─────────────────────┐
│                     │
│   Product Name      │
│     (2 lines)       │
│                     │
│   ┌───────────┐     │
│   │           │     │
│   │  QR Code  │     │
│   │ (100x100) │     │
│   │           │     │
│   └───────────┘     │
│                     │
│  Rp 25,000 (Price)  │
│  PRD-001 (Code)     │
│                     │
└─────────────────────┘
```

## 🚀 How It Works

### Workflow: Adding a Product

1. **User submits form** with product details
2. **process_product.php** validates and inserts into database
3. **QRCodeGenerator** automatically generates QR code
4. QR file saved to `assets/img/qrcodes/qr_PRODUCTCODE.png`
5. **Products table** displays QR preview
6. **Label button** available for download/print

### Workflow: Editing Product Code

1. **User changes product code**
2. **Update query** executed
3. **Old QR code** deleted (`deleteByCode()`)
4. **New QR code** generated with new code
5. **Table refreshes** with new QR preview

### Workflow: Downloading Label

1. **User clicks "Label" button** for product
2. **generate_label.php** opens in new window
3. Shows product information and QR code
4. **Quantity selector** allows 1-100 labels
5. **Print button** opens browser print dialog
6. **Can be saved as PDF** or printed directly

## 📊 QR Code Specifications

- **Format**: PNG image
- **Size**: 300x300px (generated), displayed 40x40px in table
- **Filename**: `qr_PRODUCT_CODE.png` (sanitized)
- **Content**: Product code (SKU)
- **Storage**: `assets/img/qrcodes/`
- **API**: QR Server (api.qrserver.com)

## 🔐 Security Features

✅ **Sanitized filenames** - Special characters converted to underscores
✅ **File type validation** - Only PNG allowed for QR codes
✅ **Access control** - Files served through web server (no direct access needed)
✅ **Automatic cleanup** - Old QR codes deleted when products updated/deleted
✅ **Error handling** - Graceful fallback if API unavailable

## 📋 Database Considerations

**No schema changes required** - QR codes stored as files, not in database

**File-based approach advantages**:

- No database bloat
- Easy to regenerate
- Can be cached
- Simple cleanup on deletion

## 🎯 User Experience

### For Store Managers:

1. **Add Product** → QR automatically created ✓
2. **View Products** → QR preview in table ✓
3. **Download Labels** → Click "Label" button ✓
4. **Print Labels** → Use browser print dialog ✓
5. **Edit Product Code** → QR auto-updated ✓
6. **Delete Product** → QR auto-deleted ✓

### For Customers/Inventory:

1. Scan QR code with smartphone
2. Gets product code/information
3. Can look up product details
4. Useful for inventory management
5. Professional appearance

## 🔄 Bulk Label Operations

The label generator supports printing multiple labels:

```javascript
// User can select quantity 1-100
// Each label generated identically
// All can be printed at once
// Supports print-to-PDF
```

## 📱 Responsive Design

- **Desktop**: Grid layout with multiple columns
- **Tablet**: 2 columns
- **Mobile**: Single column
- **Print**: Optimized for label printers

## ⚙️ Technical Stack

- **QR Generation**: QR Server API (https://api.qrserver.com)
- **Fallback**: GD Library (PHP built-in image functions)
- **Frontend**: Bootstrap 5, JavaScript
- **Backend**: PHP with MySQL
- **Storage**: Local filesystem

## 🐛 Troubleshooting

### QR Codes Not Displaying

**Issue**: QR icon appears instead of QR image

**Solutions**:

1. Check `assets/img/qrcodes/` directory exists
2. Verify write permissions (755)
3. Regenerate QR code by editing product
4. Check internet connection for API

### API Unavailable

**Issue**: QR Server API down

**Fallback**: Local generation creates placeholder with product code

**Fix**:

- System automatically falls back to local generation
- QR code will still be generated
- Appears as placeholder with text

### File Permissions

**Issue**: QR codes not saving

**Solutions**:

1. Create directory: `assets/img/qrcodes/`
2. Set permissions: `chmod 755 assets/img/qrcodes/`
3. Check disk space
4. Verify PHP write permissions

## 📞 Support & Maintenance

### Regular Maintenance:

1. **Disk space**: Monitor `assets/img/qrcodes/` size
2. **Cleanup**: Old QR codes auto-deleted on product updates
3. **Backups**: Include QR code directory in backups
4. **Security**: No sensitive data in QR codes

### API Monitoring:

- QR Server API is free and stable
- Fallback generation ensures service continuity
- No API key required
- Automatic retry not implemented (single attempt)

## 🎓 Examples

### Generate Label for Product ID 5:

```
GET /generate_label.php?id=5
```

### Programmatic QR Generation:

```php
require_once 'libraries/QRCodeGenerator.php';

$qr = new QRCodeGenerator();
$result = $qr->generate('MK-001');

if ($result) {
    echo $result['filename'];  // qr_MK-001.png
}
```

## 🔮 Future Enhancements

Potential improvements:

- [ ] Batch QR code generation
- [ ] QR code history/versioning
- [ ] Custom QR code designs
- [ ] TCPDF integration for native PDF generation
- [ ] Barcode alternative support (Code128, EAN-13)
- [ ] Label template customization
- [ ] Thermal printer driver support

## 📋 Checklist

✅ QR codes auto-generate on product creation
✅ QR codes display in products table
✅ QR codes update when product code changes
✅ QR codes delete when product deleted
✅ Professional label generator created
✅ Print/PDF export functionality
✅ Quantity selector for bulk labels
✅ Responsive design
✅ Error handling and fallbacks
✅ Clean file organization

---

**Version**: 1.0  
**Last Updated**: May 8, 2026  
**Status**: Production Ready ✓
