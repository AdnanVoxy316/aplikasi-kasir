-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2026 at 09:22 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aplikasi_kasir_copy`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `total_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `status` enum('Masuk','Pulang') NOT NULL DEFAULT 'Pulang',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `clock_in_at` datetime NOT NULL,
  `clock_out_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_logs`
--

INSERT INTO `attendance_logs` (`id`, `user_id`, `clock_in_at`, `clock_out_at`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-05-10 07:33:06', '2026-05-10 07:33:08', '2026-05-10 00:33:06', '2026-05-10 00:33:08'),
(2, 1, '2026-05-10 07:33:12', '2026-05-10 07:33:14', '2026-05-10 00:33:12', '2026-05-10 00:33:14'),
(3, 1, '2026-05-10 08:06:15', '2026-05-10 08:06:16', '2026-05-10 01:06:15', '2026-05-10 01:06:16'),
(4, 1, '2026-05-10 10:39:06', '2026-05-10 10:39:08', '2026-05-10 03:39:06', '2026-05-10 03:39:08'),
(5, 1, '2026-05-10 13:12:40', '2026-05-10 13:12:44', '2026-05-10 06:12:40', '2026-05-10 06:12:44'),
(6, 1, '2026-05-10 21:29:26', '2026-05-10 21:29:28', '2026-05-10 14:29:26', '2026-05-10 14:29:28'),
(7, 1, '2026-05-10 21:29:32', '2026-05-10 21:29:38', '2026-05-10 14:29:32', '2026-05-10 14:29:38'),
(8, 1, '2026-05-11 12:08:59', '2026-05-11 12:09:09', '2026-05-11 05:08:59', '2026-05-11 05:09:09'),
(9, 1, '2026-05-11 12:13:12', '2026-05-11 12:13:16', '2026-05-11 05:13:12', '2026-05-11 05:13:16'),
(10, 1, '2026-05-11 12:51:28', '2026-05-11 12:51:33', '2026-05-11 05:51:28', '2026-05-11 05:51:33'),
(11, 1, '2026-05-11 13:10:55', '2026-05-11 13:10:57', '2026-05-11 06:10:55', '2026-05-11 06:10:57'),
(12, 1, '2026-05-12 22:38:14', '2026-05-12 22:38:19', '2026-05-12 15:38:14', '2026-05-12 15:38:19'),
(13, 1, '2026-05-12 22:38:26', '2026-05-12 22:38:48', '2026-05-12 15:38:26', '2026-05-12 15:38:48'),
(14, 1, '2026-05-12 22:43:26', '2026-05-12 22:43:30', '2026-05-12 15:43:26', '2026-05-12 15:43:30'),
(15, 1, '2026-05-12 23:27:21', '2026-05-12 23:27:24', '2026-05-12 16:27:21', '2026-05-12 16:27:24'),
(16, 1, '2026-05-12 23:30:00', '2026-05-12 23:30:21', '2026-05-12 16:30:00', '2026-05-12 16:30:21'),
(17, 1, '2026-05-12 23:38:32', '2026-05-12 23:38:34', '2026-05-12 16:38:32', '2026-05-12 16:38:34'),
(18, 1, '2026-05-12 23:38:46', '2026-05-12 23:39:11', '2026-05-12 16:38:46', '2026-05-12 16:39:11'),
(19, 1, '2026-05-13 00:11:39', '2026-05-13 00:11:41', '2026-05-12 17:11:39', '2026-05-12 17:11:41'),
(20, 1, '2026-05-13 00:11:56', '2026-05-13 00:11:57', '2026-05-12 17:11:56', '2026-05-12 17:11:57'),
(21, 1, '2026-05-13 00:11:59', '2026-05-13 00:12:19', '2026-05-12 17:11:59', '2026-05-12 17:12:19'),
(22, 1, '2026-05-13 00:12:26', '2026-05-13 00:12:37', '2026-05-12 17:12:26', '2026-05-12 17:12:37'),
(23, 1, '2026-05-13 01:09:57', '2026-05-13 01:10:01', '2026-05-12 18:09:57', '2026-05-12 18:10:01'),
(24, 1, '2026-05-13 01:10:09', '2026-05-13 01:10:59', '2026-05-12 18:10:09', '2026-05-12 18:10:59'),
(25, 1, '2026-05-13 01:11:00', '2026-05-13 01:11:04', '2026-05-12 18:11:00', '2026-05-12 18:11:04'),
(26, 1, '2026-05-13 01:11:05', '2026-05-13 01:11:06', '2026-05-12 18:11:05', '2026-05-12 18:11:06'),
(27, 1, '2026-05-13 01:11:06', '2026-05-13 01:11:07', '2026-05-12 18:11:06', '2026-05-12 18:11:07'),
(28, 1, '2026-05-13 01:36:18', '2026-05-13 01:36:20', '2026-05-12 18:36:18', '2026-05-12 18:36:20'),
(29, 1, '2026-05-13 01:36:23', '2026-05-13 01:36:25', '2026-05-12 18:36:23', '2026-05-12 18:36:25'),
(30, 1, '2026-05-13 01:36:26', '2026-05-13 01:36:26', '2026-05-12 18:36:26', '2026-05-12 18:36:26'),
(31, 1, '2026-05-13 01:36:27', '2026-05-13 01:36:28', '2026-05-12 18:36:27', '2026-05-12 18:36:28'),
(32, 1, '2026-05-13 01:36:28', '2026-05-13 01:36:29', '2026-05-12 18:36:28', '2026-05-12 18:36:29'),
(33, 1, '2026-05-13 01:36:30', '2026-05-13 01:36:30', '2026-05-12 18:36:30', '2026-05-12 18:36:30'),
(34, 1, '2026-05-13 01:36:34', '2026-05-13 01:36:35', '2026-05-12 18:36:34', '2026-05-12 18:36:35'),
(35, 1, '2026-05-13 01:36:37', '2026-05-13 01:36:39', '2026-05-12 18:36:37', '2026-05-12 18:36:39'),
(36, 1, '2026-05-13 01:37:14', '2026-05-13 01:37:16', '2026-05-12 18:37:14', '2026-05-12 18:37:16'),
(37, 1, '2026-05-13 02:39:08', '2026-05-13 02:39:09', '2026-05-12 19:39:08', '2026-05-12 19:39:09'),
(38, 1, '2026-05-13 02:39:10', '2026-05-13 02:39:10', '2026-05-12 19:39:10', '2026-05-12 19:39:10'),
(39, 1, '2026-05-13 02:39:11', '2026-05-13 02:39:12', '2026-05-12 19:39:11', '2026-05-12 19:39:12'),
(40, 1, '2026-05-13 02:39:12', '2026-05-13 02:39:17', '2026-05-12 19:39:12', '2026-05-12 19:39:17'),
(41, 1, '2026-05-13 02:39:21', '2026-05-13 02:47:07', '2026-05-12 19:39:21', '2026-05-12 19:47:07'),
(42, 1, '2026-05-13 02:47:08', '2026-05-13 02:47:10', '2026-05-12 19:47:08', '2026-05-12 19:47:10'),
(43, 1, '2026-05-13 03:00:18', '2026-05-13 03:00:19', '2026-05-12 20:00:18', '2026-05-12 20:00:19');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL COMMENT 'Product code/SKU',
  `name` varchar(255) NOT NULL COMMENT 'Product name',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Product price',
  `stock` int(11) NOT NULL DEFAULT 0 COMMENT 'Product stock quantity',
  `image` varchar(255) DEFAULT NULL COMMENT 'Product image filename',
  `description` text DEFAULT NULL COMMENT 'Product description',
  `category` varchar(100) DEFAULT NULL COMMENT 'Product category',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Products inventory';

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `code`, `name`, `price`, `stock`, `image`, `description`, `category`, `created_at`, `updated_at`) VALUES
(2, 'MN-002', 'Teh Botol Sosro', 3000.00, 200, NULL, 'Minuman teh siap minum', 'Minuman', '2026-05-08 17:22:30', '2026-05-08 19:43:34'),
(3, 'PRD003', 'Roti Tawar', 15000.00, 45, NULL, 'Roti tawar premium', 'Bakery', '2026-05-08 17:22:30', '2026-05-08 17:22:30'),
(4, 'BK-004', 'Mentega Blok', 45000.00, 20, NULL, 'Mentega kemasan 1kg', 'Bakery', '2026-05-08 17:22:30', '2026-05-08 20:03:59'),
(5, 'PRD005', 'Gula Pasir', 12000.00, 8, NULL, 'Gula pasir 1kg - STOK RENDAH', 'Bumbu', '2026-05-08 17:22:30', '2026-05-08 17:22:30'),
(6, 'MK-006', 'Telur Ayam', 22000.00, 120, 'product_1778370247_3ce4205b.jpeg', 'Telur ayam segar 1 krat', 'Makanan', '2026-05-08 17:22:30', '2026-05-09 23:44:07'),
(17, 'PB-1', '1ads', 11.00, 111, 'product_1778393449_36d691a7.jpeg', '', 'Pembersih & Sabun', '2026-05-10 06:10:49', '2026-05-10 06:10:49'),
(18, 'PB-111', 'tolong', 10000.00, 100, '', 'ajdsls', 'Pembersih & Sabun', '2026-05-11 05:19:39', '2026-05-11 05:19:39');

-- --------------------------------------------------------

--
-- Table structure for table `receipt_settings`
--

CREATE TABLE `receipt_settings` (
  `id` int(11) NOT NULL,
  `header_text` varchar(255) DEFAULT NULL,
  `footer_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `receipt_settings`
--

INSERT INTO `receipt_settings` (`id`, `header_text`, `footer_text`, `created_at`, `updated_at`) VALUES
(1, 'Terima kasih telah berbelanja', 'Barang yang sudah dibeli tidak dapat ditukar', '2026-05-09 23:43:34', '2026-05-09 23:43:34');

-- --------------------------------------------------------

--
-- Table structure for table `store_profile`
--

CREATE TABLE `store_profile` (
  `id` int(11) NOT NULL,
  `store_name` varchar(150) NOT NULL,
  `store_address` text DEFAULT NULL,
  `store_logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_profile`
--

INSERT INTO `store_profile` (`id`, `store_name`, `store_address`, `store_logo`, `created_at`, `updated_at`) VALUES
(1, 'Kasir Pintar Store', '', '', '2026-05-09 23:43:34', '2026-05-09 23:44:31');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `transaction_number` varchar(50) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) DEFAULT NULL,
  `payment_amount` decimal(12,2) DEFAULT NULL,
  `change_amount` decimal(12,2) DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL COMMENT 'Cash, Card, E-wallet',
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_time` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales transactions';

-- --------------------------------------------------------

--
-- Table structure for table `transaction_details`
--

CREATE TABLE `transaction_details` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_items`
--

CREATE TABLE `transaction_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL COMMENT 'Price at time of transaction',
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items in transactions';

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','kasir') NOT NULL DEFAULT 'kasir',
  `nama_lengkap` varchar(120) NOT NULL,
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `nama_lengkap`, `security_question`, `security_answer`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$GLsdGXECStAdvQejkoiHF.pX3wbcvB6TpOW4hYX0ABrPAempvyqbG', 'kasir', 'Muhammad Alip Gunastiar', 'Siapa nama ibu kandung Anda?', '$2y$10$bcfJQAq7mVpLgeSwayGLiemYEDCu4/Ib7ugEzyma33If5vC4BdI72', 1, '2026-05-09 23:43:33', '2026-05-11 05:12:55'),
(2, 'cashier', '$2y$10$4R2rRMkXx3vfPkYXz/rFFOowe0J5OEJCgtGZydx/oWXHpCokZ9/sm', 'kasir', 'Default Cashier', 'Siapa nama ibu kandung Anda?', 'ibukandung', 1, '2026-05-10 01:17:01', '2026-05-10 01:17:01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_date` (`user_id`,`date`),
  ADD KEY `idx_attendance_date` (`date`),
  ADD KEY `idx_attendance_status` (`status`);

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attendance_user` (`user_id`),
  ADD KEY `idx_attendance_in` (`clock_in_at`),
  ADD KEY `idx_attendance_out` (`clock_out_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `receipt_settings`
--
ALTER TABLE `receipt_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `store_profile`
--
ALTER TABLE `store_profile`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_number` (`transaction_number`),
  ADD KEY `idx_transaction_number` (`transaction_number`),
  ADD KEY `idx_transaction_date` (`transaction_date`);

--
-- Indexes for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `receipt_settings`
--
ALTER TABLE `receipt_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `store_profile`
--
ALTER TABLE `store_profile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_details`
--
ALTER TABLE `transaction_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_items`
--
ALTER TABLE `transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD CONSTRAINT `fk_transaction_details_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_transaction_details_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD CONSTRAINT `transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
