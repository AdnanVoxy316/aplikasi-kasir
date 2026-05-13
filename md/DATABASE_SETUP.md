# Database Setup Guide

## 🗄️ How to Import the Database

### Method 1: Using phpMyAdmin (Recommended)

1. **Start XAMPP**:
   - Open XAMPP Control Panel
   - Start Apache and MySQL

2. **Open phpMyAdmin**:
   - Go to: `http://localhost/phpmyadmin`
   - Login (default: username=root, no password)

3. **Import Database**:
   - Click on "Import" tab
   - Click "Choose File" and select `database.sql`
   - Click "Go" button
   - Wait for import to complete

4. **Verify**:
   - In left sidebar, you should see `aplikasi_kasir` database
   - Click it to see tables: `products`, `transactions`, `transaction_items`

### Method 2: Using MySQL Command Line

```bash
# Navigate to project directory
cd C:\xampp\htdocs\aplikasi-kasir

# Import the SQL file
mysql -u root -p < database.sql

# (Press Enter if no password, or enter your MySQL password)
```

### Method 3: Using MySQL Workbench

1. Open MySQL Workbench
2. Connect to your MySQL server
3. Go to: File > Open SQL Script
4. Select `database.sql`
5. Click Execute (or Ctrl+Shift+Enter)

## 📊 Database Schema

### Products Table Structure

| Column      | Type          | Description                  |
| ----------- | ------------- | ---------------------------- |
| id          | INT           | Primary key (auto-increment) |
| code        | VARCHAR(50)   | Unique product code/SKU      |
| name        | VARCHAR(255)  | Product name                 |
| price       | DECIMAL(10,2) | Product price                |
| stock       | INT           | Current stock quantity       |
| image       | VARCHAR(255)  | Image filename               |
| description | TEXT          | Product description          |
| category    | VARCHAR(100)  | Product category             |
| created_at  | TIMESTAMP     | Creation timestamp           |
| updated_at  | TIMESTAMP     | Last update timestamp        |

### Sample Data Included

The database.sql includes 8 sample products:

- Indomie Goreng (Stock: 150)
- Teh Botol Sosro (Stock: 200)
- Roti Tawar (Stock: 45)
- Mentega Blok (Stock: 20)
- Gula Pasir (Stock: 8) - LOW STOCK
- Telur Ayam (Stock: 120)
- Minyak Goreng (Stock: 50)
- Kopi Nescafe (Stock: 75)

## 🔧 Database Configuration

The connection settings are in `config/database.php`:

```php
define('DB_HOST', 'localhost');    // MySQL host
define('DB_USER', 'root');          // MySQL username
define('DB_PASS', '');              // MySQL password (empty by default)
define('DB_NAME', 'aplikasi_kasir'); // Database name
```

**Modify these if:**

- Your MySQL server is on a different host
- You've set a MySQL root password
- You want to use a different database name

## ✅ Verification

After import, verify the setup:

1. **Check Database Exists**:

   ```sql
   SHOW DATABASES;
   ```

2. **Check Tables Exist**:

   ```sql
   USE aplikasi_kasir;
   SHOW TABLES;
   ```

3. **View Sample Products**:
   ```sql
   SELECT * FROM products;
   ```

## 🐛 Troubleshooting

### Import Failed

- Ensure MySQL service is running
- Check file permissions
- Verify UTF-8 encoding

### Connection Error

- Verify MySQL service is running
- Check database credentials in `config/database.php`
- Ensure database name matches (aplikasi_kasir)

### Permission Denied

- Check folder permissions on `aplikasi-kasir` directory
- Ensure write permissions for log files

## 📝 Notes

- Default database name: `aplikasi_kasir`
- Default charset: UTF-8 (utf8mb4)
- Default collation: utf8mb4_unicode_ci
- Tables use InnoDB engine for transaction support
