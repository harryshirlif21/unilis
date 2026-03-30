-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Mar 23, 2026 at 07:04 AM
-- Server version: 8.0.33
-- PHP Version: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `unilis`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `password`) VALUES
(1, 'Super Admin', 'admin@unilis.com', '$2y$10$eN/UP8DRvYqZnn8Qrdj8GOMa3bB4grcUG8.9NC9vTFRhdJS90Xa8q');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `unit_id` int DEFAULT NULL,
  `team_id` int DEFAULT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_global` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int NOT NULL,
  `unit_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `module_id` int DEFAULT NULL,
  `lesson_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('quiz','assignment','cat','exam') COLLATE utf8mb4_unicode_ci NOT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `time_limit_mins` int DEFAULT NULL,
  `total_marks` int NOT NULL DEFAULT '0',
  `pass_mark` int NOT NULL DEFAULT '0',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `due_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `unit_id`, `lecturer_id`, `module_id`, `lesson_id`, `title`, `type`, `instructions`, `time_limit_mins`, `total_marks`, `pass_mark`, `is_published`, `due_date`, `created_at`) VALUES
(1, 5, 1, NULL, NULL, 'module1 quiz', 'quiz', 'do all questions', NULL, 7, 0, 1, '2026-03-31 13:15:00', '2026-03-16 13:15:54');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_questions`
--

CREATE TABLE `assessment_questions` (
  `id` int NOT NULL,
  `assessment_id` int NOT NULL,
  `question_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_type` enum('mcq','true_false','matching','short_answer','essay','file_upload') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'short_answer',
  `marks` int NOT NULL DEFAULT '1',
  `position` int NOT NULL DEFAULT '0',
  `auto_grade` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_questions`
--

INSERT INTO `assessment_questions` (`id`, `assessment_id`, `question_text`, `question_type`, `marks`, `position`, `auto_grade`, `created_at`) VALUES
(1, 1, 'this is a try', 'mcq', 1, 0, 1, '2026-03-16 21:51:46'),
(2, 1, 'what is a computer', 'short_answer', 3, 2, 0, '2026-03-16 21:55:07'),
(3, 1, 'draw a db schema', 'file_upload', 1, 3, 0, '2026-03-16 21:55:07'),
(4, 1, 'is a computer an electronic machine', 'true_false', 1, 1, 1, '2026-03-16 21:55:08'),
(5, 1, 'whats a distributed system', 'mcq', 1, 4, 1, '2026-03-16 22:08:12');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_submissions`
--

CREATE TABLE `assessment_submissions` (
  `id` int NOT NULL,
  `assessment_id` int NOT NULL,
  `student_id` int NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `score` decimal(8,2) DEFAULT NULL,
  `graded_by` int DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  `status` enum('submitted','graded','flagged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `violations_json` longtext COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_submissions`
--

INSERT INTO `assessment_submissions` (`id`, `assessment_id`, `student_id`, `submitted_at`, `score`, `graded_by`, `graded_at`, `status`, `violations_json`) VALUES
(4, 1, 3, '2026-03-23 09:12:11', NULL, NULL, NULL, 'submitted', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_weights`
--

CREATE TABLE `assessment_weights` (
  `id` int NOT NULL,
  `unit_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `assessment_type` varchar(32) NOT NULL,
  `weight_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int NOT NULL,
  `unit_id` int DEFAULT NULL,
  `lecturer_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `deadline` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `file_path` varchar(255) DEFAULT NULL,
  `mode` enum('text','speech','hybrid') DEFAULT 'text',
  `rubric` text,
  `voice_instructions` mediumblob
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `unit_id`, `lecturer_id`, `title`, `description`, `deadline`, `created_at`, `file_path`, `mode`, `rubric`, `voice_instructions`) VALUES
(1, 2, 1, 'Assignment 1', 'Intro topic', '2025-07-20 23:59:00', '2025-07-09 09:50:16', '1752054616_Functions-SS-notes2.pdf', 'text', NULL, NULL),
(2, 2, 1, 'Assignment 2', 'Advanced topic', '2025-07-21 23:59:00', '2025-07-10 08:21:51', NULL, 'text', NULL, NULL),
(3, 2, 1, 'River Mapping Task', 'Project PPT review', '2025-07-25 23:59:00', '2025-07-14 06:56:59', '1752476219_Kisii_River_Project_Educational_PPT.pptx', 'text', NULL, NULL),
(4, 4, 1, 'assignment 1', 'submit on time', '2025-08-09 06:18:00', '2025-07-23 03:19:01', '1753240741_en_FLB.pdf', 'text', NULL, NULL),
(5, 5, 1, 'bloack chain', 'do all quizes', '2025-08-08 09:56:00', '2025-07-23 06:56:24', '1753253784_kaitheri_timetables.pdf', 'text', NULL, NULL),
(6, 5, 1, 'assignment 2', 'submit on time', '2025-07-23 12:24:00', '2025-07-23 09:19:26', '1753262366_Data_Repository_and_Unilis_System_Presentation.pptx', 'text', NULL, NULL),
(7, 5, 1, 'try', 'attempt all questions', '2025-09-04 17:19:00', '2025-09-04 10:15:22', NULL, 'text', '', ''),
(8, 5, 1, 'trin', 'submit', '2025-09-19 12:03:00', '2025-09-12 09:03:47', '1757667827_assignment_report (1).pdf', 'text', NULL, NULL),
(9, 5, 1, 'try', 'hgyubhnm', '2025-10-16 10:50:00', '2025-10-01 07:50:35', NULL, 'text', NULL, NULL),
(10, 98, 1, 'aiu', 'describe how ai would be effective and efficient in modular programming today', '2025-10-08 00:00:00', '2025-10-06 09:43:45', NULL, 'text', NULL, NULL),
(11, 5, 1, ' distributed ledgers assignment 1', 'do all questions', '2025-11-26 00:33:00', '2025-11-19 01:34:37', '1763516077_INDABA POSTER.pdf', 'text', NULL, NULL),
(12, 5, NULL, 'distributed systems assignment 2', 'submit before deadline', '2025-12-05 00:00:00', '2025-11-27 07:21:13', '1764228073_BCT 2404 Course Outline.pdf', 'text', NULL, NULL),
(13, 5, 1, 'dbms', 'try', '2025-12-06 10:37:00', '2025-11-27 07:38:02', '1764229082_BCT 2404 Course Outline.pdf', 'text', NULL, NULL),
(14, 5, 1, 'dbms', 'try', '2025-12-06 10:37:00', '2025-11-27 07:46:35', '1764229595_BCT 2404 Course Outline.pdf', 'text', NULL, NULL),
(15, 5, 1, 'dbms', 'try', '2025-12-06 10:37:00', '2025-11-27 07:46:40', '1764229600_BCT 2404 Course Outline.pdf', 'text', NULL, NULL),
(16, 5, 1, 'dbms', 'try', '2025-12-06 10:37:00', '2025-11-27 07:46:45', '1764229605_BCT 2404 Course Outline.pdf', 'text', NULL, NULL),
(17, 5, 1, 'dbms', 'try', '2025-12-06 10:37:00', '2025-11-27 07:48:55', '1764229735_BCT 2404 Course Outline.pdf', 'text', NULL, NULL),
(18, 5, 1, 'dbms', 'try', '2025-12-04 10:50:00', '2025-11-27 07:51:02', '1764229862_BCT 2404 Course Outline.pdf', 'text', NULL, NULL),
(19, 5, NULL, 'dbms', 'try', '2025-12-04 10:50:00', '2025-11-27 07:55:55', '1764230155_BCT 2404 Course Outline.pdf', 'text', NULL, NULL),
(20, 5, 1, 'try', 'make you do all assignments', '2025-11-28 11:01:00', '2025-11-27 08:01:59', NULL, 'text', NULL, NULL),
(21, 5, 1, 'try', 'make you do all assignments', '2025-11-28 11:01:00', '2025-11-27 08:11:23', NULL, 'text', NULL, NULL),
(22, 5, 1, 'dbms', 'try', '2025-11-28 11:12:00', '2025-11-27 08:12:26', NULL, 'text', NULL, NULL),
(23, 5, 1, 'dbms', 'dbms', '2025-11-28 11:36:00', '2025-11-27 08:36:03', NULL, 'text', NULL, NULL),
(24, 5, 1, 'knowledge based', 'do all questions\r\n', '2026-02-26 19:02:00', '2026-02-12 16:02:37', '1770912157_submitted_assignments (1).pdf', 'text', NULL, NULL),
(25, 5, 1, 'knowledge based', 'do all questions\r\n', '2026-02-26 19:02:00', '2026-02-12 16:03:04', '1770912184_submitted_assignments (1).pdf', 'text', NULL, NULL),
(26, 98, 1, 'assignment 1', 'do all questions', '2026-02-27 19:38:00', '2026-02-20 16:39:12', NULL, 'text', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int NOT NULL,
  `session_id` int NOT NULL,
  `student_id` int NOT NULL,
  `attended` tinyint(1) DEFAULT '0',
  `attended_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_sessions`
--

CREATE TABLE `attendance_sessions` (
  `id` int NOT NULL,
  `unit_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `session_code` varchar(6) NOT NULL,
  `duration_minutes` int NOT NULL,
  `deadline` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `attendance_sessions`
--

INSERT INTO `attendance_sessions` (`id`, `unit_id`, `lecturer_id`, `session_code`, `duration_minutes`, `deadline`, `created_at`) VALUES
(1, 5, 1, '585095', 10, '2025-11-28 06:02:00', '2025-11-28 08:52:00'),
(2, 5, 1, '854230', 10, '2025-11-28 06:05:59', '2025-11-28 08:55:59'),
(3, 5, 1, '147002', 10, '2025-11-28 08:12:52', '2025-11-28 11:02:52'),
(4, 5, 1, '622223', 10, '2025-11-28 08:17:07', '2025-11-28 11:07:07'),
(5, 5, 1, '064167', 10, '2025-11-28 08:21:40', '2025-11-28 11:11:40'),
(6, 5, 1, '643094', 10, '2025-11-28 08:25:52', '2025-11-28 11:15:52'),
(7, 5, 1, '946781', 10, '2025-11-28 08:31:04', '2025-11-28 11:21:04'),
(8, 5, 1, '341237', 10, '2025-11-28 08:31:09', '2025-11-28 11:21:09'),
(9, 5, 1, '309887', 10, '2025-11-28 08:31:14', '2025-11-28 11:21:14'),
(10, 5, 1, '312289', 10, '2025-11-28 08:31:32', '2025-11-28 11:21:32'),
(11, 5, 1, '539251', 10, '2025-11-28 08:33:18', '2025-11-28 11:23:18'),
(12, 5, 1, '109395', 10, '2025-11-28 09:14:31', '2025-11-28 12:04:31'),
(13, 5, 1, '393035', 10, '2025-11-28 11:34:18', '2025-11-28 14:24:18'),
(14, 5, 1, '841024', 10, '2025-11-28 11:58:34', '2025-11-28 14:48:34'),
(15, 5, 1, '522365', 10, '2025-11-28 12:03:22', '2025-11-28 14:53:22'),
(16, 5, 1, '468991', 10, '2025-11-28 12:09:46', '2025-11-28 14:59:46'),
(17, 5, 1, '163542', 10, '2025-11-28 12:09:55', '2025-11-28 14:59:55'),
(18, 5, 1, '515403', 10, '2025-11-28 12:17:20', '2025-11-28 15:07:20'),
(19, 5, 1, '148305', 10, '2025-11-28 12:34:32', '2025-11-28 15:24:32'),
(20, 5, 1, '670488', 10, '2025-11-28 12:52:18', '2025-11-28 15:42:18'),
(21, 5, 1, '767590', 10, '2025-11-28 12:52:23', '2025-11-28 15:42:23'),
(22, 5, 1, '572996', 10, '2025-11-28 12:56:50', '2025-11-28 15:46:50'),
(23, 5, 1, '021333', 10, '2025-11-28 13:04:21', '2025-11-28 15:54:21'),
(24, 5, 1, '024487', 10, '2025-11-28 13:04:25', '2025-11-28 15:54:25'),
(25, 5, 1, '101922', 10, '2025-11-28 13:04:36', '2025-11-28 15:54:36'),
(26, 5, 1, '702841', 10, '2025-11-28 13:05:20', '2025-11-28 15:55:20'),
(27, 5, 1, '654068', 10, '2025-11-28 13:05:28', '2025-11-28 15:55:28'),
(28, 5, 1, '366487', 10, '2025-11-28 13:05:38', '2025-11-28 15:55:38'),
(29, 5, 1, '735618', 10, '2025-11-28 13:05:50', '2025-11-28 15:55:50'),
(30, 5, 1, '568159', 10, '2025-11-28 13:05:56', '2025-11-28 15:55:56'),
(31, 5, 1, '408902', 10, '2025-11-28 13:06:00', '2025-11-28 15:56:00'),
(32, 5, 1, '636119', 10, '2025-11-28 13:21:03', '2025-11-28 16:11:03');

-- --------------------------------------------------------

--
-- Table structure for table `chat`
--

CREATE TABLE `chat` (
  `id` int NOT NULL,
  `meeting_id` int NOT NULL,
  `user_id` int NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classnotes`
--

CREATE TABLE `classnotes` (
  `id` int NOT NULL,
  `unit_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtopics_json` longtext NOT NULL,
  `description` text,
  `file_path` varchar(255) DEFAULT NULL,
  `media_type` enum('pdf','ppt','excel','video','other','image') DEFAULT 'other',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `classnotes`
--

INSERT INTO `classnotes` (`id`, `unit_id`, `lecturer_id`, `title`, `subtopics_json`, `description`, `file_path`, `media_type`, `uploaded_at`) VALUES
(1, 5, 1, 'block chain', '[{\"id\":1763657069160,\"title\":\"whats block chain\",\"content\":\"<img data-placeholder=\\\"img_1763657092521\\\" src=\\\"..\\/uploads\\/images\\/691f457fd98ca_download.jpg\\\"jpeg;base64,\\/9j\\/4AAQSkZJRgABAQAAAQABAAD\\/2wCEAAkGBxAQEBUQDxAQDxUPFRUQFQ8OEBUQEBAQFRUWFhUVFRUYHSggGBslHRgVITEhJSkrMDowFx8zODMtNygtLi0BCgoKDg0OGxAQGy0lHyUrLzIrLS8tLTUtLSstLyszLi0tLSstLS0tLy0wNS0tLS0tKy8tLS0rLTUtLS0tListLf\\/AABEIAK4BIgMBIgACEQEDEQH\\/xAAbAAACAwEBAQAAAAAAAAAAAAADBQIEBgEAB\\/\\/EAEgQAAIBAwMCBAQCBgUHDQEAAAECAwAEEQUSIRMxIkFRYQYUMnEjgRUkQlJykTNDYoKhVIOSorGy0TQ1U1VjZHR1k6Ozw\\/AW\\/8QAGQEAAwEBAQAAAAAAAAAAAAAAAQIDBAAF\\/8QALREAAgIBAwEGBQUBAAAAAAAAAAECESEDEjFBBFFhocHwEzJxgdEikbHh8RT\\/2gAMAwEAAhEDEQA\\/APkdSFcxU1FemiZIURaiBU1FEDJrRBUVFT7d6ZCMkKkKYx\\/D16y71tLggjIxC25l9VXG4j3AxVACuVMVnhUwK8BUgKdIRs9U4YmdlRBlnYIozjLMcAfzIqIFGtZmikSRBlonWRQexZWDD\\/ECmoRs1Ol20eWjt92Y5o7RZIbW3ubi6mdZmZwbhwsUf4LbVUjgjdk5NVdVtopMquOp8ut7HMIUtmlj6fVkjlijJQOE3sGX9wg5yCGUED2jytELgAXCTxyW1tFffLsI7iN7e4QyARyKJ9vOeUJHkaqau+xzcOssZ+V+VhjuI1gnld4jDJMYVZunGFaQjnBO0DPOIR+bA7eDK16pYr2K00Q3Ea9Uqe6VYBQvEPWliluQ1ypeC2tYldjI0YVuo7bHIBVgAFOCWBVZOkGLszwcdsj7ZruK1D3V1s6rapG0RJTMsV9JaswGSnTa1MZOOduOxqnr+mCMuQqRvBJ0LiKEsYVdgSksW7kI21htPYj0YAKpZoYRVyp4rhFNQVIEagHB7EH7HNajRbNEhMxYLIY5LhZDCtyYYIpEiykTsqmRpGYbmPhERxy2RbeRzhZ7q5vFktpL4W97BG0ckKRySYWQXDvbuRGwBQAg4yMcVJyKIxZqBphqtosUzIjF0wjo7DDNFKiyxlscbtjrnHnmqLjiiNZZtdLkkTql4oI8lRNcOURnABKoAC8hAPOxTjzxkVC60p1jMqSQ3Ea43yWzs3S3HC9RHVZEBPAYqBnjOTitc2lSzPNHbxRyC3awgieaK3kWO0MEzysnzA2kszxStjkmQHsatTaU8M6FYIelPfx26NFFbL1NOuFaOSOQwDlSGUZfzAIqLmUSPmpFQNGKjyO4eTfvD1\\/OhkU5yYE1A0VhUCKDGBmuVIiomlAcNczXa4aASYNerwr1ccFxU1FRxUxVDiaiiKKiooiiihGSAp78LwDc83G6IwpHkA7JZ5ljEuDwSi72GQRu2HypIBTHSL0Qsd4ZklXY4jIEigMrpJGTxvR0Rhng7SDgE1zWBbyN9Q063WO5u+hcyC2ujbtI96DI7jd+Iz\\/Lnnwp3OfH7V34stgGZss7Q3E9k0shBknWHY0UkhAG6Ta5Vm89ik8k0xXVyYXDXOnyO77xeyQbJ0TIypthbEMT4s\\/Vy3DcZpbfXMF0BGJnjdXkk690oEV3NMVMkr9PJgY7VUDxLtRcsvJKxu8glVCECpAUe7s5IWCyoUJG4ZwVdf3kYZV1\\/tKSKPaaXLIvU8EcZJUTTyLDGWHcKXI3keYUEir2qsg2UgKa2A+XjF0cdRiRbKRnDKcNcEeiHIX1fJ\\/YIJbTQiSzvJFJDCplla0mSWQRr3VU+oEnA3FdozknANUL+7aaQyMAvZVRfoijUYSNB+6owP8AE8k0fmwvuI5bcgYpGU7kZlbtuVirc9+RzTC8PVtopSctAzWrknLFWLTQsfc5nX7RiltNfh+NpTNbKrP14mKhBuImh\\/FjP57Wj\\/ztPKNK+4ip26FVepjLoF4o3Naz4HJIjY7R\\/awPD+dLqeNPhkpSkuUerWaZEjTpK8scUU9h8uJJHjTa3yxs32iRlVisiFiu4HDA+dKI7FIFEl2CzMAUswSrsDyHmI5jT0H1N5bQQ1dOtvJlblRPEe0SnpdDAwPlyARFxgYwVOOQTg1OcXL5ff0KQmo\\/N7+poptKtGtVsv0jaBUcz9UR23VLMCpBf53aRgjjGfCtUfiJgWvpwyMl08SRmORJQW6glOWQldyrHyATjqL60oIsBz+uP\\/2eIY\\/y6uW\\/nsqxdOt3EOigha0VsWqElHgyWaVNxJMg\\/bzyQoYcKQJrTaduyvxU1Sq\\/qI8VzFSr1XcScdQeaNdM0YhjWF5VV7dYLjKpdW80iydNWDriRZASBuG4PgZIwW+rSOyp+qmyVYGtZLrURMOkjoUdbRGnYsu1nCxhWPI+4z2ieAyXP+SxllP\\/AHiT8OH8wWMn+aNKViA7AD7Cs707kaY6mCxqtysszOilEwiIjHLLFEixRhseexFz75qkRRSKiRTbaHUhhDqSNGsc5uIzGoRLi1YF+mPpSSJmUSBewYMpAwOQBg2oXa2jywRSXE8qNLbtc3DbFj2lo3MEQZsMRuG9myATgAnISy8An0FX\\/iZf166\\/8Vc\\/\\/M9ScFZRSE5WhstWCKhsJIABYkgAKCSSTgAAck+1FjJlZhQ2FP5tHii8M80xkGQ0dnai6WFx3R5WljUsOxCbgCCN1U9T0sxKJEcTROdglCNGySAZMcsbcxvjkDJBHKk4OEtDikioEUZhQ2Fcwg65UjXKVgOgV2vCu0DgwqQrgFTWqHMIooiirOm6a8wZtyRIhUNNLu2KzZ2oAoLO5wcIoJ4J7AkNG+Hs4EMxdydqxz20toZWPZYmcbGY+SkqT5Aniu3JCsTCpgVEDyIIxwQRgg+hHlRFFOibJAVICuCpCqIlJjj4cnZpUtnIeB2LSQyDemxVLSOg7xybQ2GUg5xzirbzQyW\\/zlxA8zPO1tHDHMYY4IY4o3SOMBTwN5H5Z7kkqdJuxDOkrAsqN41HdomBWRR7lSwH3rTWd7aWUK29zBJdAubiObwmGTIVBLCQykAqiZByQQQcEGkmqeF76+gqeMs7PpkVvcTwpFLBNaQPdR3CXD\\/WsauMI8YOPFtP2NZ7WUXcksaqi3Maz9NBhUfc8ciqPJepG5A8gQPKtYnxBaXBaOO0uGmuEaAyR4MpjYYYbpJGCjHcngAE8Ug1G5s8pF05pRbRiATQ3SRpIQzO7KrQscF3kwc9sdqOluvKdk9ba1hoWabZmaVY9wQHJaRvpjjVS7uR5hVDHHtWon04G2i\\/XIbC3uAzRwOJWlmVGKmS4aNCHcnnBOBnAApdYm36Vz8utwsny54meORdnWh6mCqqc7N\\/l2zV\\/X7WSSx07pxu+IZc7EZsfjHvgU823JLjPpZKCSi+uPWinFoduhDpqlshHKusdypGOMqRHTa+tmjUTFre5uHha4gu1VsSxxseqWjcAGZACwcg5CtnLBTRfi2NJbOzige4ne1XptG1pIgUMi7iCY1zhlA7nv8Ac1KSNlOkqwK4jcOGBBEfVbqZB\\/sbqluckm33+V\\/TkpSi2ku7zr68GFcliWYlixLFmOWZjySSe5qO2pgcV3Fb6POsFtqcErxuroxRkIZWXgqw5BBruK4RQaCpDaXT450F0rR2sZbZMpB2xzcHECDlw4O4IPpwQSq7SahaxXA2Xknq\\/Vhg\\/wDb6cn8t9XNYAFytsP6O0YW4XyLq2Jnx6s4Y\\/baPIVs9Rvrnr3wt49MSPTsttmt0DsnOAvHJ4PpyQPOskpOKX5r6G2MU2\\/xeepi5II3tSlm7SbZGuJo5UCT7ETbGVAJEioDMSQc+PJUAE0irUfGM5W5triIJBI9pa3JMCCMCZlJLBRx6fypRrkKrKHRQiTolwqL9KdRQWQeyvvUeyinhwvEMpU\\/oLSK4RUjRLGJZJo4i2BLIkZIPIDMFJH86LQ8JWE+RiWMPdSmFZRujijUSXEqZxvCsyqicHDMecHCsKsajcWN1NJIs8ls88kkv6yEkgDSOW2l4zuQZPfY3v608vtanhs5poZXt2fUpYz0jsIiS3QRxfwqAAB7VfuNZmj1Cwii1EzpM1osyxXoulaVplWZWwBgEEeH3P2rK5P3\\/hqVHz68tXido5F2MnBGQRyAQQQcEEEEEcEEEcGm3wnpk7TxTrEQg39OeTEcRuDG4t9jvgO3VMWAuTnFWtQ1B44kaNYg6T3VsszxJJKsMLRSRqhcEJg3DjIGcBQCAKz13cSSv1JZJJX\\/AOkldnfH8ROa7LQ6as2OkwnoRfr9rABYvai3k1ARPDctJIyzFEDgYDA+TA99uKWa+GCXO6eK4UWlhCZop1nWW8SWIAgglt2xLv6gDjdxil0mqRSkvdWqzSHkzRTNbvKfNpQAysx82AUnucnmqOoX7SqsaokEUZLJBDu2BmwC7MxLO5AA3MTwMDA4pFB2PuFbChsKOwobCnCgJFRNEIqBpTjor1dFcoALFSWo1NacLNZoRIk0gD6Tdhmx53Hzkatn3EQtvyb3phf3CvpTfrFxM4itTcC4Nz+HcdVixBmO3sQMpx4M+dZbTNSMQKMnVjZlk6e9onSZPomhlXmKQdtwyCO4OBhrJ8Q8cG9nYEOv6SvzdwxyDs4hCKruPIvkdsqaVxdgtUV\\/iPPzTlxh2WFpR2\\/WGgiafPv1DJn3zVBaazILzdNEMXHikmgXtP3Z5oR+93Zo\\/uy8ZCqlNVhxRGRMVIVwVIVVEZMPZ2rzSJFHjdIwQZ4AJOMsfIDuT6A0zvNccMUgbNumI44Zo0ljKJwJDHIpUO3Lk4zlzQbL8K3kn\\/am3WsXsCoNw4+yMsf+ePpSymjFSeSGpJxWB\\/bahJPb3EPgjIVZwLeGK3EkcZIljcRqu8bWEniz\\/RGkYFNPh4CNzdSHEduRuAGTMXDDoAH99RICfJQx5OAXVxo0C9RGjgBtwxkW3vzJdoEOHJVl2OR5gBfuK7dHTk0TcJakU7M5pt0YZVk2hwMq0Z4EkbqUdCfLKswz71orqTULJdsFzdLbrgja5Bg3+LZMgP4UnPKnGTkiqVyUssCAmSSRVkW7K7QkbjIMC87X8i55BBAAI3FZbXMkbb4pHjbnxxuytz38QOabbvzWPHr+CW74a2t58On5Ntq\\/xpHLCsVi+pxzgqFd5y\\/V\\/eDKHOSe42gc+1JL+7mQM13K81zIhhVJH3tawuCHL5+l2UlQncBmJwdtUn127YYNxKAeDtYpuH9orjP50vAoafZ1H36navaXLr6eRHbXdtSxXcVpMlg9tcI9KJiuYrqCmMtXT9d6o+m5cXSH+xK+4j+625D7oa3d499b3F4kem\\/MpeTORI\\/BZSm3C+oxuP5msBa3i7OjOpkjzuUoQssLHG4xk8EHAyh4OPI81YJU\\/TqTKPSVblJB\\/dQOv+tWPU0m6T6eD9DdpaqVtdfFeob44tXSW1iZWDpY2sZTHiEgUgrj1zxiq+ragsTrALe2lNtGkBlkEkhLqMyADfsIEhcA7ew86g99HCS0DSTzHj5qYbenxjMSZJ3ejscjyUHBCQinhp4V9AT1aba6l79Nyg5VLWP+Cytsj+8Yy3+Ndf4gvSMC6nT2hkMI\\/kmBS0irNjYSTZ6ajCAF5HZY44weAXdiFX2yefLNGUILNIOnqTbqx9a6nduWk0+d42nbqz2cRUSGcjxyQowO9W74XJXOCMAEuNZ+INRl2NDFc6UsYIklvJmETdsNuljB3DnhMk54FY2XS4iCpvbTJ4xtuiuf4hBj8+3vRvieBvmZJ9oMc8sjRTKVkjkTccYdSRkDGVzkZ5ArK9OLkvx7s3Rm6KmrXKuUjiLNHACqu4w8sjsWlmYdwWbsCThVQHkGlrCikVBh\\/jx+Zp6odMCwobCrd1aSR46sUkW7t1Y2Td9tw5qswpCiAOKEwqwwoLClY6AsKGRRmFDNBjHBXqkBXqAAtTWoVJaY5hhRVoS0VaZCMLE5UhlJVlIZWUlWVgchlI5BB5yKc7BeZaNQtyMl4UAVbvHJkhUcCXzaMcN3XnK0lWmfw9Asl1CrfT1A7YOCUj\\/EYAjsSFIzRfFkwi28MCK90WZpAHS2jYRnY3KvLIQdgYchQCxBByoIJ4NVt\\/8AI7TGfKW63Y\\/i6\\/f8se1Ovh\\/VZ\\/l9SvBIVnYWz9ZfqBlugH2k9gQcY9MU2S+1D9EnUPmrnf1+mH6ibOhwn07c56nFDc0899ciNJ\\/4Zy9MdzEr2uVFrHta1Zt7JHuLPMjjHUBZiW4BGRwVGQoVSxCqCxbgKoySfQDzrT67qkoXT7t26kohd2Z8ZlC3Eo2v6grlSPQmqms6jLbzzW9uRbRxyPEBbL0meNWIXfIPG+RjO5jVtJvhe8mXWS5ZObS51sXR4zG8UnzBhYqJjC0exnMWd4CELyQOJM+RxpdTv4jHcqHtiGa7lSSO8Ejym5clVEA7HGztzx71gLaZo2DxsyMpyroSrKfUEdqYrrlwDuUxRsc\\/ixW0EU3PciREDA+4OaMtKTJx14pE9ZTZ0YT9dvFskH7kjyyylD7qJACPIhh5VQUVEVNa0wVIxzlbskKkBXBUhTEmexXa8BUqItkMVwip1wiuOsGRUSKKRUDQGTBMKgRRWFQNBlEzlvbtI6xoMtIyooPmzEBR\\/MitHHb20qXAZpha6cEKJbFFe4keRYjOxYEFmzntwu1RwOVGhSql3A7kBUmiZieyqHGT+Xf8q0fwh8PTSQ3lu6vGJdkHVSPrBJoJld0ZFO7PFZdeSWW+7+c+Rt7Om+F3\\/wAY8xcNNsTbm7Eeo9FZBAW61ru6pUMBt25xgjy8\\/vULlLe2WB0M7Wt+jmaGco0iiOVououwAb1xuU4z3B4YitiPhSUWh0\\/qR7Wbq9X9GTfMZDg539THkF7dqzfxvoU1vBaRBJJFgSSHqtH0hJLLM8ioqElicGs8dSMnV9fI2bHFXXTzEVz8NXqOyfK3L7GZOolvKY3CkjcjbcFTjIPoRTLTrI2sbdRZ4JjBLdsUxDcxwpKsKRo7q3T3N1GZgMkBAMAnKj4nVGvJsBW2v092M7jEojY588lSc+9WNLvfwhEkkUEkayRo1xHG9vNbysHeGTqKyqQ4LKzDHiYErgZMlJxQ8WkxgMw7QfnGFzZy34juLkT20saQyzBJY2gXdnpY3KcjeCrZFZjWbVIp3SMkp4JIy31dKWNJYw3qQrqCfUGtRqF5vVA62ViiwtDK9udOu7q4R1KOIBBEGiLKXXuqYbuMHOV1S7M0rylQm8jainIjjVQkaA+e1VVc+1JFMrZRYUFhR2FCcUzCgJFDYUVhQyKA6PAV6ugV2gE8KmtDFTWiBhloooKUVaZCMKtX9IvOhPHMQWEbqzKO7JnDqPcruH50vWiLTLJNmy0S8s9OM0Nys9wJgnAhgntbi3BEkEoEjKcnv5jPuMC8PiTR92f0cuMf9W2m\\/P36vbFZG01HCCKaMTxqSVUsUkhLcsYpADtyeSpDKTztzzV\\/5Wy6HX\\/XCOp0eluhB3bN4PU24x352eXakcFduxdzWENdWu7PUHiWFZ4Ut1O8GGGG3gswxeVlWMsd5LYAyMlgO5rM6jdGaaSYjaZpHlK5zt3sWx+WcUa61HcnSijWCLIYxqSzSMOzSyHlyMnA4UZOFHNUjWnSjtMeu7O5r6RJ8G6aLmOx696s88YkVtsTQ8qzYIChv2W\\/4182PavvVz86LuMrFbizMCCa5kZUkUbTuAcMHH7OOMc1LtU5RqnXPod2XTjK7V8HzB\\/h1EsJ7lpGMlvdm0wuOkwXblu2e5Pn6UfQ9AtzatfX8ssUO\\/oxpAFMsz9zgsCAOD\\/ontjl9p+kfNaZd29hh1\\/SDmLc4UGFVj2nLd+MUWf4cuJNMTTvwxdWspueh1UzLC5lAZTnHdj3\\/d9xQ+PinKv1fdI7\\/nV2o3+n7NiHVPhVf1aSxkaeG+bpxtKArpIDgq+B7NyB+y35k1nStMtRJCbi6luIlIyiILfrAfScjIGeDgmnbXqaZHp9rOytJBO1xMIzv6KP1FwceeJc\\/wBw+1K\\/if4QuTLPdxdOa3cyXQmWVMbGy5GM5J7gYznijDVbklKVLNPvz+BdTSSi3CNvFruwSPwapmt0SRxHLbLdzyvtxCndsHGB6DP37A1mLxY+qy2+903bYzJjqOOwJAA7+nvivpl3qERFtYXHgjvbKFesp2ukwHgyfNc+R4yfQmvnd5aSWV105V8UDq3HZ1BDKyn0Ix\\/On7PqSle7msePj6Eu1aUI1tWLz4eHqP7zQNOs9sV9cXJnKh3W1VDHFnsCWBz\\/APuBkUo1HR0isLa7DOXuWkVlONi7GYDbxnyrTfFHw3Nf3HztiY54rlUJbqKpiZUCEMCeOFHvnIxxXjrctnpNkYliYu06kTRiTGJH7c8UkdWVRadtvK+zx4Dy0o3JSjUUsPvys+P9mZ+IdFS3jtHjZ3N3Ak5U4OHYKdq4Hbmj\\/GXwv8gsLK5kEilZCcYS4UAsox5c8Z54NbG4iF1dadPNtCxWgvZcDai7QrDA8hvK8egNULu6ttRsryO3a5aSNzqAW6EeQed6xBD9IUEYP7w70I9oncb46\\/dtL9h5dmhUq5fy\\/ZW\\/3FcHwbA1zaQGWULd23zLtlco+wtheMbePOqOnfCBN7cWlyzobeCSdXjwBIFKbGGQfCQ3+GPI1s7D\\/nDTP\\/L\\/AP6zVX4L1mO6hdZsC5s7aaFXPea1YA8+pUqn+39o1N62qk30r1eSy0NLcl4+iwfKDTKXpXYBkdIp1AUvNxFchRhSz\\/1cmBglvCcZJBzuWmoGvQlGzz4Sov8A\\/wDNXPfoAjvvEkRix69QNtx75q1YWK26PPE8M9zCAwjgO8WyYO643AbZWTjhCQuQ5J2kBERRIZ2jYSIxRkO5XU4ZSPMGpTjJrLNOnOK4QHHFRJra29rAZZQwitpLeHr3M\\/y63UccweNGjgt2OxSC43E5wwYKFAFSmWKSKV\\/mhe9CEXBgutMW23wl0TMc6sHT6xgqcfes71fD378TZGBhiKE1MtTtFTY8RZop1Lxl8bxglXjfHG9WBBI4I2txuwFxo3ZRAmobU4i0obBJPK0XUUOkUUJubh4z2kKblVEPOCzgnGQCOaFd6ThDJBIZljAaRJITb3ESkhd5jJYMmSoLIzYLDdtyMo2iiQmahmitQzQHR4V2vCvVwwMGiLQqmprgB0NW7K1kmcRxLvYgnGQoCqMszMxCqoGSWJAHmaoqa1PwkVUIxK4e\\/wBPhm3gbRal5JCrZ\\/YZo1Jzx+Etc3SEopLp0edovrItyNu6dFJ9BM8Ii\\/MuF96rzwPG7RyKyOh2sjjDKR5EVt7S3v8A5eJnYGY3QEin5baLTYO7Z2k7gTgc4b2pZrunPclHt1SQ5uIwscsQc28V1KkBWPcGZQoKAqCMRAeVCOpkWUcGbBp9b2EpsiGEcXUnhljNxPFbdWPpTq7p1WXcuTHyPX2NAsdOkg6k9zbSILZA6x3ELIkk7OscYYOBuUFi5HYiPHnWi0pjJHZDoafPPqD3ZkutUiMpJhYbcuCCBtyMfbtTyn3e+pPaZS70+aEK0iYV\\/pkRllic+iyxkox9gar1rNVvUjitZWt7aNLg3UV1DZR9OCeOGZUVlGT41GWV+4PPbIrNX9qYZpIWO4wyPEWAwG2MV3Aehxn86tpTb5M2tCuAAqSgego1lZSTMVjAO0bmZmCRxp5s7sQFX3J8wO9XRZWo4e9BPn0Ld5Ix\\/ecoT+QNX3ox\\/DkUMA+QP3FTUDyAq3PphCGWKSO4jX6mi3Box2BkjYBlHluwVzxmqYNNGSfBGUXHkKtSFDU1IGnJMIK6P5VAGu5oitE8VyuZr2aADhA9B\\/KuMK8TXDXDIiVHpUGqZNWdJtllnRJDhOXcjg9KNTJJj32q1LJpKykE26PQacNglnkEEbZ2eHqSy4yD04wRkAjG5iq5yM5GK6y2HbfeKf3+lEw\\/9PeP96tDfagkdnbXTWlpNJdvcBvmEd1jSFkSKOMK67VVcDHsPeoXt2I7SG7+T0dhcMy9FYpjKm0kZYdXzwf8PXjL8ST5766G5acVxXF9TMX2nGNRKjrNEx2iaPIAbk7HVgCjYGcEYODgsBmhad\\/TxeBpMSI3TRS7uFYEgKOTwDWtmng6VjObeGFL9ri3uooAyRPEksao2GYkMmd4Oc5A8qz19qM6F4F2WyozRvFagxqxUlSHfO+QcH62auU5SVe+4bYouxpFA6S6sj53LBODkHkm6hIbHoQQfsab6trFtdrcNbG5Ih00wH5pVBCrc25TbtPPBbJPoKzWla+0WMvNA6J0UurTaZRCMYikjYhZVGBjxKQABkgABhqutOqCO4vb28SeNJ\\/l+mlrE6ud4Ekgd27g5CjnnxDvWecHu9+\\/M2RkqExjiFnCJ5Joy8txMghgSfMTCCLJ3TR7QXhkAxn6T286bW1mQf1u5Xj9qwQf7ty1Dvrt5nLvgHAUKg2pGijCoi\\/sqBwB\\/PJJNVGp6feMjdAEzXAa7trULqCz7ZbxLfr2SKwjRQMnaYjEBuXGCO+MUOUDrRZu7a6jNxfTusN4tyILCW3XfG4OGwESUcLt7diQKyqakhRY7mH5gRjZHIspgnjTuE37WVkHOAynGcAgcUO51JQjRW8It1kAEjGQzTyqCDsaUgAJkKdqqoJUZzgYjsZVMUc4Ge+Bn71A0V6EadhR0VyuivVwSvmpqaHmpKaCCHU1f0y\\/MJbKrLHKvTlhfISWPIbGRyrAgMrDkEDyyCtU0VTRAzSpqlsIBb51B4Q3U+SeaFYepnJJlVMnnPIRT7ilt9eNO+9wo4VFjQYjjjUYVEUk4UD7nuSSSSaKmiKaKSQjHehyl0ksy+0XCARhmxGLlHV4xycLuAZM+sgzwK1Gh3kkMFq8F1YW09n85FJDqEnTkTqzIR+GRnOFPfHmKwArQ6FqhluIIrpYrlHeODdcRq8qIzBBiXh9q5HBJGBgChKNoQca9ALhbaNrm1lKNeXF3PZP1III551kLZxwT4sL5tgDuKU32p2txK8sttLCZHeQtbz5xuJPMcqtk\\/ZlHpjtS+71GaRem+2JVOehDGsMQkHBJVANzDkbmyfeqjDII9RiqQjXJGbs3NvYWyxXUTrNLHp8cUzori3e4uZWAJkI346YOwKMgEOf2jVbTra0nimmi02dktFEkpOoqCqnPYGLnhWPHp9qvWSrK+pR9WGI6hFDcQm4kESOsjiUgMf3dxXHqpHlVv4e0021tcQOmlXJuAQ0x1KNGiQrtGB0z2JLZyOT7VPfSec46\\/S+45wtrGM9F40KbD5V7e4urSGa1lshE6l7gXCSCSQRsjoY1ypUkEeefSkmsQIkgaMbY50WdFznYr\\/UmfPawdM\\/2afw6Z8lYXolubNzcJAiJb3KSuzLMGPhHPbn8jS2\\/tIisETXAhlhgRWSaJhFmV5JwOou4hgJQCCoAx39L6ckpNri\\/HuXr\\/Jl1YNxSaz9lm36CYGpA07sdFCANMIpWcSPGnXBgMUYJeaSSIklM5UKpBJVskbcGy9nGynrRWsaiMS9S0MyzJEWCiVUk8MqZPI7nnDCrfHimZ\\/+eTRnQa7mpXts0MjRPjdGSpKnKn0KnzBHIPoRQg1WUrVmdxadBM17NchRnYIilmYhQqjLMx4AAHc0xazgj4nnJcd4rVFl2H0aVmVc\\/wAO8e9BzSDHTbF2a4TTD5W2kOIrh428lu41RCfTqozAH+IKPU1QuoHido5FKMhwVbuD3\\/ljBz6Ggpp4C9NrIMmruhOouFDnasgkgLHgIJonh3H2G\\/P5VQJqJNdJWqDB00zWkWktlBa3N4LKWykuQ8cltLKcu4PdBgY2kU81i26mmwxTX0KWsfT6U40q7UnapVWLEkeIE84Gc8ViDdw3AAuWaKRQFFyi9QSKBhRMmc5AAHUXJx3UnmnGp394U+XudVgMbImY9szExsqyJhRACOCpwce+KxTg7Wet\\/wCYZ6EJqnjpX+5OX620qWFlb3IuRbvcPNMInhVIpHSRmIf91Ecn+GsxqFz1ZpJcY60jy4PlvYtj\\/GrU95HHG0NsHxJxJcSgLLKoIOxVBIjjyASMknAyceGltVjGjrs4av6scx2retsV\\/NLm5T\\/YFqvZ2jTPsTaOCzO52xxxqMs7t5KB\\/wABkkAtrmK2aOKEm9zAHUTrZjpsHkL\\/ANG0gbaCTzwefp8qSckmi8EZ00NquahZtCwBKuHUOkiZKSRkkBlyARyGBBAIKkEAiqZpW7KogaG1TNDalHQN6EaI1DNKOjor1cFerhqKtSBqFSFKAKpoimgg0RTTBDqaKpqspoqmihGHU0RXYcqcMOQfRhyD\\/OgqaefD1krFZJFV+pPDZwo+emZ5Ty8oBBZEGCVGMl1B4yCboSjnxDt+bmKkESP11x5RzgTJ\\/qyLQbCyeYkJtAQbnlkOyKJScbpH8h6DknsATxWitZJJ+luuOrHNJ8rGk+mWwgLoqAJhJQ0SgMoDJgjBA7Uo1u53LCIgI7eSNZ4rdc4jfLRSb2PMkgeOQb25Ix2BAHQm+BZR6liS5tGjS2YylYt2y9K+IFjll6HfoZywGd4LM3mUp1oGuXljCYbR9Lkjcl2d54lZy3HjWWSN8Y4wVFYkGpg0+xNUyTbTtD1bOK3X5lzDcnqFFhg8dtHLtDr1mP1LjJCLkNsILeEgp55md2d2LM5Lszd2Zjkk\\/c1a0m6RGaOYkQzjpyYBJTnKSgebI2G9xuX9o0C7tnhkaKQANGdpwcg+hU+akYIPmCDVYOnkzakLWDRaPPF0kmkErrbwzWs0cKB3WOV5HWTl1wh6rru5wUAP1CrY1C1uVaG3S56hg+WRnjiEcdujK+ZCsg2qCOXbOByc9qyFvO8bB4neNl7PGxRh9iORVq71m5mXZLNIynkpuwjEcgso4Y+5pXpW79\\/2BatKmvfoF1q5SSd2jJKDZGrHgukSLGrYPbIUH86pZoQNdzWiKpUZJZdjm0fo2zTLw87tbI3mkaorTkehIeNc+jOPOmd5PHa29rstLWZp4TKzTpI7l+q64G1xxgDjFJgN9lx3tp2Zh6RzpGqt9g0OPu6+ta6E6c0UCz3Nu72qdNJI7meHK72cEobY4Pi9fKs2pKndN5zXkatKNqsLCr1B3qJb38FnJaWMgmeAM\\/ys8LbZXCsFDS+XIB57fcUj1ZA8cyjvYTGJCTk\\/Ks7qqknk7GVcZ8pSPIVqnvNOkmjuZrhJJIGVkM1\\/KxHTfeo4tu2ece9Zu5uPl1uZ45YZfmpVjVlTqRN4jNMAsyeIL+ECSv8AWCp6UnjDvH8\\/grqQVPKrP7V+TNE1EmmX6WRsdW0tX9WjV7dvyETKn+qaPp0FjPIEK3Vv9TsRJHOvTjRpH7ohXwq37351rc6y0ZFpp8MX2umTzKzxp4V4MrssUQb0MjkKD7Zph8UWcnWlnCh4d+xJ4nSaLYuEjy8ZIUkBcA4NMdN1EPBcXD29rMYpLWCCG5Vmt7aKUzZVAGGPpXLZ5wSckk1c1WRreK5Ig0+Ga0ltoxJp6N0pY50lZ45N7HqIQFBUgDv34rPLVlv9+H9GuOlHZ76WYeosad31hbIyytKY450SeO2hRpp1Vx4kLNtRQrh1DFicLnBqt+lRH\\/yWFICP66TFxc\\/cSMNqH3jRT702++BowrkZfDtrJFG80kbRpvtZN7qV320dwjzMgPLKMRsSARhPamdzazvZTSJecm7LLdi7f5dYipAiMn7LZYHb2+msat7KsvXEj9UHPVZt7k4wdxbO4EEgg5BBIOQaufpiPbg2cPJ3bRLcrbls5yYBLjyHAIHtjioTg27NEWqosfFUwfLg7hNe6hPEc97V5IwrAful1lx7q1ZxqsXt28rl5Dk4CjACqqKMKqKoAVQOAAAKqmilSoYiaG1TahMa5jog1DNSY1A0o6Oiu1wV6uCVTXhXK7SIBIGiKaEKkDRRwZTRFNAU0QGmOZYU1oPhy7GFiO3fFOl5AHcRLJMg2mIyNwjHETKWGCY9p+oVm1aig1zyhT6LBqtyCEGjPEIm6i9RejDBJzmXfJCEj\\/iOB781lNYmj3JFAd0dtH0lYElXYyPK7KSASu6RlBPJVVJwTilO4lQpJKryq58Kn2HYVIGujFRYsshwakDQQamDVUyTQVcngAkngADJJ8gB5mtna2MboyubPqacscM11etKYVLs4jhRI+JCmGTe3HhAAIAas38MsBe2xbGOvFyewbeu0n2zimHw8g+SvFmYxjq6esjMpYxjrSh2K8kkcnHtS6j9PNgURjeWEHQeZ3sLhIiiO+mCSG4i6pIRgrARuMqfCQCcHxDvWa1C0MMhQkOMK6SLkLJG4DI658iCOPI5B5BrZ\\/E97bTx6hLaSLLG403LJCYQriSdSu0gE8BTn3x5VmNUgcWtpI0cgHSkHVKMI9puZii78Yzyxxnsw9RXaM3194sTV00xXivVwGrNnYyS5KKNqY3ySOsUSZ7bpHIUE+Qzk4OAa1b65Mr0r4PWF60D712sCCjo4ykkZ+pHA7g\\/zBAIwQDTTUdJgWTYlykD4Be3uRITC5AJj6yIVYrnByFweO4Nds7JrZHu8xTmDaIxbyJcLHK+ds0u0nYqY43AZcp3GaRlieSSSeSSckk9yT5mk3bpWmHZtjTQyFjbx8zXSSY\\/q7NXd29t8iqiD38X8JqtqN8ZmGFEaRjZHCmSsaZJxk8sSSSWPJJJ9hVr2aZc2wOOKSo5V3R7hI51aXIRg8UhAyViljaJ2A8yFcnHtVMc8AZzwAOST6CmMmlrGSs91BA47xETSuh9JDEjKp9RkkeYBoTkqpjQ06djz4ft2hS4haa0gmjnsriP5yZY4JREZmDIx+tDlCMdwwq3rtzNdQSmWaxuLi7ktEjg06YSOwiE5O5M5BG8DPbjyxSbU7toEhhCwXMKxZSSRBNHK7HdMYpBh0CsdmwFT4QWUFqWTas5VkjSG2VwVcW0e1nU91aRi0hU+a7sHzFZtrk9xpWFtO63KpkWNGV1t40txIhysjLlpGU+amRpMHzGDS4mvE1Emq8I5I8TUGNdJqBNK2USOE0MmpE1BjSFERY0JjU2NCY0GMiJqDVI0MmgOiQNeqIr1A4r12vFa9tqZx2uivba8FpkAkDRFNDAqQFMghgamrUICpgUQNB1NTBoK0QUbFaCg1MGhAVMCmEaCA1qNM+IgOo2+KCWcL1jcWwubW5dCSsrKFZopMsxO1WBJJ8J4rKipAVzSfIvBrbvXFaExTTW8qMyM0GnWjW5mKZKCWeSNCq5PcBj9s5pKdZuOo0qSvCzYGLdmiRUUBUjCqfoVQFAOeBzml4FdxRjFIV2xp+l9\\/8AT29vPzkuI\\/l5f9OArk+7BqfFR0JFgteqLeKzlihkU3J3XSGWZyFA3tyse7HAiUcVjsU3sb1XEcUjyxOn4cVxB4jsZi3SlQsu5AxYghsjJGGGABKPcBGkvIWtZbgpaxFIIYXSWOBlErPNbRywl8AMGEkyFcdj7ZrIapbrFPNEpysUssSknJKo7KDn7AVoNc1Vopw8shnnVVMaJCtvaRlSdkrIrHqOpwQMKMquSQMVlefM59yckn3Ndo3Vgmk8Hc1zNcxXsVaye0ZfD7ET7lzvjiuJI8DJ6yW8rxke4IDD3UVqbeylIkKJGIxZwG22W8EgkuTBAW8Wwn6uqDuPdu\\/FYiCZ43WSNijIQ6uvdWByDTez1mCPqFVuLU3C7JY7NozBMpzkBZFJjU5Phyw54wMAQ1It5RWFIv68hEDrIqIyJZSMqBEAvHEqv4U4DNGpJ9ekvoKyZNW728DqsUSCGKMlljDb2aQgBpJHwN7kADsABwAMnNPFNBUgvJ4mok10ioEUWwpHCagTUiKiRSjpESaGxqZFDYUrGRBjQzU2FQIoDogxoZNTYVAigwnQa9XgK9QAf\\/\\/Z\\\" class=\\\"inline-img\\\" data-placeholder=\\\"img_1763657092521\\\"><span style=\\\"color: rgb(22, 22, 22); font-family: &quot;IBM Plex Sans&quot;, &quot;Helvetica Neue&quot;, Arial, sans-serif; font-size: 20px;\\\">Blockchain is a shared, immutable digital ledger, enabling the recording of transactions and the tracking of assets within a business network and providing a single source of truth.&nbsp;<\\/span><span style=\\\"font-size: 16px; color: rgb(22, 22, 22); font-family: &quot;IBM Plex Sans&quot;, &quot;Helvetica Neue&quot;, Arial, sans-serif;\\\">Blockchain operates as a decentralized distributed<\\/span><span style=\\\"font-size: 16px; color: rgb(22, 22, 22); font-family: &quot;IBM Plex Sans&quot;, &quot;Helvetica Neue&quot;, Arial, sans-serif;\\\">&nbsp;<\\/span><a href=\\\"https:\\/\\/www.ibm.com\\/think\\/topics\\/database\\\" target=\\\"_self\\\" rel=\\\"noopener noreferrer\\\" style=\\\"font-size: inherit; font-style: inherit; font-variant: inherit; box-sizing: border-box; border: 0px; font-stretch: inherit; line-height: 24px; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px; padding: 0px; vertical-align: baseline; color: rgb(15, 98, 254); text-decoration-line: none; font-family: &quot;IBM Plex Sans&quot; !important;\\\">database<\\/a><span style=\\\"font-size: 16px; color: rgb(22, 22, 22); font-family: &quot;IBM Plex Sans&quot;, &quot;Helvetica Neue&quot;, Arial, sans-serif;\\\">, with data stored across multiple computers, making it resistant to tampering. Transactions are validated through a consensus mechanism, ensuring agreement across the network.&nbsp;<\\/span><p style=\\\"font-size: 16px; box-sizing: border-box; border: 0px; font-variant-numeric: inherit; font-variant-east-asian: inherit; font-variant-alternates: inherit; font-variant-position: inherit; font-variant-emoji: inherit; font-stretch: inherit; line-height: 24px; font-family: &quot;IBM Plex Sans&quot;, &quot;Helvetica Neue&quot;, Arial, sans-serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px 0px 1.5rem; padding: 0px; vertical-align: baseline; color: rgb(22, 22, 22);\\\">In blockchain technology, each transaction is grouped into blocks, which are then linked together, forming a secure and transparent chain. This structure guarantees data integrity and provides a tamper-proof record, making blockchain ideal for applications like cryptocurrencies and&nbsp;<a href=\\\"https:\\/\\/www.ibm.com\\/think\\/topics\\/supply-chain-management\\\" target=\\\"_self\\\" rel=\\\"noopener noreferrer\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-stretch: inherit; line-height: 24px; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; font-size: inherit; margin: 0px; padding: 0px; vertical-align: baseline; color: rgb(15, 98, 254); text-decoration-line: none; font-family: &quot;IBM Plex Sans&quot; !important;\\\">supply chain management<\\/a>.&nbsp;<\\/p><div class=\\\"rich-text text\\\" style=\\\"font-size: 16px; box-sizing: border-box; border: 0px; font-variant-numeric: inherit; font-variant-east-asian: inherit; font-variant-alternates: inherit; font-variant-position: inherit; font-variant-emoji: inherit; font-stretch: inherit; line-height: inherit; font-family: &quot;IBM Plex Sans&quot;, &quot;Helvetica Neue&quot;, Arial, sans-serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px; padding: 0px; vertical-align: baseline; color: rgb(22, 22, 22);\\\"><div class=\\\"cms-richtext \\\" id=\\\"rich-text-67809ddac8\\\" data-dynamic-inner-content=\\\"description\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px 0px 2rem; padding: 0px; vertical-align: baseline; scroll-margin-top: 79px;\\\"><p style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-stretch: inherit; line-height: 24px; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px; padding: 0px; vertical-align: baseline;\\\">The key benefit of blockchain lies in its ability to provide security, transparency and trust without relying on traditional intermediaries, such as banks or other third parties. Its design reduces the risk of fraud and errors, making it especially valuable in industries where secure transactions are critical, including finance and healthcare. In addition, blockchain helps businesses improve efficiency and reduce costs by streamlining processes and enhancing accountability.&nbsp;<\\/p><\\/div><\\/div>\",\"choices\":[],\"correctChoice\":null,\"images\":[],\"files\":[]},{\"id\":1763693384186,\"title\":\"The evolution of blockchain\",\"content\":\"<div class=\\\"rich-text text\\\" style=\\\"box-sizing: border-box; border: 0px; font-variant-numeric: inherit; font-variant-east-asian: inherit; font-variant-alternates: inherit; font-variant-position: inherit; font-variant-emoji: inherit; font-stretch: inherit; line-height: inherit; font-family: &quot;IBM Plex Sans&quot;, &quot;Helvetica Neue&quot;, Arial, sans-serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; font-size: 16px; margin: 0px; padding: 0px; vertical-align: baseline; color: rgb(22, 22, 22);\\\"><div class=\\\"cms-richtext \\\" id=\\\"rich-text-7bddc001ae\\\" data-dynamic-inner-content=\\\"description\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px 0px 2rem; padding: 0px; vertical-align: baseline; scroll-margin-top: 79px;\\\"><p style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-stretch: inherit; line-height: 24px; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px 0px 1.5rem; padding: 0px; vertical-align: baseline;\\\">Blockchain technology began with the introduction of Bitcoin in 2008, created by an anonymous figure or group known as Satoshi Nakamoto. Bitcoin\\u2019s underlying technology was designed as a decentralized digital currency to enable peer-to-peer transactions without the need for a trusted intermediary like a bank. The blockchain served as a public ledger, securely recording all transactions and preventing double-spending, a key issue for digital currencies at the time.<\\/p><p style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-stretch: inherit; line-height: 24px; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px 0px 1.5rem; padding: 0px; vertical-align: baseline;\\\">&nbsp;With the development of platforms like Ethereum in 2015, blockchain began to support&nbsp;<a href=\\\"https:\\/\\/www.ibm.com\\/think\\/topics\\/smart-contracts\\\" target=\\\"_self\\\" rel=\\\"noopener noreferrer\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-stretch: inherit; line-height: 24px; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; font-size: inherit; margin: 0px; padding: 0px; vertical-align: baseline; color: rgb(15, 98, 254); text-decoration-line: none; font-family: &quot;IBM Plex Sans&quot; !important;\\\">smart contracts<\\/a>\\u2014digital contracts stored on a blockchain that are automatically executed when predetermined terms and conditions are met.<\\/p><p style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-stretch: inherit; line-height: 24px; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px 0px 1.5rem; padding: 0px; vertical-align: baseline;\\\">This development broadened blockchain\\u2019s real-world applications, extending into areas such as real estate, finance, supply chain management, healthcare and even voting systems. Over time, blockchain has grown well beyond its cryptocurrency roots, becoming a key player in decentralized finance (DeFi) and non-fungible tokens (NFTs).<\\/p><p style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-stretch: inherit; line-height: 24px; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px 0px 1.5rem; padding: 0px; vertical-align: baseline;\\\">Today, blockchain continues to evolve, with ongoing advancements aimed at improving scalability, privacy and its integration with emerging technologies like&nbsp;<a href=\\\"https:\\/\\/www.ibm.com\\/think\\/topics\\/artificial-intelligence\\\" target=\\\"_self\\\" rel=\\\"noopener noreferrer\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-stretch: inherit; line-height: 24px; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; font-size: inherit; margin: 0px; padding: 0px; vertical-align: baseline; color: rgb(15, 98, 254); text-decoration-line: none; font-family: &quot;IBM Plex Sans&quot; !important;\\\">artificial intelligence (AI)<\\/a>&nbsp;and the&nbsp;<a href=\\\"https:\\/\\/www.ibm.com\\/think\\/topics\\/internet-of-things\\\" target=\\\"_self\\\" rel=\\\"noopener noreferrer\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-stretch: inherit; line-height: 24px; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; font-size: inherit; margin: 0px; padding: 0px; vertical-align: baseline; color: rgb(15, 98, 254); text-decoration-line: none; font-family: &quot;IBM Plex Sans&quot; !important;\\\">Internet of Things (IoT)<\\/a>.<\\/p><p style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-stretch: inherit; line-height: 24px; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px; padding: 0px; vertical-align: baseline;\\\">According to a report from Statista, blockchain technology is forecast to grow by nearly 1 trillion US dollars by 2032, with a compound annual growth rate (CAGR) of 56.1% since 2021.<sup style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; font-size: 0.5rem; margin: 0px; padding: 0px;\\\">1<\\/sup><\\/p><\\/div><\\/div><div class=\\\"article-content-slot\\\" style=\\\"box-sizing: border-box; border: 0px; font-variant-numeric: inherit; font-variant-east-asian: inherit; font-variant-alternates: inherit; font-variant-position: inherit; font-variant-emoji: inherit; font-stretch: inherit; line-height: inherit; font-family: &quot;IBM Plex Sans&quot;, &quot;Helvetica Neue&quot;, Arial, sans-serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; font-size: 16px; margin: 0px; padding: 0px; vertical-align: baseline; color: rgb(22, 22, 22);\\\"><div class=\\\"xfpage page basicpage\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px; padding: 0px; vertical-align: baseline;\\\"><div class=\\\"xf-content-height\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px; padding: 0px; vertical-align: baseline;\\\"><div class=\\\"root container responsivegrid\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px; padding: 0px; vertical-align: baseline;\\\"><div id=\\\"container-99eefea693\\\" class=\\\"cmp-container\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px; padding: 0px; vertical-align: baseline; scroll-margin-top: 79px;\\\"><div class=\\\"media-player-ad-video\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px 0px 3rem; padding: 0px; vertical-align: baseline; position: relative;\\\"><div class=\\\"media-player-ad-video__container theme-media-player-ad--image-bkg-light\\\" data-theme=\\\"image-bkg-light\\\" data-autoid=\\\"\\\" data-cmp-data-layer=\\\"{&quot;media-player-ad-video-46b9a18d46&quot;:{&quot;linkLabel&quot;:&quot;Explore blockchain solutions&quot;,&quot;linkNumber&quot;:1,&quot;mediaPlayerLink&quot;:&quot;https:\\/\\/www.ibm.com\\/products\\/blockchain-platform-hyperledger-fabric&quot;,&quot;@type&quot;:&quot;adobe-cms\\/components\\/content\\/molecules\\/media-player-ad-video&quot;,&quot;ctaInXf&quot;:&quot;true&quot;,&quot;topic&quot;:&quot;Blockchain&quot;,&quot;eyebrowLabel&quot;:&quot;IBM Blockchain Services: Success by design&quot;,&quot;componentName&quot;:&quot;Media Player Ad - Video&quot;}}\\\" data-attribute2=\\\"Blockchain\\\" data-attribute1=\\\"media-player\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px; padding: 1.5rem 4rem 0px; vertical-align: baseline; --cds-ai-aura-end: hsla(0,0%,100%,0); --cds-ai-aura-hover-background: #edf5ff; --cds-ai-aura-hover-end: hsla(0,0%,100%,0); --cds-ai-aura-hover-start: rgba(69,137,255,.32); --cds-ai-aura-start: rgba(69,137,255,.1); --cds-ai-aura-start-sm: rgba(69,137,255,.16); --cds-ai-border-end: #78a9ff; --cds-ai-border-start: rgba(166,200,255,.64); --cds-ai-border-strong: #4589ff; --cds-ai-drop-shadow: rgba(15,98,254,.1); --cds-ai-inner-shadow: rgba(69,137,255,.1); --cds-ai-overlay: rgba(0,17,65,.5); --cds-ai-popover-background: #fff; --cds-ai-popover-caret-bottom: #78a9ff; --cds-ai-popover-caret-bottom-background: #eaf1ff; --cds-ai-popover-caret-bottom-background-actions: #e9effa; --cds-ai-popover-caret-center: #a0c3ff; --cds-ai-popover-shadow-outer-01: rgba(0,67,206,.06); --cds-ai-popover-shadow-outer-02: rgba(0,0,0,.04); --cds-ai-skeleton-background: #d0e2ff; --cds-ai-skeleton-element-background: #4589ff; --cds-background: #fff; --cds-background-active: hsla(0,0%,55%,.5); --cds-background-brand: #0f62fe; --cds-background-hover: hsla(0,0%,55%,.12); --cds-background-inverse: #393939; --cds-background-inverse-hover: #474747; --cds-background-selected: hsla(0,0%,55%,.2); --cds-background-selected-hover: hsla(0,0%,55%,.32); --cds-border-disabled: #c6c6c6; --cds-border-interactive: #0f62fe; --cds-border-inverse: #161616; --cds-border-strong-01: #8d8d8d; --cds-border-strong-02: #8d8d8d; --cds-border-strong-03: #8d8d8d; --cds-border-subtle-00: #e0e0e0; --cds-border-subtle-01: #c6c6c6; --cds-border-subtle-02: #e0e0e0; --cds-border-subtle-03: #c6c6c6; --cds-border-subtle-selected-01: #c6c6c6; --cds-border-subtle-selected-02: #c6c6c6; --cds-border-subtle-selected-03: #c6c6c6; --cds-border-tile-01: #c6c6c6; --cds-border-tile-02: #a8a8a8; --cds-border-tile-03: #c6c6c6; --cds-chat-avatar-agent: #393939; --cds-chat-avatar-bot: #6f6f6f; --cds-chat-avatar-user: #0f62fe; --cds-chat-bubble-agent: #fff; --cds-chat-bubble-border: #e0e0e0; --cds-chat-bubble-user: #e0e0e0; --cds-chat-button: #0f62fe; --cds-chat-button-active: hsla(0,0%,55%,.5); --cds-chat-button-hover: hsla(0,0%,55%,.12); --cds-chat-button-selected: hsla(0,0%,55%,.2); --cds-chat-button-text-hover: #0043ce; --cds-chat-button-text-selected: #525252; --cds-chat-header-background: #fff; --cds-chat-prompt-background: #fff; --cds-chat-prompt-border-end: hsla(0,0%,96%,0); --cds-chat-prompt-border-start: #f4f4f4; --cds-chat-shell-background: #fff; --cds-field-01: #f4f4f4; --cds-field-02: #fff; --cds-field-03: #f4f4f4; --cds-field-hover-01: #e8e8e8; --cds-field-hover-02: #e8e8e8; --cds-field-hover-03: #e8e8e8; --cds-focus: #0f62fe; --cds-focus-inset: #fff; --cds-focus-inverse: #fff; --cds-highlight: #d0e2ff; --cds-icon-disabled: hsla(0,0%,9%,.25); --cds-icon-interactive: #0f62fe; --cds-icon-inverse: #fff; --cds-icon-on-color: #fff; --cds-icon-on-color-disabled: #8d8d8d; --cds-icon-primary: #161616; --cds-icon-secondary: #525252; --cds-interactive: #0f62fe; --cds-layer-01: #f4f4f4; --cds-layer-02: #fff; --cds-layer-03: #f4f4f4; --cds-layer-accent-01: #e0e0e0; --cds-layer-accent-02: #e0e0e0; --cds-layer-accent-03: #e0e0e0; --cds-layer-accent-active-01: #a8a8a8; --cds-layer-accent-active-02: #a8a8a8; --cds-layer-accent-active-03: #a8a8a8; --cds-layer-accent-hover-01: #d1d1d1; --cds-layer-accent-hover-02: #d1d1d1; --cds-layer-accent-hover-03: #d1d1d1; --cds-layer-active-01: #c6c6c6; --cds-layer-active-02: #c6c6c6; --cds-layer-active-03: #c6c6c6; --cds-layer-background-01: #fff; --cds-layer-background-02: #f4f4f4; --cds-layer-background-03: #fff; --cds-layer-hover-01: #e8e8e8; --cds-layer-hover-02: #e8e8e8; --cds-layer-hover-03: #e8e8e8; --cds-layer-selected-01: #e0e0e0; --cds-layer-selected-02: #e0e0e0; --cds-layer-selected-03: #e0e0e0; --cds-layer-selected-disabled: #8d8d8d; --cds-layer-selected-hover-01: #d1d1d1; --cds-layer-selected-hover-02: #d1d1d1; --cds-layer-selected-hover-03: #d1d1d1; --cds-layer-selected-inverse: #161616; --cds-link-inverse: #78a9ff; --cds-link-inverse-active: #f4f4f4; --cds-link-inverse-hover: #a6c8ff; --cds-link-inverse-visited: #be95ff; --cds-link-primary: #0f62fe; --cds-link-primary-hover: #0043ce; --cds-link-secondary: #0043ce; --cds-link-visited: #8a3ffc; --cds-overlay: hsla(0,0%,9%,.5); --cds-shadow: rgba(0,0,0,.3); --cds-skeleton-background: #e8e8e8; --cds-skeleton-element: #c6c6c6; --cds-support-caution-major: #ff832b; --cds-support-caution-minor: #f1c21b; --cds-support-caution-undefined: #8a3ffc; --cds-support-error: #da1e28; --cds-support-error-inverse: #fa4d56; --cds-support-info: #0043ce; --cds-support-info-inverse: #4589ff; --cds-support-success: #24a148; --cds-support-success-inverse: #42be65; --cds-support-warning: #f1c21b; --cds-support-warning-inverse: #f1c21b; --cds-text-disabled: hsla(0,0%,9%,.25); --cds-text-error: #da1e28; --cds-text-helper: #6f6f6f; --cds-text-inverse: #fff; --cds-text-on-color: #fff; --cds-text-on-color-disabled: #8d8d8d; --cds-text-placeholder: hsla(0,0%,9%,.4); --cds-text-primary: #161616; --cds-text-secondary: #525252; --cds-toggle-off: #8d8d8d; --cds-spacing-01: 0.125rem; --cds-spacing-02: 0.25rem; --cds-spacing-03: 0.5rem; --cds-spacing-04: 0.75rem; --cds-spacing-05: 1rem; --cds-spacing-06: 1.5rem; --cds-spacing-07: 2rem; --cds-spacing-08: 2.5rem; --cds-spacing-09: 3rem; --cds-spacing-10: 4rem; --cds-spacing-11: 5rem; --cds-spacing-12: 6rem; --cds-spacing-13: 10rem; --cds-fluid-spacing-01: 0; --cds-fluid-spacing-02: 2vw; --cds-fluid-spacing-03: 5vw; --cds-fluid-spacing-04: 10vw; --cds-caption-01-font-size: 0.75rem; --cds-caption-01-font-weight: 400; --cds-caption-01-line-height: 1.33333; --cds-caption-01-letter-spacing: 0.32px; --cds-caption-02-font-size: 0.875rem; --cds-caption-02-font-weight: 400; --cds-caption-02-line-height: 1.28572; --cds-caption-02-letter-spacing: 0.32px; --cds-label-01-font-size: 0.75rem; --cds-label-01-font-weight: 400; --cds-label-01-line-height: 1.33333; --cds-label-01-letter-spacing: 0.32px; --cds-label-02-font-size: 0.875rem; --cds-label-02-font-weight: 400; --cds-label-02-line-height: 1.28572; --cds-label-02-letter-spacing: 0.16px; --cds-helper-text-01-font-size: 0.75rem; --cds-helper-text-01-line-height: 1.33333; --cds-helper-text-01-letter-spacing: 0.32px; --cds-helper-text-02-font-size: 0.875rem; --cds-helper-text-02-font-weight: 400; --cds-helper-text-02-line-height: 1.28572; --cds-helper-text-02-letter-spacing: 0.16px; --cds-body-short-01-font-size: 0.875rem; --cds-body-short-01-font-weight: 400; --cds-body-short-01-line-height: 1.28572; --cds-body-short-01-letter-spacing: 0.16px; --cds-body-short-02-font-size: 1rem; --cds-body-short-02-font-weight: 400; --cds-body-short-02-line-height: 1.375; --cds-body-short-02-letter-spacing: 0; --cds-body-long-01-font-size: 0.875rem; --cds-body-long-01-font-weight: 400; --cds-body-long-01-line-height: 1.42857; --cds-body-long-01-letter-spacing: 0.16px; --cds-body-long-02-font-size: 1rem; --cds-body-long-02-font-weight: 400; --cds-body-long-02-line-height: 1.5; --cds-body-long-02-letter-spacing: 0; --cds-code-01-font-family: &quot;IBM Plex Mono&quot;,system-ui,-apple-system,BlinkMacSystemFont,&quot;.SFNSText-Regular&quot;,monospace; --cds-code-01-font-size: 0.75rem; --cds-code-01-font-weight: 400; --cds-code-01-line-height: 1.33333; --cds-code-01-letter-spacing: 0.32px; --cds-code-02-font-family: &quot;IBM Plex Mono&quot;,system-ui,-apple-system,BlinkMacSystemFont,&quot;.SFNSText-Regular&quot;,monospace; --cds-code-02-font-size: 0.875rem; --cds-code-02-font-weight: 400; --cds-code-02-line-height: 1.42857; --cds-code-02-letter-spacing: 0.32px; --cds-heading-01-font-size: 0.875rem; --cds-heading-01-font-weight: 600; --cds-heading-01-line-height: 1.42857; --cds-heading-01-letter-spacing: 0.16px; --cds-heading-02-font-size: 1rem; --cds-heading-02-font-weight: 600; --cds-heading-02-line-height: 1.5; --cds-heading-02-letter-spacing: 0; --cds-productive-heading-01-font-size: 0.875rem; --cds-productive-heading-01-font-weight: 600; --cds-productive-heading-01-line-height: 1.28572; --cds-productive-heading-01-letter-spacing: 0.16px; --cds-productive-heading-02-font-size: 1rem; --cds-productive-heading-02-font-weight: 600; --cds-productive-heading-02-line-height: 1.375; --cds-productive-heading-02-letter-spacing: 0; --cds-productive-heading-03-font-size: 1.25rem; --cds-productive-heading-03-font-weight: 400; --cds-productive-heading-03-line-height: 1.4; --cds-productive-heading-03-letter-spacing: 0; --cds-productive-heading-04-font-size: 1.75rem; --cds-productive-heading-04-font-weight: 400; --cds-productive-heading-04-line-height: 1.28572; --cds-productive-heading-04-letter-spacing: 0; --cds-productive-heading-05-font-size: 2rem; --cds-productive-heading-05-font-weight: 400; --cds-productive-heading-05-line-height: 1.25; --cds-productive-heading-05-letter-spacing: 0; --cds-productive-heading-06-font-size: 2.625rem; --cds-productive-heading-06-font-weight: 300; --cds-productive-heading-06-line-height: 1.199; --cds-productive-heading-06-letter-spacing: 0; --cds-productive-heading-07-font-size: 3.375rem; --cds-productive-heading-07-font-weight: 300; --cds-productive-heading-07-line-height: 1.19; --cds-productive-heading-07-letter-spacing: 0; --cds-expressive-paragraph-01-font-size: 1.5rem; --cds-expressive-paragraph-01-font-weight: 300; --cds-expressive-paragraph-01-line-height: 1.334; --cds-expressive-paragraph-01-letter-spacing: 0; --cds-expressive-heading-01-font-size: 0.875rem; --cds-expressive-heading-01-font-weight: 600; --cds-expressive-heading-01-line-height: 1.42857; --cds-expressive-heading-01-letter-spacing: 0.16px; --cds-expressive-heading-02-font-size: 1rem; --cds-expressive-heading-02-font-weight: 600; --cds-expressive-heading-02-line-height: 1.5; --cds-expressive-heading-02-letter-spacing: 0; --cds-expressive-heading-03-font-size: 1.25rem; --cds-expressive-heading-03-font-weight: 400; --cds-expressive-heading-03-line-height: 1.4; --cds-expressive-heading-03-letter-spacing: 0; --cds-expressive-heading-04-font-size: 1.75rem; --cds-expressive-heading-04-font-weight: 400; --cds-expressive-heading-04-line-height: 1.28572; --cds-expressive-heading-04-letter-spacing: 0; --cds-expressive-heading-05-font-size: 2rem; --cds-expressive-heading-05-font-weight: 400; --cds-expressive-heading-05-line-height: 1.25; --cds-expressive-heading-05-letter-spacing: 0; --cds-expressive-heading-06-font-size: 2rem; --cds-expressive-heading-06-font-weight: 600; --cds-expressive-heading-06-line-height: 1.25; --cds-expressive-heading-06-letter-spacing: 0; --cds-quotation-01-font-family: &quot;IBM Plex Serif&quot;,system-ui,-apple-system,BlinkMacSystemFont,&quot;.SFNSText-Regular&quot;,serif; --cds-quotation-01-font-size: 1.25rem; --cds-quotation-01-font-weight: 400; --cds-quotation-01-line-height: 1.3; --cds-quotation-01-letter-spacing: 0; --cds-quotation-02-font-family: &quot;IBM Plex Serif&quot;,system-ui,-apple-system,BlinkMacSystemFont,&quot;.SFNSText-Regular&quot;,serif; --cds-quotation-02-font-size: 2rem; --cds-quotation-02-font-weight: 300; --cds-quotation-02-line-height: 1.25; --cds-quotation-02-letter-spacing: 0; --cds-display-01-font-size: 2.625rem; --cds-display-01-font-weight: 300; --cds-display-01-line-height: 1.19; --cds-display-01-letter-spacing: 0; --cds-display-02-font-size: 2.625rem; --cds-display-02-font-weight: 600; --cds-display-02-line-height: 1.19; --cds-display-02-letter-spacing: 0; --cds-display-03-font-size: 2.625rem; --cds-display-03-font-weight: 300; --cds-display-03-line-height: 1.19; --cds-display-03-letter-spacing: 0; --cds-display-04-font-size: 2.625rem; --cds-display-04-font-weight: 300; --cds-display-04-line-height: 1.19; --cds-display-04-letter-spacing: 0; --cds-legal-01-font-size: 0.75rem; --cds-legal-01-font-weight: 400; --cds-legal-01-line-height: 1.33333; --cds-legal-01-letter-spacing: 0.32px; --cds-legal-02-font-size: 0.875rem; --cds-legal-02-font-weight: 400; --cds-legal-02-line-height: 1.28572; --cds-legal-02-letter-spacing: 0.16px; --cds-body-compact-01-font-size: 0.875rem; --cds-body-compact-01-font-weight: 400; --cds-body-compact-01-line-height: 1.28572; --cds-body-compact-01-letter-spacing: 0.16px; --cds-body-compact-02-font-size: 1rem; --cds-body-compact-02-font-weight: 400; --cds-body-compact-02-line-height: 1.375; --cds-body-compact-02-letter-spacing: 0; --cds-heading-compact-01-font-size: 0.875rem; --cds-heading-compact-01-font-weight: 600; --cds-heading-compact-01-line-height: 1.28572; --cds-heading-compact-01-letter-spacing: 0.16px; --cds-heading-compact-02-font-size: 1rem; --cds-heading-compact-02-font-weight: 600; --cds-heading-compact-02-line-height: 1.375; --cds-heading-compact-02-letter-spacing: 0; --cds-body-01-font-size: 0.875rem; --cds-body-01-font-weight: 400; --cds-body-01-line-height: 1.42857; --cds-body-01-letter-spacing: 0.16px; --cds-body-02-font-size: 1rem; --cds-body-02-font-weight: 400; --cds-body-02-line-height: 1.5; --cds-body-02-letter-spacing: 0; --cds-heading-03-font-size: 1.25rem; --cds-heading-03-font-weight: 400; --cds-heading-03-line-height: 1.4; --cds-heading-03-letter-spacing: 0; --cds-heading-04-font-size: 1.75rem; --cds-heading-04-font-weight: 400; --cds-heading-04-line-height: 1.28572; --cds-heading-04-letter-spacing: 0; --cds-heading-05-font-size: 2rem; --cds-heading-05-font-weight: 400; --cds-heading-05-line-height: 1.25; --cds-heading-05-letter-spacing: 0; --cds-heading-06-font-size: 2.625rem; --cds-heading-06-font-weight: 300; --cds-heading-06-line-height: 1.199; --cds-heading-06-letter-spacing: 0; --cds-heading-07-font-size: 3.375rem; --cds-heading-07-font-weight: 300; --cds-heading-07-line-height: 1.19; --cds-heading-07-letter-spacing: 0; --cds-fluid-heading-03-font-size: 1.25rem; --cds-fluid-heading-03-font-weight: 400; --cds-fluid-heading-03-line-height: 1.4; --cds-fluid-heading-03-letter-spacing: 0; --cds-fluid-heading-04-font-size: 1.75rem; --cds-fluid-heading-04-font-weight: 400; --cds-fluid-heading-04-line-height: 1.28572; --cds-fluid-heading-04-letter-spacing: 0; --cds-fluid-heading-05-font-size: 2rem; --cds-fluid-heading-05-font-weight: 400; --cds-fluid-heading-05-line-height: 1.25; --cds-fluid-heading-05-letter-spacing: 0; --cds-fluid-heading-06-font-size: 2rem; --cds-fluid-heading-06-font-weight: 600; --cds-fluid-heading-06-line-height: 1.25; --cds-fluid-heading-06-letter-spacing: 0; --cds-fluid-paragraph-01-font-size: 1.5rem; --cds-fluid-paragraph-01-font-weight: 300; --cds-fluid-paragraph-01-line-height: 1.334; --cds-fluid-paragraph-01-letter-spacing: 0; --cds-fluid-quotation-01-font-family: &quot;IBM Plex Serif&quot;,system-ui,-apple-system,BlinkMacSystemFont,&quot;.SFNSText-Regular&quot;,serif; --cds-fluid-quotation-01-font-size: 1.25rem; --cds-fluid-quotation-01-font-weight: 400; --cds-fluid-quotation-01-line-height: 1.3; --cds-fluid-quotation-01-letter-spacing: 0; --cds-fluid-quotation-02-font-family: &quot;IBM Plex Serif&quot;,system-ui,-apple-system,BlinkMacSystemFont,&quot;.SFNSText-Regular&quot;,serif; --cds-fluid-quotation-02-font-size: 2rem; --cds-fluid-quotation-02-font-weight: 300; --cds-fluid-quotation-02-line-height: 1.25; --cds-fluid-quotation-02-letter-spacing: 0; --cds-fluid-display-01-font-size: 2.625rem; --cds-fluid-display-01-font-weight: 300; --cds-fluid-display-01-line-height: 1.19; --cds-fluid-display-01-letter-spacing: 0; --cds-fluid-display-02-font-size: 2.625rem; --cds-fluid-display-02-font-weight: 600; --cds-fluid-display-02-line-height: 1.19; --cds-fluid-display-02-letter-spacing: 0; --cds-fluid-display-03-font-size: 2.625rem; --cds-fluid-display-03-font-weight: 300; --cds-fluid-display-03-line-height: 1.19; --cds-fluid-display-03-letter-spacing: 0; --cds-fluid-display-04-font-size: 2.625rem; --cds-fluid-display-04-font-weight: 300; --cds-fluid-display-04-line-height: 1.19; --cds-fluid-display-04-letter-spacing: 0; --cds-button-separator: #e0e0e0; --cds-button-primary: #0f62fe; --cds-button-secondary: #393939; --cds-button-tertiary: #0f62fe; --cds-button-danger-primary: #da1e28; --cds-button-danger-secondary: #da1e28; --cds-button-danger-active: #750e13; --cds-button-primary-active: #002d9c; --cds-button-secondary-active: #6f6f6f; --cds-button-tertiary-active: #002d9c; --cds-button-danger-hover: #b81921; --cds-button-primary-hover: #0050e6; --cds-button-secondary-hover: #474747; --cds-button-tertiary-hover: #0050e6; --cds-button-disabled: #c6c6c6; --cds-tag-background-red: #ffd7d9; --cds-tag-color-red: #a2191f; --cds-tag-hover-red: #ffc2c5; --cds-tag-background-magenta: #ffd6e8; --cds-tag-color-magenta: #9f1853; --cds-tag-hover-magenta: #ffbdda; --cds-tag-background-purple: #e8daff; --cds-tag-color-purple: #6929c4; --cds-tag-hover-purple: #dcc7ff; --cds-tag-background-blue: #d0e2ff; --cds-tag-color-blue: #0043ce; --cds-tag-hover-blue: #b8d3ff; --cds-tag-background-cyan: #bae6ff; --cds-tag-color-cyan: #00539a; --cds-tag-hover-cyan: #99daff; --cds-tag-background-teal: #9ef0f0; --cds-tag-color-teal: #005d5d; --cds-tag-hover-teal: #57e5e5; --cds-tag-background-green: #a7f0ba; --cds-tag-color-green: #0e6027; --cds-tag-hover-green: #74e792; --cds-tag-background-gray: #e0e0e0; --cds-tag-color-gray: #161616; --cds-tag-hover-gray: #d1d1d1; --cds-tag-border-red: #ff8389; --cds-tag-border-blue: #78a9ff; --cds-tag-border-cyan: #33b1ff; --cds-tag-border-teal: #08bdba; --cds-tag-border-green: #42be65; --cds-tag-border-magenta: #ff7eb6; --cds-tag-border-purple: #be95ff; --cds-tag-border-gray: #a8a8a8; --cds-tag-border-cool-gray: #a2a9b0; --cds-tag-border-warm-gray: #ada8a8; --cds-tag-background-cool-gray: #dde1e6; --cds-tag-color-cool-gray: #121619; --cds-tag-hover-cool-gray: #cdd3da; --cds-tag-background-warm-gray: #e5e0df; --cds-tag-color-warm-gray: #171414; --cds-tag-hover-warm-gray: #d8d0cf; --cds-layer: #f4f4f4; --cds-layer-active: #c6c6c6; --cds-layer-hover: #e8e8e8; --cds-layer-selected: #e0e0e0; --cds-layer-selected-hover: #d1d1d1; --cds-layer-accent: #e0e0e0; --cds-layer-accent-hover: #d1d1d1; --cds-layer-accent-active: #a8a8a8; --cds-field: #f4f4f4; --cds-field-hover: #e8e8e8; --cds-border-subtle: #e0e0e0; --cds-border-subtle-selected: #c6c6c6; --cds-border-strong: #8d8d8d; --cds-border-tile: #c6c6c6; --backgroundImage: url(https:\\/\\/cdnsecakmi.kaltura.com\\/p\\/1773841\\/thumbnail\\/entry_id\\/1_juj87qgj\\/width\\/full);\\\"><div class=\\\"media-player-ad-video__content\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px; padding: 0px; vertical-align: baseline; position: relative; z-index: 1;\\\"><div class=\\\"media-player-ad-video__top\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: inherit; font-stretch: inherit; line-height: inherit; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; margin: 0px 0px 0.75rem; padding: 0px; vertical-align: baseline; align-items: center; display: flex; justify-content: space-between;\\\"><div class=\\\"media-player-ad-video__eyebrow\\\" style=\\\"box-sizing: border-box; border: 0px; font-style: inherit; font-variant: inherit; font-weight: 600; font-stretch: inherit; line-height: 1.28572; font-family: inherit; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; font-size: 14px; margin: 0px; padding: 0px; vertical-align: baseline; color: rgb(82, 82, 82); letter-spacing: 0.16px; width: 478.156px;\\\">IBM Blockchain Services: Success by design<\\/div><\\/div><\\/div><\\/div><\\/div><\\/div><\\/div><\\/div><\\/div><\\/div>\",\"choices\":[],\"correctChoice\":null,\"images\":[],\"files\":[]}]', NULL, NULL, 'other', '2025-11-21 02:50:20');
INSERT INTO `classnotes` (`id`, `unit_id`, `lecturer_id`, `title`, `subtopics_json`, `description`, `file_path`, `media_type`, `uploaded_at`) VALUES
(2, 98, 1, 'Modular Programming by; Kenneth Leroy Busbee and Dave Braunschweig', '[{\"id\":1763690026813,\"title\":\"Overview\",\"content\":\"<span style=\\\"box-sizing: border-box; font-weight: bolder; color: rgb(55, 61, 63); font-family: Montserrat, sans-serif; font-size: 18px; orphans: 1;\\\">Modular programming<\\/span><span style=\\\"color: rgb(55, 61, 63); font-family: Montserrat, sans-serif; font-size: 18px; orphans: 1;\\\">&nbsp;is a software design technique that emphasizes separating the functionality of a program into independent, interchangeable modules, such that each contains everything necessary to execute only one aspect of the desired functionality.<\\/span><a class=\\\"footnote\\\" title=\\\"Wikipedia: Modular programming\\\" id=\\\"return-footnote-87-1\\\" href=\\\"https:\\/\\/press.rebus.community\\/programmingfundamentals\\/chapter\\/modular-programming\\/#footnote-87-1\\\" aria-label=\\\"Footnote 1\\\" style=\\\"box-sizing: border-box; color: rgb(205, 75, 24); touch-action: manipulation; font-family: Montserrat, sans-serif; font-size: 18px; orphans: 1;\\\"><span class=\\\"footnote\\\" style=\\\"box-sizing: border-box; font-size: 0.8em; line-height: 0.5em; position: relative; vertical-align: baseline; top: -0.5em;\\\">[1]<\\/span><\\/a>\",\"choices\":[],\"correctChoice\":null,\"images\":[],\"files\":[]}]', NULL, NULL, 'other', '2025-11-21 01:54:22');
INSERT INTO `classnotes` (`id`, `unit_id`, `lecturer_id`, `title`, `subtopics_json`, `description`, `file_path`, `media_type`, `uploaded_at`) VALUES
(3, 5, 1, 'Blockchain implementation', '[{\"id\":1763711271909,\"title\":\"get started\",\"content\":\"<img src=\\\"..\\/uploads\\/images\\/69201953be63d_image.png\\\" class=\\\"inline-img\\\" ><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">A blockchain\'s nodes communicate with one another to exchange information through a peer-to-peer network to ensure unison on the blockchain\'s overall state at any given time. This peer-to-peer network, which is the underlying technology, is crucial. Since blockchain isn\'t reliant on a middleman to transfer information through the system, the nodes keep each other in check by verifying that all transactions and transaction history are valid.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">In a blockchain, information is stored in data structures called \\u201cblocks,\\u201d and every block is hashed together to form a chain. This is where the phrase \\u201cblockchain\\u201d originated.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">So, how does this work? This works through various protocols put into place. The \\u201cGossip Protocol\\u201d communication method is utilized in many blockchains, where blockchain data is broadcast from one node to another until it eventually spreads across the entire network.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">Through this method, blockchains ensure transparency and security; all transaction data is public and immutable, meaning it can not be changed once finalized. Without blockchain, decentralized technology would not be easy to come by.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">Blockchain implementation has truly revolutionized the&nbsp;<em style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased;\\\">decentralized ecosystem<\\/em><strong style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased;\\\">&nbsp;<\\/strong>and the possibilities when it comes to storing and exchanging data, and enabling trust, transparency, and security in a multitude of industries, from financial services to healthcare to supply management and beyond.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\"><strong style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased;\\\">Table of contents<\\/strong><\\/p><ul automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 24px 0px 32px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px;\\\"><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#blockchain-architecture-design\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">Blockchain architecture design<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#how-blockchain-began\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">How blockchain began<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#5-steps-to-implement-blockchain-technology\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">5 steps to implement blockchain technology<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#identify-the-use-case-for-blockchain-implementation\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">Identify the use case for blockchain implementation<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#create-a-proof-of-concept-for-blockchain-implementation\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">Create a proof of concept for blockchain implementation<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#how-to-choose-a-blockchain-platform\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">How to choose a blockchain platform, consensus protocol, and design architecture<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#how-to-choose-the-blockchain-layer\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">How to choose the blockchain layer<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#developing-smart-contracts\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">Developing smart contracts<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#maintaining-and-updating-the-network\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">Maintaining and updating the network<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#considerations-when-implementing-blockchain-technology\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">Considerations when implementing blockchain technology<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#what-are-the-main-features-of-blockchain-technology\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">What are the main features of blockchain technology?<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#drawbacks-to-blockchain-technology\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">Drawbacks to blockchain technology<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#advantages-of-blockchain-for-business\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">Advantages of blockchain for business<\\/span><\\/span><\\/a><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><a automation-testid=\\\"flora-Link-Link\\\" tabindex=\\\"0\\\" href=\\\"https:\\/\\/www.mongodb.com\\/resources\\/basics\\/databases\\/blockchain-implementation#in-summary\\\" target=\\\"_self\\\" class=\\\"css-1m1tiat\\\" data-track=\\\"true\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; cursor: pointer; text-decoration-line: none; display: inline; font-size: inherit; line-height: 32px; color: rgb(0, 108, 250);\\\"><span automation-testid=\\\"flora-Link-LinkContent\\\" class=\\\"css-ua3fs4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center;\\\"><span automation-testid=\\\"flora-Link-Text\\\" class=\\\"textlink-default-text-class css-pbhol6\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; display: inline; -webkit-box-align: center; align-items: center; font-size: inherit; line-height: 32px; border-bottom: 2px solid transparent;\\\">In summary<\\/span><\\/span><\\/a><\\/li><\\/ul><h2 automation-testid=\\\"flora-TypographyScale\\\" class=\\\"custom-class style-h5 mb-inc30 mt-inc50 font-medium lg:mb-inc40 lg:mt-inc70 css-qy7wiv\\\" id=\\\"blockchain-architecture-design\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 24px; margin-top: 48px; font-weight: 500; grid-column: 3 \\/ span 8; color: rgb(0, 30, 43); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 36px; line-height: 48px;\\\">Blockchain architecture design<\\/h2><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">Based on the chosen use case, the blockchain architecture can be designed and built. The main components are as follows:<\\/p><h3 automation-testid=\\\"flora-TypographyScale\\\" class=\\\"custom-class style-h6 mb-inc30 mt-inc40 font-medium lg:mb-inc40 lg:mt-inc50 css-1dmj5i4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 24px; margin-top: 32px; font-weight: 500; grid-column: 3 \\/ span 8; color: rgb(0, 30, 43); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 24px; line-height: 32px;\\\">Node types<\\/h3><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">A node in blockchain is an integral part of the network. Some nodes just hold information about the blockchain while others participate in the consensus mechanism to validate transactions. There are different types of nodes available. The two most common are full nodes (nodes that store the entire blockchain state on disk) and lightweight nodes (nodes that just store the block headers, saving a ton of time and memory).<\\/p><h3 automation-testid=\\\"flora-TypographyScale\\\" class=\\\"custom-class style-h6 mb-inc30 mt-inc40 font-medium lg:mb-inc40 lg:mt-inc50 css-1dmj5i4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 24px; margin-top: 32px; font-weight: 500; grid-column: 3 \\/ span 8; color: rgb(0, 30, 43); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 24px; line-height: 32px;\\\">Public or private architecture<\\/h3><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">Bitcoin and Ethereum are public architectures, as they are open-source and decentralized. Private architectures only allow specific members, such as financial institutions, to access the information. This is an important step in designing the architecture of the blockchain solution or the intended use case.<\\/p><h3 automation-testid=\\\"flora-TypographyScale\\\" class=\\\"custom-class style-h6 mb-inc30 mt-inc40 font-medium lg:mb-inc40 lg:mt-inc50 css-1dmj5i4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 24px; margin-top: 32px; font-weight: 500; grid-column: 3 \\/ span 8; color: rgb(0, 30, 43); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 24px; line-height: 32px;\\\">Storage<\\/h3><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">It\'s crucial to consider how the information will be stored over the network. Should it be the responsibility of the users or pre-selected nodes in a private system? Accessing the information back is another important factor to take into account.<\\/p><h3 automation-testid=\\\"flora-TypographyScale\\\" class=\\\"custom-class style-h6 mb-inc30 mt-inc40 font-medium lg:mb-inc40 lg:mt-inc50 css-1dmj5i4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 24px; margin-top: 32px; font-weight: 500; grid-column: 3 \\/ span 8; color: rgb(0, 30, 43); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 24px; line-height: 32px;\\\">Miners<\\/h3><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">Miners are used in PoW systems and compete to incorporate blocks into the blockchain and ensure that they are valid.<\\/p><h3 automation-testid=\\\"flora-TypographyScale\\\" class=\\\"custom-class style-h6 mb-inc30 mt-inc40 font-medium lg:mb-inc40 lg:mt-inc50 css-1dmj5i4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 24px; margin-top: 32px; font-weight: 500; grid-column: 3 \\/ span 8; color: rgb(0, 30, 43); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 24px; line-height: 32px;\\\">Validators<\\/h3><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">Validators are used in PoS systems that require participants to stake ETH and become an entity that validates transactions and receives rewards for securing the network.<\\/p><h3 automation-testid=\\\"flora-TypographyScale\\\" class=\\\"custom-class style-h6 mb-inc30 mt-inc40 font-medium lg:mb-inc40 lg:mt-inc50 css-1dmj5i4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 24px; margin-top: 32px; font-weight: 500; grid-column: 3 \\/ span 8; color: rgb(0, 30, 43); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 24px; line-height: 32px;\\\">Chain<\\/h3><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">A chain is a string of blocks hashed together to form an immutable record of transactions.<\\/p><h3 automation-testid=\\\"flora-TypographyScale\\\" class=\\\"custom-class style-h6 mb-inc30 mt-inc40 font-medium lg:mb-inc40 lg:mt-inc50 css-1dmj5i4\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 24px; margin-top: 32px; font-weight: 500; grid-column: 3 \\/ span 8; color: rgb(0, 30, 43); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 24px; line-height: 32px;\\\">Consensus<\\/h3><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">Consensus is when protocol participants agree on the state of a blockchain.<\\/p><h2 automation-testid=\\\"flora-TypographyScale\\\" class=\\\"custom-class style-h5 mb-inc30 mt-inc50 font-medium lg:mb-inc40 lg:mt-inc70 css-qy7wiv\\\" id=\\\"how-blockchain-began\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 24px; margin-top: 48px; font-weight: 500; grid-column: 3 \\/ span 8; color: rgb(0, 30, 43); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 36px; line-height: 48px;\\\">How blockchain began<\\/h2><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">It is a common misconception that blockchain was invented by the same person who created Bitcoin\\u2014the anonymous person (or regularly theorized to potentially be a group of people) named Satoshi Nakamoto, in 2008. There were several attempts to create a \\u201cdecentralized cash,\\u201d such as Bit Gold, by Nicholas Szabo in 1998, and eCash, by David Chaum in 1990.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">These attempts at creating technology for decentralized money paved the path for Bitcoin, which in turn allowed for the popularization of a decentralized blockchain, as Bitcoin is seen as the first public, real-world application of blockchain.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">Blockchain technology and its implementation became known in 2008 by Nakamoto, when they released their white paper, \\u201cBitcoin: A Peer-to-Peer Electronic Cash System,\\u201d to a cryptography mailing list. This white paper contained technologies from previous decentralized cash attempts, including a blockchain structure very similar to the previous systems with the addition of the Bitcoin proof-of-work mechanism for validating blocks and mining coins.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">After Bitcoin\'s whitepaper was published in 2008 by Nakamoto, self-motivated software engineers around the globe helped contribute to the success of Bitcoin. This made it so the very first block of a working blockchain launched in 2009. Bitcoin organically rose to mainstream popularity from a small internet forum and some computer scientists that were interested in cryptography.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">The first block of the Bitcoin blockchain can explain why so many were inspired to operate and spread the word about Bitcoin. The first block, otherwise known as the \\u201cgenesis block,\\u201d portrays the ethos behind the invention of blockchain and blockchain technology.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">In the coinbase of the genesis block was the text, \\u201cThe Times 03\\/Jan\\/2009 Chancellor on brink of second bailout for banks,\\u201d which references an article published by&nbsp;<em style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased;\\\">The Times<\\/em>&nbsp;and is interpreted as a comment on the instability that comes from money being centralized and irresponsibly handled by governments.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">The release of the first successful blockchain with Bitcoin inspired individuals and groups of individuals to create alternative blockchain systems with different capabilities and features, the most notable one being Ethereum.<\\/p><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 32px 0px 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">Even though blockchain was brought to our society as an invention for enabling decentralized currency, individuals have developed an array of other uses in various alternative industries. Blockchain technology is clearly here to stay, and it will continue to improve over time.<\\/p><h2 automation-testid=\\\"flora-TypographyScale\\\" class=\\\"custom-class style-h5 mb-inc30 mt-inc50 font-medium lg:mb-inc40 lg:mt-inc70 css-qy7wiv\\\" id=\\\"5-steps-to-implement-blockchain-technology\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 24px; margin-top: 48px; font-weight: 500; grid-column: 3 \\/ span 8; color: rgb(0, 30, 43); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 36px; line-height: 48px;\\\">5 steps to implement blockchain technology<\\/h2><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">Implementing blockchain technology can seem incredibly daunting, but these five essential steps will help simplify the process:<\\/p><ol automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 24px 0px 32px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px;\\\"><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin: 0px; line-height: 32px; --tw-text-opacity: 1;\\\">Identify the use case, and if a blockchain is necessary or if a centralized platform makes more sense.<\\/p><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin: 0px; line-height: 32px; --tw-text-opacity: 1;\\\">Create a proof of concept.<\\/p><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin: 0px; line-height: 32px; --tw-text-opacity: 1;\\\">Choose a blockchain platform, the right consensus protocol, and overall architecture.<\\/p><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin: 0px; line-height: 32px; --tw-text-opacity: 1;\\\">Develop smart contracts.<\\/p><\\/li><li style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin-bottom: 8px; --tw-text-opacity: 1;\\\"><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; margin: 0px; line-height: 32px; --tw-text-opacity: 1;\\\">Maintain and update the network.<\\/p><\\/li><\\/ol><p automation-testid=\\\"flora-TypographyScale\\\" class=\\\"block css-bjkbxq\\\" style=\\\"--tw-border-spacing-x: 0; --tw-border-spacing-y: 0; --tw-translate-x: 0; --tw-translate-y: 0; --tw-rotate: 0; --tw-skew-x: 0; --tw-skew-y: 0; --tw-scale-x: 1; --tw-scale-y: 1; --tw-pan-x: ; --tw-pan-y: ; --tw-pinch-zoom: ; --tw-scroll-snap-strictness: proximity; --tw-gradient-from-position: ; --tw-gradient-via-position: ; --tw-gradient-to-position: ; --tw-ordinal: ; --tw-slashed-zero: ; --tw-numeric-figure: ; --tw-numeric-spacing: ; --tw-numeric-fraction: ; --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgba(59,130,246,.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000; --tw-shadow-colored: 0 0 #0000; --tw-blur: ; --tw-brightness: ; --tw-contrast: ; --tw-grayscale: ; --tw-hue-rotate: ; --tw-invert: ; --tw-saturate: ; --tw-sepia: ; --tw-drop-shadow: ; --tw-backdrop-blur: ; --tw-backdrop-brightness: ; --tw-backdrop-contrast: ; --tw-backdrop-grayscale: ; --tw-backdrop-hue-rotate: ; --tw-backdrop-invert: ; --tw-backdrop-opacity: ; --tw-backdrop-saturate: ; --tw-backdrop-sepia: ; --tw-contain-size: ; --tw-contain-layout: ; --tw-contain-paint: ; --tw-contain-style: ; scrollbar-color: auto; scrollbar-width: auto; box-sizing: border-box; -webkit-font-smoothing: antialiased; grid-column: 3 \\/ span 8; margin: 0px; color: rgb(61, 79, 88); font-family: &quot;Euclid Circular A&quot;, &quot;Noto Sans KR&quot;, &quot;Noto Sans SC&quot;, &quot;Noto Sans JP&quot;; font-size: 20px; line-height: 32px; --tw-text-opacity: 1;\\\">In the sections below, we will dive into further details on how each step is integral to the blockchain technology integration.<\\/p>\",\"choices\":[],\"correctChoice\":null,\"images\":[{\"name\":\"69201953be63d_image.png\",\"original_name\":\"69201953be63d_image.png\"}],\"files\":[]}]', NULL, NULL, 'other', '2025-11-21 07:48:35');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `duration` int DEFAULT '4'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `name`, `department_id`, `duration`) VALUES
(1, 'computer science', 1, 4),
(2, 'computer technology', 1, 4),
(3, 'information technology', 2, 4);

-- --------------------------------------------------------

--
-- Table structure for table `course_lessons`
--

CREATE TABLE `course_lessons` (
  `id` int NOT NULL,
  `module_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lesson_number` int NOT NULL DEFAULT '1',
  `position` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_lessons`
--

INSERT INTO `course_lessons` (`id`, `module_id`, `unit_id`, `title`, `lesson_number`, `position`, `created_at`) VALUES
(1, 4, 100, 'lesson 1', 1, 1, '2026-03-09 12:22:09'),
(2, 1, 5, 'lesson 1', 1, 1, '2026-03-09 13:21:13'),
(3, 5, 98, 'lesson 1', 1, 1, '2026-03-17 21:39:49');

-- --------------------------------------------------------

--
-- Table structure for table `course_modules`
--

CREATE TABLE `course_modules` (
  `id` int NOT NULL,
  `unit_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_modules`
--

INSERT INTO `course_modules` (`id`, `unit_id`, `lecturer_id`, `title`, `position`, `created_at`) VALUES
(1, 5, 1, 'introduction to kbs', 1, '2026-03-09 09:19:09'),
(2, 5, 1, 'introduction to kbs', 2, '2026-03-09 09:22:35'),
(3, 5, 1, 'knowledge based systems', 3, '2026-03-09 09:23:32'),
(4, 100, 1, 'introduction to kbs', 1, '2026-03-09 09:59:07'),
(5, 98, 1, 'introduction to kbs', 1, '2026-03-17 21:39:48'),
(6, 98, 1, 'knowledge based systems', 2, '2026-03-17 21:39:49');

-- --------------------------------------------------------

--
-- Table structure for table `course_outlines`
--

CREATE TABLE `course_outlines` (
  `id` int NOT NULL,
  `unit_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `outline` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_outlines`
--

INSERT INTO `course_outlines` (`id`, `unit_id`, `lecturer_id`, `description`, `outline`, `created_at`, `updated_at`) VALUES
(1, 5, 1, 'kbs', 'kbs', '2026-03-09 09:22:57', '2026-03-09 09:22:57'),
(2, 100, 1, 'kbs 1', 'kbs 1', '2026-03-09 09:52:33', '2026-03-09 09:54:11');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `university_id` int DEFAULT NULL,
  `school_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `university_id`, `school_id`) VALUES
(1, 'computing', 7, 1),
(2, 'information technology', 7, 1),
(3, 'microbiology', 7, 1);

-- --------------------------------------------------------

--
-- Table structure for table `exam_violations`
--

CREATE TABLE `exam_violations` (
  `id` int NOT NULL,
  `submission_id` int NOT NULL,
  `student_id` int NOT NULL,
  `violation_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `occurred_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `details` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ghost_flags`
--

CREATE TABLE `ghost_flags` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `user_id` int NOT NULL,
  `flagged_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `nudge_sent_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interactive_answers`
--

CREATE TABLE `interactive_answers` (
  `id` int NOT NULL,
  `submission_id` int NOT NULL,
  `question_id` int NOT NULL,
  `option_id` int DEFAULT NULL,
  `answer_text` text,
  `marks_awarded` int NOT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `answer_audio` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `interactive_answers`
--

INSERT INTO `interactive_answers` (`id`, `submission_id`, `question_id`, `option_id`, `answer_text`, `marks_awarded`, `is_correct`, `answer_audio`) VALUES
(1, 6, 9, 14, NULL, 1, 1, NULL),
(2, 6, 10, 16, NULL, 1, 1, NULL),
(3, 6, 11, 20, NULL, 1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `interactive_assignments`
--

CREATE TABLE `interactive_assignments` (
  `id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `due_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `interactive_assignments`
--

INSERT INTO `interactive_assignments` (`id`, `lecturer_id`, `unit_id`, `title`, `description`, `due_date`, `created_at`) VALUES
(2, 1, 5, 'distributed ledgers', 'distributed sys', '2025-09-18 11:09:00', '2025-09-11 06:08:03'),
(3, 1, 5, 'trying', 'kjlj;p[', '2025-09-16 09:10:00', '2025-09-11 06:10:50'),
(5, 1, 5, 'assignment questions', 'distributed sys', '2026-02-19 19:05:00', '2026-02-12 16:11:06');

-- --------------------------------------------------------

--
-- Table structure for table `interactive_options`
--

CREATE TABLE `interactive_options` (
  `id` int NOT NULL,
  `question_id` int NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `is_correct` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `interactive_options`
--

INSERT INTO `interactive_options` (`id`, `question_id`, `option_text`, `is_correct`) VALUES
(5, 6, 'mouse', 0),
(6, 6, 'register', 1),
(7, 6, 'keyboard', 0),
(8, 6, 'speakers', 0),
(9, 7, 'protect data', 1),
(10, 7, 'register', 0),
(11, 8, 'cpu', 0),
(12, 8, 'alu', 1),
(13, 9, 'james gosling', 0),
(14, 9, 'james gabbage', 1),
(15, 9, 'webster', 0),
(16, 10, 'yes', 1),
(17, 10, 'no', 0),
(18, 11, 'store data', 0),
(19, 11, 'connect to internet', 0),
(20, 11, 'manage hardware resources', 1);

-- --------------------------------------------------------

--
-- Table structure for table `interactive_questions`
--

CREATE TABLE `interactive_questions` (
  `id` int NOT NULL,
  `interactive_assignment_id` int NOT NULL,
  `question_text` text NOT NULL,
  `type` enum('text','multiple_choice') NOT NULL DEFAULT 'text',
  `points` int NOT NULL DEFAULT '1',
  `question_type` enum('multiple_choice','true_false','short_answer','essay') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `media_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `interactive_questions`
--

INSERT INTO `interactive_questions` (`id`, `interactive_assignment_id`, `question_text`, `type`, `points`, `question_type`, `created_at`, `media_url`) VALUES
(6, 3, 'Which of the following is NOT a peripheral device?', 'multiple_choice', 3, 'multiple_choice', '2025-09-12 04:48:37', NULL),
(7, 2, 'What is the primary function of a firewall?', 'multiple_choice', 1, 'multiple_choice', '2025-09-12 05:07:08', NULL),
(8, 2, 'what is a serial bus', 'multiple_choice', 1, 'multiple_choice', '2025-09-12 05:07:08', NULL),
(9, 5, '1. Who is the father of Computers?', 'text', 1, 'multiple_choice', '2026-02-12 16:11:06', NULL),
(10, 5, 'Does a Computer/Laptop have a GUI (Graphics User Interface)?', 'text', 1, 'multiple_choice', '2026-02-12 16:11:06', NULL),
(11, 5, 'What is the main function of an operating system in a computer?', 'text', 1, 'multiple_choice', '2026-02-12 16:11:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `interactive_submissions`
--

CREATE TABLE `interactive_submissions` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `assignment_id` int NOT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `score` decimal(5,2) DEFAULT NULL,
  `graded` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `interactive_submissions`
--

INSERT INTO `interactive_submissions` (`id`, `student_id`, `assignment_id`, `submitted_at`, `score`, `graded`) VALUES
(6, 3, 5, '2026-02-12 18:17:35', 3.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `labs`
--

CREATE TABLE `labs` (
  `id` int NOT NULL,
  `unit_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `module_id` int DEFAULT NULL,
  `lesson_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode` enum('pdf_manual','fillable_pdf','html_worksheet') COLLATE utf8mb4_unicode_ci NOT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `html_content` longtext COLLATE utf8mb4_unicode_ci,
  `due_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_submissions`
--

CREATE TABLE `lab_submissions` (
  `id` int NOT NULL,
  `lab_id` int NOT NULL,
  `student_id` int NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `html_answers` longtext COLLATE utf8mb4_unicode_ci,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lecturers`
--

CREATE TABLE `lecturers` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `university_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lecturers`
--

INSERT INTO `lecturers` (`id`, `name`, `email`, `department_id`, `password`, `university_id`) VALUES
(1, 'mane', 'mane@gmail.com', NULL, '$2y$10$uKWTTNqGyN.7VqbHDrbrRO/fDY9o1yY3u7G7IvRRZ/XSy3yJFDZpy', 7);

-- --------------------------------------------------------

--
-- Table structure for table `lecturer_units`
--

CREATE TABLE `lecturer_units` (
  `id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `unit_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lecturer_units`
--

INSERT INTO `lecturer_units` (`id`, `lecturer_id`, `unit_id`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 4),
(4, 1, 5),
(5, 1, 98),
(6, 1, 100);

-- --------------------------------------------------------

--
-- Table structure for table `lesson_content_blocks`
--

CREATE TABLE `lesson_content_blocks` (
  `id` int NOT NULL,
  `lesson_id` int NOT NULL,
  `block_type` enum('text','image','video','audio','diagram','pdf') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `position` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lesson_content_blocks`
--

INSERT INTO `lesson_content_blocks` (`id`, `lesson_id`, `block_type`, `content`, `position`, `created_at`) VALUES
(1, 2, 'image', '{\"src\":\"../uploads/course_images/blk_2_69aea0b27e095.png\",\"caption\":\"\"}', 0, '2026-03-09 13:28:04'),
(2, 2, 'text', '<span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">A&nbsp;</span><b style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">knowledge-based system</b><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;(</span><b style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">KBS</b><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">) is a&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Computer_program\" title=\"Computer program\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">computer program</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;that&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Automated_reasoning\" title=\"Automated reasoning\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">reasons</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;and uses a&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Knowledge_base\" title=\"Knowledge base\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">knowledge base</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;to&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Problem_solving\" title=\"Problem solving\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">solve</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Complex_systems\" class=\"mw-redirect\" title=\"Complex systems\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">complex problems</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">.</span><sup id=\"cite_ref-1\" class=\"reference\" style=\"line-height: 1; unicode-bidi: isolate; text-wrap-mode: nowrap; font-size: 12.8px; color: rgb(32, 33, 34); font-family: sans-serif; background-color: rgb(255, 255, 255);\"><a href=\"https://en.wikipedia.org/wiki/Knowledge-based_systems#cite_note-1\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none; border-radius: 2px; overflow-wrap: break-word;\"><span class=\"cite-bracket\" style=\"pointer-events: none;\">[</span>1<span class=\"cite-bracket\" style=\"pointer-events: none;\">]</span></a></sup><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;Knowledge-based systems were the focus of early&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Artificial_intelligence\" title=\"Artificial intelligence\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">artificial intelligence</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;researchers in the 1980s. The term can refer to a broad range of systems. However, all knowledge-based systems have two defining components: an attempt to represent knowledge explicitly, called a&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Knowledge_base\" title=\"Knowledge base\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">knowledge base</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">, and a&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Reasoning_system\" title=\"Reasoning system\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">reasoning system</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;that allows them to derive new knowledge, known as an&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Inference_engine\" title=\"Inference engine\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">inference engine</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">.</span>', 1, '2026-03-09 13:28:04'),
(4, 2, 'pdf', '{\"src\":\"uploads/course_pdfs/blk_2_69b94793b8a92.pdf\",\"name\":\"lecture-01-introduction-to-knowledge-based-intelligent-systems.pdf\",\"caption\":\"\"}', 2, '2026-03-17 15:22:58'),
(5, 2, 'video', '{\"type\":\"upload\",\"src\":\"uploads/course_videos/blk_2_69b94f1daca7c.mp4\",\"name\":\"Blockchain In 7 Minutes _ What Is Blockchain _ Blockchain Explained_How Blockchain Works_Simplilearn.mp4\"}', 3, '2026-03-17 15:55:00'),
(6, 3, 'image', '{\"src\":\"../uploads/course_images/blk_2_69aea0b27e095.png\",\"caption\":\"\"}', 0, '2026-03-17 21:39:49'),
(7, 3, 'text', '<span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">A&nbsp;</span><b style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">knowledge-based system</b><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;(</span><b style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">KBS</b><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">) is a&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Computer_program\" title=\"Computer program\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">computer program</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;that&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Automated_reasoning\" title=\"Automated reasoning\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">reasons</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;and uses a&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Knowledge_base\" title=\"Knowledge base\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">knowledge base</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;to&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Problem_solving\" title=\"Problem solving\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">solve</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Complex_systems\" class=\"mw-redirect\" title=\"Complex systems\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">complex problems</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">.</span><sup id=\"cite_ref-1\" class=\"reference\" style=\"line-height: 1; unicode-bidi: isolate; text-wrap-mode: nowrap; font-size: 12.8px; color: rgb(32, 33, 34); font-family: sans-serif; background-color: rgb(255, 255, 255);\"><a href=\"https://en.wikipedia.org/wiki/Knowledge-based_systems#cite_note-1\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none; border-radius: 2px; overflow-wrap: break-word;\"><span class=\"cite-bracket\" style=\"pointer-events: none;\">[</span>1<span class=\"cite-bracket\" style=\"pointer-events: none;\">]</span></a></sup><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;Knowledge-based systems were the focus of early&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Artificial_intelligence\" title=\"Artificial intelligence\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">artificial intelligence</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;researchers in the 1980s. The term can refer to a broad range of systems. However, all knowledge-based systems have two defining components: an attempt to represent knowledge explicitly, called a&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Knowledge_base\" title=\"Knowledge base\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">knowledge base</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">, and a&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Reasoning_system\" title=\"Reasoning system\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">reasoning system</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">&nbsp;that allows them to derive new knowledge, known as an&nbsp;</span><a href=\"https://en.wikipedia.org/wiki/Inference_engine\" title=\"Inference engine\" style=\"text-decoration-line: none; color: rgb(51, 102, 204); background: none rgb(255, 255, 255); border-radius: 2px; overflow-wrap: break-word; font-family: sans-serif; font-size: 16px;\">inference engine</a><span style=\"color: rgb(32, 33, 34); font-family: sans-serif; font-size: 16px; background-color: rgb(255, 255, 255);\">.</span>', 1, '2026-03-17 21:39:49'),
(8, 3, 'pdf', '{\"src\":\"uploads/course_pdfs/blk_2_69b94793b8a92.pdf\",\"name\":\"lecture-01-introduction-to-knowledge-based-intelligent-systems.pdf\",\"caption\":\"\"}', 2, '2026-03-17 21:39:49'),
(9, 3, 'video', '{\"type\":\"upload\",\"src\":\"uploads/course_videos/blk_2_69b94f1daca7c.mp4\",\"name\":\"Blockchain In 7 Minutes _ What Is Blockchain _ Blockchain Explained_How Blockchain Works_Simplilearn.mp4\"}', 3, '2026-03-17 21:39:49');

-- --------------------------------------------------------

--
-- Table structure for table `meetings`
--

CREATE TABLE `meetings` (
  `id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `meeting_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `scheduled_time` datetime NOT NULL,
  `duration` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meetings`
--

INSERT INTO `meetings` (`id`, `lecturer_id`, `unit_id`, `title`, `meeting_link`, `scheduled_time`, `duration`, `created_at`, `ended`) VALUES
(1, 1, 5, 'database', 'http://localhost/unilis/meeting_ide.php?meeting_id=1763039387', '2025-11-13 16:11:00', 60, '2025-11-13 13:09:47', 0),
(2, 1, 5, 'dbms', 'http://localhost/unilis/meeting_ide.php?meeting_id=1763098036', '2025-11-14 08:28:00', 180, '2025-11-14 05:27:16', 0),
(3, 1, 5, 'database', 'http://localhost/unilis/meeting_ide.php?meeting_id=1763272222', '2025-11-16 08:52:00', 180, '2025-11-16 05:50:22', 0),
(4, 1, 98, 'dbs', 'http://localhost/unilis/meeting_ide.php?meeting_id=1763360520', '2025-11-17 09:21:00', 180, '2025-11-17 06:22:00', 0),
(5, 1, 5, 'dbms', 'http://localhost/unilis/meeting_ide.php?meeting_id=1763361050', '2025-11-17 09:30:00', 180, '2025-11-17 06:30:50', 0),
(6, 1, 5, 'dbms', 'http://localhost/unilis/meeting_ide.php?meeting_id=1763712742', '2025-11-21 11:13:00', 240, '2025-11-21 08:12:22', 0),
(7, 1, 5, 'compputer systems', 'http://localhost/unilis/meeting_ide.php?meeting_id=1763970214', '2025-11-24 10:44:00', 180, '2025-11-24 07:43:34', 0);

-- --------------------------------------------------------

--
-- Table structure for table `meeting_attendance`
--

CREATE TABLE `meeting_attendance` (
  `id` int NOT NULL,
  `meeting_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `lecturer_id` int DEFAULT NULL,
  `joined_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `duration_minutes` int DEFAULT NULL,
  `status` enum('joined','left','absent') DEFAULT 'joined'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `meeting_attendance`
--

INSERT INTO `meeting_attendance` (`id`, `meeting_id`, `student_id`, `lecturer_id`, `joined_at`, `duration_minutes`, `status`) VALUES
(1, 6, 3, NULL, '2025-11-21 11:29:20', NULL, 'left'),
(2, 6, 3, NULL, '2025-11-21 11:29:32', NULL, 'left'),
(3, 6, 3, NULL, '2025-11-21 11:29:34', NULL, 'left'),
(4, 6, 3, NULL, '2025-11-21 11:29:35', NULL, 'left'),
(5, 6, 3, NULL, '2025-11-21 11:29:35', NULL, 'left'),
(6, 6, 3, NULL, '2025-11-21 11:29:37', NULL, 'left'),
(7, 6, 3, NULL, '2025-11-21 11:29:38', NULL, 'left'),
(8, 6, 3, NULL, '2025-11-21 11:29:38', NULL, 'left'),
(9, 6, 3, NULL, '2025-11-21 11:29:40', NULL, 'left'),
(10, 6, 3, NULL, '2025-11-21 11:29:41', NULL, 'left'),
(11, 6, 3, NULL, '2025-11-21 11:29:41', NULL, 'left'),
(12, 6, 3, NULL, '2025-11-21 11:29:43', NULL, 'left'),
(13, 6, 3, NULL, '2025-11-21 11:29:44', NULL, 'left'),
(14, 6, 3, NULL, '2025-11-21 11:29:44', NULL, 'left'),
(15, 6, 3, NULL, '2025-11-21 11:29:46', NULL, 'left'),
(16, 6, 3, NULL, '2025-11-21 11:29:47', NULL, 'left'),
(17, 6, 3, NULL, '2025-11-21 11:29:47', NULL, 'left'),
(18, 6, 3, NULL, '2025-11-21 11:29:49', NULL, 'left'),
(19, 6, 3, NULL, '2025-11-21 11:29:50', NULL, 'left'),
(20, 6, 3, NULL, '2025-11-21 11:29:50', NULL, 'left'),
(21, 6, 3, NULL, '2025-11-21 11:29:52', NULL, 'left'),
(22, 6, 3, NULL, '2025-11-21 11:29:53', NULL, 'left'),
(23, 6, 3, NULL, '2025-11-21 11:29:53', NULL, 'left'),
(24, 6, 3, NULL, '2025-11-21 11:29:55', NULL, 'left'),
(25, 6, 3, NULL, '2025-11-21 11:29:56', NULL, 'left'),
(26, 6, 3, NULL, '2025-11-21 11:29:56', NULL, 'left'),
(27, 6, 3, NULL, '2025-11-21 11:29:58', NULL, 'left'),
(28, 6, 3, NULL, '2025-11-21 11:29:59', NULL, 'left'),
(29, 6, 3, NULL, '2025-11-21 11:29:59', NULL, 'left'),
(30, 6, 3, NULL, '2025-11-21 11:30:01', NULL, 'left'),
(31, 6, 3, NULL, '2025-11-21 11:30:02', NULL, 'left'),
(32, 6, 3, NULL, '2025-11-21 11:30:02', NULL, 'left'),
(33, 6, 3, NULL, '2025-11-21 11:30:04', NULL, 'left'),
(34, 6, 3, NULL, '2025-11-21 11:30:05', NULL, 'left'),
(35, 6, 3, NULL, '2025-11-21 11:30:05', NULL, 'left'),
(36, 6, 3, NULL, '2025-11-21 11:30:07', NULL, 'left'),
(37, 6, 3, NULL, '2025-11-21 11:30:08', NULL, 'left'),
(38, 6, 3, NULL, '2025-11-21 11:30:08', NULL, 'left'),
(39, 6, 3, NULL, '2025-11-21 11:30:10', NULL, 'left'),
(40, 6, 3, NULL, '2025-11-21 11:30:11', NULL, 'left'),
(41, 6, 3, NULL, '2025-11-21 11:30:11', NULL, 'left'),
(42, 6, 3, NULL, '2025-11-21 11:30:13', NULL, 'left'),
(43, 6, 3, NULL, '2025-11-21 11:30:14', NULL, 'left'),
(44, 6, 3, NULL, '2025-11-21 11:30:14', NULL, 'left'),
(45, 6, 3, NULL, '2025-11-21 11:30:16', NULL, 'left'),
(46, 6, 3, NULL, '2025-11-21 11:30:17', NULL, 'left'),
(47, 6, 3, NULL, '2025-11-21 11:30:17', NULL, 'left'),
(48, 6, 3, NULL, '2025-11-21 11:30:19', NULL, 'left'),
(49, 6, 3, NULL, '2025-11-21 11:30:20', NULL, 'left'),
(50, 6, 3, NULL, '2025-11-21 11:30:20', NULL, 'left'),
(51, 6, 3, NULL, '2025-11-21 11:30:22', NULL, 'left'),
(52, 6, 3, NULL, '2025-11-21 11:30:23', NULL, 'left'),
(53, 6, 3, NULL, '2025-11-21 11:30:23', NULL, 'left'),
(54, 6, 3, NULL, '2025-11-21 11:30:25', NULL, 'left'),
(55, 6, 3, NULL, '2025-11-21 11:30:26', NULL, 'left'),
(56, 6, 3, NULL, '2025-11-21 11:30:26', NULL, 'left'),
(57, 6, 3, NULL, '2025-11-21 11:30:28', NULL, 'left'),
(58, 6, 3, NULL, '2025-11-21 11:30:29', NULL, 'left'),
(59, 6, 3, NULL, '2025-11-21 11:30:29', NULL, 'left'),
(60, 6, 3, NULL, '2025-11-21 11:30:31', NULL, 'left'),
(61, 6, 3, NULL, '2025-11-21 11:30:32', NULL, 'left'),
(62, 6, 3, NULL, '2025-11-21 11:30:32', NULL, 'left'),
(63, 6, 3, NULL, '2025-11-21 11:30:34', NULL, 'left'),
(64, 6, 3, NULL, '2025-11-21 11:30:35', NULL, 'left'),
(65, 6, 3, NULL, '2025-11-21 11:30:35', NULL, 'left'),
(66, 6, 3, NULL, '2025-11-21 11:30:37', NULL, 'left'),
(67, 6, 3, NULL, '2025-11-21 11:30:38', NULL, 'left'),
(68, 6, 3, NULL, '2025-11-21 11:30:38', NULL, 'left'),
(69, 6, 3, NULL, '2025-11-21 11:30:40', NULL, 'left'),
(70, 6, 3, NULL, '2025-11-21 11:30:41', NULL, 'left'),
(71, 6, 3, NULL, '2025-11-21 11:30:41', NULL, 'left'),
(72, 6, 3, NULL, '2025-11-21 11:30:43', NULL, 'left'),
(73, 6, 3, NULL, '2025-11-21 11:30:44', NULL, 'left'),
(74, 6, 3, NULL, '2025-11-21 11:30:44', NULL, 'left'),
(75, 6, 3, NULL, '2025-11-21 11:30:46', NULL, 'left'),
(76, 6, 3, NULL, '2025-11-21 11:30:47', NULL, 'left'),
(77, 6, 3, NULL, '2025-11-21 11:30:47', NULL, 'left'),
(78, 6, 3, NULL, '2025-11-21 11:30:49', NULL, 'left'),
(79, 6, 3, NULL, '2025-11-21 11:30:50', NULL, 'left'),
(80, 6, 3, NULL, '2025-11-21 11:30:50', NULL, 'left'),
(81, 6, 3, NULL, '2025-11-21 11:30:52', NULL, 'left'),
(82, 6, 3, NULL, '2025-11-21 11:30:53', NULL, 'left'),
(83, 6, 3, NULL, '2025-11-21 11:30:53', NULL, 'left'),
(84, 6, 3, NULL, '2025-11-21 11:30:55', NULL, 'left'),
(85, 6, 3, NULL, '2025-11-21 11:30:56', NULL, 'left'),
(86, 6, 3, NULL, '2025-11-21 11:30:56', NULL, 'left'),
(87, 6, 3, NULL, '2025-11-21 11:30:58', NULL, 'left'),
(88, 6, 3, NULL, '2025-11-21 11:30:59', NULL, 'left'),
(89, 6, 3, NULL, '2025-11-21 11:30:59', NULL, 'left'),
(90, 6, 3, NULL, '2025-11-21 11:31:01', NULL, 'left'),
(91, 6, 3, NULL, '2025-11-21 11:31:02', NULL, 'left'),
(92, 6, 3, NULL, '2025-11-21 11:31:02', NULL, 'left'),
(93, 6, 3, NULL, '2025-11-21 11:31:04', NULL, 'left'),
(94, 6, 3, NULL, '2025-11-21 11:31:05', NULL, 'left'),
(95, 6, 3, NULL, '2025-11-21 11:31:05', NULL, 'left'),
(96, 6, 3, NULL, '2025-11-21 11:31:07', NULL, 'left'),
(97, 6, 3, NULL, '2025-11-21 11:31:08', NULL, 'left'),
(98, 6, 3, NULL, '2025-11-21 11:31:08', NULL, 'left'),
(99, 6, 3, NULL, '2025-11-21 11:31:10', NULL, 'left'),
(100, 6, 3, NULL, '2025-11-21 11:31:11', NULL, 'left'),
(101, 6, 3, NULL, '2025-11-21 11:31:11', NULL, 'left'),
(102, 6, 3, NULL, '2025-11-21 11:31:13', NULL, 'left'),
(103, 6, 3, NULL, '2025-11-21 11:31:14', NULL, 'left'),
(104, 6, 3, NULL, '2025-11-21 11:31:14', NULL, 'left'),
(105, 6, 3, NULL, '2025-11-21 11:31:16', NULL, 'left'),
(106, 6, 3, NULL, '2025-11-21 11:31:17', NULL, 'left'),
(107, 6, 3, NULL, '2025-11-21 11:31:17', NULL, 'left'),
(108, 6, 3, NULL, '2025-11-21 11:31:19', NULL, 'left'),
(109, 6, 3, NULL, '2025-11-21 11:31:20', NULL, 'left'),
(110, 6, 3, NULL, '2025-11-21 11:31:20', NULL, 'left'),
(111, 6, 3, NULL, '2025-11-21 11:31:22', NULL, 'left'),
(112, 6, 3, NULL, '2025-11-21 11:31:23', NULL, 'left'),
(113, 6, 3, NULL, '2025-11-21 11:31:23', NULL, 'left'),
(114, 6, 3, NULL, '2025-11-21 11:31:25', NULL, 'left'),
(115, 6, 3, NULL, '2025-11-21 11:31:26', NULL, 'left'),
(116, 6, 3, NULL, '2025-11-21 11:31:26', NULL, 'left'),
(117, 6, 3, NULL, '2025-11-21 11:31:28', NULL, 'left'),
(118, 6, 3, NULL, '2025-11-21 11:31:29', NULL, 'left'),
(119, 6, 3, NULL, '2025-11-21 11:31:29', NULL, 'left'),
(120, 6, 3, NULL, '2025-11-21 11:31:31', NULL, 'left'),
(121, 6, 3, NULL, '2025-11-21 11:31:32', NULL, 'left'),
(122, 6, 3, NULL, '2025-11-21 11:31:32', NULL, 'left'),
(123, 6, 3, NULL, '2025-11-21 11:31:34', NULL, 'left'),
(124, 6, 3, NULL, '2025-11-21 11:31:35', NULL, 'left'),
(125, 6, 3, NULL, '2025-11-21 11:31:35', NULL, 'left'),
(126, 6, 3, NULL, '2025-11-21 11:31:37', NULL, 'left'),
(127, 6, 3, NULL, '2025-11-21 11:31:38', NULL, 'left'),
(128, 6, 3, NULL, '2025-11-21 11:31:38', NULL, 'left'),
(129, 6, 3, NULL, '2025-11-21 11:31:40', NULL, 'left'),
(130, 6, 3, NULL, '2025-11-21 11:31:41', NULL, 'left'),
(131, 6, 3, NULL, '2025-11-21 11:31:41', NULL, 'left'),
(132, 6, 3, NULL, '2025-11-21 11:31:43', NULL, 'left'),
(133, 6, 3, NULL, '2025-11-21 11:31:44', NULL, 'left'),
(134, 6, 3, NULL, '2025-11-21 11:31:44', NULL, 'left'),
(135, 6, 3, NULL, '2025-11-21 11:31:46', NULL, 'left'),
(136, 6, 3, NULL, '2025-11-21 11:31:47', NULL, 'left'),
(137, 6, 3, NULL, '2025-11-21 11:31:47', NULL, 'left'),
(138, 6, 3, NULL, '2025-11-21 11:31:49', NULL, 'left'),
(139, 6, 3, NULL, '2025-11-21 11:31:50', NULL, 'left'),
(140, 6, 3, NULL, '2025-11-21 11:31:50', NULL, 'left'),
(141, 6, 3, NULL, '2025-11-21 11:31:52', NULL, 'left'),
(142, 6, 3, NULL, '2025-11-21 11:31:53', NULL, 'left'),
(143, 6, 3, NULL, '2025-11-21 11:31:53', NULL, 'left'),
(144, 6, 3, NULL, '2025-11-21 11:31:55', NULL, 'left'),
(145, 6, 3, NULL, '2025-11-21 11:31:56', NULL, 'left'),
(146, 6, 3, NULL, '2025-11-21 11:31:56', NULL, 'left'),
(147, 6, 3, NULL, '2025-11-21 11:31:58', NULL, 'left'),
(148, 6, 3, NULL, '2025-11-21 11:31:59', NULL, 'left'),
(149, 6, 3, NULL, '2025-11-21 11:31:59', NULL, 'left'),
(150, 6, 3, NULL, '2025-11-21 11:32:01', NULL, 'left'),
(151, 6, 3, NULL, '2025-11-21 11:32:02', NULL, 'left'),
(152, 6, 3, NULL, '2025-11-21 11:32:02', NULL, 'left'),
(153, 6, 3, NULL, '2025-11-21 11:32:04', NULL, 'left'),
(154, 6, 3, NULL, '2025-11-21 11:32:05', NULL, 'left'),
(155, 6, 3, NULL, '2025-11-21 11:32:05', NULL, 'left'),
(156, 6, 3, NULL, '2025-11-21 11:32:07', NULL, 'left'),
(157, 6, 3, NULL, '2025-11-21 11:32:08', NULL, 'left'),
(158, 6, 3, NULL, '2025-11-21 11:32:08', NULL, 'left'),
(159, 6, 3, NULL, '2025-11-21 11:32:10', NULL, 'left'),
(160, 6, 3, NULL, '2025-11-21 11:32:11', NULL, 'left'),
(161, 6, 3, NULL, '2025-11-21 11:32:11', NULL, 'left'),
(162, 6, 3, NULL, '2025-11-21 11:32:13', NULL, 'left'),
(163, 6, 3, NULL, '2025-11-21 11:32:14', NULL, 'left'),
(164, 6, 3, NULL, '2025-11-21 11:32:14', NULL, 'left'),
(165, 6, 3, NULL, '2025-11-21 11:32:16', NULL, 'left'),
(166, 6, 3, NULL, '2025-11-21 11:32:17', NULL, 'left'),
(167, 6, 3, NULL, '2025-11-21 11:32:17', NULL, 'left'),
(168, 6, 3, NULL, '2025-11-21 11:32:19', NULL, 'left'),
(169, 6, 3, NULL, '2025-11-21 11:32:20', NULL, 'left'),
(170, 6, 3, NULL, '2025-11-21 11:32:20', NULL, 'left'),
(171, 6, 3, NULL, '2025-11-21 11:32:22', NULL, 'left'),
(172, 6, 3, NULL, '2025-11-21 11:32:23', NULL, 'left'),
(173, 6, 3, NULL, '2025-11-21 11:32:23', NULL, 'left'),
(174, 6, 3, NULL, '2025-11-21 11:32:25', NULL, 'left'),
(175, 6, 3, NULL, '2025-11-21 11:32:26', NULL, 'left'),
(176, 6, 3, NULL, '2025-11-21 11:32:26', NULL, 'left'),
(177, 6, 3, NULL, '2025-11-21 11:32:28', NULL, 'left'),
(178, 6, 3, NULL, '2025-11-21 11:32:29', NULL, 'left'),
(179, 6, 3, NULL, '2025-11-21 11:32:29', NULL, 'left'),
(180, 6, 3, NULL, '2025-11-21 11:32:31', NULL, 'left'),
(181, 6, 3, NULL, '2025-11-21 11:32:32', NULL, 'left'),
(182, 6, 3, NULL, '2025-11-21 11:32:32', NULL, 'left'),
(183, 6, 3, NULL, '2025-11-21 11:32:34', NULL, 'left'),
(184, 6, 3, NULL, '2025-11-21 11:32:35', NULL, 'left'),
(185, 6, 3, NULL, '2025-11-21 11:32:35', NULL, 'left'),
(186, 6, 3, NULL, '2025-11-21 11:32:37', NULL, 'left'),
(187, 6, 3, NULL, '2025-11-21 11:32:38', NULL, 'left'),
(188, 6, 3, NULL, '2025-11-21 11:32:38', NULL, 'left'),
(189, 6, 3, NULL, '2025-11-21 11:32:40', NULL, 'left'),
(190, 6, 3, NULL, '2025-11-21 11:32:42', NULL, 'left'),
(191, 6, 3, NULL, '2025-11-21 11:32:42', NULL, 'left'),
(192, 6, 3, NULL, '2025-11-21 11:32:43', NULL, 'left'),
(193, 6, 3, NULL, '2025-11-21 11:32:44', NULL, 'left'),
(194, 6, 3, NULL, '2025-11-21 11:32:44', NULL, 'left'),
(195, 6, 3, NULL, '2025-11-21 11:32:46', NULL, 'left'),
(196, 6, 3, NULL, '2025-11-21 11:32:47', NULL, 'left'),
(197, 6, 3, NULL, '2025-11-21 11:32:47', NULL, 'left'),
(198, 6, 3, NULL, '2025-11-21 11:32:49', NULL, 'left'),
(199, 6, 3, NULL, '2025-11-21 11:32:50', NULL, 'left'),
(200, 6, 3, NULL, '2025-11-21 11:32:50', NULL, 'left'),
(201, 6, 3, NULL, '2025-11-21 11:32:52', NULL, 'left'),
(202, 6, 3, NULL, '2025-11-21 11:32:53', NULL, 'left'),
(203, 6, 3, NULL, '2025-11-21 11:32:53', NULL, 'left'),
(204, 6, 3, NULL, '2025-11-21 11:32:55', NULL, 'left'),
(205, 6, 3, NULL, '2025-11-21 11:32:56', NULL, 'left'),
(206, 6, 3, NULL, '2025-11-21 11:32:56', NULL, 'left'),
(207, 6, 3, NULL, '2025-11-21 11:32:58', NULL, 'left'),
(208, 6, 3, NULL, '2025-11-21 11:32:59', NULL, 'left'),
(209, 6, 3, NULL, '2025-11-21 11:32:59', NULL, 'left'),
(210, 6, 3, NULL, '2025-11-21 11:33:01', NULL, 'left'),
(211, 6, 3, NULL, '2025-11-21 11:33:02', NULL, 'left'),
(212, 6, 3, NULL, '2025-11-21 11:33:02', NULL, 'left'),
(213, 6, 3, NULL, '2025-11-21 11:33:04', NULL, 'left'),
(214, 6, 3, NULL, '2025-11-21 11:33:06', NULL, 'left'),
(215, 6, 3, NULL, '2025-11-21 11:33:06', NULL, 'left'),
(216, 6, 3, NULL, '2025-11-21 11:33:07', NULL, 'left'),
(217, 6, 3, NULL, '2025-11-21 11:33:08', NULL, 'left'),
(218, 6, 3, NULL, '2025-11-21 11:33:08', NULL, 'left'),
(219, 6, 3, NULL, '2025-11-21 11:33:11', NULL, 'left'),
(220, 6, 3, NULL, '2025-11-21 11:33:11', NULL, 'left'),
(221, 6, 3, NULL, '2025-11-21 11:33:11', NULL, 'left'),
(222, 6, 3, NULL, '2025-11-21 11:33:13', NULL, 'left'),
(223, 6, 3, NULL, '2025-11-21 11:33:14', NULL, 'left'),
(224, 6, 3, NULL, '2025-11-21 11:33:14', NULL, 'left'),
(225, 6, 3, NULL, '2025-11-21 11:33:16', NULL, 'left'),
(226, 6, 3, NULL, '2025-11-21 11:33:17', NULL, 'left'),
(227, 6, 3, NULL, '2025-11-21 11:33:17', NULL, 'left'),
(228, 6, 3, NULL, '2025-11-21 11:33:19', NULL, 'left'),
(229, 6, 3, NULL, '2025-11-21 11:33:20', NULL, 'left'),
(230, 6, 3, NULL, '2025-11-21 11:33:20', NULL, 'left'),
(231, 6, 3, NULL, '2025-11-21 11:33:22', NULL, 'left'),
(232, 6, 3, NULL, '2025-11-21 11:33:23', NULL, 'left'),
(233, 6, 3, NULL, '2025-11-21 11:33:23', NULL, 'left'),
(234, 6, 3, NULL, '2025-11-21 11:33:25', NULL, 'left'),
(235, 6, 3, NULL, '2025-11-21 11:33:26', NULL, 'left'),
(236, 6, 3, NULL, '2025-11-21 11:33:26', NULL, 'left'),
(237, 6, 3, NULL, '2025-11-21 11:33:28', NULL, 'left'),
(238, 6, 3, NULL, '2025-11-21 11:33:29', NULL, 'left'),
(239, 6, 3, NULL, '2025-11-21 11:33:29', NULL, 'left'),
(240, 6, 3, NULL, '2025-11-21 11:33:31', NULL, 'left'),
(241, 6, 3, NULL, '2025-11-21 11:33:57', NULL, 'left'),
(242, 6, 3, NULL, '2025-11-21 11:33:57', NULL, 'left'),
(243, 6, 3, NULL, '2025-11-21 11:34:58', NULL, 'left'),
(244, 6, 3, NULL, '2025-11-21 11:34:58', NULL, 'left'),
(245, 6, 3, NULL, '2025-11-21 11:35:58', NULL, 'left'),
(246, 6, 3, NULL, '2025-11-21 11:35:58', NULL, 'left'),
(247, 6, 3, NULL, '2025-11-21 11:36:58', NULL, 'left'),
(248, 6, 3, NULL, '2025-11-21 11:36:58', NULL, 'left'),
(249, 6, 3, NULL, '2025-11-21 11:37:58', NULL, 'left'),
(250, 6, 3, NULL, '2025-11-21 11:37:58', NULL, 'left'),
(251, 6, 3, NULL, '2025-11-21 11:38:58', NULL, 'left'),
(252, 6, 3, NULL, '2025-11-21 11:38:58', NULL, 'left'),
(253, 6, 3, NULL, '2025-11-21 11:39:58', NULL, 'left'),
(254, 6, 3, NULL, '2025-11-21 11:39:58', NULL, 'left'),
(255, 6, 3, NULL, '2025-11-21 11:40:57', NULL, 'left'),
(256, 6, 3, NULL, '2025-11-21 11:40:57', NULL, 'left'),
(257, 6, 3, NULL, '2025-11-21 12:14:20', NULL, 'left'),
(258, 6, 3, NULL, '2025-11-21 12:14:25', NULL, 'left'),
(259, 6, 3, NULL, '2025-11-21 12:14:26', NULL, 'left'),
(260, 6, 3, NULL, '2025-11-21 12:14:28', NULL, 'left'),
(261, 6, 3, NULL, '2025-11-21 12:14:28', NULL, 'left'),
(262, 6, 3, NULL, '2025-11-21 12:14:29', NULL, 'left'),
(263, 6, 3, NULL, '2025-11-21 12:14:31', NULL, 'left'),
(264, 6, 3, NULL, '2025-11-21 12:14:31', NULL, 'left'),
(265, 6, 3, NULL, '2025-11-21 12:14:32', NULL, 'left'),
(266, 6, 3, NULL, '2025-11-21 12:14:34', NULL, 'left'),
(267, 6, 3, NULL, '2025-11-21 12:14:34', NULL, 'left'),
(268, 6, 3, NULL, '2025-11-21 12:14:36', NULL, 'left'),
(269, 6, 3, NULL, '2025-11-21 12:14:37', NULL, 'left'),
(270, 6, 3, NULL, '2025-11-21 12:14:37', NULL, 'left'),
(271, 6, 3, NULL, '2025-11-21 12:14:38', NULL, 'left'),
(272, 6, 3, NULL, '2025-11-21 12:14:40', NULL, 'left'),
(273, 6, 3, NULL, '2025-11-21 12:14:40', NULL, 'left'),
(274, 6, 3, NULL, '2025-11-21 12:14:41', NULL, 'left'),
(275, 6, 3, NULL, '2025-11-21 12:14:43', NULL, 'left'),
(276, 6, 3, NULL, '2025-11-21 12:14:43', NULL, 'left'),
(277, 6, 3, NULL, '2025-11-21 12:14:44', NULL, 'left'),
(278, 6, 3, NULL, '2025-11-21 12:14:46', NULL, 'left'),
(279, 6, 3, NULL, '2025-11-21 12:14:46', NULL, 'left'),
(280, 6, 3, NULL, '2025-11-21 12:14:47', NULL, 'left'),
(281, 6, 3, NULL, '2025-11-21 12:14:47', NULL, 'left'),
(282, 6, 3, NULL, '2025-11-21 12:14:47', NULL, 'left'),
(283, 6, 3, NULL, '2025-11-21 12:14:53', NULL, 'left'),
(284, 6, 3, NULL, '2025-11-21 12:14:55', NULL, 'left'),
(285, 6, 3, NULL, '2025-11-21 12:14:56', NULL, 'left'),
(286, 6, 3, NULL, '2025-11-21 12:14:56', NULL, 'left'),
(287, 6, 3, NULL, '2025-11-21 12:14:58', NULL, 'left'),
(288, 6, 3, NULL, '2025-11-21 12:14:59', NULL, 'left'),
(289, 6, 3, NULL, '2025-11-21 12:14:59', NULL, 'left'),
(290, 6, 3, NULL, '2025-11-21 12:15:01', NULL, 'left'),
(291, 6, 3, NULL, '2025-11-21 12:15:02', NULL, 'left'),
(292, 6, 3, NULL, '2025-11-21 12:15:02', NULL, 'left'),
(293, 6, 3, NULL, '2025-11-21 12:15:04', NULL, 'left'),
(294, 6, 3, NULL, '2025-11-21 12:15:05', NULL, 'left'),
(295, 6, 3, NULL, '2025-11-21 12:15:05', NULL, 'left'),
(296, 6, 3, NULL, '2025-11-21 12:15:07', NULL, 'left'),
(297, 6, 3, NULL, '2025-11-21 12:15:08', NULL, 'left'),
(298, 6, 3, NULL, '2025-11-21 12:15:08', NULL, 'left'),
(299, 6, 3, NULL, '2025-11-21 12:15:10', NULL, 'left'),
(300, 6, 3, NULL, '2025-11-21 12:15:11', NULL, 'left'),
(301, 6, 3, NULL, '2025-11-21 12:15:11', NULL, 'left'),
(302, 6, 3, NULL, '2025-11-21 12:15:13', NULL, 'left'),
(303, 6, 3, NULL, '2025-11-21 12:15:14', NULL, 'left'),
(304, 6, 3, NULL, '2025-11-21 12:15:14', NULL, 'left'),
(305, 6, 3, NULL, '2025-11-21 12:15:16', NULL, 'left'),
(306, 6, 3, NULL, '2025-11-21 12:15:18', NULL, 'left'),
(307, 6, 3, NULL, '2025-11-21 12:15:20', NULL, 'left'),
(308, 6, 3, NULL, '2025-11-21 12:15:20', NULL, 'left'),
(309, 6, 3, NULL, '2025-11-21 12:15:21', NULL, 'left'),
(310, 6, 3, NULL, '2025-11-21 12:15:23', NULL, 'left'),
(311, 6, 3, NULL, '2025-11-21 12:15:23', NULL, 'left'),
(312, 6, 3, NULL, '2025-11-21 12:15:24', NULL, 'left'),
(313, 6, 3, NULL, '2025-11-21 12:15:26', NULL, 'left'),
(314, 6, 3, NULL, '2025-11-21 12:15:26', NULL, 'left'),
(315, 6, 3, NULL, '2025-11-21 12:15:27', NULL, 'left'),
(316, 6, 3, NULL, '2025-11-21 12:15:29', NULL, 'left'),
(317, 6, 3, NULL, '2025-11-21 12:15:29', NULL, 'left'),
(318, 6, 3, NULL, '2025-11-21 12:15:30', NULL, 'left'),
(319, 6, 3, NULL, '2025-11-21 12:15:32', NULL, 'left'),
(320, 6, 3, NULL, '2025-11-21 12:15:32', NULL, 'left'),
(321, 6, 3, NULL, '2025-11-21 12:15:33', NULL, 'left'),
(322, 6, 3, NULL, '2025-11-21 12:15:35', NULL, 'left'),
(323, 6, 3, NULL, '2025-11-21 12:15:35', NULL, 'left'),
(324, 6, 3, NULL, '2025-11-21 12:15:36', NULL, 'left'),
(325, 6, 3, NULL, '2025-11-21 12:15:38', NULL, 'left'),
(326, 6, 3, NULL, '2025-11-21 12:15:38', NULL, 'left'),
(327, 6, 3, NULL, '2025-11-21 12:15:39', NULL, 'left'),
(328, 6, 3, NULL, '2025-11-21 12:15:41', NULL, 'left'),
(329, 6, 3, NULL, '2025-11-21 12:15:41', NULL, 'left'),
(330, 6, 3, NULL, '2025-11-21 12:15:42', NULL, 'left'),
(331, 6, 3, NULL, '2025-11-21 12:15:44', NULL, 'left'),
(332, 6, 3, NULL, '2025-11-21 12:15:44', NULL, 'left'),
(333, 6, 3, NULL, '2025-11-21 12:15:45', NULL, 'left'),
(334, 6, 3, NULL, '2025-11-21 12:15:47', NULL, 'left'),
(335, 6, 3, NULL, '2025-11-21 12:15:47', NULL, 'left'),
(336, 6, 3, NULL, '2025-11-21 12:15:48', NULL, 'left'),
(337, 6, 3, NULL, '2025-11-21 12:15:50', NULL, 'left'),
(338, 6, 3, NULL, '2025-11-21 12:15:50', NULL, 'left'),
(339, 6, 3, NULL, '2025-11-21 12:15:51', NULL, 'left'),
(340, 6, 3, NULL, '2025-11-21 12:15:54', NULL, 'left'),
(341, 6, 3, NULL, '2025-11-21 12:15:54', NULL, 'left'),
(342, 6, 3, NULL, '2025-11-21 12:15:54', NULL, 'left'),
(343, 6, 3, NULL, '2025-11-21 12:15:56', NULL, 'left'),
(344, 6, 3, NULL, '2025-11-21 12:15:56', NULL, 'left'),
(345, 6, 3, NULL, '2025-11-21 12:15:58', NULL, 'left'),
(346, 6, 3, NULL, '2025-11-21 12:16:00', NULL, 'left'),
(347, 6, 3, NULL, '2025-11-21 12:16:00', NULL, 'left'),
(348, 6, 3, NULL, '2025-11-21 12:16:01', NULL, 'left'),
(349, 6, 3, NULL, '2025-11-21 12:16:02', NULL, 'left'),
(350, 6, 3, NULL, '2025-11-21 12:16:02', NULL, 'left'),
(351, 6, 3, NULL, '2025-11-21 12:16:03', NULL, 'left'),
(352, 6, 3, NULL, '2025-11-21 12:16:06', NULL, 'left'),
(353, 6, 3, NULL, '2025-11-21 12:16:06', NULL, 'left'),
(354, 6, 3, NULL, '2025-11-21 12:16:07', NULL, 'left'),
(355, 6, 3, NULL, '2025-11-21 12:16:09', NULL, 'left'),
(356, 6, 3, NULL, '2025-11-21 12:16:09', NULL, 'left'),
(357, 6, 3, NULL, '2025-11-21 12:16:10', NULL, 'left'),
(358, 6, 3, NULL, '2025-11-21 12:16:12', NULL, 'left'),
(359, 6, 3, NULL, '2025-11-21 12:16:12', NULL, 'left'),
(360, 6, 3, NULL, '2025-11-21 12:16:12', NULL, 'left'),
(361, 6, 3, NULL, '2025-11-21 12:16:14', NULL, 'left'),
(362, 6, 3, NULL, '2025-11-21 12:16:14', NULL, 'left'),
(363, 6, 3, NULL, '2025-11-21 12:16:15', NULL, 'left'),
(364, 6, 3, NULL, '2025-11-21 12:16:17', NULL, 'left'),
(365, 6, 3, NULL, '2025-11-21 12:16:17', NULL, 'left'),
(366, 6, 3, NULL, '2025-11-21 12:16:18', NULL, 'left'),
(367, 6, 3, NULL, '2025-11-21 12:16:20', NULL, 'left'),
(368, 6, 3, NULL, '2025-11-21 12:16:20', NULL, 'left'),
(369, 6, 3, NULL, '2025-11-21 12:16:21', NULL, 'left'),
(370, 6, 3, NULL, '2025-11-21 12:16:23', NULL, 'left'),
(371, 6, 3, NULL, '2025-11-21 12:16:23', NULL, 'left'),
(372, 6, 3, NULL, '2025-11-21 12:16:24', NULL, 'left'),
(373, 6, 3, NULL, '2025-11-21 12:16:27', NULL, 'left'),
(374, 6, 3, NULL, '2025-11-21 12:16:27', NULL, 'left'),
(375, 6, 3, NULL, '2025-11-21 12:16:27', NULL, 'left'),
(376, 6, 3, NULL, '2025-11-21 12:16:58', NULL, 'left'),
(377, 6, 3, NULL, '2025-11-21 12:16:58', NULL, 'left'),
(378, 6, 3, NULL, '2025-11-21 12:17:57', NULL, 'left'),
(379, 6, 3, NULL, '2025-11-21 12:17:58', NULL, 'left'),
(380, 6, 3, NULL, '2025-11-21 12:18:57', NULL, 'left'),
(381, 6, 3, NULL, '2025-11-21 12:18:57', NULL, 'left'),
(382, 6, 3, NULL, '2025-11-21 12:19:57', NULL, 'left'),
(383, 6, 3, NULL, '2025-11-21 12:19:57', NULL, 'left'),
(384, 6, 3, NULL, '2025-11-21 12:20:14', NULL, 'left'),
(385, 6, 3, NULL, '2025-11-21 12:20:14', NULL, 'left'),
(386, 6, 3, NULL, '2025-11-21 12:20:15', NULL, 'left'),
(387, 6, 3, NULL, '2025-11-21 12:20:17', NULL, 'left'),
(388, 6, 3, NULL, '2025-11-21 12:20:17', NULL, 'left'),
(389, 6, 3, NULL, '2025-11-21 12:20:18', NULL, 'left'),
(390, 6, 3, NULL, '2025-11-21 12:20:20', NULL, 'left'),
(391, 6, 3, NULL, '2025-11-21 12:20:20', NULL, 'left'),
(392, 6, 3, NULL, '2025-11-21 12:20:22', NULL, 'left'),
(393, 6, 3, NULL, '2025-11-21 12:20:23', NULL, 'left'),
(394, 6, 3, NULL, '2025-11-21 12:20:24', NULL, 'left'),
(395, 6, 3, NULL, '2025-11-21 12:20:25', NULL, 'left'),
(396, 6, 3, NULL, '2025-11-21 12:20:27', NULL, 'left'),
(397, 6, 3, NULL, '2025-11-21 12:20:27', NULL, 'left'),
(398, 6, 3, NULL, '2025-11-21 12:20:28', NULL, 'left'),
(399, 6, 3, NULL, '2025-11-21 12:20:30', NULL, 'left'),
(400, 6, 3, NULL, '2025-11-21 12:20:30', NULL, 'left'),
(401, 6, 3, NULL, '2025-11-21 12:20:31', NULL, 'left'),
(402, 6, 3, NULL, '2025-11-21 12:20:33', NULL, 'left'),
(403, 6, 3, NULL, '2025-11-21 12:20:33', NULL, 'left'),
(404, 6, 3, NULL, '2025-11-21 12:20:33', NULL, 'left'),
(405, 6, 3, NULL, '2025-11-21 12:20:35', NULL, 'left'),
(406, 6, 3, NULL, '2025-11-21 12:20:35', NULL, 'left'),
(407, 6, 3, NULL, '2025-11-21 12:20:36', NULL, 'left'),
(408, 6, 3, NULL, '2025-11-21 12:20:39', NULL, 'left'),
(409, 6, 3, NULL, '2025-11-21 12:20:39', NULL, 'left'),
(410, 6, 3, NULL, '2025-11-21 12:20:39', NULL, 'left'),
(411, 6, 3, NULL, '2025-11-21 12:20:41', NULL, 'left'),
(412, 6, 3, NULL, '2025-11-21 12:20:41', NULL, 'left'),
(413, 6, 3, NULL, '2025-11-21 12:20:42', NULL, 'left'),
(414, 6, 3, NULL, '2025-11-21 12:20:44', NULL, 'left'),
(415, 6, 3, NULL, '2025-11-21 12:20:44', NULL, 'left'),
(416, 6, 3, NULL, '2025-11-21 12:20:45', NULL, 'left'),
(417, 6, 3, NULL, '2025-11-21 12:20:47', NULL, 'left'),
(418, 6, 3, NULL, '2025-11-21 12:20:47', NULL, 'left'),
(419, 6, 3, NULL, '2025-11-21 12:20:49', NULL, 'left'),
(420, 6, 3, NULL, '2025-11-21 12:20:50', NULL, 'left'),
(421, 6, 3, NULL, '2025-11-21 12:20:50', NULL, 'left'),
(422, 6, 3, NULL, '2025-11-21 12:20:52', NULL, 'left'),
(423, 6, 3, NULL, '2025-11-21 12:20:53', NULL, 'left'),
(424, 6, 3, NULL, '2025-11-21 12:20:53', NULL, 'left'),
(425, 6, 3, NULL, '2025-11-21 12:20:55', NULL, 'left'),
(426, 6, 3, NULL, '2025-11-21 12:20:56', NULL, 'left'),
(427, 6, 3, NULL, '2025-11-21 12:20:57', NULL, 'left'),
(428, 6, 3, NULL, '2025-11-21 12:20:58', NULL, 'left'),
(429, 6, 3, NULL, '2025-11-21 12:20:59', NULL, 'left'),
(430, 6, 3, NULL, '2025-11-21 12:21:00', NULL, 'left'),
(431, 6, 3, NULL, '2025-11-21 12:21:01', NULL, 'left'),
(432, 6, 3, NULL, '2025-11-21 12:21:02', NULL, 'left'),
(433, 6, 3, NULL, '2025-11-21 12:21:02', NULL, 'left'),
(434, 6, 3, NULL, '2025-11-21 12:21:03', NULL, 'left'),
(435, 6, 3, NULL, '2025-11-21 12:21:05', NULL, 'left'),
(436, 6, 3, NULL, '2025-11-21 12:21:05', NULL, 'left'),
(437, 6, 3, NULL, '2025-11-21 12:21:06', NULL, 'left'),
(438, 6, 3, NULL, '2025-11-21 12:21:08', NULL, 'left'),
(439, 6, 3, NULL, '2025-11-21 12:21:08', NULL, 'left'),
(440, 6, 3, NULL, '2025-11-21 12:21:09', NULL, 'left'),
(441, 6, 3, NULL, '2025-11-21 12:21:12', NULL, 'left'),
(442, 6, 3, NULL, '2025-11-21 12:21:12', NULL, 'left'),
(443, 6, 3, NULL, '2025-11-21 12:21:12', NULL, 'left'),
(444, 6, 3, NULL, '2025-11-21 12:21:14', NULL, 'left'),
(445, 6, 3, NULL, '2025-11-21 12:21:15', NULL, 'left'),
(446, 6, 3, NULL, '2025-11-21 12:21:15', NULL, 'left'),
(447, 6, 3, NULL, '2025-11-21 12:21:18', NULL, 'left'),
(448, 6, 3, NULL, '2025-11-21 12:21:18', NULL, 'left'),
(449, 6, 3, NULL, '2025-11-21 12:21:19', NULL, 'left'),
(450, 6, 3, NULL, '2025-11-21 12:21:59', NULL, 'left'),
(451, 6, 3, NULL, '2025-11-21 12:21:59', NULL, 'left'),
(452, 6, 3, NULL, '2025-11-21 12:22:58', NULL, 'left'),
(453, 6, 3, NULL, '2025-11-21 12:22:58', NULL, 'left'),
(454, 6, 3, NULL, '2025-11-21 12:23:58', NULL, 'left'),
(455, 6, 3, NULL, '2025-11-21 12:23:58', NULL, 'left'),
(456, 6, 3, NULL, '2025-11-21 12:24:57', NULL, 'left'),
(457, 6, 3, NULL, '2025-11-21 12:24:57', NULL, 'left'),
(458, 6, 3, NULL, '2025-11-21 12:25:57', NULL, 'left'),
(459, 6, 3, NULL, '2025-11-21 12:25:57', NULL, 'left'),
(460, 6, 3, NULL, '2025-11-21 12:26:57', NULL, 'left'),
(461, 6, 3, NULL, '2025-11-21 12:26:57', NULL, 'left'),
(462, 6, 3, NULL, '2025-11-21 12:27:57', NULL, 'left'),
(463, 6, 3, NULL, '2025-11-21 12:27:57', NULL, 'left'),
(464, 6, 3, NULL, '2025-11-21 12:28:57', NULL, 'left'),
(465, 6, 3, NULL, '2025-11-21 12:28:57', NULL, 'left'),
(466, 6, 3, NULL, '2025-11-21 12:29:57', NULL, 'left'),
(467, 6, 3, NULL, '2025-11-21 12:29:57', NULL, 'left'),
(468, 6, 3, NULL, '2025-11-21 12:30:57', NULL, 'left'),
(469, 6, 3, NULL, '2025-11-21 12:30:57', NULL, 'left'),
(470, 6, 3, NULL, '2025-11-21 12:31:57', NULL, 'left'),
(471, 6, 3, NULL, '2025-11-21 12:31:57', NULL, 'left'),
(472, 6, 3, NULL, '2025-11-21 12:32:57', NULL, 'left'),
(473, 6, 3, NULL, '2025-11-21 12:32:57', NULL, 'left'),
(474, 6, 3, NULL, '2025-11-21 12:33:57', NULL, 'left'),
(475, 6, 3, NULL, '2025-11-21 12:33:57', NULL, 'left'),
(476, 6, 3, NULL, '2025-11-21 12:34:57', NULL, 'left'),
(477, 6, 3, NULL, '2025-11-21 12:34:57', NULL, 'left'),
(478, 6, 3, NULL, '2025-11-21 12:35:58', NULL, 'left'),
(479, 6, 3, NULL, '2025-11-21 12:35:58', NULL, 'left'),
(480, 6, 3, NULL, '2025-11-21 12:36:57', NULL, 'left'),
(481, 6, 3, NULL, '2025-11-21 12:36:57', NULL, 'left'),
(482, 6, 3, NULL, '2025-11-21 12:37:57', NULL, 'left'),
(483, 6, 3, NULL, '2025-11-21 12:37:57', NULL, 'left'),
(484, 6, 3, NULL, '2025-11-21 12:38:57', NULL, 'left'),
(485, 6, 3, NULL, '2025-11-21 12:38:57', NULL, 'left'),
(486, 6, 3, NULL, '2025-11-21 12:39:57', NULL, 'left'),
(487, 6, 3, NULL, '2025-11-21 12:39:57', NULL, 'left'),
(488, 6, 3, NULL, '2025-11-21 12:40:57', NULL, 'left'),
(489, 6, 3, NULL, '2025-11-21 12:40:57', NULL, 'left'),
(490, 6, 3, NULL, '2025-11-21 12:41:58', NULL, 'left'),
(491, 6, 3, NULL, '2025-11-21 12:41:58', NULL, 'left'),
(492, 6, 3, NULL, '2025-11-21 12:42:57', NULL, 'left'),
(493, 6, 3, NULL, '2025-11-21 12:42:57', NULL, 'left'),
(494, 6, 3, NULL, '2025-11-21 12:43:57', NULL, 'left'),
(495, 6, 3, NULL, '2025-11-21 12:43:57', NULL, 'left'),
(496, 6, 3, NULL, '2025-11-21 12:44:57', NULL, 'left'),
(497, 6, 3, NULL, '2025-11-21 12:44:57', NULL, 'left'),
(498, 6, 3, NULL, '2025-11-21 12:45:57', NULL, 'left'),
(499, 6, 3, NULL, '2025-11-21 12:45:57', NULL, 'left'),
(500, 6, 3, NULL, '2025-11-21 12:46:57', NULL, 'left'),
(501, 6, 3, NULL, '2025-11-21 12:46:58', NULL, 'left'),
(502, 6, 3, NULL, '2025-11-21 12:47:57', NULL, 'left'),
(503, 6, 3, NULL, '2025-11-21 12:47:57', NULL, 'left'),
(504, 6, 3, NULL, '2025-11-21 12:48:57', NULL, 'left'),
(505, 6, 3, NULL, '2025-11-21 12:48:57', NULL, 'left'),
(506, 6, 3, NULL, '2025-11-21 12:49:57', NULL, 'left'),
(507, 6, 3, NULL, '2025-11-21 12:49:58', NULL, 'left'),
(508, 6, 3, NULL, '2025-11-21 12:50:58', NULL, 'left'),
(509, 6, 3, NULL, '2025-11-21 12:50:58', NULL, 'left'),
(510, 6, 3, NULL, '2025-11-21 12:51:58', NULL, 'left'),
(511, 6, 3, NULL, '2025-11-21 12:51:59', NULL, 'left'),
(512, 6, 3, NULL, '2025-11-21 12:53:03', NULL, 'left'),
(513, 6, 3, NULL, '2025-11-21 12:53:05', NULL, 'left'),
(514, 6, 3, NULL, '2025-11-21 12:53:58', NULL, 'left'),
(515, 6, 3, NULL, '2025-11-21 12:53:58', NULL, 'left'),
(516, 6, 3, NULL, '2025-11-21 12:54:58', NULL, 'left'),
(517, 6, 3, NULL, '2025-11-21 12:54:58', NULL, 'left'),
(518, 6, 3, NULL, '2025-11-21 12:55:58', NULL, 'left'),
(519, 6, 3, NULL, '2025-11-21 12:55:58', NULL, 'left'),
(520, 6, 3, NULL, '2025-11-21 12:56:59', NULL, 'left'),
(521, 6, 3, NULL, '2025-11-21 12:56:59', NULL, 'left'),
(522, 6, 3, NULL, '2025-11-21 12:57:57', NULL, 'left'),
(523, 6, 3, NULL, '2025-11-21 12:57:57', NULL, 'left'),
(524, 6, 3, NULL, '2025-11-21 12:58:57', NULL, 'left'),
(525, 6, 3, NULL, '2025-11-21 12:58:57', NULL, 'left'),
(526, 6, 3, NULL, '2025-11-21 12:59:57', NULL, 'left'),
(527, 6, 3, NULL, '2025-11-21 12:59:57', NULL, 'left'),
(528, 6, 3, NULL, '2025-11-21 13:00:57', NULL, 'left'),
(529, 6, 3, NULL, '2025-11-21 13:00:57', NULL, 'left'),
(530, 6, 3, NULL, '2025-11-21 13:01:58', NULL, 'left'),
(531, 6, 3, NULL, '2025-11-21 13:01:58', NULL, 'left'),
(532, 6, 3, NULL, '2025-11-21 13:02:58', NULL, 'left'),
(533, 6, 3, NULL, '2025-11-21 13:02:58', NULL, 'left'),
(534, 6, 3, NULL, '2025-11-21 13:03:57', NULL, 'left'),
(535, 6, 3, NULL, '2025-11-21 13:03:58', NULL, 'left'),
(536, 6, 3, NULL, '2025-11-21 13:04:57', NULL, 'left'),
(537, 6, 3, NULL, '2025-11-21 13:04:57', NULL, 'left'),
(538, 6, 3, NULL, '2025-11-21 13:05:57', NULL, 'left'),
(539, 6, 3, NULL, '2025-11-21 13:05:57', NULL, 'left'),
(540, 6, 3, NULL, '2025-11-21 13:06:57', NULL, 'left'),
(541, 6, 3, NULL, '2025-11-21 13:06:57', NULL, 'left'),
(542, 6, 3, NULL, '2025-11-21 13:07:57', NULL, 'left'),
(543, 6, 3, NULL, '2025-11-21 13:07:57', NULL, 'left'),
(544, 6, 3, NULL, '2025-11-21 13:08:57', NULL, 'left'),
(545, 6, 3, NULL, '2025-11-21 13:08:57', NULL, 'left'),
(546, 6, 3, NULL, '2025-11-21 13:09:58', NULL, 'left'),
(547, 6, 3, NULL, '2025-11-21 13:09:58', NULL, 'left'),
(548, 6, 3, NULL, '2025-11-21 13:10:57', NULL, 'left'),
(549, 6, 3, NULL, '2025-11-21 13:10:58', NULL, 'left'),
(550, 6, 3, NULL, '2025-11-21 13:13:21', NULL, 'left'),
(551, 6, 3, NULL, '2025-11-21 13:13:21', NULL, 'left'),
(552, 6, 3, NULL, '2025-11-21 13:13:57', NULL, 'left'),
(553, 6, 3, NULL, '2025-11-21 13:13:57', NULL, 'left'),
(554, 6, 3, NULL, '2025-11-23 16:36:18', NULL, 'left'),
(555, 6, 3, NULL, '2025-11-23 16:37:37', NULL, 'left'),
(556, 6, 3, NULL, '2025-11-23 16:37:37', NULL, 'left'),
(557, 6, 3, NULL, '2025-11-23 16:37:38', NULL, 'left'),
(558, 6, 3, NULL, '2025-11-23 16:37:40', NULL, 'left'),
(559, 6, 3, NULL, '2025-11-23 16:37:40', NULL, 'left'),
(560, 6, 3, NULL, '2025-11-23 16:37:41', NULL, 'left'),
(561, 6, 3, NULL, '2025-11-23 16:37:43', NULL, 'left'),
(562, 6, 3, NULL, '2025-11-23 16:37:43', NULL, 'left'),
(563, 6, 3, NULL, '2025-11-23 16:37:44', NULL, 'left'),
(564, 6, 3, NULL, '2025-11-23 16:37:46', NULL, 'left'),
(565, 6, 3, NULL, '2025-11-23 16:37:47', NULL, 'left'),
(566, 6, 3, NULL, '2025-11-23 16:37:48', NULL, 'left'),
(567, 6, 3, NULL, '2025-11-23 16:38:38', NULL, 'left'),
(568, 6, 3, NULL, '2025-11-23 16:38:38', NULL, 'left'),
(569, 6, 3, NULL, '2025-11-23 16:38:39', NULL, 'left'),
(570, 6, 3, NULL, '2025-11-23 16:38:41', NULL, 'left'),
(571, 6, 3, NULL, '2025-11-23 16:38:42', NULL, 'left'),
(572, 6, 3, NULL, '2025-11-23 16:38:43', NULL, 'left'),
(573, 6, 3, NULL, '2025-11-23 16:38:43', NULL, 'left'),
(574, 6, 3, NULL, '2025-11-23 16:38:43', NULL, 'left'),
(575, 6, 3, NULL, '2025-11-23 16:38:44', NULL, 'left'),
(576, 6, 3, NULL, '2025-11-23 16:38:49', NULL, 'left'),
(577, 6, 3, NULL, '2025-11-23 16:38:49', NULL, 'left'),
(578, 6, 3, NULL, '2025-11-23 16:38:49', NULL, 'left'),
(579, 6, 3, NULL, '2025-11-23 16:38:49', NULL, 'left'),
(580, 6, 3, NULL, '2025-11-23 16:38:49', NULL, 'left'),
(581, 6, 3, NULL, '2025-11-23 16:39:10', NULL, 'left'),
(582, 6, 3, NULL, '2025-11-23 16:39:11', NULL, 'left'),
(583, 6, 3, NULL, '2025-11-23 16:39:11', NULL, 'left'),
(584, 6, 3, NULL, '2025-11-23 16:39:12', NULL, 'left'),
(585, 6, 3, NULL, '2025-11-23 16:39:12', NULL, 'left'),
(586, 7, 9, NULL, '2025-11-24 12:35:52', NULL, 'left'),
(587, 7, 9, NULL, '2025-11-24 12:35:56', NULL, 'left'),
(588, 7, 9, NULL, '2025-11-24 12:35:58', NULL, 'left'),
(589, 7, 9, NULL, '2025-11-24 12:35:59', NULL, 'left'),
(590, 7, 9, NULL, '2025-11-24 12:35:59', NULL, 'left'),
(591, 7, 9, NULL, '2025-11-24 12:36:01', NULL, 'left'),
(592, 7, 9, NULL, '2025-11-24 12:36:02', NULL, 'left'),
(593, 7, 9, NULL, '2025-11-24 12:36:02', NULL, 'left'),
(594, 7, 9, NULL, '2025-11-24 12:36:04', NULL, 'left'),
(595, 7, 9, NULL, '2025-11-24 12:36:05', NULL, 'left'),
(596, 7, 9, NULL, '2025-11-24 12:36:05', NULL, 'left'),
(597, 7, 9, NULL, '2025-11-24 12:36:07', NULL, 'left'),
(598, 7, 9, NULL, '2025-11-24 12:36:08', NULL, 'left'),
(599, 7, 9, NULL, '2025-11-24 12:36:08', NULL, 'left'),
(600, 7, 9, NULL, '2025-11-24 12:36:10', NULL, 'left'),
(601, 7, 9, NULL, '2025-11-24 12:36:11', NULL, 'left'),
(602, 7, 9, NULL, '2025-11-24 12:36:11', NULL, 'left'),
(603, 7, 9, NULL, '2025-11-24 12:36:13', NULL, 'left'),
(604, 7, 9, NULL, '2025-11-24 12:36:14', NULL, 'left'),
(605, 7, 9, NULL, '2025-11-24 12:36:14', NULL, 'left'),
(606, 7, 9, NULL, '2025-11-24 12:36:16', NULL, 'left'),
(607, 7, 9, NULL, '2025-11-24 12:36:17', NULL, 'left'),
(608, 7, 9, NULL, '2025-11-24 12:36:17', NULL, 'left'),
(609, 7, 9, NULL, '2025-11-24 12:36:19', NULL, 'left'),
(610, 7, 9, NULL, '2025-11-24 12:36:20', NULL, 'left'),
(611, 7, 9, NULL, '2025-11-24 12:36:20', NULL, 'left'),
(612, 7, 9, NULL, '2025-11-24 12:36:22', NULL, 'left'),
(613, 7, 9, NULL, '2025-11-24 12:36:23', NULL, 'left'),
(614, 7, 9, NULL, '2025-11-24 12:36:23', NULL, 'left'),
(615, 7, 9, NULL, '2025-11-24 12:36:24', NULL, 'left');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_signals`
--

CREATE TABLE `meeting_signals` (
  `id` int NOT NULL,
  `meeting_id` int NOT NULL,
  `from_student_id` int DEFAULT NULL,
  `from_lecturer_id` int DEFAULT NULL,
  `to_student_id` int DEFAULT NULL,
  `to_lecturer_id` int DEFAULT NULL,
  `type` enum('offer','answer','candidate') NOT NULL,
  `data` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `id` int NOT NULL,
  `unit_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notes`
--

INSERT INTO `notes` (`id`, `unit_id`, `lecturer_id`, `file_path`, `uploaded_at`) VALUES
(1, 5, 1, '1757829451_river twin models proposal.pdf', '2025-09-14 05:57:31'),
(2, 5, 1, '1757829451_Kisii_River_Climate_Digital_Twin_Project.pdf', '2025-09-14 05:57:31'),
(3, 5, 1, '1758176919_Introduction_to_IoT_certificate_mwendihillary21-gmail-com_39b115b8-4b83-4883-9021-7e6fc27b9efa.pdf', '2025-09-18 06:28:39'),
(4, 5, 1, '1758177649_receipt.pdf', '2025-09-18 06:40:49'),
(5, 5, 1, '1758177835_receipt.pdf', '2025-09-18 06:43:55'),
(6, 5, 1, '1758178100_chapter_4.pdf', '2025-09-18 06:48:20'),
(7, 5, 1, '1758179513_c0bf643c-baeb-431e-9a58-a17c67daec40-combined.pdf', '2025-09-18 07:11:53'),
(8, 5, 1, '1758179725_assignment_report__3_.pdf', '2025-09-18 07:15:25'),
(9, 5, 1, '1759304982_jitsi_setup_guide.pdf', '2025-10-01 07:49:42'),
(10, 5, 1, '1759307357_jitsi_setup_guide.pdf', '2025-10-01 08:29:17'),
(11, 5, 1, '1759395751_KCDF YEIC Fund Application Guide 2025 Final.pdf', '2025-10-02 09:02:31'),
(12, 5, 1, '1759733364_Units_computer science (1).pdf', '2025-10-06 06:49:24'),
(13, 5, 1, '1759743579_Units_computer technology (1).pdf', '2025-10-06 09:39:39'),
(14, 98, 1, '1759743710_calculus-ii-lec-notes-set-1.pdf', '2025-10-06 09:41:50'),
(15, 5, 1, '1764178930_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:42:10'),
(16, 5, 1, '1764179614_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:53:34'),
(17, 5, 1, '1764179621_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:53:41'),
(18, 5, 1, '1764179625_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:53:45'),
(19, 5, 1, '1764179629_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:53:49'),
(20, 5, 1, '1764179633_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:53:53'),
(21, 5, 1, '1764179639_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:53:59'),
(22, 5, 1, '1764179643_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:54:03'),
(23, 5, 1, '1764179647_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:54:07'),
(24, 5, 1, '1764179653_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:54:13'),
(25, 5, 1, '1764179657_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:54:17'),
(26, 5, 1, '1764179660_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:54:20'),
(27, 5, 1, '1764179664_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:54:24'),
(28, 5, 1, '1764179669_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:54:29'),
(29, 5, 1, '1764179673_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:54:33'),
(30, 5, 1, '1764179677_OPERATIONS RESEARCH ASSIGNMENT II  OCTOBER  2024.pdf', '2025-11-26 17:54:37'),
(31, 5, 1, '1764180244_Summary For Informed Heuristics.docx', '2025-11-26 18:04:04'),
(32, 5, 1, '1764181455_0704632722.docx', '2025-11-26 18:24:15'),
(33, 5, 1, '1764231231_BCT 2404 Course Outline.pdf', '2025-11-27 08:13:51'),
(34, 5, 1, '1768888680_SDF.docx', '2026-01-20 05:58:00'),
(35, 100, 1, '1769081453_TASK 1. CLIENT SERVER SYSTEMS.pdf', '2026-01-22 11:30:53'),
(36, 98, 1, '1771572279_IoT IN LMS.pdf', '2026-02-20 07:24:39');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes_id` int DEFAULT NULL,
  `assignment_id` int DEFAULT NULL,
  `interactive_assignment_id` int DEFAULT NULL,
  `meeting_id` int DEFAULT NULL,
  `attendance_session_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `message`, `link`, `is_read`, `created_at`, `notes_id`, `assignment_id`, `interactive_assignment_id`, `meeting_id`, `attendance_session_id`) VALUES
(1, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:42:10', 15, NULL, NULL, NULL, NULL),
(2, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:53:34', 16, NULL, NULL, NULL, NULL),
(3, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:53:41', 17, NULL, NULL, NULL, NULL),
(4, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:53:45', 18, NULL, NULL, NULL, NULL),
(5, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:53:49', 19, NULL, NULL, NULL, NULL),
(6, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:53:53', 20, NULL, NULL, NULL, NULL),
(7, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:53:59', 21, NULL, NULL, NULL, NULL),
(8, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:54:03', 22, NULL, NULL, NULL, NULL),
(9, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:54:07', 23, NULL, NULL, NULL, NULL),
(10, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:54:13', 24, NULL, NULL, NULL, NULL),
(11, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:54:17', 25, NULL, NULL, NULL, NULL),
(12, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:54:20', 26, NULL, NULL, NULL, NULL),
(13, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:54:24', 27, NULL, NULL, NULL, NULL),
(14, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:54:29', 28, NULL, NULL, NULL, NULL),
(15, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:54:33', 29, NULL, NULL, NULL, NULL),
(16, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 17:54:37', 30, NULL, NULL, NULL, NULL),
(17, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 18:04:04', 31, NULL, NULL, NULL, NULL),
(18, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 18:04:04', NULL, NULL, NULL, NULL, NULL),
(19, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 18:24:15', 32, NULL, NULL, NULL, NULL),
(20, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 18:24:17', 32, NULL, NULL, NULL, NULL),
(21, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 18:24:18', 32, NULL, NULL, NULL, NULL),
(22, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 18:24:20', 32, NULL, NULL, NULL, NULL),
(23, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 18:24:21', 32, NULL, NULL, NULL, NULL),
(24, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/notes.php?unit_id=5', 0, '2025-11-26 18:24:22', 32, NULL, NULL, NULL, NULL),
(25, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2025-11-27 08:01:59', NULL, NULL, NULL, NULL, NULL),
(26, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2025-11-27 08:11:23', NULL, NULL, NULL, NULL, NULL),
(27, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2025-11-27 08:12:26', NULL, NULL, NULL, NULL, NULL),
(28, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2025-11-27 08:12:28', NULL, NULL, NULL, NULL, NULL),
(29, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2025-11-27 08:12:29', NULL, NULL, NULL, NULL, NULL),
(30, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2025-11-27 08:12:31', NULL, NULL, NULL, NULL, NULL),
(31, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2025-11-27 08:12:32', NULL, NULL, NULL, NULL, NULL),
(32, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2025-11-27 08:12:34', NULL, NULL, NULL, NULL, NULL),
(33, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/dashboard.php', 0, '2025-11-27 08:13:51', 33, NULL, NULL, NULL, NULL),
(34, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/dashboard.php', 0, '2025-11-27 08:13:53', 33, NULL, NULL, NULL, NULL),
(35, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/dashboard.php', 0, '2025-11-27 08:13:54', 33, NULL, NULL, NULL, NULL),
(36, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/dashboard.php', 0, '2025-11-27 08:13:55', 33, NULL, NULL, NULL, NULL),
(37, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/dashboard.php', 0, '2025-11-27 08:13:56', 33, NULL, NULL, NULL, NULL),
(38, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/dashboard.php', 0, '2025-11-27 08:13:57', 33, NULL, NULL, NULL, NULL),
(39, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2025-11-27 08:36:03', NULL, 23, NULL, NULL, NULL),
(40, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>643094</strong>.<br>Valid until <strong>08:25 AM</strong>.', 'student_attendance.php?session=6', 0, '2025-11-28 08:15:52', NULL, NULL, NULL, NULL, 6),
(41, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>946781</strong>.<br>Valid until <strong>08:31 AM</strong>.', 'student_attendance.php?session=7', 0, '2025-11-28 08:21:04', NULL, NULL, NULL, NULL, 7),
(42, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>341237</strong>.<br>Valid until <strong>08:31 AM</strong>.', 'student_attendance.php?session=8', 0, '2025-11-28 08:21:09', NULL, NULL, NULL, NULL, 8),
(43, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>309887</strong>.<br>Valid until <strong>08:31 AM</strong>.', 'student_attendance.php?session=9', 0, '2025-11-28 08:21:14', NULL, NULL, NULL, NULL, 9),
(44, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>312289</strong>.<br>Valid until <strong>08:31 AM</strong>.', 'student_attendance.php?session=10', 0, '2025-11-28 08:21:32', NULL, NULL, NULL, NULL, 10),
(45, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>539251</strong>.<br>Valid until <strong>08:33 AM</strong>.', 'student_attendance.php?session=11', 0, '2025-11-28 08:23:18', NULL, NULL, NULL, NULL, 11),
(46, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>539251</strong>.<br>Valid until <strong>08:33 AM</strong>.', 'student_attendance.php?session=11', 0, '2025-11-28 08:23:20', NULL, NULL, NULL, NULL, 11),
(47, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>539251</strong>.<br>Valid until <strong>08:33 AM</strong>.', 'student_attendance.php?session=11', 0, '2025-11-28 08:23:21', NULL, NULL, NULL, NULL, 11),
(48, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>539251</strong>.<br>Valid until <strong>08:33 AM</strong>.', 'student_attendance.php?session=11', 0, '2025-11-28 08:23:22', NULL, NULL, NULL, NULL, 11),
(49, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>539251</strong>.<br>Valid until <strong>08:33 AM</strong>.', 'student_attendance.php?session=11', 0, '2025-11-28 08:23:23', NULL, NULL, NULL, NULL, 11),
(50, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>539251</strong>.<br>Valid until <strong>08:33 AM</strong>.', 'student_attendance.php?session=11', 0, '2025-11-28 08:23:26', NULL, NULL, NULL, NULL, 11),
(51, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>109395</strong>.<br>Valid until <strong>09:14 AM</strong>.', 'student_attendance.php?session=12', 0, '2025-11-28 09:04:31', NULL, NULL, NULL, NULL, 12),
(52, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>109395</strong>.<br>Valid until <strong>09:14 AM</strong>.', 'student_attendance.php?session=12', 0, '2025-11-28 09:04:33', NULL, NULL, NULL, NULL, 12),
(53, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>109395</strong>.<br>Valid until <strong>09:14 AM</strong>.', 'student_attendance.php?session=12', 0, '2025-11-28 09:04:34', NULL, NULL, NULL, NULL, 12),
(54, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>109395</strong>.<br>Valid until <strong>09:14 AM</strong>.', 'student_attendance.php?session=12', 0, '2025-11-28 09:04:35', NULL, NULL, NULL, NULL, 12),
(55, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>109395</strong>.<br>Valid until <strong>09:14 AM</strong>.', 'student_attendance.php?session=12', 0, '2025-11-28 09:04:36', NULL, NULL, NULL, NULL, 12),
(56, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>109395</strong>.<br>Valid until <strong>09:14 AM</strong>.', 'student_attendance.php?session=12', 0, '2025-11-28 09:04:38', NULL, NULL, NULL, NULL, 12),
(57, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>393035</strong>.<br>Valid until <strong>11:34 AM</strong>.', 'student_attendance.php?session=13', 0, '2025-11-28 11:24:18', NULL, NULL, NULL, NULL, 13),
(58, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>393035</strong>.<br>Valid until <strong>11:34 AM</strong>.', 'student_attendance.php?session=13', 0, '2025-11-28 11:24:20', NULL, NULL, NULL, NULL, 13),
(59, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>393035</strong>.<br>Valid until <strong>11:34 AM</strong>.', 'student_attendance.php?session=13', 0, '2025-11-28 11:24:21', NULL, NULL, NULL, NULL, 13),
(60, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>393035</strong>.<br>Valid until <strong>11:34 AM</strong>.', 'student_attendance.php?session=13', 0, '2025-11-28 11:24:22', NULL, NULL, NULL, NULL, 13),
(61, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>393035</strong>.<br>Valid until <strong>11:34 AM</strong>.', 'student_attendance.php?session=13', 0, '2025-11-28 11:24:23', NULL, NULL, NULL, NULL, 13),
(62, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>393035</strong>.<br>Valid until <strong>11:34 AM</strong>.', 'student_attendance.php?session=13', 0, '2025-11-28 11:24:25', NULL, NULL, NULL, NULL, 13),
(63, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>515403</strong><br>Valid until 12:17 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=18', 0, '2025-11-28 12:07:20', NULL, NULL, NULL, NULL, 18),
(64, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>148305</strong><br>Valid until 12:34 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=19', 0, '2025-11-28 12:24:32', NULL, NULL, NULL, NULL, 19),
(65, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>670488</strong><br>Valid until 12:52 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=20', 0, '2025-11-28 12:42:18', NULL, NULL, NULL, NULL, 20),
(66, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>767590</strong><br>Valid until 12:52 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=21', 0, '2025-11-28 12:42:23', NULL, NULL, NULL, NULL, 21),
(67, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>572996</strong><br>Valid until 12:56 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=22', 0, '2025-11-28 12:46:50', NULL, NULL, NULL, NULL, 22),
(68, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>021333</strong><br>Valid until 01:04 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=23', 0, '2025-11-28 12:54:21', NULL, NULL, NULL, NULL, 23),
(69, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>024487</strong><br>Valid until 01:04 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=24', 0, '2025-11-28 12:54:25', NULL, NULL, NULL, NULL, 24),
(70, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>101922</strong><br>Valid until 01:04 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=25', 0, '2025-11-28 12:54:36', NULL, NULL, NULL, NULL, 25),
(71, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>702841</strong><br>Valid until 01:05 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=26', 0, '2025-11-28 12:55:20', NULL, NULL, NULL, NULL, 26),
(72, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>654068</strong><br>Valid until 01:05 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=27', 0, '2025-11-28 12:55:28', NULL, NULL, NULL, NULL, 27),
(73, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>366487</strong><br>Valid until 01:05 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=28', 0, '2025-11-28 12:55:38', NULL, NULL, NULL, NULL, 28),
(74, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>735618</strong><br>Valid until 01:05 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=29', 0, '2025-11-28 12:55:50', NULL, NULL, NULL, NULL, 29),
(75, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>568159</strong><br>Valid until 01:05 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=30', 0, '2025-11-28 12:55:56', NULL, NULL, NULL, NULL, 30),
(76, 'Attendance Started: Distributed Ledgers and Blockchain', 'Code: <strong style=\'font-size:1.5em;color:#f59e0b;\'>408902</strong><br>Valid until 01:06 PM', 'https://unilis.jhubafrica.com/student/student_attendance.php?session=31', 0, '2025-11-28 12:56:00', NULL, NULL, NULL, NULL, 31),
(77, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>636119</strong>.<br>Valid until <strong>01:21 PM</strong>.', 'student_attendance.php?session=32', 0, '2025-11-28 13:11:03', NULL, NULL, NULL, NULL, 32),
(78, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>636119</strong>.<br>Valid until <strong>01:21 PM</strong>.', 'student_attendance.php?session=32', 0, '2025-11-28 13:11:04', NULL, NULL, NULL, NULL, 32),
(79, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>636119</strong>.<br>Valid until <strong>01:21 PM</strong>.', 'student_attendance.php?session=32', 0, '2025-11-28 13:11:05', NULL, NULL, NULL, NULL, 32),
(80, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>636119</strong>.<br>Valid until <strong>01:21 PM</strong>.', 'student_attendance.php?session=32', 0, '2025-11-28 13:11:07', NULL, NULL, NULL, NULL, 32),
(81, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>636119</strong>.<br>Valid until <strong>01:21 PM</strong>.', 'student_attendance.php?session=32', 0, '2025-11-28 13:11:08', NULL, NULL, NULL, NULL, 32),
(82, '0', 'Your lecturer started attendance for <strong>Distributed Ledgers and Blockchain</strong>.<br>Code: <strong>636119</strong>.<br>Valid until <strong>01:21 PM</strong>.', 'student_attendance.php?session=32', 0, '2025-11-28 13:11:10', NULL, NULL, NULL, NULL, 32),
(83, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/dashboard.php', 0, '2026-01-20 05:58:00', 34, NULL, NULL, NULL, NULL),
(84, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/dashboard.php', 0, '2026-01-22 11:30:53', 35, NULL, NULL, NULL, NULL),
(85, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2026-02-12 16:02:38', NULL, 24, NULL, NULL, NULL),
(86, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2026-02-12 16:03:04', NULL, 25, NULL, NULL, NULL),
(87, 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'https://unilis.jhubafrica.com/student/dashboard.php', 0, '2026-02-20 07:24:39', 36, NULL, NULL, NULL, NULL),
(88, 'New Assignment Posted', 'Your lecturer has uploaded a new assignment for your unit.', 'https://unilis.jhubafrica.com/student/assignments.php', 0, '2026-02-20 16:39:12', NULL, 26, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `peer_evaluations`
--

CREATE TABLE `peer_evaluations` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `evaluator_id` int NOT NULL,
  `evaluatee_id` int NOT NULL,
  `contribution` tinyint UNSIGNED NOT NULL,
  `communication` tinyint UNSIGNED NOT NULL,
  `quality` tinyint UNSIGNED NOT NULL,
  `reliability` tinyint UNSIGNED NOT NULL,
  `submitted_at` datetime DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int NOT NULL,
  `assignment_id` int DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','short_answer','speech') NOT NULL,
  `marks` int DEFAULT '1',
  `ai_rubric` text,
  `correct_answer` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `question_options`
--

CREATE TABLE `question_options` (
  `id` int NOT NULL,
  `question_id` int NOT NULL,
  `option_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `match_pair` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `question_options`
--

INSERT INTO `question_options` (`id`, `question_id`, `option_text`, `is_correct`, `match_pair`, `position`) VALUES
(1, 1, 'yes', 1, '', 0),
(2, 1, 'no', 0, '', 1),
(3, 4, 'True', 1, '', 0),
(4, 4, 'False', 0, '', 1),
(5, 5, 'a system with nodes scatered', 0, '', 0),
(6, 5, 'a collection of independent computers (nodes) that act as a single, cohesive system, collaborating to achieve common goals through network communication', 1, '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `recordings`
--

CREATE TABLE `recordings` (
  `id` int NOT NULL,
  `meeting_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `recorded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int NOT NULL,
  `name` varchar(150) NOT NULL,
  `short_name` varchar(20) NOT NULL,
  `university_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`id`, `name`, `short_name`, `university_id`, `created_at`) VALUES
(1, 'School of Computing & Information Technology', 'SCIT', 7, '2025-11-25 09:54:02'),
(2, 'School of Engineering', 'SOE', 7, '2025-11-25 09:54:02'),
(3, 'School of Business & Entrepreneurship', 'SOBE', 7, '2025-11-25 09:54:02'),
(4, 'School of Architecture & Building Sciences', 'SABS', 7, '2025-11-25 09:54:02'),
(5, 'School of Health Sciences', 'SOHS', 7, '2025-11-25 09:54:02'),
(6, 'School of Agriculture & Environmental Sciences', 'SOAES', 7, '2025-11-25 09:54:02');

-- --------------------------------------------------------

--
-- Table structure for table `standup_entries`
--

CREATE TABLE `standup_entries` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `user_id` int NOT NULL,
  `did_today` text COLLATE utf8mb4_general_ci NOT NULL,
  `will_do_next` text COLLATE utf8mb4_general_ci NOT NULL,
  `blockers` text COLLATE utf8mb4_general_ci,
  `entry_date` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int NOT NULL,
  `reg_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `university_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `course_id` int DEFAULT NULL,
  `year_of_study` int DEFAULT NULL,
  `year_joined` int DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `verification_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `reg_no`, `name`, `email`, `university_id`, `department_id`, `course_id`, `year_of_study`, `year_joined`, `password`, `verification_code`, `token_expires_at`, `is_verified`, `verified_at`) VALUES
(3, 'sct4322/21', 'mwendi kimaiga', 'mwendihillary21@gmail.com', 7, 1, 2, 4, 2022, '$2y$10$cnNQ/IyS4t21gH97/Oy0E.MAq72dWeqggyBWpfKp3AHwbjP2lcOca', NULL, NULL, 1, '2025-11-26 12:43:31'),
(4, 'sct4322/2009', 'mane ibrahim', 'ibra@gmail.com', 7, 1, 2, 4, 2022, '$2y$10$LvGFbwXzQAwXNCXtyByOgeGE7QTjuKRUK4MEpimcV.PvKp00cSx3i', NULL, NULL, 0, NULL),
(5, 'SCT212-0132/2022', 'Deborah Wanjiku Njoroge', 'deborah.njoroge@students.jkuat.ac.ke', 7, 1, 2, 4, 2022, '$2y$10$2Rit0GN/9QoVr4osqPnmme9ab4LtZHM3KK6yXjTMkaGMVEe7QsjGi', NULL, NULL, 0, NULL),
(6, 'sct212/3017/166', 'mane', 'mwendihillaryer21@gmail.com', 7, 1, 1, 4, 2022, '$2y$10$x6ekj14Tvp1u61d2NfAY0.4LfceQ//uv2LBh2akg/JHJXfVq/qWLO', NULL, NULL, 0, NULL),
(7, 'sct212/3017/1', 'mane', 'mwendihillaryer261@gmail.com', 7, 1, 1, 4, 2022, '$2y$10$rXvFKxLoNZY79d.XiX5L5Ojd8IUKibINDK/BbS2yq6gOQuhU6IGXG', NULL, NULL, 0, NULL),
(9, 'sct212-0012/2025', 'justin kereu', 'just@gmail.com', 7, 1, 2, 4, 2022, '$2y$10$7P/oJk47aplivpwlAx.F..MNYkE3FXuo4UTGxg3DDhNh0QrwwY4DK', NULL, NULL, 1, NULL),
(10, 'sct4322/234', 'john', 'mwendikimaiga21@gmail.com', 0, 1, 2, 4, 2022, '$2y$10$BfHsmOnE7ZwgCQ0j5eimleY3KrsfyunZgIufLJwRg/CdhAV0jCkRG', NULL, NULL, 1, '2025-11-26 12:29:04'),
(12, 'sct43227643', 'kimli', 'mwendihillary2132167@gmail.com', 0, 1, 2, 1, 2025, '$2y$10$DfAWGF/cKIazO6YaW2IVNOr99cyWCzLysARCm3G.wklumZfjY17wG', '3951deea971a84d5abc8b1aafd2c6697e212b11e40a6ba3d859955cd1a0fe1e1', '2026-01-15 14:28:35', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `id` int NOT NULL,
  `submission_id` int DEFAULT NULL,
  `question_id` int DEFAULT NULL,
  `answer_text` text,
  `selected_option_id` int DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT '0',
  `ai_feedback` text,
  `marks_awarded` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_classnotes_progress`
--

CREATE TABLE `student_classnotes_progress` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `classnote_id` int NOT NULL,
  `status` enum('not_started','in_progress','completed') DEFAULT 'not_started',
  `last_accessed` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_classnotes_progress`
--

INSERT INTO `student_classnotes_progress` (`id`, `student_id`, `classnote_id`, `status`, `last_accessed`) VALUES
(1, 3, 1, 'completed', '2026-03-05 08:19:44'),
(2, 3, 3, 'completed', '2025-11-21 07:50:13'),
(5, 9, 1, 'in_progress', '2025-11-24 09:33:44'),
(6, 9, 3, 'in_progress', '2025-11-24 09:33:44');

-- --------------------------------------------------------

--
-- Table structure for table `student_classnotes_subtopic_progress`
--

CREATE TABLE `student_classnotes_subtopic_progress` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `classnote_id` int NOT NULL,
  `subtopic_index` int NOT NULL DEFAULT '0',
  `subtopic_id` int NOT NULL,
  `viewed` tinyint(1) DEFAULT '0',
  `completed` tinyint(1) DEFAULT '0',
  `selected_choice` int DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_progress`
--

CREATE TABLE `student_progress` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `lesson_id` int DEFAULT NULL,
  `assessment_id` int DEFAULT NULL,
  `lab_id` int DEFAULT NULL,
  `event_type` enum('lesson_viewed','lesson_completed','quiz_score','assignment_score','cat_score','exam_score','lab_completed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` decimal(8,2) DEFAULT NULL,
  `completed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_progress`
--

INSERT INTO `student_progress` (`id`, `student_id`, `unit_id`, `lesson_id`, `assessment_id`, `lab_id`, `event_type`, `score`, `completed_at`, `created_at`) VALUES
(1, 3, 5, 2, NULL, NULL, 'lesson_viewed', NULL, '2026-03-17 20:52:53', '2026-03-23 10:02:25'),
(12, 3, 5, NULL, 1, NULL, 'quiz_score', NULL, '2026-03-23 09:12:11', '2026-03-23 10:02:25');

-- --------------------------------------------------------

--
-- Table structure for table `student_units`
--

CREATE TABLE `student_units` (
  `id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_unit_enrollments`
--

CREATE TABLE `student_unit_enrollments` (
  `id` int UNSIGNED NOT NULL,
  `student_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `semester` tinyint NOT NULL DEFAULT '1' COMMENT '1 or 2',
  `academic_year` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2025/2026' COMMENT 'e.g. 2025/2026',
  `enrolled_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_unit_enrollments`
--

INSERT INTO `student_unit_enrollments` (`id`, `student_id`, `unit_id`, `semester`, `academic_year`, `enrolled_at`) VALUES
(1, 3, 5, 1, '2026/2027', '2026-03-09 10:54:28'),
(2, 3, 100, 1, '2026/2027', '2026-03-09 10:54:28');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int NOT NULL,
  `assignment_id` int DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `marks` int DEFAULT NULL,
  `is_graded` tinyint(1) DEFAULT '0',
  `comment` text,
  `answer_audio` mediumblob,
  `answer_text` text,
  `ai_score` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`id`, `assignment_id`, `student_id`, `file_path`, `submitted_at`, `marks`, `is_graded`, `comment`, `answer_audio`, `answer_text`, `ai_score`) VALUES
(1, 5, 1, '1753253824_chapter_4.pdf', '2025-07-23 06:57:04', 12, 1, 'you can do better', NULL, NULL, NULL),
(2, 6, 1, '1753262430_Supps Invigilation Timetable - Computing July 2025.pdf', '2025-07-23 09:20:30', 23, 1, 'good work', NULL, NULL, NULL),
(3, 9, 3, '1759745245_db exam.pdf', '2025-10-06 10:07:25', NULL, 0, NULL, NULL, NULL, NULL),
(4, 11, 3, '1763516185_New Microsoft Word Document.docx', '2025-11-19 01:36:25', 12, 1, '', NULL, NULL, NULL),
(5, 25, 3, '1770912265_submitted_assignments (1).pdf', '2026-02-12 16:04:25', NULL, 0, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `submission_answers`
--

CREATE TABLE `submission_answers` (
  `id` int NOT NULL,
  `submission_id` int NOT NULL,
  `question_id` int NOT NULL,
  `answer_text` longtext COLLATE utf8mb4_unicode_ci,
  `selected_option` int DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marks_awarded` decimal(5,2) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `submission_answers`
--

INSERT INTO `submission_answers` (`id`, `submission_id`, `question_id`, `answer_text`, `selected_option`, `file_path`, `marks_awarded`, `is_correct`) VALUES
(1, 4, 1, NULL, 1, NULL, 1.00, 1),
(2, 4, 4, NULL, 3, NULL, 1.00, 1),
(3, 4, 2, 'a device', NULL, NULL, NULL, NULL),
(4, 4, 3, NULL, NULL, 'uploads/answers/1/3/q3_s3_1774246324_848c4804.png', NULL, NULL),
(5, 4, 5, NULL, 6, NULL, 1.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `submission_checklist`
--

CREATE TABLE `submission_checklist` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `item_label` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `is_checked` tinyint(1) DEFAULT '0',
  `checked_by` int DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submission_signoffs`
--

CREATE TABLE `submission_signoffs` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `user_id` int NOT NULL,
  `signed_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `unit_id` int NOT NULL,
  `assessment_id` int DEFAULT NULL COMMENT 'References team_assignments.id or similar',
  `assessment_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assignment',
  `created_by` int NOT NULL,
  `course_id` int NOT NULL,
  `year` int NOT NULL,
  `status` enum('active','locked','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `submission_mode` enum('team','individual','mixed') COLLATE utf8mb4_unicode_ci DEFAULT 'team' COMMENT 'team = single shared file, individual = one per member, mixed = both',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deadline` datetime DEFAULT NULL COMMENT 'Team-specific deadline (can override assignment default)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `title`, `description`, `unit_id`, `assessment_id`, `assessment_type`, `created_by`, `course_id`, `year`, `status`, `submission_mode`, `created_at`, `updated_at`, `deadline`) VALUES
(22, 'quantum', NULL, 5, NULL, '0', 3, 2, 4, 'active', 'team', '2026-03-03 07:10:43', NULL, NULL),
(23, 'quantum computing', NULL, 5, NULL, '0', 3, 2, 4, 'active', 'team', '2026-03-03 07:18:14', NULL, NULL),
(24, 'database', NULL, 5, NULL, '0', 3, 2, 4, 'active', 'team', '2026-03-03 07:27:42', NULL, NULL),
(28, 'db', NULL, 5, NULL, 'assignment', 3, 2, 0, 'active', 'team', '2026-03-11 09:35:40', NULL, NULL),
(29, 'dfg', NULL, 5, NULL, 'cat', 3, 2, 4, 'active', 'team', '2026-03-11 10:56:11', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `team_activity_log`
--

CREATE TABLE `team_activity_log` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `user_id` int NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `action_detail` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `team_activity_log`
--

INSERT INTO `team_activity_log` (`id`, `team_id`, `user_id`, `action_type`, `action_detail`, `created_at`) VALUES
(1, 28, 3, 'team_create', 'Team created: \"db\" | unit_id=5 | course_id=2 | type=assignment | by user=3', '2026-03-11 12:35:40'),
(2, 29, 3, 'team_create', 'Team created: \"dfg\" | unit_id=5 | course_id=2 | year=4 | type=cat | by user=3', '2026-03-11 13:56:11');

-- --------------------------------------------------------

--
-- Table structure for table `team_assignments`
--

CREATE TABLE `team_assignments` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `unit_id` int NOT NULL,
  `course_id` int NOT NULL,
  `assignment_mode` enum('individual_only','team_only','both') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'individual_only',
  `submission_deadline` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_files`
--

CREATE TABLE `team_files` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `uploader_id` int NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(100) NOT NULL,
  `filepath` varchar(512) NOT NULL,
  `file_size` bigint UNSIGNED NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `version` int UNSIGNED NOT NULL DEFAULT '1',
  `is_current` tinyint UNSIGNED DEFAULT '1',
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_invitations`
--

CREATE TABLE `team_invitations` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `invited_student_id` int NOT NULL,
  `invited_by` int NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `invited_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `student_id` int NOT NULL,
  `role` enum('leader','editor','researcher','presenter','developer','lab_partner','member') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `team_id`, `student_id`, `role`, `joined_at`) VALUES
(1, 22, 3, 'leader', '2026-03-03 07:10:44'),
(2, 23, 3, 'leader', '2026-03-03 07:18:14'),
(3, 24, 3, 'leader', '2026-03-03 07:27:42'),
(5, 24, 5, 'member', '2026-03-03 10:11:13'),
(6, 28, 3, 'leader', '2026-03-11 09:35:40'),
(7, 29, 3, 'leader', '2026-03-11 10:56:11');

-- --------------------------------------------------------

--
-- Table structure for table `team_submissions`
--

CREATE TABLE `team_submissions` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `assessment_id` int NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint UNSIGNED NOT NULL DEFAULT '0',
  `submission_type` enum('team','individual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  `is_current` tinyint UNSIGNED DEFAULT '1',
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lecturer_status` enum('Received','Under Review','Needs Revision','Accepted','Rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'Received',
  `lecturer_note` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` datetime DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_tasks`
--

CREATE TABLE `team_tasks` (
  `id` int NOT NULL,
  `team_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `assigned_to` int DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `status` enum('Backlog','In Progress','In Review','Done') DEFAULT 'Backlog',
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `course_id` int DEFAULT NULL,
  `year` int DEFAULT NULL,
  `semester` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `name`, `code`, `course_id`, `year`, `semester`) VALUES
(5, 'Distributed Ledgers and Blockchain', 'BCT 2403', 2, 4, 1),
(15, 'Development studies and Social Ethics', 'HRD 2102', 1, 1, 1),
(16, 'Mathematics for Sciences', 'SMA 2104', 1, 1, 1),
(17, 'Discrete Mathematics', 'SMA 2100', 1, 1, 1),
(18, 'Calculus I', 'SMA 2101', 1, 1, 1),
(19, 'introduction to computer systems', 'ICS 2100', 1, 1, 1),
(20, 'Computer Organization', 'ICS 2101', 1, 1, 1),
(21, 'Introduction To Programming', 'ICS 2102', 1, 1, 1),
(22, 'HIV/AIDS', 'SZL 2111', 1, 1, 1),
(23, 'Internet Technologies', 'ICS 2107', 1, 1, 1),
(24, 'Communication & Information Literacy skills', 'CILS 2101', 1, 1, 2),
(25, 'Calculus II', 'SMA 2102', 1, 1, 2),
(26, 'Pobability and Statistics, I', 'STA 2100', 1, 1, 2),
(27, 'Physics', 'SPH 2172', 1, 1, 2),
(28, 'Computer Aided Design', 'BIT 2111', 1, 1, 2),
(29, 'Object Oriented Programing', 'BIT 2109', 1, 1, 2),
(30, 'Data Structures and Algorthms', 'ICS 2105', 1, 1, 2),
(31, 'Discrete Structures', 'ICS 2106', 1, 1, 2),
(32, 'Vector Analysis', 'SMA 2220', 1, 2, 1),
(33, 'Probability and Statistics II', 'STA 2100', 1, 2, 1),
(34, 'Ordinary Differential Equations', 'SMA 2304', 1, 2, 1),
(35, 'Analogue Electronics', 'EEE 2206', 1, 2, 1),
(36, 'Object Oriented Programing II', 'BIT 2115', 1, 2, 1),
(37, 'Internet Application Programming', 'ICS 2203', 1, 2, 1),
(38, 'Principles Of Programing Languages', 'ICS 2204', 1, 2, 1),
(39, 'Operating Systems', 'BIT 2106', 1, 2, 1),
(40, 'Introduction to Quantum Computing', 'ICS 2118', 1, 2, 2),
(41, 'Digital Electronics', 'EEE 2206', 1, 2, 2),
(42, 'Database Management Systems', 'ICS 2206', 1, 2, 2),
(43, 'Scientific Computing', 'ICS 2207', 1, 2, 2),
(44, 'Computer Networks', 'ICS 2209', 1, 2, 2),
(45, 'Systems Analysis and Design', 'ICS2210', 1, 2, 2),
(46, 'Numerical Linear Algebra', 'ICS 2211', 1, 2, 2),
(47, 'Systems Programing', 'ICS 2305', 1, 2, 2),
(48, 'Industrial Attachment I', 'ICS 2213', 1, 2, 2),
(49, 'General Economics', 'HRD 2103', 1, 3, 1),
(50, 'Operations Research For Statistics', 'STA 2209', 1, 3, 1),
(51, 'Mobile application Design and Development', 'ICS 2300', 1, 3, 1),
(52, 'Design Analysis for Algorthms', 'ICS 2301', 1, 3, 1),
(53, 'Software Engineering', 'ICS 2302', 1, 3, 1),
(54, 'Multimedia SYstems and Applications', 'BCT 2207', 1, 3, 1),
(55, 'Cloud Computing', 'ICS 2307', 1, 3, 1),
(56, 'Distributed Systems', 'ICS 2306', 1, 3, 1),
(57, 'Advanced database systems', 'BCT 2402', 1, 3, 2),
(58, 'Fundamentals of Computer Security and Cryptography', 'ICS 2316', 1, 3, 2),
(59, 'Simulation and Modeling', 'ICS 2307', 1, 3, 2),
(60, 'Artificial Inteligence', 'ICS 2308', 1, 3, 2),
(61, 'Discrete Structures', 'ICS 2310', 1, 3, 2),
(62, 'Computer Graphics and Digital image Processing', 'ICS 2311', 1, 3, 2),
(63, 'Research Methodology in Computing', 'BCT 2315', 1, 3, 2),
(64, 'Software Testing and Quality Assurance', 'ICS 2313', 1, 3, 2),
(65, 'Industrial Attachment II', 'ICS 2314', 1, 3, 2),
(66, 'Network Security', 'ICS 2414', 1, 4, 1),
(67, 'Human Computer Interaction', 'ICS 2402', 1, 4, 1),
(68, 'Machine Learning', 'ICS 2403', 1, 4, 1),
(69, 'Data Archtecture and Warehousing', 'ICS 2315', 1, 4, 1),
(70, 'Embeded Systems and IoT', 'BCT 2308', 1, 4, 1),
(71, 'Computer Systems Project', 'ICS 2406', 1, 4, 1),
(72, 'Theory of Computing', 'ICS 2407', 1, 4, 1),
(73, 'Enterpreneurship Skills', 'HPS 2112', 1, 4, 1),
(74, 'Accounts and Finance', 'HRD 2115', 1, 4, 2),
(75, 'Compiler Construction', 'ICS 2401', 1, 4, 2),
(76, 'Neural Networks', 'ICS 2409', 1, 4, 2),
(77, 'Parallel Systems', 'ICS 2410', 1, 4, 2),
(78, 'Legal and Professional Issues in Computing', 'ICS 2411', 1, 4, 2),
(79, 'Cyber Forensics', 'ICS 2416', 1, 4, 2),
(80, 'Computer vission', 'ICS 2412', 1, 4, 2),
(81, 'Computer systems Project', 'ICS 2406', 1, 4, 2),
(82, 'HIV/AIDS SZL 2111', 'SZL 2111', 3, 1, 1),
(83, 'introduction to computer systems', 'ICS 2100', 2, 1, 1),
(84, 'Computer Organisation and Maintanance', 'BIT 2102', 2, 1, 1),
(85, 'Software Applications', 'BCT 2103', 2, 1, 1),
(86, 'Networking Essentials', 'BIT 2205', 2, 1, 1),
(87, 'Internet Technologies', 'BCT 2106', 2, 1, 1),
(88, 'Introduction to Programming', 'ICS 2102', 2, 1, 1),
(89, 'Mathematics for science', 'SMA 2104', 2, 1, 1),
(90, 'Development studies and Social Ethics', 'HRD 2102', 2, 1, 1),
(91, 'calculus 1', 'SMA 2101', 2, 1, 2),
(92, 'Probability and Statistics I', 'STA 2100', 2, 1, 2),
(93, 'Object Oriented Programing I', 'BIT 2109', 2, 1, 2),
(94, 'Network Systems Design and Implementation', 'BIT2116', 2, 1, 2),
(95, 'Communication & Information Literacy skills', 'CILS 2101', 2, 1, 2),
(96, 'Principles of Electronic Engineering', 'EEE 2262', 2, 1, 2),
(97, 'Operating Systems', 'BIT 2106', 2, 1, 2),
(98, 'Modular Programming', 'BCT 2102', 2, 1, 2),
(99, 'HIV/AIDS', 'SZL 2111', 2, 1, 2),
(100, 'Knowledge Based Systems', 'ics 2404', 2, 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `universities`
--

CREATE TABLE `universities` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `universities`
--

INSERT INTO `universities` (`id`, `name`) VALUES
(0, 'erdtfyguhijokpl;poiuytr'),
(1, 'Alupe University'),
(2, 'Chuka University'),
(3, 'Dedan Kimathi University of Technology'),
(4, 'Egerton University'),
(5, 'Garissa University'),
(6, 'Jaramogi Oginga Odinga University of Science and Technology'),
(7, 'Jomo Kenyatta University of Agriculture & Technology'),
(8, 'Kaimosi Friends University'),
(9, 'Karatina University'),
(10, 'Kenyatta University'),
(11, 'Kibabii University'),
(12, 'Kirinyaga University'),
(13, 'Kisii University'),
(14, 'Laikipia University'),
(15, 'Machakos University'),
(16, 'Maasai Mara University'),
(17, 'Maseno University'),
(18, 'Masinde Muliro University of Science and Technology'),
(19, 'Meru University of Science and Technology'),
(20, 'Moi University'),
(21, 'Multimedia University of Kenya'),
(22, 'Murang’a University of Technology'),
(23, 'Pwani University'),
(24, 'Rongo University'),
(25, 'South Eastern Kenya University'),
(26, 'Taita Taveta University'),
(27, 'Technical University of Kenya'),
(28, 'Technical University of Mombasa'),
(29, 'Tharaka University'),
(30, 'University of Eldoret'),
(31, 'University of Embu'),
(32, 'University of Kabianga'),
(33, 'University of Nairobi'),
(34, 'Adventist University of Africa'),
(35, 'Africa International University'),
(36, 'Africa Nazarene University'),
(37, 'Aga Khan University'),
(38, 'AMREF International University'),
(39, 'Catholic University of Eastern Africa'),
(40, 'Daystar University'),
(41, 'East Africa School of Theology'),
(42, 'Great Lakes University of Kisumu'),
(43, 'Gretsa University'),
(44, 'International Leadership University'),
(45, 'Islamic University of Kenya'),
(46, 'Kabarak University'),
(47, 'KAG East University'),
(48, 'KCA University'),
(49, 'Kenya Highlands University'),
(50, 'Kenya Methodist University'),
(51, 'Kiriri Women\'s University of Science & Technology'),
(52, 'Lukenya University'),
(53, 'Mount Kenya University'),
(54, 'Pan Africa Christian University'),
(55, 'Pioneer International University'),
(56, 'Riara University'),
(57, 'Scott Christian University'),
(58, 'St. Paul\'s University'),
(59, 'Strathmore University'),
(60, 'The Presbyterian University of East Africa'),
(61, 'Umma University'),
(62, 'United States International University – Africa'),
(63, 'University of Eastern Africa – Baraton'),
(64, 'Zetech University');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `idx_announce_unit` (`unit_id`);

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_asmnt_unit` (`unit_id`),
  ADD KEY `idx_asmnt_lecturer` (`lecturer_id`),
  ADD KEY `idx_asmnt_module` (`module_id`),
  ADD KEY `idx_asmnt_lesson` (`lesson_id`),
  ADD KEY `idx_asmnt_type` (`type`);

--
-- Indexes for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aq_assessment` (`assessment_id`),
  ADD KEY `idx_aq_position` (`assessment_id`,`position`);

--
-- Indexes for table `assessment_submissions`
--
ALTER TABLE `assessment_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_one_submission` (`assessment_id`,`student_id`),
  ADD KEY `idx_asub_assessment` (`assessment_id`),
  ADD KEY `idx_asub_student` (`student_id`),
  ADD KEY `idx_asub_status` (`status`);

--
-- Indexes for table `assessment_weights`
--
ALTER TABLE `assessment_weights`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_unit_lec_type` (`unit_id`,`lecturer_id`,`assessment_type`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_session` (`session_id`,`student_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_code` (`session_code`),
  ADD KEY `idx_unit_lecturer` (`unit_id`,`lecturer_id`),
  ADD KEY `idx_deadline` (`deadline`),
  ADD KEY `fk_as_lecturer` (`lecturer_id`);

--
-- Indexes for table `chat`
--
ALTER TABLE `chat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_meeting` (`meeting_id`);

--
-- Indexes for table `classnotes`
--
ALTER TABLE `classnotes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `lecturer_id` (`lecturer_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `course_lessons`
--
ALTER TABLE `course_lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cl_module` (`module_id`),
  ADD KEY `idx_cl_unit` (`unit_id`),
  ADD KEY `idx_cl_position` (`module_id`,`position`);

--
-- Indexes for table `course_modules`
--
ALTER TABLE `course_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cm_unit` (`unit_id`),
  ADD KEY `idx_cm_lecturer` (`lecturer_id`),
  ADD KEY `idx_cm_position` (`unit_id`,`position`);

--
-- Indexes for table `course_outlines`
--
ALTER TABLE `course_outlines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_unit_lecturer` (`unit_id`,`lecturer_id`),
  ADD KEY `idx_unit_id` (`unit_id`),
  ADD KEY `idx_lecturer_id` (`lecturer_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `university_id` (`university_id`),
  ADD KEY `fk_dept_school` (`school_id`);

--
-- Indexes for table `exam_violations`
--
ALTER TABLE `exam_violations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ev_submission` (`submission_id`),
  ADD KEY `idx_ev_student` (`student_id`),
  ADD KEY `idx_ev_type` (`violation_type`);

--
-- Indexes for table `ghost_flags`
--
ALTER TABLE `ghost_flags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `one_flag_per_pair` (`team_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `interactive_answers`
--
ALTER TABLE `interactive_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `option_id` (`option_id`);

--
-- Indexes for table `interactive_assignments`
--
ALTER TABLE `interactive_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `interactive_options`
--
ALTER TABLE `interactive_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `interactive_questions`
--
ALTER TABLE `interactive_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_interactive_assignment` (`interactive_assignment_id`);

--
-- Indexes for table `interactive_submissions`
--
ALTER TABLE `interactive_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `assignment_id` (`assignment_id`);

--
-- Indexes for table `labs`
--
ALTER TABLE `labs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_labs_unit` (`unit_id`),
  ADD KEY `idx_labs_lecturer` (`lecturer_id`),
  ADD KEY `idx_labs_module` (`module_id`),
  ADD KEY `idx_labs_lesson` (`lesson_id`);

--
-- Indexes for table `lab_submissions`
--
ALTER TABLE `lab_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_one_lab_submission` (`lab_id`,`student_id`),
  ADD KEY `idx_lsub_lab` (`lab_id`),
  ADD KEY `idx_lsub_student` (`student_id`);

--
-- Indexes for table `lecturers`
--
ALTER TABLE `lecturers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `fk_university` (`university_id`);

--
-- Indexes for table `lecturer_units`
--
ALTER TABLE `lecturer_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_lu` (`lecturer_id`,`unit_id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `lesson_content_blocks`
--
ALTER TABLE `lesson_content_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lcb_lesson` (`lesson_id`),
  ADD KEY `idx_lcb_position` (`lesson_id`,`position`);

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `meeting_attendance`
--
ALTER TABLE `meeting_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meeting_id` (`meeting_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `lecturer_id` (`lecturer_id`);

--
-- Indexes for table `meeting_signals`
--
ALTER TABLE `meeting_signals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meeting_id` (`meeting_id`),
  ADD KEY `from_student_id` (`from_student_id`),
  ADD KEY `from_lecturer_id` (`from_lecturer_id`),
  ADD KEY `to_student_id` (`to_student_id`),
  ADD KEY `to_lecturer_id` (`to_lecturer_id`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `fk_notes_lecturer` (`lecturer_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_notes` (`notes_id`),
  ADD KEY `fk_notifications_assignments` (`assignment_id`),
  ADD KEY `fk_notifications_interactive_assignments` (`interactive_assignment_id`),
  ADD KEY `fk_notifications_meetings` (`meeting_id`),
  ADD KEY `idx_att_session_notif` (`attendance_session_id`),
  ADD KEY `idx_attendance_session` (`attendance_session_id`);

--
-- Indexes for table `peer_evaluations`
--
ALTER TABLE `peer_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `one_eval_per_pair` (`team_id`,`evaluator_id`,`evaluatee_id`),
  ADD KEY `evaluator_id` (`evaluator_id`),
  ADD KEY `idx_eval_evaluatee` (`evaluatee_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_id` (`assignment_id`);

--
-- Indexes for table `question_options`
--
ALTER TABLE `question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_qo_question` (`question_id`);

--
-- Indexes for table `recordings`
--
ALTER TABLE `recordings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meeting_id` (`meeting_id`),
  ADD KEY `lecturer_id` (`lecturer_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_short_name_per_uni` (`university_id`,`short_name`),
  ADD KEY `idx_short_name` (`short_name`);

--
-- Indexes for table `standup_entries`
--
ALTER TABLE `standup_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `one_per_day` (`team_id`,`user_id`,`entry_date`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_standup_date_team` (`entry_date` DESC,`team_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `university_id` (`university_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `idx_verification_code` (`verification_code`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`);

--
-- Indexes for table `student_classnotes_progress`
--
ALTER TABLE `student_classnotes_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_progress` (`student_id`,`classnote_id`),
  ADD KEY `classnote_id` (`classnote_id`);

--
-- Indexes for table `student_classnotes_subtopic_progress`
--
ALTER TABLE `student_classnotes_subtopic_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_subtopic` (`student_id`,`classnote_id`,`subtopic_index`),
  ADD KEY `classnote_id` (`classnote_id`);

--
-- Indexes for table `student_progress`
--
ALTER TABLE `student_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lesson_event` (`student_id`,`lesson_id`,`event_type`),
  ADD UNIQUE KEY `uq_assessment_event` (`student_id`,`assessment_id`,`event_type`),
  ADD UNIQUE KEY `uq_progress_event` (`student_id`,`unit_id`,`lesson_id`,`event_type`),
  ADD KEY `idx_sp_student` (`student_id`),
  ADD KEY `idx_sp_unit` (`unit_id`),
  ADD KEY `idx_sp_lesson` (`lesson_id`),
  ADD KEY `idx_sp_assessment` (`assessment_id`),
  ADD KEY `idx_sp_lab` (`lab_id`);

--
-- Indexes for table `student_units`
--
ALTER TABLE `student_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_su` (`student_id`,`unit_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `student_unit_enrollments`
--
ALTER TABLE `student_unit_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_unit_semester` (`student_id`,`unit_id`,`semester`,`academic_year`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_unit_id` (`unit_id`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `submission_answers`
--
ALTER TABLE `submission_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sa_submission` (`submission_id`),
  ADD KEY `idx_sa_question` (`question_id`);

--
-- Indexes for table `submission_checklist`
--
ALTER TABLE `submission_checklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_per_team` (`team_id`,`item_label`(120)),
  ADD KEY `idx_checklist_team` (`team_id`),
  ADD KEY `checked_by` (`checked_by`);

--
-- Indexes for table `submission_signoffs`
--
ALTER TABLE `submission_signoffs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_signoff` (`team_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assessment` (`assessment_type`),
  ADD KEY `idx_unit` (`unit_id`),
  ADD KEY `idx_creator` (`created_by`),
  ADD KEY `idx_course_year` (`course_id`,`year`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_teams_assessment_id` (`assessment_id`),
  ADD KEY `idx_teams_deadline` (`deadline`);

--
-- Indexes for table `team_activity_log`
--
ALTER TABLE `team_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_team_time` (`team_id`,`created_at` DESC),
  ADD KEY `idx_log_user` (`user_id`);

--
-- Indexes for table `team_assignments`
--
ALTER TABLE `team_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_unit` (`unit_id`),
  ADD KEY `idx_course` (`course_id`);

--
-- Indexes for table `team_files`
--
ALTER TABLE `team_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_files_team_current` (`team_id`,`is_current`),
  ADD KEY `idx_files_uploader` (`uploader_id`);

--
-- Indexes for table `team_invitations`
--
ALTER TABLE `team_invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_invite_team_student` (`team_id`,`invited_student_id`),
  ADD KEY `fk_ti_inviter` (`invited_by`),
  ADD KEY `idx_team_status` (`team_id`,`status`),
  ADD KEY `idx_invited_status` (`invited_student_id`,`status`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_team_student` (`team_id`,`student_id`),
  ADD KEY `idx_team` (`team_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `team_submissions`
--
ALTER TABLE `team_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_team_assessment` (`team_id`,`assessment_id`),
  ADD KEY `idx_assessment` (`assessment_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `team_tasks`
--
ALTER TABLE `team_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_tasks_team_status` (`team_id`,`status`),
  ADD KEY `idx_tasks_assignee` (`assigned_to`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `universities`
--
ALTER TABLE `universities`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `assessment_submissions`
--
ALTER TABLE `assessment_submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `assessment_weights`
--
ALTER TABLE `assessment_weights`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `chat`
--
ALTER TABLE `chat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classnotes`
--
ALTER TABLE `classnotes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_lessons`
--
ALTER TABLE `course_lessons`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_modules`
--
ALTER TABLE `course_modules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `course_outlines`
--
ALTER TABLE `course_outlines`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `exam_violations`
--
ALTER TABLE `exam_violations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ghost_flags`
--
ALTER TABLE `ghost_flags`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interactive_answers`
--
ALTER TABLE `interactive_answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `interactive_assignments`
--
ALTER TABLE `interactive_assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `interactive_options`
--
ALTER TABLE `interactive_options`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `interactive_questions`
--
ALTER TABLE `interactive_questions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `interactive_submissions`
--
ALTER TABLE `interactive_submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `labs`
--
ALTER TABLE `labs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_submissions`
--
ALTER TABLE `lab_submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lecturers`
--
ALTER TABLE `lecturers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lecturer_units`
--
ALTER TABLE `lecturer_units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `lesson_content_blocks`
--
ALTER TABLE `lesson_content_blocks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `meetings`
--
ALTER TABLE `meetings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `meeting_attendance`
--
ALTER TABLE `meeting_attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=616;

--
-- AUTO_INCREMENT for table `meeting_signals`
--
ALTER TABLE `meeting_signals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `peer_evaluations`
--
ALTER TABLE `peer_evaluations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question_options`
--
ALTER TABLE `question_options`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `recordings`
--
ALTER TABLE `recordings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=259;

--
-- AUTO_INCREMENT for table `standup_entries`
--
ALTER TABLE `standup_entries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_classnotes_progress`
--
ALTER TABLE `student_classnotes_progress`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_classnotes_subtopic_progress`
--
ALTER TABLE `student_classnotes_subtopic_progress`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_progress`
--
ALTER TABLE `student_progress`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `student_unit_enrollments`
--
ALTER TABLE `student_unit_enrollments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `submission_answers`
--
ALTER TABLE `submission_answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `submission_checklist`
--
ALTER TABLE `submission_checklist`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submission_signoffs`
--
ALTER TABLE `submission_signoffs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `team_activity_log`
--
ALTER TABLE `team_activity_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `team_assignments`
--
ALTER TABLE `team_assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_files`
--
ALTER TABLE `team_files`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_invitations`
--
ALTER TABLE `team_invitations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `team_submissions`
--
ALTER TABLE `team_submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_tasks`
--
ALTER TABLE `team_tasks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_ibfk_3` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD CONSTRAINT `fk_questions_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `fk_ar_session` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ar_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD CONSTRAINT `fk_as_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_as_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `classnotes`
--
ALTER TABLE `classnotes`
  ADD CONSTRAINT `classnotes_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `classnotes_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_lessons`
--
ALTER TABLE `course_lessons`
  ADD CONSTRAINT `fk_lessons_module` FOREIGN KEY (`module_id`) REFERENCES `course_modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_dept_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exam_violations`
--
ALTER TABLE `exam_violations`
  ADD CONSTRAINT `fk_violation_submission` FOREIGN KEY (`submission_id`) REFERENCES `assessment_submissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ghost_flags`
--
ALTER TABLE `ghost_flags`
  ADD CONSTRAINT `ghost_flags_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ghost_flags_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interactive_answers`
--
ALTER TABLE `interactive_answers`
  ADD CONSTRAINT `interactive_answers_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `interactive_submissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interactive_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `interactive_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interactive_answers_ibfk_3` FOREIGN KEY (`option_id`) REFERENCES `interactive_options` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `interactive_assignments`
--
ALTER TABLE `interactive_assignments`
  ADD CONSTRAINT `interactive_assignments_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interactive_assignments_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interactive_options`
--
ALTER TABLE `interactive_options`
  ADD CONSTRAINT `interactive_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `interactive_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interactive_questions`
--
ALTER TABLE `interactive_questions`
  ADD CONSTRAINT `fk_interactive_assignment` FOREIGN KEY (`interactive_assignment_id`) REFERENCES `interactive_assignments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interactive_submissions`
--
ALTER TABLE `interactive_submissions`
  ADD CONSTRAINT `interactive_submissions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interactive_submissions_ibfk_2` FOREIGN KEY (`assignment_id`) REFERENCES `interactive_assignments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_submissions`
--
ALTER TABLE `lab_submissions`
  ADD CONSTRAINT `fk_labsub_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lesson_content_blocks`
--
ALTER TABLE `lesson_content_blocks`
  ADD CONSTRAINT `fk_blocks_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `course_lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meeting_attendance`
--
ALTER TABLE `meeting_attendance`
  ADD CONSTRAINT `meeting_attendance_ibfk_1` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meeting_attendance_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meeting_attendance_ibfk_3` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meeting_signals`
--
ALTER TABLE `meeting_signals`
  ADD CONSTRAINT `meeting_signals_ibfk_1` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meeting_signals_ibfk_2` FOREIGN KEY (`from_student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meeting_signals_ibfk_3` FOREIGN KEY (`from_lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meeting_signals_ibfk_4` FOREIGN KEY (`to_student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meeting_signals_ibfk_5` FOREIGN KEY (`to_lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_session` FOREIGN KEY (`attendance_session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifications_assignments` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifications_interactive_assignments` FOREIGN KEY (`interactive_assignment_id`) REFERENCES `interactive_assignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifications_meetings` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifications_notes` FOREIGN KEY (`notes_id`) REFERENCES `notes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `peer_evaluations`
--
ALTER TABLE `peer_evaluations`
  ADD CONSTRAINT `peer_evaluations_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `peer_evaluations_ibfk_2` FOREIGN KEY (`evaluator_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `peer_evaluations_ibfk_3` FOREIGN KEY (`evaluatee_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`);

--
-- Constraints for table `question_options`
--
ALTER TABLE `question_options`
  ADD CONSTRAINT `fk_options_question` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recordings`
--
ALTER TABLE `recordings`
  ADD CONSTRAINT `recordings_ibfk_1` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recordings_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schools`
--
ALTER TABLE `schools`
  ADD CONSTRAINT `schools_ibfk_1` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `standup_entries`
--
ALTER TABLE `standup_entries`
  ADD CONSTRAINT `standup_entries_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `standup_entries_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_classnotes_progress`
--
ALTER TABLE `student_classnotes_progress`
  ADD CONSTRAINT `student_classnotes_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_classnotes_progress_ibfk_2` FOREIGN KEY (`classnote_id`) REFERENCES `classnotes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_classnotes_subtopic_progress`
--
ALTER TABLE `student_classnotes_subtopic_progress`
  ADD CONSTRAINT `student_classnotes_subtopic_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `student_classnotes_subtopic_progress_ibfk_2` FOREIGN KEY (`classnote_id`) REFERENCES `classnotes` (`id`);

--
-- Constraints for table `submission_answers`
--
ALTER TABLE `submission_answers`
  ADD CONSTRAINT `fk_answers_submission` FOREIGN KEY (`submission_id`) REFERENCES `assessment_submissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_checklist`
--
ALTER TABLE `submission_checklist`
  ADD CONSTRAINT `submission_checklist_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_checklist_ibfk_2` FOREIGN KEY (`checked_by`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `submission_checklist_ibfk_3` FOREIGN KEY (`checked_by`) REFERENCES `students` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `submission_signoffs`
--
ALTER TABLE `submission_signoffs`
  ADD CONSTRAINT `submission_signoffs_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_signoffs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `fk_teams_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teams_creator` FOREIGN KEY (`created_by`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teams_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_activity_log`
--
ALTER TABLE `team_activity_log`
  ADD CONSTRAINT `team_activity_log_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_activity_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_assignments`
--
ALTER TABLE `team_assignments`
  ADD CONSTRAINT `fk_ta_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ta_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_files`
--
ALTER TABLE `team_files`
  ADD CONSTRAINT `team_files_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_files_ibfk_2` FOREIGN KEY (`uploader_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_invitations`
--
ALTER TABLE `team_invitations`
  ADD CONSTRAINT `fk_ti_invited` FOREIGN KEY (`invited_student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ti_inviter` FOREIGN KEY (`invited_by`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ti_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `fk_tm_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tm_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_submissions`
--
ALTER TABLE `team_submissions`
  ADD CONSTRAINT `fk_ts_assignment` FOREIGN KEY (`assessment_id`) REFERENCES `team_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ts_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ts_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_tasks`
--
ALTER TABLE `team_tasks`
  ADD CONSTRAINT `team_tasks_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `team_tasks_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
