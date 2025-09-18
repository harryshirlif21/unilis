-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Sep 15, 2025 at 09:05 AM
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

--
-- Dumping data for table `interactive_assignments`
--

INSERT INTO `interactive_assignments` (`id`, `lecturer_id`, `unit_id`, `title`, `description`, `due_date`, `created_at`) VALUES
(1, 1, 2, 'Sample Assignment', 'This is a test assignment', '2025-09-20 23:59:59', '2025-09-10 09:06:23'),
(2, 1, 5, 'distributed ledgers', 'distributed sys', '2025-09-18 11:09:00', '2025-09-11 06:08:03'),
(3, 1, 5, 'trying', 'kjlj;p[', '2025-09-16 09:10:00', '2025-09-11 06:10:50');

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
(1, 4, 'a machine that does computation', 1),
(2, 4, 'a machine', 0),
(3, 5, 'tech engt', 1),
(4, 5, 'business', 0),
(5, 6, 'mouse', 0),
(6, 6, 'register', 1),
(7, 6, 'keyboard', 0),
(8, 6, 'speakers', 0),
(9, 7, 'protect data', 1),
(10, 7, 'register', 0),
(11, 8, 'cpu', 0),
(12, 8, 'alu', 1);

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
(4, 1, 'what is a computer', 'multiple_choice', 4, 'multiple_choice', '2025-09-11 05:14:17', NULL),
(5, 1, 'who is elon', 'multiple_choice', 1, 'multiple_choice', '2025-09-11 05:14:17', NULL),
(6, 3, 'Which of the following is NOT a peripheral device?', 'multiple_choice', 3, 'multiple_choice', '2025-09-12 04:48:37', NULL),
(7, 2, 'What is the primary function of a firewall?', 'multiple_choice', 1, 'multiple_choice', '2025-09-12 05:07:08', NULL),
(8, 2, 'what is a serial bus', 'multiple_choice', 1, 'multiple_choice', '2025-09-12 05:07:08', NULL);

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
(1, 1, 3, '2025-09-12 04:50:51', NULL, 0);

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
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notes`
--

INSERT INTO `notes` (`id`, `unit_id`, `lecturer_id`, `file_path`, `uploaded_at`) VALUES
(1, 5, 1, '1757829451_river twin models proposal.pdf', '2025-09-14 05:57:31'),
(2, 5, 1, '1757829451_Kisii_River_Climate_Digital_Twin_Project.pdf', '2025-09-14 05:57:31');

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

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `reg_no`, `name`, `email`, `university_id`, `department_id`, `course_id`, `year_of_study`, `year_joined`, `password`) VALUES
(1, 'sct4322', 'kimaiga hillary', 'mwendi@gmail.com', 7, 1, 2, 4, 2022, '$2a$10$sH2MGsD.J3bNVuboBt8YiOCy/Ej4CDzEb7MZdAlab2gK5Qd8Iw0yi'),
(2, 'sct4322-001', 'tilis kimu', 'man.kimu@gmail.com', 7, 1, 2, 3, 2022, '$2y$10$/nZIX5lKaqUMqoJuORSkSeuCjVHYJ.wnfy68jsXHn7tW.duBTBAE.');

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
(1, 'database', 'BCT 2301', 1, 3, 0),
(2, 'operating systems 1', 'ICS 2304', 3, 3, 0),
(4, 'Distributed.', 'ics 2304', 2, 1, 1),
(5, 'Distributed Ledgers and Blockchain', 'BCT 2403', 2, 4, 1),
(6, 'Advanced Database Systems', 'CS401', 2, 4, 1),
(7, 'introduction to computer systems', 'ICS2100', 2, 1, 1),
(8, 'computer organisation and maintanance', 'BIT 2102', 2, 1, 1),
(9, 'software applications', 'BCT 2103', 2, 1, 1),
(10, 'probability', 'BCT 2118', 2, 1, 1),
(11, 'blockchain', 'bics 2304', 1, 1, 1),
(12, 'software applications1', 'ics 2304', 1, 1, 1),
(13, 'Distributed ledgers and blockchain', 'bct 23012', 1, 1, 1);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
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

--
-- Constraints for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`),
  ADD CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  ADD CONSTRAINT `student_answers_ibfk_3` FOREIGN KEY (`selected_option_id`) REFERENCES `multiple_choice_options` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
