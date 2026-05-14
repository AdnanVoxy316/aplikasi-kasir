<?php
/**
 * Ensure user/auth-related tables and default admin exist.
 */

function ensureAuthTablesAndSeedAdmin(mysqli $conn) {
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
        nama_lengkap VARCHAR(120) NOT NULL,
        cashier_id VARCHAR(11) NULL,
        contact_number VARCHAR(30) NULL,
        email VARCHAR(150) NULL,
        profile_photo VARCHAR(255) NULL,
        security_question VARCHAR(255) NULL,
        security_answer VARCHAR(255) NULL,
        security_question_secondary VARCHAR(255) NULL,
        security_answer_secondary VARCHAR(255) NULL,
        security_question_tertiary VARCHAR(255) NULL,
        security_answer_tertiary VARCHAR(255) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_role (role),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensureColumnExists($conn, 'users', 'password', "ALTER TABLE users ADD COLUMN password VARCHAR(255) NULL AFTER username");
    ensureColumnExists($conn, 'users', 'role', "ALTER TABLE users ADD COLUMN role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir' AFTER password");
    ensureColumnExists($conn, 'users', 'nama_lengkap', "ALTER TABLE users ADD COLUMN nama_lengkap VARCHAR(120) NULL AFTER role");
    ensureColumnExists($conn, 'users', 'cashier_id', "ALTER TABLE users ADD COLUMN cashier_id VARCHAR(11) NULL AFTER nama_lengkap");
    ensureColumnExists($conn, 'users', 'contact_number', "ALTER TABLE users ADD COLUMN contact_number VARCHAR(30) NULL AFTER cashier_id");
    ensureColumnExists($conn, 'users', 'email', "ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL AFTER contact_number");
    ensureColumnExists($conn, 'users', 'profile_photo', "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER email");
    ensureColumnExists($conn, 'users', 'security_question', "ALTER TABLE users ADD COLUMN security_question VARCHAR(255) NULL AFTER nama_lengkap");
    ensureColumnExists($conn, 'users', 'security_answer', "ALTER TABLE users ADD COLUMN security_answer VARCHAR(255) NULL AFTER security_question");
    ensureColumnExists($conn, 'users', 'security_question_secondary', "ALTER TABLE users ADD COLUMN security_question_secondary VARCHAR(255) NULL AFTER security_answer");
    ensureColumnExists($conn, 'users', 'security_answer_secondary', "ALTER TABLE users ADD COLUMN security_answer_secondary VARCHAR(255) NULL AFTER security_question_secondary");
    ensureColumnExists($conn, 'users', 'security_question_tertiary', "ALTER TABLE users ADD COLUMN security_question_tertiary VARCHAR(255) NULL AFTER security_answer_secondary");
    ensureColumnExists($conn, 'users', 'security_answer_tertiary', "ALTER TABLE users ADD COLUMN security_answer_tertiary VARCHAR(255) NULL AFTER security_question_tertiary");
    ensureColumnExists($conn, 'users', 'is_active', "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER nama_lengkap");
    ensureColumnExists($conn, 'users', 'created_at', "ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    ensureColumnExists($conn, 'users', 'updated_at', "ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    // Migrate old schema data when present
    if (columnExists($conn, 'users', 'password_hash') && columnExists($conn, 'users', 'password')) {
        $conn->query("UPDATE users SET password = password_hash WHERE (password IS NULL OR password = '') AND password_hash IS NOT NULL");
    }

    if (columnExists($conn, 'users', 'name') && columnExists($conn, 'users', 'nama_lengkap')) {
        $conn->query("UPDATE users SET nama_lengkap = name WHERE (nama_lengkap IS NULL OR nama_lengkap = '') AND name IS NOT NULL");
    }

    $conn->query("UPDATE users
                  SET cashier_id = CONCAT('92141', LPAD(id, 6, '0'))
                  WHERE cashier_id IS NULL
                     OR cashier_id = ''
                     OR cashier_id NOT REGEXP '^[0-9]{11}$'");

    $admin_exists = false;
    $exists_result = $conn->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    if ($exists_result && $exists_result->num_rows > 0) {
        $admin_exists = true;
    }

    if (!$admin_exists) {
        $hashed = $conn->real_escape_string(password_hash('123456', PASSWORD_DEFAULT));
        $conn->query("INSERT INTO users (username, password, role, nama_lengkap, is_active) VALUES ('admin', '$hashed', 'admin', 'Administrator', 1)");
    }

    $count_result = $conn->query("SELECT COUNT(*) AS total FROM users");
    if ($count_result) {
        $row = $count_result->fetch_assoc();
        if ((int) ($row['total'] ?? 0) === 0) {
            $hashed = $conn->real_escape_string(password_hash('123456', PASSWORD_DEFAULT));
            $conn->query("INSERT INTO users (username, password, role, nama_lengkap, is_active) VALUES ('admin', '$hashed', 'admin', 'Administrator', 1)");
        }
    }

    $security_question_default = $conn->real_escape_string('Siapa nama ibu kandung Anda?');
    $cashier_default_answer = $conn->real_escape_string('ibukandung');
    $cashier_exists = $conn->query("SELECT id FROM users WHERE username = 'cashier' LIMIT 1");
    if (!$cashier_exists || $cashier_exists->num_rows === 0) {
        $cashier_pass = $conn->real_escape_string(password_hash('123456', PASSWORD_DEFAULT));
        $conn->query("INSERT INTO users (username, password, role, nama_lengkap, security_question, security_answer, is_active) VALUES ('cashier', '$cashier_pass', 'kasir', 'Default Cashier', '$security_question_default', '$cashier_default_answer', 1)");
    } else {
        $conn->query("UPDATE users SET security_question = '$security_question_default' WHERE role = 'kasir' AND (security_question IS NULL OR security_question = '')");
        $conn->query("UPDATE users SET security_answer = '$cashier_default_answer' WHERE username = 'cashier' AND (security_answer IS NULL OR security_answer = '')");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS store_profile (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_name VARCHAR(150) NOT NULL,
        store_address TEXT NULL,
        store_logo VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS receipt_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        header_text VARCHAR(255) NULL,
        footer_text VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS attendance_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        clock_in_at DATETIME NOT NULL,
        clock_out_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_attendance_user (user_id),
        INDEX idx_attendance_in (clock_in_at),
        INDEX idx_attendance_out (clock_out_at),
        CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // New attendance table used by dashboard live attendance module
    $conn->query("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date DATE NOT NULL,
        clock_in DATETIME NULL,
        clock_out DATETIME NULL,
        total_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
        status ENUM('Masuk','Pulang') NOT NULL DEFAULT 'Pulang',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_date (user_id, date),
        INDEX idx_attendance_date (date),
        INDEX idx_attendance_status (status),
        CONSTRAINT fk_attendance_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("INSERT INTO store_profile (store_name, store_address, store_logo)
                  SELECT 'Kasir Pintar Store', '', NULL
                  WHERE NOT EXISTS (SELECT 1 FROM store_profile)");

    $conn->query("INSERT INTO receipt_settings (header_text, footer_text)
                  SELECT 'Terima kasih telah berbelanja', 'Barang yang sudah dibeli tidak dapat ditukar'
                  WHERE NOT EXISTS (SELECT 1 FROM receipt_settings)");

    $bootstrapped = true;
}

function columnExists(mysqli $conn, $table, $column) {
    $table_safe = $conn->real_escape_string($table);
    $column_safe = $conn->real_escape_string($column);

    $query = "SHOW COLUMNS FROM `{$table_safe}` LIKE '{$column_safe}'";
    $result = $conn->query($query);
    return $result && $result->num_rows > 0;
}

function ensureColumnExists(mysqli $conn, $table, $column, $alter_sql) {
    if (!columnExists($conn, $table, $column)) {
        $conn->query($alter_sql);
    }
}
