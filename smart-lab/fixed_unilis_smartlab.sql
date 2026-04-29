-- Fixed SQL schema with proper foreign key constraint ordering
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
-- Table structure for table `labs` (must be created first)
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
-- Indexes for table `labs`
--
ALTER TABLE `labs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lab_code` (`lab_code`);

-- --------------------------------------------------------

--
-- Table structure for table `users` (created after labs)
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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reg_number` (`reg_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_lab` (`lab_id`);

--
-- Constraints for table `users` (added immediately after table creation)
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE SET NULL;

-- Include the rest of the tables from the original schema...
-- (Other tables would follow here with their proper constraints)

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
