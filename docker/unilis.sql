-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Sep 27, 2025 at 08:18 PM
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
(8, 5, 1, 'trin', 'submit', '2025-09-19 12:03:00', '2025-09-12 09:03:47', '1757667827_assignment_report (1).pdf', 'text', NULL, NULL);

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
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `university_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `university_id`) VALUES
(1, 'computing', 7),
(2, 'information technology', 7),
(3, 'microbiology', 7);

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
  `is_correct` tinyint(1) DEFAULT NULL,
  `answer_audio` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(4, 1, 5);

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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meetings`
--

INSERT INTO `meetings` (`id`, `lecturer_id`, `unit_id`, `title`, `meeting_link`, `scheduled_time`, `duration`, `created_at`) VALUES
(0, 1, 5, 'dbms', 'http://localhost/unilis/meeting_ide.php?meeting_id=1757668130', '2025-09-12 15:59:00', 120, '2025-09-12 09:08:50'),
(1, 1, 5, 'data structures', 'http://localhost/unilis/meeting_ide.php?meeting_id=1753347599', '2025-07-24 12:00:00', 60, '2025-07-24 08:59:59');

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
(8, 5, 1, '1758179725_assignment_report__3_.pdf', '2025-09-18 07:15:25');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `user_role` enum('student','lecturer','admin') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `user_role`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 1, 'student', 'Test Notification', 'This is a test message', 'student/dashboard.php', 1, '2025-09-18 06:49:38'),
(2, 0, 'student', 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'student/dashboard.php?view=notes&unit_id=5', 0, '2025-09-18 07:11:53'),
(3, 1, 'student', 'New Notes Uploaded', 'Your lecturer has uploaded new notes for your unit.', 'student/dashboard.php?view=notes&unit_id=5', 1, '2025-09-18 07:11:53');

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
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `student_units`
--

CREATE TABLE `student_units` (
  `id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 6, 1, '1753262430_Supps Invigilation Timetable - Computing July 2025.pdf', '2025-07-23 09:20:30', 23, 1, 'good work', NULL, NULL, NULL);

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
(81, 'Computer systems Project', 'ICS 2406', 1, 4, 2);

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
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `university_id` (`university_id`);

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
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lecturer_id` (`lecturer_id`),
  ADD KEY `unit_id` (`unit_id`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_id` (`assignment_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `university_id` (`university_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`);

--
-- Indexes for table `student_units`
--
ALTER TABLE `student_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `interactive_answers`
--
ALTER TABLE `interactive_answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interactive_assignments`
--
ALTER TABLE `interactive_assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `interactive_options`
--
ALTER TABLE `interactive_options`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `interactive_questions`
--
ALTER TABLE `interactive_questions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `interactive_submissions`
--
ALTER TABLE `interactive_submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lecturers`
--
ALTER TABLE `lecturers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lecturer_units`
--
ALTER TABLE `lecturer_units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
