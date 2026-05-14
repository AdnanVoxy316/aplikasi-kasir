-- Kasir Pintar - Database Schema
-- Created: 2026-05-07

-- Create Database
CREATE DATABASE IF NOT EXISTS aplikasi_kasir_copy;
USE aplikasi_kasir_copy;

-- Users Table (Admin & Cashier)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
    nama_lengkap VARCHAR(120) NOT NULL,
    cashier_id VARCHAR(11) NULL COMMENT 'Admin-managed cashier identifier',
    contact_number VARCHAR(30) NULL COMMENT 'Contact phone number',
    email VARCHAR(150) NULL COMMENT 'Email address',
    profile_photo VARCHAR(255) NULL COMMENT 'Profile photo filename',
    security_question VARCHAR(255) NULL,
    security_answer VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System users';

INSERT INTO users (username, password, role, nama_lengkap, is_active)
SELECT 'admin', '$2y$10$H4e6aFRPDFSurIYu5eoXe.7Q0TAjhvw15e.iCpvgqGWjm1Dg/UicS', 'admin', 'Administrator', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- Store Profile Table
CREATE TABLE IF NOT EXISTS store_profile (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_name VARCHAR(150) NOT NULL,
    store_address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Store profile settings';

INSERT INTO store_profile (store_name, store_address)
SELECT 'Kasir Pintar Store', ''
WHERE NOT EXISTS (SELECT 1 FROM store_profile);

-- Products Table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL COMMENT 'Product code/SKU',
    name VARCHAR(255) NOT NULL COMMENT 'Product name',
    price DECIMAL(10, 2) NOT NULL DEFAULT 0 COMMENT 'Product price',
    stock INT NOT NULL DEFAULT 0 COMMENT 'Product stock quantity',
    image VARCHAR(255) NULL COMMENT 'Product image filename',
    description TEXT NULL COMMENT 'Product description',
    category VARCHAR(100) NULL COMMENT 'Product category',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_name (name),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Products inventory';

-- Insert Sample Data
INSERT INTO products (code, name, price, stock, category, description) VALUES
('PRD001', 'Indomie Goreng', 2500, 150, 'Makanan', 'Mie instan rasa goreng'),
('PRD002', 'Teh Botol Sosro', 3000, 200, 'Minuman', 'Minuman teh siap minum'),
('PRD003', 'Roti Tawar', 15000, 45, 'Bakery', 'Roti tawar premium'),
('PRD004', 'Mentega Blok', 45000, 20, 'Dairy', 'Mentega kemasan 1kg'),
('PRD005', 'Gula Pasir', 12000, 8, 'Bumbu', 'Gula pasir 1kg - STOK RENDAH'),
('PRD006', 'Telur Ayam', 22000, 120, 'Protein', 'Telur ayam segar 1 krat'),
('PRD007', 'Minyak Goreng', 35000, 50, 'Bumbu', 'Minyak goreng 2L'),
('PRD008', 'Kopi Nescafe', 28000, 75, 'Minuman', 'Kopi instan sachet box');

-- Transactions Table (for future use)
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NULL,
    cashier_name VARCHAR(100) NULL COMMENT 'Snapshot cashier name used on receipt',
    total_amount DECIMAL(12, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL COMMENT 'Cash, Card, E-wallet',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_transaction_number (transaction_number),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_user_id (user_id),
    INDEX idx_cashier_name (cashier_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales transactions';

-- Ensure existing databases (created before this update) also get cashier columns
ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS user_id INT NULL,
    ADD COLUMN IF NOT EXISTS cashier_name VARCHAR(100) NULL COMMENT 'Snapshot cashier name used on receipt';

-- Transaction Items Table (for future use)
CREATE TABLE IF NOT EXISTS transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL COMMENT 'Price at time of transaction',
    subtotal DECIMAL(12, 2) NOT NULL,
    
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items in transactions';
