-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 05:56 PM
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
(7, 23, 4, 'hnjk', 'ui7yi', 'fuytgiy', 'completed', '2025-11-24 04:41:06', '2025-11-24 04:41:06'),
(8, 33, 4, '.JB H V', 'VGUHB', ' GHVBGBHBN', 'completed', '2025-11-24 15:48:49', '2025-11-24 15:48:49'),
(9, 28, 4, 'KICHWA MIGUU', 'HIII INSABABISHW NA UKOSEFU WA MAJI MWILIN', 'HHFDHBGXCGVVG', 'completed', '2025-11-24 16:06:39', '2025-11-24 16:06:39'),
(10, 28, 4, 'zv c', ' c ', '', 'completed', '2025-11-25 09:50:09', '2025-11-25 09:50:09'),
(11, 28, 4, 'zv c', ' c ', '', 'completed', '2025-11-25 10:12:17', '2025-11-25 10:12:17');

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
(13, 6, 'X-Ray', 'bjknbjkl', 'dvgd g cygdsy f ywgfg  yfgwyfq ', 'completed', 4, '2025-11-24 04:51:48', '2025-11-24 14:24:55'),
(14, 8, 'Blood Test', 'GGV UYGYG GVYGUY', 'FVSGSHHASJVCHGU', 'completed', 4, '2025-11-24 15:50:00', '2025-11-24 15:50:57'),
(15, 9, 'Urine Test', 'BJHFHGCJRGGHGH', 'MCHAFU SANA 10%', 'completed', 4, '2025-11-24 16:09:48', '2025-11-24 16:24:03'),
(16, 11, 'X-Ray', '', '', 'completed', 4, '2025-11-25 10:35:53', '2025-11-25 11:29:10'),
(17, 11, 'Typhoid Test', '', '', 'completed', 4, '2025-11-25 10:35:53', '2025-11-25 11:29:10'),
(18, 6, 'Malaria Test', '', 'hana ', 'completed', 4, '2025-11-25 10:38:03', '2025-11-25 10:57:16'),
(19, 6, 'Pregnancy Test', '', 'anayo ', 'completed', 4, '2025-11-25 10:38:03', '2025-11-25 10:57:32'),
(20, 8, 'X-Ray', '', NULL, 'pending', 4, '2025-11-25 14:44:26', '2025-11-25 14:44:26');

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
(31, 'PT2025-00011', 'Mudathir JUma Kassim', 'Ushirika', 21, 18.00, '066554433', 'male', 6, '2025-11-23 09:17:13'),
(33, 'PT2025-00012', 'HUSSEIN ABDULRAHMAN', 'Ushirika', 23, 77.00, '0658216348', 'male', 6, '2025-11-24 15:44:57');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `checking_form_id` int(11) NOT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `lab_test_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `status` enum('pending','paid') DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `patient_id`, `checking_form_id`, `prescription_id`, `lab_test_id`, `amount`, `payment_type`, `status`, `processed_by`, `created_at`) VALUES
(1, 29, 6, NULL, NULL, 5000.00, 'cash', 'paid', 8, '2025-11-24 10:30:45'),
(2, 25, 2, NULL, NULL, 10000.00, 'card', 'paid', 8, '2025-11-24 10:31:37'),
(3, 33, 8, NULL, NULL, 10000.00, 'cash', 'paid', 8, '2025-11-24 16:00:59'),
(4, 28, 9, NULL, NULL, 20000.00, 'cash', 'pending', 8, '2025-11-24 16:53:05'),
(5, 31, 4, NULL, NULL, 3000.00, 'cash', 'paid', 8, '2025-11-24 16:53:11'),
(6, 28, 3, NULL, NULL, 5000.00, 'cash', 'paid', 8, '2025-11-24 16:53:16'),
(7, 29, 6, NULL, NULL, 1000.00, 'medicine', 'pending', 7, '2025-11-25 12:51:58'),
(8, 29, 6, NULL, NULL, 1000.00, 'medicine', 'pending', 7, '2025-11-25 12:53:17'),
(9, 29, 6, NULL, NULL, 1000.00, 'medicine', 'pending', 7, '2025-11-25 13:01:26'),
(10, 29, 6, NULL, NULL, 1000.00, 'medicine', 'pending', 7, '2025-11-25 13:01:29'),
(11, 33, 8, NULL, NULL, 2000.00, 'medicine', 'pending', 7, '2025-11-25 13:50:07'),
(12, 33, 8, NULL, NULL, 500.00, 'medicine', 'pending', 7, '2025-11-25 13:51:44'),
(13, 30, 5, NULL, NULL, 1000.00, 'medicine', 'pending', 7, '2025-11-25 14:00:20'),
(14, 30, 5, NULL, NULL, 0.00, 'medicine', 'pending', 7, '2025-11-25 14:00:23'),
(17, 33, 8, NULL, NULL, 100.00, 'medicine_and_lab', 'pending', 7, '2025-11-25 16:02:55'),
(18, 30, 5, NULL, NULL, 300.00, 'medicine_and_lab', 'pending', 7, '2025-11-25 16:27:38'),
(19, 29, 6, NULL, NULL, 100.00, 'medicine_and_lab', 'pending', 7, '2025-11-25 16:35:41');

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
(2, 4, 'fdbhjnhgm', 'mm,j,', 'k;lk/l', 'lkj;', 'lkj;lkl', 'pending', '2025-11-23 21:07:14'),
(3, 2, 'fdbhjnhgm', 'fbfbf', 'fhbnm', 'nghmn', 'ngn', 'pending', '2025-11-23 21:11:38'),
(4, 2, 'nbggn', 'nvc', 'n bcv', 'bvc', 'bzn', 'pending', '2025-11-24 04:51:06'),
(5, 6, 'nbggn', 'mm,j,', 'n bcv', 'fbvcn ', 'bhhi', '', '2025-11-24 05:42:56'),
(6, 8, 'GGGHFT', 'FDTR', 'RTDTYG', 'FDFTY', 'B BN ', 'dispensed', '2025-11-24 15:52:12'),
(7, 9, 'PANADOL', '200%', '3/3', '5', 'KIJIKO KIMOJA', 'dispensed', '2025-11-24 16:26:18'),
(8, 9, 'EXTENAL', '300%', '1/3', '6', '', 'dispensed', '2025-11-24 16:28:51'),
(9, 8, 'fdbhjnhgm', '', '', '', '', '', '2025-11-25 09:49:01'),
(10, 8, 'jnjdbn', 'fbn', 'bdba', 'ngfn', 'nfnsf', '', '2025-11-25 10:14:49'),
(11, 8, 'rhgf', 'ngj nn', 'fv jfuibg', ' bxchb', 'ffrnh', '', '2025-11-25 10:14:49'),
(12, 8, 'nbfgm', 'gmnhgm', 'bgnh', 'gnghm', 'hbgfmh,', '', '2025-11-25 10:14:49'),
(13, 6, 'dawa ya kichwa ', '500mg', 'three per day', '7 days', '', '', '2025-11-25 10:37:36'),
(14, 6, 'panadol', 'three per day ', 'twice ', '12 days', '', '', '2025-11-25 10:37:36'),
(15, 6, 'dawa ya kichwa ', '500mg', 'bdba', '7 days', '', '', '2025-11-25 11:28:15'),
(16, 6, 'dawa ya kichwa ', '500mg', 'three per day', '7 days', '', '', '2025-11-25 11:28:15'),
(17, 6, 'PANADOL', '200%', '3/3', '5', 'utanunua nje ', '', '2025-11-25 12:57:56'),
(18, 6, 'mfano', '500gm', '3 times', '7 day', 'hii utanunua nje ', 'dispensed', '2025-11-25 12:59:48'),
(19, 8, 'PANADOL', '200%', '3/3', '5', ' p\'oo\'\'', '', '2025-11-25 13:51:34'),
(20, 5, 'dawa ya kichwa ', '500mg', 'bdba', '7 days', '', 'dispensed', '2025-11-25 13:57:56'),
(21, 5, 'mgongo', '500mg', 'bdba', '7 days', '', '', '2025-11-25 13:57:56'),
(22, 5, 'fgfgfgfgfgfgfgfgfgfgfg', 'gh', 'fhjj', 'jghj', '', 'dispensed', '2025-11-25 13:59:57'),
(23, 8, 'dawa ', '500mg', 'three per day', '7 days', '', '', '2025-11-25 14:45:12'),
(24, 8, 'dawa ya mguu', '500mg', 'bdba', '7 days', '', '', '2025-11-25 15:21:24');

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
(5, 'khamis', '$2y$10$gldUu0D/esdCVeks6zZIkugjklDWgAuvpFCTf/5z/MZ0evAbBL6Le', '', 'laboratory', '', '', 'mjini magharibi', 'magharibi B', '2025-11-18 06:49:39', 'active', NULL, NULL),
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
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_prescription_id` (`prescription_id`),
  ADD KEY `idx_lab_test_id` (`lab_test_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `insurance`
--
ALTER TABLE `insurance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `laboratory_tests`
--
ALTER TABLE `laboratory_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

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
  ADD CONSTRAINT `fk_payments_lab_tests` FOREIGN KEY (`lab_test_id`) REFERENCES `laboratory_tests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payments_prescriptions` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL,
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
