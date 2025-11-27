-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 27, 2025 at 11:43 PM
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
-- Database: `hospital_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `checking_forms`
--

CREATE TABLE `checking_forms` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `symptoms` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checking_forms`
--

INSERT INTO `checking_forms` (`id`, `patient_id`, `doctor_id`, `symptoms`, `diagnosis`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(3, 3, 3, 'anaumwa na tumbo', 'kala kwa shangazi ', 'asile sana ', 'completed', '2025-11-27 19:32:00', '2025-11-27 19:32:00');

-- --------------------------------------------------------

--
-- Table structure for table `insurance`
--

CREATE TABLE `insurance` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `insurance_company` varchar(100) DEFAULT NULL,
  `policy_number` varchar(100) DEFAULT NULL,
  `coverage_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laboratory_tests`
--

CREATE TABLE `laboratory_tests` (
  `id` int(11) NOT NULL,
  `checking_form_id` int(11) NOT NULL,
  `test_type` varchar(100) NOT NULL,
  `results` text DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `test_description` text DEFAULT NULL,
  `conducted_by` int(11) DEFAULT NULL,
  `lab_price` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laboratory_tests`
--

INSERT INTO `laboratory_tests` (`id`, `checking_form_id`, `test_type`, `results`, `status`, `created_at`, `updated_at`, `test_description`, `conducted_by`, `lab_price`) VALUES
(3, 3, 'Blood Pressure', 'hamna ', 'completed', '2025-11-27 19:32:44', '2025-11-27 19:33:18', 'mpime hii ', NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `card_no` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `patient_type` enum('standard','child','senior','emergency','follow_up') DEFAULT 'standard'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `card_no`, `full_name`, `age`, `gender`, `weight`, `phone`, `address`, `consultation_fee`, `created_by`, `created_at`, `updated_at`, `patient_type`) VALUES
(3, 'PT2025-00011', 'HUSSEIN ABDULRAHMAN', 23, 'male', 23.00, '0658216348', 'Ushirika', 10000.00, 2, '2025-11-27 19:30:37', '2025-11-27 19:30:37', 'standard');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `checking_form_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `medicine_amount` decimal(10,2) DEFAULT 0.00,
  `lab_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('cash','card','mobile','insurance') DEFAULT 'cash',
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `checking_form_id` int(11) NOT NULL,
  `medicine_name` varchar(100) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('prescribed','dispensed','cancelled') DEFAULT 'prescribed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `frequency` varchar(50) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `alternative_medicine` varchar(255) DEFAULT NULL,
  `medicine_price` decimal(10,2) DEFAULT 0.00,
  `is_available` enum('yes','no') DEFAULT 'yes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `checking_form_id`, `medicine_name`, `dosage`, `quantity`, `instructions`, `status`, `created_at`, `updated_at`, `frequency`, `duration`, `alternative_medicine`, `medicine_price`, `is_available`) VALUES
(2, 3, 'dawa ya kichwa ', '500mg', 0, 'mpime hii ', 'prescribed', '2025-11-27 19:37:43', '2025-11-27 19:37:43', 'three per day', '7 days', NULL, 0.00, 'yes'),
(3, 3, 'PANADOL', '300%', 0, 'tumia kwa wakati', '', '2025-11-27 20:20:52', '2025-11-27 20:20:52', 'k;lk/l', '5', NULL, 0.00, 'yes'),
(4, 3, 'PANADOL', '300%', 0, 'tumia kwa wakati', '', '2025-11-27 20:26:22', '2025-11-27 20:26:22', 'k;lk/l', '5', NULL, 0.00, 'yes'),
(5, 3, 'PANADOL', '300%', 0, 'tumia kwa wakati', '', '2025-11-27 20:27:30', '2025-11-27 20:27:30', 'k;lk/l', '5', NULL, 0.00, 'yes'),
(6, 3, 'PANADOL', '300%', 0, 'tumia kwa wakati', '', '2025-11-27 20:28:04', '2025-11-27 20:28:04', 'k;lk/l', '5', NULL, 0.00, 'yes'),
(7, 3, 'dawa ya kichwa ', '500mg', 0, 'csd bn', 'prescribed', '2025-11-27 21:51:46', '2025-11-27 21:51:46', 'bdba', '7 days', NULL, 0.00, 'yes');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) DEFAULT 0.00,
  `issued_by` int(11) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `refund_reason` text DEFAULT NULL,
  `processed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'consultation_fee_standard', '10000', '2025-11-26 16:05:01', '2025-11-26 16:05:01'),
(2, 'consultation_fee_child', '5000', '2025-11-26 16:05:01', '2025-11-26 16:05:01'),
(3, 'consultation_fee_senior', '7000', '2025-11-26 16:05:01', '2025-11-26 16:05:01'),
(4, 'consultation_fee_emergency', '15000', '2025-11-26 16:05:01', '2025-11-26 16:05:01'),
(5, 'consultation_fee_follow_up', '8000', '2025-11-26 16:05:01', '2025-11-26 16:05:01');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'clinic_name', 'ALMAJYD DISPENSARY', 'Name of the clinic', NULL, '2025-11-27 16:59:27'),
(2, 'clinic_address', 'TOMONDO - ZANZIBAR', 'Physical address of the clinic', NULL, '2025-11-27 16:59:27'),
(3, 'clinic_phone', '+255 777 567 478 / +255 719 053 764', 'Contact phone numbers', NULL, '2025-11-27 16:59:27'),
(4, 'clinic_email', 'amrykassim@gmail.com', 'Contact email address', NULL, '2025-11-27 16:59:27'),
(5, 'consultation_fee', '5000', 'Default consultation fee in TZS', NULL, '2025-11-27 16:59:27'),
(6, 'receipt_prefix', 'RCP', 'Prefix for receipt numbers', NULL, '2025-11-27 16:59:27'),
(7, 'currency', 'TZS', 'Default currency', NULL, '2025-11-27 16:59:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','doctor','receptionist','laboratory','pharmacy','cashier') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `district` varchar(50) DEFAULT NULL,
  `region` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `full_name`, `phone`, `district`, `region`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'amrykassim@gmail.com', 'admin', 'HUSSEIN ABDULRAHMAN', '+255777567478', 'Tomondo', 'Zanzibar', 'active', '2025-11-27 16:59:27', '2025-11-27 17:03:48'),
(2, 'receptionist', '$2y$10$rd9zt5qIOLCCYijvWPsrMO6PwfkDgEqZHyoNTFrI7AfUixhd2m7NK', 'rec@gmail.com', 'receptionist', 'RECEPTIONIST', '+255658216348', 'Tomondo', 'Zanzibar', 'active', '2025-11-27 17:01:44', '2025-11-27 17:01:44'),
(3, 'doctor', '$2y$10$f9LDa4rRmUqFn3t8N2Wd1.EsRnOpN1OTYe1GXYYH6NvqA9VThJeve', 'd@gmail.com', 'doctor', 'HUSSEIN ABDULRAHMAN', '+255658216348', 'Tomondo', 'Zanzibar', 'active', '2025-11-27 17:37:25', '2025-11-27 17:37:25'),
(4, 'labolatory', '$2y$10$Asc7wWPyej34m5hZV9SBceR/B7hN3XQsocFJZLMaMoyB3z2yuXL1a', 'lab@gmail.com', 'laboratory', 'HUSSEIN ABDULRAHMAN', '+255658216348', 'Tomondo', 'Zanzibar', 'active', '2025-11-27 18:47:01', '2025-11-27 18:47:01'),
(5, 'phamarcy', '$2y$10$K4y6wWhhFitXwfmQDI8PTO7rHxpPlsgkBvpgbTgFGY1mtb59LRkuS', 'phamarcy@gmail.com', 'pharmacy', 'HUSSEIN ABDULRAHMAN', '+255658216348', 'Tomondo', 'Zanzibar', 'active', '2025-11-27 19:35:12', '2025-11-27 19:35:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_logs_user_id` (`user_id`),
  ADD KEY `idx_activity_logs_created_at` (`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `checking_forms`
--
ALTER TABLE `checking_forms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_checking_forms_patient_id` (`patient_id`),
  ADD KEY `idx_checking_forms_doctor_id` (`doctor_id`);

--
-- Indexes for table `insurance`
--
ALTER TABLE `insurance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `laboratory_tests`
--
ALTER TABLE `laboratory_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checking_form_id` (`checking_form_id`),
  ADD KEY `conducted_by` (`conducted_by`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `card_no` (`card_no`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_patients_card_no` (`card_no`),
  ADD KEY `idx_patients_created_at` (`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checking_form_id` (`checking_form_id`),
  ADD KEY `idx_payments_patient_id` (`patient_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checking_form_id` (`checking_form_id`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `issued_by` (`issued_by`),
  ADD KEY `idx_receipts_payment_id` (`payment_id`),
  ADD KEY `idx_receipts_receipt_number` (`receipt_number`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `checking_forms`
--
ALTER TABLE `checking_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `insurance`
--
ALTER TABLE `insurance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `laboratory_tests`
--
ALTER TABLE `laboratory_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `checking_forms`
--
ALTER TABLE `checking_forms`
  ADD CONSTRAINT `checking_forms_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `checking_forms_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `insurance`
--
ALTER TABLE `insurance`
  ADD CONSTRAINT `insurance_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);

--
-- Constraints for table `laboratory_tests`
--
ALTER TABLE `laboratory_tests`
  ADD CONSTRAINT `laboratory_tests_ibfk_1` FOREIGN KEY (`checking_form_id`) REFERENCES `checking_forms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `laboratory_tests_ibfk_2` FOREIGN KEY (`conducted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`checking_form_id`) REFERENCES `checking_forms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`checking_form_id`) REFERENCES `checking_forms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `receipts_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  ADD CONSTRAINT `refunds_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
