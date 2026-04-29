-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 28, 2026 at 10:47 AM
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
-- Database: `unilis_smartlab`
--

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `id` varchar(36) NOT NULL DEFAULT (uuid()),
  `document_type` enum('notebook','report') NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `reviewer_id` varchar(36) NOT NULL,
  `action` enum('approved','rejected','revision_requested') NOT NULL,
  `comments` text DEFAULT NULL,
  `signature_hash` varchar(255) DEFAULT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` varchar(36) NOT NULL DEFAULT (uuid()),
  `asset_code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `type` enum('equipment','chemical','consumable','instrument') NOT NULL,
  `lab_id` varchar(36) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit` varchar(30) DEFAULT NULL,
  `status` enum('available','in_use','maintenance','disposed','in_transit') DEFAULT 'available',
  `serial_number` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `min_quantity` int(11) DEFAULT 5,
  `warranty_expiry` date DEFAULT NULL,
  `safety_notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_transactions`
--

CREATE TABLE `asset_transactions` (
  `id` varchar(36) NOT NULL DEFAULT (uuid()),
  `asset_id` varchar(36) NOT NULL,
  `action` enum('registered','issued','returned','transferred','disposed','usage_logged') NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `lab_id` varchar(36) DEFAULT NULL,
  `target_lab_id` varchar(36) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `action` varchar(200) NOT NULL,
  `module` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `module`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, '1448f570d86fe4e421d2c9b77e261a9e', 'user_registered', 'auth', '::1', NULL, '2026-03-25 12:29:50'),
(2, 'a041a1c61798188a5effa1581da0e959', 'user_registered', 'auth', '::1', NULL, '2026-03-25 14:35:34'),
(3, '1448f570d86fe4e421d2c9b77e261a9e', 'login_password', 'auth', '::1', NULL, '2026-03-25 14:36:32'),
(4, '1448f570d86fe4e421d2c9b77e261a9e', 'logout', 'auth', '::1', NULL, '2026-03-25 15:28:58'),
(5, 'c03095e3f4c590c75599f91c8279cd64', 'user_registered', 'auth', '::1', NULL, '2026-03-25 15:31:06'),
(6, 'c03095e3f4c590c75599f91c8279cd64', 'logout', 'auth', '::1', NULL, '2026-03-25 15:37:34'),
(7, '71bf048cda937785152023a19f9e2ef2', 'user_registered', 'auth', '::1', NULL, '2026-03-25 15:38:49'),
(8, '71bf048cda937785152023a19f9e2ef2', 'logout', 'auth', '::1', NULL, '2026-03-25 15:52:02'),
(9, '1448f570d86fe4e421d2c9b77e261a9e', 'login_password', 'auth', '::1', NULL, '2026-03-27 05:37:16'),
(10, '1448f570d86fe4e421d2c9b77e261a9e', 'logout', 'auth', '::1', NULL, '2026-03-27 07:24:44'),
(11, '1448f570d86fe4e421d2c9b77e261a9e', 'login_password', 'auth', '::1', NULL, '2026-03-27 07:25:51'),
(12, '1448f570d86fe4e421d2c9b77e261a9e', 'logout', 'auth', '::1', NULL, '2026-03-27 07:52:54'),
(13, '1448f570d86fe4e421d2c9b77e261a9e', 'login_password', 'auth', '::1', NULL, '2026-03-27 07:58:32'),
(14, '1448f570d86fe4e421d2c9b77e261a9e', 'logout', 'auth', '::1', NULL, '2026-03-27 10:52:07'),
(15, '1448f570d86fe4e421d2c9b77e261a9e', 'login_password', 'auth', '::1', NULL, '2026-03-27 11:14:08'),
(16, 'a041a1c61798188a5effa1581da0e959', 'login_password', 'auth', '::1', NULL, '2026-04-15 10:11:01'),
(17, 'a041a1c61798188a5effa1581da0e959', 'logout', 'auth', '::1', NULL, '2026-04-21 08:33:43');

-- --------------------------------------------------------

--
-- Table structure for table `blockchain_blocks`
--

CREATE TABLE `blockchain_blocks` (
  `id` int(11) NOT NULL,
  `block_index` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `block_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`block_data`)),
  `previous_hash` varchar(64) NOT NULL,
  `hash` varchar(64) NOT NULL,
  `nonce` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blockchain_blocks`
--

INSERT INTO `blockchain_blocks` (`id`, `block_index`, `timestamp`, `block_data`, `previous_hash`, `hash`, `nonce`, `created_at`) VALUES
(1, 0, '2026-03-25 16:01:48', '{\"event\":\"Genesis\",\"system\":\"UNILIS SmartLab\"}', '0', 'c648605732ac1d81ff62ea9e3482b3d0d92d32180ec35595ecc50fd27691f64e', 0, '2026-03-25 15:01:48');

-- --------------------------------------------------------

--
-- Table structure for table `labs`
--

CREATE TABLE `labs` (
  `id` char(36) NOT NULL DEFAULT (uuid()),
  `name` varchar(150) NOT NULL,
  `lab_code` varchar(20) NOT NULL,
  `type` enum('physics','chemistry','engineering','clinical','computer','general') NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `room_number` varchar(30) DEFAULT NULL,
  `max_capacity` int(11) DEFAULT 30,
  `current_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `labs`
--

INSERT INTO `labs` (`id`, `name`, `lab_code`, `type`, `building`, `room_number`, `max_capacity`, `current_count`, `is_active`, `created_at`) VALUES
('lab-chem-001', 'Chemistry Laboratory B', 'CHEM-B', 'chemistry', 'Science Block', '205', 25, 0, 1, '2026-03-25 08:14:29'),
('lab-clin-001', 'Clinical Skills Lab', 'CLIN-A', 'clinical', 'Health Sciences', '301', 15, 0, 1, '2026-03-25 08:14:29'),
('lab-eng-001', 'Engineering Workshop', 'ENG-W', 'engineering', 'Engineering Block', 'G01', 20, 0, 1, '2026-03-25 08:14:29'),
('lab-phy-001', 'Physics Laboratory A', 'PHY-A', 'physics', 'Science Block', '101', 30, 0, 1, '2026-03-25 08:14:29');

-- --------------------------------------------------------

--
-- Table structure for table `lab_requests`
--

CREATE TABLE `lab_requests` (
  `id` varchar(36) NOT NULL DEFAULT (uuid()),
  `requester_id` varchar(36) NOT NULL,
  `requesting_lab` varchar(36) NOT NULL,
  `target_lab` varchar(36) DEFAULT NULL,
  `asset_id` varchar(36) DEFAULT NULL,
  `asset_name` varchar(200) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','fulfilled') DEFAULT 'pending',
  `approved_by` varchar(36) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_sessions`
--

CREATE TABLE `lab_sessions` (
  `id` char(36) NOT NULL DEFAULT (uuid()),
  `practical_id` char(36) NOT NULL,
  `lab_id` char(36) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('open','closed') DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_session_students`
--

CREATE TABLE `lab_session_students` (
  `id` int(11) NOT NULL,
  `session_id` char(36) NOT NULL,
  `student_id` char(36) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enrolled','attended','absent') DEFAULT 'enrolled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notebooks`
--

CREATE TABLE `notebooks` (
  `id` char(36) NOT NULL DEFAULT (uuid()),
  `session_id` char(36) NOT NULL,
  `student_id` char(36) NOT NULL,
  `group_id` char(36) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `version` int(11) DEFAULT 1,
  `status` enum('draft','submitted','approved','rejected') DEFAULT 'draft',
  `tech_signature` varchar(255) DEFAULT NULL,
  `approved_by` char(36) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` char(36) DEFAULT NULL,
  `creator_role` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notebook_versions`
--

CREATE TABLE `notebook_versions` (
  `id` varchar(36) NOT NULL DEFAULT (uuid()),
  `notebook_id` varchar(36) NOT NULL,
  `version` int(11) NOT NULL,
  `content` longtext DEFAULT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `practicals`
--

CREATE TABLE `practicals` (
  `id` char(36) NOT NULL DEFAULT (uuid()),
  `title` varchar(200) NOT NULL,
  `lab_id` char(36) NOT NULL,
  `lecturer_id` char(36) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  `course_code` varchar(30) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `max_students` int(11) DEFAULT 30,
  `required_equipment` text DEFAULT NULL,
  `required_chemicals` text DEFAULT NULL,
  `safety_notes` text DEFAULT NULL,
  `status` enum('draft','published','ongoing','completed') DEFAULT 'draft',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `practicals`
--

INSERT INTO `practicals` (`id`, `title`, `lab_id`, `lecturer_id`, `created_at`, `description`, `course_code`, `scheduled_date`, `start_time`, `end_time`, `max_students`, `required_equipment`, `required_chemicals`, `safety_notes`, `status`, `updated_at`) VALUES
('prac1', 'Physics Experiment 1', 'lab-chem-001', 'lect1', '2026-03-25 14:53:58', 'Introduction to basic physics measurements', 'PHY101', '2026-03-26', '09:00:00', '11:00:00', 25, 'Measuring tapes, scales, timers', 'None', 'Wear safety glasses at all times', 'published', '2026-03-25 14:53:58'),
('prac2', 'Chemistry Lab 1', 'lab-chem-001', 'lect1', '2026-03-25 14:53:58', 'Basic chemical reactions and observations', 'CHEM101', '2026-03-27', '14:00:00', '16:00:00', 20, 'Test tubes, beakers, Bunsen burners', 'HCl, NaOH, indicator solutions', 'Use fume hood, wear gloves and goggles', 'published', '2026-03-25 14:53:58');

-- --------------------------------------------------------

--
-- Table structure for table `practical_requests`
--

CREATE TABLE `practical_requests` (
  `id` char(36) NOT NULL DEFAULT (uuid()),
  `student_id` char(36) NOT NULL,
  `practical_id` char(36) NOT NULL,
  `reason` text NOT NULL,
  `preferred_lab` char(36) DEFAULT NULL,
  `urgency` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` varchar(36) NOT NULL DEFAULT (uuid()),
  `notebook_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `practical_id` varchar(36) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `submission_notes` text DEFAULT NULL,
  `grade` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` varchar(36) DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','submitted','graded','returned') DEFAULT 'draft',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_deadlines`
--

CREATE TABLE `report_deadlines` (
  `id` char(36) NOT NULL DEFAULT (uuid()),
  `practical_id` char(36) NOT NULL,
  `student_id` char(36) NOT NULL,
  `deadline_date` datetime NOT NULL,
  `extended` tinyint(1) DEFAULT 0,
  `extended_until` datetime DEFAULT NULL,
  `created_by` char(36) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `label`, `created_at`) VALUES
(1, 'admin', 'Administrator', '2026-03-25 08:14:29'),
(2, 'lecturer', 'Lecturer', '2026-03-25 08:14:29'),
(3, 'technician', 'Lab Technician', '2026-03-25 08:14:29'),
(4, 'student', 'Student', '2026-03-25 08:14:29');

-- --------------------------------------------------------

--
-- Table structure for table `student_practicals`
--

CREATE TABLE `student_practicals` (
  `id` int(11) NOT NULL,
  `student_id` char(36) NOT NULL,
  `practical_id` char(36) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enrolled','completed','dropped') DEFAULT 'enrolled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_sessions`
--

CREATE TABLE `student_sessions` (
  `id` varchar(36) NOT NULL DEFAULT (uuid()),
  `session_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `auth_method` enum('biometric','qr_code','confirmation_code','manual') NOT NULL,
  `checked_in_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `checked_out_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` char(36) NOT NULL DEFAULT (uuid()),
  `reg_number` varchar(50) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','lecturer','technician','admin') NOT NULL DEFAULT 'student',
  `lab_id` char(36) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `biometric_hash` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `reg_number`, `full_name`, `email`, `password`, `role`, `lab_id`, `department`, `biometric_hash`, `is_active`, `created_at`, `updated_at`) VALUES
('1448f570d86fe4e421d2c9b77e261a9e', 'ADMIN00320', 'john kamau', 'man12@unilis.com', '$2y$12$FRruWQDGhtG22/R1XEVwZudKd15ZTc5EyZc07/2dV5C5xF.tEjTzu', 'student', NULL, 'computing', NULL, 1, '2026-03-25 12:29:50', '2026-03-25 12:29:50'),
('469473c92a38adc70a1a6f268ff5c513', 'ADMIN0032', 'john kamau', 'man@unilis.com', '$2y$12$hD7ZsrdtRgNiOpzX4aWrxuWAQI5w57fQCg85TcEMpbiTrKoz4KRJK', 'student', NULL, 'computing', NULL, 1, '2026-03-25 12:23:16', '2026-03-25 12:23:16'),
('71bf048cda937785152023a19f9e2ef2', 'kamau1234', 'kamau john', 'kamau12@gmail.com', '$2y$12$BwfHeniV1cT4zDcizt65X.9sml4nsR0hfflyHRaUUQjKqzhjJaQoG', 'lecturer', 'lab-eng-001', 'computing', NULL, 1, '2026-03-25 15:38:49', '2026-03-25 15:38:49'),
('a041a1c61798188a5effa1581da0e959', 'ADMIN00324', 'mwendi', 'mwendikim@gmail.com', '$2y$12$sDnaYxz7e0KA4iDGUEkKTuI7kzIOgWhTaisJXkE.70XoWNfrvBFzC', 'student', 'lab-clin-001', 'computing', NULL, 1, '2026-03-25 14:35:34', '2026-03-25 14:35:34'),
('c03095e3f4c590c75599f91c8279cd64', 'mwangi1234', 'mwangi', 'mwangi@gmail.com', '$2y$12$mGvXNV6AHwd9OBCDz2QAWOXadJkFgNrXidzvW2wbdB4f4HLcuNlQe', 'admin', 'lab-clin-001', 'computing', NULL, 1, '2026-03-25 15:31:06', '2026-03-25 15:31:06'),
('lect1', 'LEC001', 'Dr. Smith', 'smith@unilis.edu', 'password', 'lecturer', 'lab-chem-001', NULL, NULL, 1, '2026-03-25 14:53:58', '2026-03-25 14:53:58'),
('usr-admin-001', 'ADMIN001', 'System Administrator', 'admin@unilis.ac.ke', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uRpFa.Be47u', 'admin', NULL, NULL, NULL, 1, '2026-03-25 08:14:29', '2026-03-25 08:14:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviewer_id` (`reviewer_id`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_code` (`asset_code`),
  ADD KEY `lab_id` (`lab_id`);

--
-- Indexes for table `asset_transactions`
--
ALTER TABLE `asset_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `blockchain_blocks`
--
ALTER TABLE `blockchain_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hash` (`hash`);

--
-- Indexes for table `labs`
--
ALTER TABLE `labs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lab_code` (`lab_code`);

--
-- Indexes for table `lab_requests`
--
ALTER TABLE `lab_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requester_id` (`requester_id`),
  ADD KEY `requesting_lab` (`requesting_lab`);

--
-- Indexes for table `lab_sessions`
--
ALTER TABLE `lab_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sess_prac` (`practical_id`),
  ADD KEY `fk_sess_lab` (`lab_id`);

--
-- Indexes for table `lab_session_students`
--
ALTER TABLE `lab_session_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`session_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `notebooks`
--
ALTER TABLE `notebooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nb_session` (`session_id`),
  ADD KEY `idx_nb_student` (`student_id`);

--
-- Indexes for table `notebook_versions`
--
ALTER TABLE `notebook_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notebook_id` (`notebook_id`);

--
-- Indexes for table `practicals`
--
ALTER TABLE `practicals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_prac_lab` (`lab_id`),
  ADD KEY `fk_prac_lecturer` (`lecturer_id`);

--
-- Indexes for table `practical_requests`
--
ALTER TABLE `practical_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `preferred_lab` (`preferred_lab`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_practical_id` (`practical_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notebook_id` (`notebook_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `practical_id` (`practical_id`);

--
-- Indexes for table `report_deadlines`
--
ALTER TABLE `report_deadlines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_practical_student` (`practical_id`,`student_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_deadline_date` (`deadline_date`),
  ADD KEY `idx_status` (`extended`,`deadline_date`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `student_practicals`
--
ALTER TABLE `student_practicals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`practical_id`),
  ADD KEY `practical_id` (`practical_id`);

--
-- Indexes for table `student_sessions`
--
ALTER TABLE `student_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reg_number` (`reg_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_lab` (`lab_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `blockchain_blocks`
--
ALTER TABLE `blockchain_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lab_session_students`
--
ALTER TABLE `lab_session_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `student_practicals`
--
ALTER TABLE `student_practicals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approvals`
--
ALTER TABLE `approvals`
  ADD CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`);

--
-- Constraints for table `asset_transactions`
--
ALTER TABLE `asset_transactions`
  ADD CONSTRAINT `asset_transactions_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `asset_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `lab_requests`
--
ALTER TABLE `lab_requests`
  ADD CONSTRAINT `lab_requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `lab_requests_ibfk_2` FOREIGN KEY (`requesting_lab`) REFERENCES `labs` (`id`);

--
-- Constraints for table `lab_sessions`
--
ALTER TABLE `lab_sessions`
  ADD CONSTRAINT `fk_sess_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sess_prac` FOREIGN KEY (`practical_id`) REFERENCES `practicals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_session_students`
--
ALTER TABLE `lab_session_students`
  ADD CONSTRAINT `lab_session_students_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `lab_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_session_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notebooks`
--
ALTER TABLE `notebooks`
  ADD CONSTRAINT `fk_nb_session_id` FOREIGN KEY (`session_id`) REFERENCES `lab_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_nb_student_id` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notebook_versions`
--
ALTER TABLE `notebook_versions`
  ADD CONSTRAINT `notebook_versions_ibfk_1` FOREIGN KEY (`notebook_id`) REFERENCES `notebooks` (`id`);

--
-- Constraints for table `practicals`
--
ALTER TABLE `practicals`
  ADD CONSTRAINT `fk_prac_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prac_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `practical_requests`
--
ALTER TABLE `practical_requests`
  ADD CONSTRAINT `practical_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `practical_requests_ibfk_2` FOREIGN KEY (`practical_id`) REFERENCES `practicals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `practical_requests_ibfk_3` FOREIGN KEY (`preferred_lab`) REFERENCES `labs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`notebook_id`) REFERENCES `notebooks` (`id`),
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`practical_id`) REFERENCES `practicals` (`id`);

--
-- Constraints for table `report_deadlines`
--
ALTER TABLE `report_deadlines`
  ADD CONSTRAINT `report_deadlines_ibfk_1` FOREIGN KEY (`practical_id`) REFERENCES `practicals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `report_deadlines_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `report_deadlines_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_practicals`
--
ALTER TABLE `student_practicals`
  ADD CONSTRAINT `student_practicals_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_practicals_ibfk_2` FOREIGN KEY (`practical_id`) REFERENCES `practicals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_sessions`
--
ALTER TABLE `student_sessions`
  ADD CONSTRAINT `student_sessions_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `lab_sessions` (`id`),
  ADD CONSTRAINT `student_sessions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
