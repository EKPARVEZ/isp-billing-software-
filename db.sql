-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 07:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `isp_billing`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `page` varchar(255) DEFAULT NULL,
  `method` varchar(10) DEFAULT NULL,
  `data` text DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `execution_time` float DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `client_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `package_name` varchar(50) DEFAULT NULL,
  `package_price` decimal(10,2) DEFAULT NULL,
  `connection_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `due_bills`
--

CREATE TABLE `due_bills` (
  `id` int(11) NOT NULL,
  `client_id` varchar(20) DEFAULT NULL,
  `month_year` date DEFAULT NULL,
  `bill_amount` decimal(10,2) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('due','partial') DEFAULT 'due',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paid_bills`
--

CREATE TABLE `paid_bills` (
  `id` int(11) NOT NULL,
  `client_id` varchar(20) DEFAULT NULL,
  `month_year` date DEFAULT NULL,
  `bill_amount` decimal(10,2) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` enum('cash','bkash','nagad','rocket','bank','baki') NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) DEFAULT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'company_name', 'BD TECHNOLOGY'),
(2, 'company_email', 'info@ispbilling.com'),
(3, 'company_phone', '01912981072'),
(4, 'company_address', 'Jashore'),
(5, 'company_website', 'www.bdtechnology.zya.me'),
(6, 'tax_rate', '0'),
(7, 'due_days', '10'),
(8, 'invoice_prefix', 'INV'),
(9, 'invoice_format', 'INV-{year}-{month}-{id}'),
(10, 'currency_symbol', '৳'),
(11, 'date_format', 'd-m-Y'),
(12, 'sms_api_key', ''),
(13, 'sms_sender_id', ''),
(14, 'email_smtp_host', ''),
(15, 'email_smtp_port', '587'),
(16, 'email_smtp_user', ''),
(17, 'email_smtp_pass', ''),
(18, 'reminder_days', '5,2,1'),
(19, 'auto_backup', '1'),
(20, 'backup_frequency', 'daily'),
(21, 'backup_time', '02:00'),
(22, 'backup_retention', '7'),
(23, 'favicon', 'assets/img/favicon/favicon.png');

-- --------------------------------------------------------

--
-- Table structure for table `sms_log`
--

CREATE TABLE `sms_log` (
  `id` int(11) NOT NULL,
  `client_id` varchar(50) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `response` text DEFAULT NULL,
  `sent_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'user',
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `name`, `password`, `email`, `role`, `status`, `created_at`) VALUES
(2, 'admin', 'Parvez', '$2y$10$ulRbjqR52lBZSHKSa4zVsecHSkfceO2K9r3/J9EQiIUV4jviLV4/q', 'admin@isp.com', 'admin', 'active', '2026-02-27 07:19:47'),
(3, 'Parvez', 'Parvez', '$2y$10$n0d7vBDCaooWAKCc/1HhZuIkBvG.MTAmM9sik8PMuHFopRsWYilx2', 'bdtechnology2019@gmail.com', 'viewer', 'active', '2026-03-01 06:59:03');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_description` text DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `is_system` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `role_name`, `role_description`, `permissions`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'সম্পূর্ণ সিস্টেম অ্যাক্সেস সহ প্রশাসক', '{\"all\":true}', 1, '2026-03-01 07:36:21', '2026-03-01 07:36:21'),
(2, 'manager', 'ক্লায়েন্ট ও বিল ম্যানেজ করতে পারেন, কিন্তু সেটিংস পরিবর্তন করতে পারেন না', '{\"dashboard\":true,\"clients\":[\"view\",\"add\",\"edit\",\"delete\"],\"bills\":[\"view\",\"add\",\"edit\",\"delete\"],\"payments\":[\"view\",\"add\",\"edit\"],\"reports\":[\"view\"],\"settings\":false}', 0, '2026-03-01 07:36:21', '2026-03-01 07:36:21'),
(3, 'accountant', 'শুধু বিল ও পেমেন্ট দেখতে ও করতে পারেন', '{\"dashboard\":true,\"clients\":[\"view\"],\"bills\":[\"view\",\"add\"],\"payments\":[\"view\",\"add\"],\"reports\":[\"view\"],\"settings\":false}', 0, '2026-03-01 07:36:21', '2026-03-01 07:36:21'),
(4, 'viewer', 'শুধু দেখতে পারেন, কোনো পরিবর্তন করতে পারেন না', '{\"dashboard\":true,\"clients\":[\"view\"],\"bills\":[\"view\"],\"payments\":[\"view\"],\"reports\":[\"view\"],\"settings\":false}', 0, '2026-03-01 07:36:21', '2026-03-01 07:36:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_id` (`client_id`);

--
-- Indexes for table `due_bills`
--
ALTER TABLE `due_bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `paid_bills`
--
ALTER TABLE `paid_bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `sms_log`
--
ALTER TABLE `sms_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1047;

--
-- AUTO_INCREMENT for table `due_bills`
--
ALTER TABLE `due_bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=358;

--
-- AUTO_INCREMENT for table `paid_bills`
--
ALTER TABLE `paid_bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `sms_log`
--
ALTER TABLE `sms_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `due_bills`
--
ALTER TABLE `due_bills`
  ADD CONSTRAINT `due_bills_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`);

--
-- Constraints for table `paid_bills`
--
ALTER TABLE `paid_bills`
  ADD CONSTRAINT `paid_bills_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
