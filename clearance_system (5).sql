-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 23, 2026 at 09:15 AM
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
-- Database: `clearance_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity` text DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_type` enum('admin','officer','student') DEFAULT 'officer',
  `target_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `account_status` enum('active','suspended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `fullname`, `email`, `phone`, `password`, `profile_image`, `created_at`, `last_login`, `account_status`) VALUES
(1, 'Abdulrazak Abdulazeez', 'abdulrazakabdullaziz9@gmail.com', '09121915801', '$2y$10$F3QjMIFRShuePGFGcnUTxe/.uwwPWnm2g9571syTgC0JNQkGryiwS', 'digital-signature.png', '2026-06-07 08:52:33', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `admission_list`
--

CREATE TABLE `admission_list` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `reg_number` varchar(50) DEFAULT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `faculty_name` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `programme` varchar(255) DEFAULT NULL,
  `degree_awarded` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'admitted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission_list`
--

INSERT INTO `admission_list` (`id`, `fullname`, `reg_number`, `faculty_id`, `faculty_name`, `department`, `programme`, `degree_awarded`, `status`) VALUES
(1, 'Abdulrazak Abdulazeez', '20/57179U/1', 1, 'faculty of science', 'mathematics', 'mathematics', 'B.Tech mathematics', 'admitted'),
(2, 'Abdulrazak Abdulazeez oyedare tunji', '20/57179D/2', 2, 'faculty of engineering', 'civil engineering', 'civil engineering', 'B.Tech Engineering', 'admitted'),
(3, 'Kabiru Omaga', '20/57179U/3', 3, 'faculty of Agric', 'Agric science', 'Agric science', 'B.Tech Agric', 'admitted');

-- --------------------------------------------------------

--
-- Table structure for table `clearance_requests`
--

CREATE TABLE `clearance_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `progress` int(11) DEFAULT 0,
  `status` enum('pending','cleared','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clearance_requests`
--

INSERT INTO `clearance_requests` (`id`, `student_id`, `progress`, `status`, `created_at`) VALUES
(1, 1, 0, 'pending', '2026-06-19 14:34:45');

-- --------------------------------------------------------

--
-- Table structure for table `clearance_status`
--

CREATE TABLE `clearance_status` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `department_role` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `comment` text DEFAULT NULL,
  `digital_signature` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `student_name` varchar(255) DEFAULT NULL,
  `reg_number` varchar(100) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `resubmitted_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `document_file` longtext DEFAULT NULL,
  `requirement_files` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clearance_status`
--

INSERT INTO `clearance_status` (`id`, `student_id`, `department_role`, `status`, `comment`, `digital_signature`, `approved_by`, `approved_at`, `student_name`, `reg_number`, `rejection_reason`, `resubmitted_at`, `updated_at`, `document_file`, `requirement_files`) VALUES
(1, 1, 'BURSAR', 'approved', 'approve', NULL, 1, '2026-06-19 14:39:03', 'Abdulrazak Abdulazeez', '20/57179U/1', NULL, NULL, '2026-06-19 14:39:03', '1781879685_clearance-template.pdf', NULL),
(2, 1, 'ALUMNI RELATION DIVISION', 'approved', 'good', NULL, 3, '2026-06-21 08:15:57', 'Abdulrazak Abdulazeez', '20/57179U/1', NULL, NULL, '2026-06-21 08:15:57', '1781879685_clearance-template.pdf', '[\"1782029740_IMG-20260609-WA0040.jpg\"]'),
(3, 1, 'LIBRARY', 'approved', 'good', NULL, 4, '2026-06-21 08:23:38', 'Abdulrazak Abdulazeez', '20/57179U/1', NULL, NULL, '2026-06-21 08:23:38', '1781879685_clearance-template.pdf', '[\"1782029791_IMG-20260609-WA0040.jpg\",\"1782030123_digital-stamp.png\",\"1782030157_digital-signature.png\",\"1782030200_student_1.png\"]'),
(4, 1, 'DEPARTMENT', 'approved', 'good', NULL, 1, '2026-06-21 08:10:11', 'Abdulrazak Abdulazeez', '20/57179U/1', NULL, NULL, '2026-06-21 08:10:11', '1781879685_clearance-template.pdf', '[\"1781889102_department-letter-template.pdf\"]'),
(5, 1, 'FACULTY', 'approved', 'good', NULL, 1, '2026-06-21 08:11:51', 'Abdulrazak Abdulazeez', '20/57179U/1', NULL, NULL, '2026-06-21 08:11:51', '1781879685_clearance-template.pdf', NULL),
(6, 1, 'SPORT UNIT', 'approved', 'approve', NULL, 5, '2026-06-19 14:40:34', 'Abdulrazak Abdulazeez', '20/57179U/1', NULL, NULL, '2026-06-19 14:40:34', '1781879685_clearance-template.pdf', NULL),
(7, 1, 'HALL', 'approved', 'good', NULL, 6, '2026-06-21 08:12:48', 'Abdulrazak Abdulazeez', '20/57179U/1', NULL, NULL, '2026-06-21 08:12:48', '1781879685_clearance-template.pdf', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `clearance_uploads`
--

CREATE TABLE `clearance_uploads` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clearance_uploads`
--

INSERT INTO `clearance_uploads` (`id`, `student_id`, `filename`, `uploaded_at`) VALUES
(1, 1, '1781879685_clearance-template.pdf', '2026-06-19 07:34:45');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `faculty_id`, `department_name`, `created_at`) VALUES
(1, 1, 'Computer Engineering', '2026-06-10 07:52:40'),
(2, 1, 'Mechanical Engineering', '2026-06-10 07:52:40'),
(3, 2, 'Computer Science', '2026-06-10 07:52:40'),
(4, 2, 'Mathematics', '2026-06-10 07:52:40'),
(5, 3, 'Architecture', '2026-06-10 07:52:40'),
(6, 3, 'Estate Management', '2026-06-10 07:52:40'),
(7, 4, 'Crop Production', '2026-06-10 07:52:40'),
(8, 4, 'Animal Science', '2026-06-10 07:52:40'),
(9, 5, 'Accounting', '2026-06-10 07:52:40'),
(10, 5, 'Business Management', '2026-06-10 07:52:40'),
(11, 6, 'Educational Technology', '2026-06-10 07:52:40'),
(12, 6, 'Science Education', '2026-06-10 07:52:40');

-- --------------------------------------------------------

--
-- Table structure for table `department_letters`
--

CREATE TABLE `department_letters` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `department_name` varchar(150) NOT NULL,
  `original_file` varchar(255) NOT NULL,
  `signed_file` varchar(255) DEFAULT NULL,
  `signature_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','signed') DEFAULT 'pending',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `signed_at` datetime DEFAULT NULL,
  `signed_pdf` varchar(255) DEFAULT NULL,
  `officer_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_letters`
--

INSERT INTO `department_letters` (`id`, `student_id`, `department_name`, `original_file`, `signed_file`, `signature_file`, `status`, `uploaded_at`, `signed_at`, `signed_pdf`, `officer_name`) VALUES
(1, 1, 'mathematics', '1781889102_department-letter-template.pdf', 'signed_letter_1.pdf', 'sig_1_1781624556.png', 'signed', '2026-06-19 17:11:42', '2026-06-19 10:11:53', NULL, 'Abdulrazak Abdulazeez');

-- --------------------------------------------------------

--
-- Table structure for table `department_officers`
--

CREATE TABLE `department_officers` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `faculty_name` varchar(255) NOT NULL,
  `department_id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `digital_signature` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_officers`
--

INSERT INTO `department_officers` (`id`, `fullname`, `email`, `phone`, `faculty_id`, `faculty_name`, `department_id`, `department_name`, `password`, `digital_signature`, `created_at`) VALUES
(1, 'Abdulrazak Abdulazeez', 'a@gmail.com', '09121915801', 1, 'Faculty of Science', 4, 'Mathematics', '$2y$10$AfmdA05.GJq8greaa4lAL.LoZI7Ykz89JYKAGKnEjEmhcpQxX6LwS', 'sig_1_1781624556.png', '2026-06-13 00:23:51'),
(2, 'Abdulrazak Abdulazeez', 'cs@gmail.com', '09121915801', 9, 'Faculty of Computing', 3, 'Computer Science', '$2y$10$AenmqFIf8Y2KA4zEityGxurxxG5nhU5WRWGtgFBe.ffuVNvs3CP82', NULL, '2026-06-17 17:35:47');

-- --------------------------------------------------------

--
-- Table structure for table `faculties`
--

CREATE TABLE `faculties` (
  `id` int(11) NOT NULL,
  `faculty_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculties`
--

INSERT INTO `faculties` (`id`, `faculty_name`) VALUES
(1, 'Faculty of Science'),
(2, 'Faculty of Engineering'),
(3, 'Faculty of Environmental Technology'),
(4, 'Faculty of Agriculture'),
(5, 'Faculty of Education'),
(6, 'Faculty of Management Sciences'),
(7, 'Faculty of Technology Education'),
(8, 'Faculty of Earth and Environmental Sciences'),
(9, 'Faculty of Computing');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_officers`
--

CREATE TABLE `faculty_officers` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `faculty_name` varchar(200) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `digital_signature` varchar(255) DEFAULT NULL,
  `signature_uploaded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty_officers`
--

INSERT INTO `faculty_officers` (`id`, `fullname`, `email`, `phone`, `faculty_id`, `faculty_name`, `password`, `created_at`, `digital_signature`, `signature_uploaded_at`) VALUES
(1, 'Abdulrazak Abdulazeez', 'f@gmail.com', '09121915801', 1, 'Faculty of Science', '$2y$10$WS6y4yQVRou0/MG65ACuzOQvVymCabu3QzCowZ10UkUDy4EgbQe9O', '2026-06-17 16:50:24', 'faculty_sig_1_1782029500.png', NULL),
(2, 'Abdulrazak Abdulazeez', 'foa@gmail.com', '09121915801', 4, 'Faculty of Agriculture', '$2y$10$N6/XONcNVqIYbaAbf9Sdy.P2hEe6jxa2IHMuYf2HOnsH4wcqsRA..', '2026-06-17 17:30:04', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `status`, `created_at`, `type`) VALUES
(1, 1, 'Your clearance for HALL was REJECTED. Please resubmit required documents.', 'read', '2026-06-19 16:41:33', NULL),
(2, 1, 'Your clearance application for HALL has been resubmitted and is awaiting review.', 'read', '2026-06-19 16:42:24', NULL),
(3, 1, 'Your clearance for HALL was REJECTED. Please resubmit required documents.', 'read', '2026-06-19 16:42:56', NULL),
(4, 1, 'Your clearance application for HALL has been resubmitted and is awaiting review.', 'read', '2026-06-19 16:43:42', NULL),
(5, 1, 'Your clearance for DEPARTMENT has been APPROVED', 'unread', '2026-06-21 08:10:11', NULL),
(6, 1, 'Your clearance for FACULTY has been APPROVED', 'unread', '2026-06-21 08:11:51', NULL),
(7, 1, 'Your clearance for HALL has been APPROVED', 'unread', '2026-06-21 08:12:48', NULL),
(8, 1, 'Your clearance for ALUMNI RELATION DIVISION has been APPROVED', 'unread', '2026-06-21 08:15:57', NULL),
(9, 1, 'Your clearance for LIBRARY has been APPROVED', 'unread', '2026-06-21 08:23:38', NULL),
(10, 1, '🎉 Clearance Completed Successfully. You can now print your slip.', 'unread', '2026-06-21 08:23:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `office` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `digital_signature` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'OFFICE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`id`, `fullname`, `office`, `email`, `phone`, `password`, `created_at`, `digital_signature`, `role`) VALUES
(1, 'Abdulrazak Abdulazeez', 'BURSAR', 'abdulrazakabdullaziz9@gmail.com', '09121915801', '$2y$10$eSQB3FnRKD4qndxoNCrAeuF/Byx8BV/WABr/9omO3F6.PEZI9q1ZG', '2026-06-13 00:21:24', '1781715140_signature_1.png', 'OFFICE'),
(2, 'Abdulrazak Abdulazeez', 'DEPARTMENT', 'a@gmail.com', '09121915801', '$2y$10$AfmdA05.GJq8greaa4lAL.LoZI7Ykz89JYKAGKnEjEmhcpQxX6LwS', '2026-06-13 00:23:51', 'sig_1_1781624556.png', 'DEPARTMENT'),
(3, 'Abdulrazak Abdulazeez', 'ALUMNI RELATION DIVISION', 'c@gmail.com', '09121915801', '$2y$10$wYnpGsWlVTBo2t0Nmvq7veqkuWLNFBRe6cgghaXCZY55aLFkP557O', '2026-06-17 16:45:50', '1782029691_signature_3.png', 'OFFICE'),
(4, 'Abdulrazak Abdulazeez', 'LIBRARY', 'd@gmail.com', '09121915801', '$2y$10$unE6BKP5fpOVmCcdUxv2COpyZ02il45P9truEYOJLpZbtEMBJfh1G', '2026-06-17 16:46:13', '1782029612_signature_4.png', 'OFFICE'),
(5, 'Abdulrazak Abdulazeez', 'SPORT UNIT', 'b@gmail.com', '09121915801', '$2y$10$LZ7cJ8eNCEka6QkUEYmGquO.OF73U6Z/yOkToapBW2KvOPNfNkrt.', '2026-06-17 16:46:54', '1781715245_signature_5.png', 'OFFICE'),
(6, 'Abdulrazak Abdulazeez', 'HALL', 'e@gmail.com', '09121915801', '$2y$10$YsclufkRuvqvt3guJC/3feHG/h1rkAfxzO/OGVLq3z/KEVKwsZ5Ca', '2026-06-17 16:47:34', '1781887138_signature_6.png', 'OFFICE'),
(7, 'Abdulrazak Abdulazeez', 'FACULTY', 'f@gmail.com', '09121915801', '$2y$10$WS6y4yQVRou0/MG65ACuzOQvVymCabu3QzCowZ10UkUDy4EgbQe9O', '2026-06-17 16:50:24', NULL, 'FACULTY'),
(9, 'Abdulrazak Abdulazeez', 'DEPARTMENT', 'cs@gmail.com', '09121915801', '$2y$10$AenmqFIf8Y2KA4zEityGxurxxG5nhU5WRWGtgFBe.ffuVNvs3CP82', '2026-06-17 17:35:47', NULL, 'DEPARTMENT');

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `id` int(11) NOT NULL,
  `offices_name` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offices`
--

INSERT INTO `offices` (`id`, `offices_name`, `status`) VALUES
(1, 'BURSAR', 'active'),
(2, 'ALUMNI RELATION DIVISION', 'active'),
(3, 'LIBRARY', 'active'),
(4, 'DEPARTMENT', 'active'),
(5, 'FACULTY', 'active'),
(6, 'SPORT UNIT', 'active'),
(7, 'HALL', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `office_requirements`
--

CREATE TABLE `office_requirements` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `bursar_100` varchar(255) DEFAULT NULL,
  `bursar_200` varchar(255) DEFAULT NULL,
  `bursar_300` varchar(255) DEFAULT NULL,
  `bursar_400` varchar(255) DEFAULT NULL,
  `bursar_500` varchar(255) DEFAULT NULL,
  `alumni_receipt` varchar(255) DEFAULT NULL,
  `library_card1` varchar(255) DEFAULT NULL,
  `library_card2` varchar(255) DEFAULT NULL,
  `library_card3` varchar(255) DEFAULT NULL,
  `library_id_card` varchar(255) DEFAULT NULL,
  `clearance_slip` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `system_name` varchar(255) DEFAULT NULL,
  `admin_email` varchar(255) DEFAULT NULL,
  `sms_api` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `system_name`, `admin_email`, `sms_api`, `updated_at`) VALUES
(1, 'Online Clearance System', 'admin@gmail.com', '', '2026-05-21 23:09:49');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `fullname` varchar(200) DEFAULT NULL,
  `reg_number` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `faculty_name` varchar(255) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `programme` varchar(255) DEFAULT NULL,
  `degree_awarded` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `passport` varchar(255) DEFAULT NULL,
  `status` enum('pending','cleared','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verification_token` varchar(255) DEFAULT NULL,
  `verified` enum('0','1') DEFAULT '0',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `clearance_slip` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `account_status` enum('active','suspended') DEFAULT 'active',
  `verification_code` varchar(100) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `email_sent` tinyint(4) DEFAULT 0,
  `senate_status` enum('pending','approved') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `fullname`, `reg_number`, `email`, `phone`, `faculty_id`, `faculty_name`, `department`, `programme`, `degree_awarded`, `password`, `passport`, `status`, `created_at`, `verification_token`, `verified`, `reset_token`, `reset_token_expiry`, `qr_code`, `clearance_slip`, `last_login`, `account_status`, `verification_code`, `deleted_at`, `email_sent`, `senate_status`) VALUES
(1, 'Abdulrazak Abdulazeez', '20/57179U/1', 'abdulrazakabdullaziz9@gmail.com', '09121915801', 1, 'faculty of science', 'mathematics', 'mathematics', 'B.Tech mathematics', '$2y$10$3Li9dk8nDDDN8KeC./RRhub7JcHyCTg.3mdCaaATk5mAq3knPYeL2', '1781870268_digital-signature.png', 'cleared', '2026-06-19 11:57:48', NULL, '0', NULL, NULL, NULL, NULL, NULL, 'active', '25ca3c88a8a712c297eeb1f1351dc47e', NULL, 1, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_documents`
--

CREATE TABLE `uploaded_documents` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admission_list`
--
ALTER TABLE `admission_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reg_number` (`reg_number`);

--
-- Indexes for table `clearance_requests`
--
ALTER TABLE `clearance_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clearance_status`
--
ALTER TABLE `clearance_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_clearance` (`student_id`,`department_role`);

--
-- Indexes for table `clearance_uploads`
--
ALTER TABLE `clearance_uploads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `department_letters`
--
ALTER TABLE `department_letters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `department_officers`
--
ALTER TABLE `department_officers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `faculty_officers`
--
ALTER TABLE `faculty_officers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `office_requirements`
--
ALTER TABLE `office_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reg_number` (`reg_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `uploaded_documents`
--
ALTER TABLE `uploaded_documents`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admission_list`
--
ALTER TABLE `admission_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `clearance_requests`
--
ALTER TABLE `clearance_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clearance_status`
--
ALTER TABLE `clearance_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `clearance_uploads`
--
ALTER TABLE `clearance_uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `department_letters`
--
ALTER TABLE `department_letters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `department_officers`
--
ALTER TABLE `department_officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `faculties`
--
ALTER TABLE `faculties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `faculty_officers`
--
ALTER TABLE `faculty_officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `office_requirements`
--
ALTER TABLE `office_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `uploaded_documents`
--
ALTER TABLE `uploaded_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `faculty_officers`
--
ALTER TABLE `faculty_officers`
  ADD CONSTRAINT `faculty_officers_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
