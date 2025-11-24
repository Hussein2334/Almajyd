-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2025 at 12:07 PM
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

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'admin', 'LOGIN', 'User logged into the system', '192.168.1.100', 'Mozilla/5.0...', '2025-11-12 06:47:33');

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
  `status` enum('pending','completed','referred_to_lab') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checking_forms`
--

INSERT INTO `checking_forms` (`id`, `patient_id`, `doctor_id`, `symptoms`, `diagnosis`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(2, 25, 4, 'dyb c', 'hjvhnjdfnbjfg', ' dvadbvjad  gfydhsbau  gydgsfyge a', 'completed', '2025-11-23 11:09:59', '2025-11-23 15:31:32'),
(3, 28, 4, 'nb ncx', 'sfacsdvnj d', 'sdbvdjfcb ', 'completed', '2025-11-23 15:07:14', '2025-11-23 15:32:25'),
(4, 31, 4, 'm mn ', 'fbdbdbgfnb', 'gfnhgfnhmghm,', 'completed', '2025-11-23 21:06:40', '2025-11-23 21:06:40'),
(5, 30, 4, 'mj,', 'htyj', 'yjlk', 'completed', '2025-11-23 21:08:12', '2025-11-23 21:08:12'),
(6, 29, 4, 'ghngm', 'fgjnghdm', 'fgnhgm,', 'completed', '2025-11-23 21:16:07', '2025-11-23 21:16:07'),
(7, 23, 4, 'hnjk', 'ui7yi', 'fuytgiy', 'completed', '2025-11-24 04:41:06', '2025-11-24 04:41:06');

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
  `test_description` text DEFAULT NULL,
  `results` text DEFAULT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `conducted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laboratory_tests`
--

INSERT INTO `laboratory_tests` (`id`, `checking_form_id`, `test_type`, `test_description`, `results`, `status`, `conducted_by`, `created_at`, `updated_at`) VALUES
(13, 6, 'X-Ray', 'bjknbjkl', 'clear ', 'completed', 4, '2025-11-24 04:51:48', '2025-11-24 04:52:04');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `card_no` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `card_no`, `full_name`, `address`, `age`, `weight`, `phone`, `gender`, `created_by`, `created_at`) VALUES
(21, 'PT2025-00001', 'Asha Mohamed Ali', 'Tombondo', 34, 62.50, '0777123456', 'female', 1, '2025-11-21 16:25:30'),
(22, 'PT2025-00002', 'Salum Hassan Omar', 'Kijitoupele', 45, 78.00, '0778234567', 'male', 1, '2025-11-21 16:25:30'),
(23, 'PT2025-00003', 'Mariam Abdalla', 'Fuoni', 22, 55.20, '0773345678', 'female', 1, '2025-11-21 16:25:30'),
(24, 'PT2025-00004', 'Khamis Ali Said', 'Mchangani', 58, 82.50, '0774456789', 'male', 1, '2025-11-21 16:25:30'),
(25, 'PT2025-00005', 'Zainab Juma', 'Kisiwandui', 29, 68.00, '0775567890', 'female', 1, '2025-11-21 16:25:30'),
(26, 'PT2025-00006', 'Omari Rashid', 'Mwera', 37, 75.30, '0776678901', 'male', 1, '2025-11-21 16:25:30'),
(27, 'PT2025-00007', 'Rehema Yusuf', 'Jumbi', 31, 59.80, '0777789012', 'female', 1, '2025-11-21 16:25:30'),
(28, 'PT2025-00008', 'Abdalla Khamis', 'Kidimni', 51, 88.90, '0778890123', 'male', 1, '2025-11-21 16:25:30'),
(29, 'PT2025-00009', 'Halima Said', 'Mchangani', 26, NULL, '0779901234', 'female', 1, '2025-11-21 16:25:30'),
(30, 'PT2025-00010', 'Juma Ali', 'Tombondo', 40, 79.00, NULL, 'male', 1, '2025-11-21 16:25:30'),
(31, 'PT2025-00011', 'Mudathir JUma Kassim', 'Ushirika', 21, 18.00, '066554433', 'male', 6, '2025-11-23 09:17:13');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `checking_form_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `status` enum('pending','paid') DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `patient_id`, `checking_form_id`, `amount`, `payment_type`, `status`, `processed_by`, `created_at`) VALUES
(1, 29, 6, 5000.00, 'cash', 'paid', 8, '2025-11-24 10:30:45'),
(2, 25, 2, 10000.00, 'card', 'paid', 8, '2025-11-24 10:31:37');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `checking_form_id` int(11) NOT NULL,
  `medicine_name` varchar(100) NOT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('pending','dispensed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `checking_form_id`, `medicine_name`, `dosage`, `frequency`, `duration`, `instructions`, `status`, `created_at`) VALUES
(1, 3, 'sdgvdfb', 'fbfbf', 'bvfdnb', 'fbvcn ', 'fnb vn ', 'dispensed', '2025-11-23 15:07:35'),
(2, 4, 'fdbhjnhgm', 'mm,j,', 'k;lk/l', 'lkj;', 'lkj;lkl', 'dispensed', '2025-11-23 21:07:14'),
(3, 2, 'fdbhjnhgm', 'fbfbf', 'fhbnm', 'nghmn', 'ngn', 'dispensed', '2025-11-23 21:11:38'),
(4, 2, 'nbggn', 'nvc', 'n bcv', 'bvc', 'bzn', 'dispensed', '2025-11-24 04:51:06'),
(5, 6, 'nbggn', 'mm,j,', 'n bcv', 'fbvcn ', 'bhhi', 'dispensed', '2025-11-24 05:42:56');

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
  `district` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `permissions` text DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `full_name`, `phone`, `district`, `region`, `created_at`, `status`, `permissions`, `last_login`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@hospital.com', 'admin', 'System Administrator', '+255 658 216 348', 'Mombasa', 'Zanzibar', '2025-11-17 17:27:41', 'active', '{\"all\":[\"full_access\"]}', NULL),
(4, 'hussein', '$2y$10$TVl7mqEx/6gPqrJTXQknoOy7Ppi7D51WXD5n4.8eLIXZglZ7c.gaC', 'husseinali2334@gmail.com', 'doctor', 'ABDALLA ABRAHMANI ABDALLA', '+255775892103', 'mjini', 'Zanzibar', '2025-11-17 22:47:33', 'active', '{\"patients\":[\"view\",\"create\",\"edit\",\"delete\"],\"appointments\":[\"view\",\"create\",\"edit\",\"delete\"],\"prescriptions\":[\"view\",\"create\",\"edit\",\"delete\"],\"lab_tests\":[\"view\",\"create\",\"edit\"],\"medical_records\":[\"view\",\"create\",\"edit\"],\"reports\":[\"view\"]}', NULL),
(5, 'khamis', '$2y$10$gldUu0D/esdCVeks6zZIkugjklDWgAuvpFCTf/5z/MZ0evAbBL6Le', 'husseinabdulrahman2334@gmail.com', 'laboratory', 'HUSSEIN ABDULRAHMAN', '0658216348', 'mjini magharibi', 'magharibi B', '2025-11-18 06:49:39', 'active', NULL, NULL),
(6, 'abuu', '$2y$10$kAvjjhBBfy.WLAyrU53x5.lawN1e5rnv2cKZ46ZCSlap2MNcRQS9G', 'abuu@gmail.com', 'receptionist', 'Abubakar DC', '+255777418200', 'mjini magharibi', 'magharibi B', '2025-11-23 08:19:20', 'active', '[]', NULL),
(7, 'salim', '$2y$10$.GCrijkI0gHnbq5/cWiOYOSsNr0k205Mf4wBGAf0GKSjrbGFVlWnq', 'husseinabdulrahman2334@gmail.com', 'pharmacy', 'HUSSEIN ABDULRAHMAN', '0658216348', 'Tomondo', 'Zanzibar', '2025-11-23 19:53:04', 'active', NULL, NULL),
(8, 'saleh', '$2y$10$Fl.74IM.XwcALS5GvnyJI.m7YE9q3F4641bOA3OZt85Bq0jSdhRAK', 'husseinabdulrahman2334@gmail.com', 'cashier', 'HUSSEIN ABDULRAHMAN', '0658216348', 'Tomondo', 'Zanzibar', '2025-11-24 09:27:00', 'active', NULL, NULL);

--
-- Indexes for dumped tables
--

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
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

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
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `checking_form_id` (`checking_form_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checking_form_id` (`checking_form_id`);

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
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `checking_forms`
--
ALTER TABLE `checking_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `insurance`
--
ALTER TABLE `insurance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `laboratory_tests`
--
ALTER TABLE `laboratory_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `checking_forms`
--
ALTER TABLE `checking_forms`
  ADD CONSTRAINT `checking_forms_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `checking_forms_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `insurance`
--
ALTER TABLE `insurance`
  ADD CONSTRAINT `insurance_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);

--
-- Constraints for table `laboratory_tests`
--
ALTER TABLE `laboratory_tests`
  ADD CONSTRAINT `laboratory_tests_ibfk_1` FOREIGN KEY (`checking_form_id`) REFERENCES `checking_forms` (`id`),
  ADD CONSTRAINT `laboratory_tests_ibfk_2` FOREIGN KEY (`conducted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`checking_form_id`) REFERENCES `checking_forms` (`id`),
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`checking_form_id`) REFERENCES `checking_forms` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
