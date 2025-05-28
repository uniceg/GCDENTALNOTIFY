-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 27, 2025 at 09:28 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `medicalclinicnotify`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `adminID` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `adminEmail` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `adminName` varchar(100) NOT NULL,
  `adminLastName` varchar(100) NOT NULL,
  `adminMiddleInitial` char(1) DEFAULT NULL,
  `id_verified` tinyint(1) DEFAULT 0,
  `contactNumber` varchar(15) NOT NULL,
  `profilePhoto` varchar(255) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `ocr_result` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`adminID`, `password`, `adminEmail`, `created_at`, `adminName`, `adminLastName`, `adminMiddleInitial`, `id_verified`, `contactNumber`, `profilePhoto`, `reset_token`, `reset_expires`, `ocr_result`) VALUES
('15', 'admin123', 'WAYNE@GMAUIL.COM', '2024-12-11 05:49:55', 'WAYNE', 'GOMEZ', 'W', 1, '0928373764', NULL, NULL, NULL, NULL),
('18', 'admin123', 'uni@gmail.com', '2025-03-03 12:04:31', 'unies', 'garnerd', 'R', -1, '09346262728', NULL, NULL, NULL, NULL),
('19', 'admin123', 'jerabi2677@jazipo.com', '2025-04-01 09:31:58', 'ani', 'niw', 'w', -1, '0987654321', NULL, '606c7436b0ebf7e404e0f39a5af75f95602d8281112f808ccbc01adc2fc5b87d', '2025-05-09 13:55:17', NULL),
('202511173', 'admin123', 'az@gmail.com', '2025-04-06 12:49:10', 'az', 'garica', 'v', 1, '09501027871', NULL, NULL, NULL, NULL),
('202511184', 'admin123', 'eunicegardner26@gmail.com', '2025-05-07 10:09:09', 'Eunice', 'Gardner', 'B', 1, '09357708539', NULL, NULL, NULL, NULL),
('202511185', 'admin123', 'nebona9006@inkight.com', '2025-05-07 16:58:00', 'Jhonny', 'Maestro', 'A', 0, '090989723', NULL, '754d4b0c766cebe544603a6f20902447b322499940527e8063281b8765cd81fd', '2025-05-09 13:52:38', NULL),
('202511189', 'admin123', 'ssuiaz2nsx@knmcadibav.com', '2025-05-09 11:40:32', 'Pra', 'Med', 'A', 0, '09098048238', NULL, NULL, NULL, NULL),
('202511190', 'admin123', '4vownge0dq@qacmjeq.com', '2025-05-09 12:01:26', 'Sha', 'Ky', 'S', 0, '09098048238', NULL, NULL, NULL, NULL),
('202511191', 'admin123', 'pocoh66427@inkight.com', '2025-05-09 16:31:29', 'Man', 'Girl', 'A', 0, '09098023132', NULL, NULL, NULL, NULL),
('202511192', 'admin123', 'pepeli6426@inkight.com', '2025-05-09 19:19:05', 'Pat', 'Ouer', 'A', 0, '0909802313', NULL, NULL, NULL, NULL),
('202511193', 'admin123', 'mehibiy245@jazipo.com', '2025-05-09 19:26:46', 'Hui', 'Lklas', 'A', 0, '09098025621', NULL, NULL, NULL, NULL),
('654321', 'admin123', 'CHANTAL@GMAIL.COM', '2025-04-01 09:37:24', 'Chantal', 'alvarez', 'R', -1, '091435252', NULL, NULL, NULL, NULL),
('ADM-2025-0001', 'admin123', 'medicalclinicnotify@gmail.com', '2025-05-12 14:35:56', 'Juan', 'Dela Cruz', 'M', 1, '09098048238', 'uploads/admin_photos/admin_ADM-2025-0001_1748200480.jpg', NULL, NULL, 'REPUBLIKA NG PILIPINAS\nRepublic of the Philippines\n\nPANIBANSANG PAGKAKAKILAN N\n\nPhilippine Identification ie\n\n ApelyidoLast Me ae \n DELA CRUZ\n\nMga PangalanGiven Names\n\n JUAN\n\nGitnang ApelyidoMiddle Name\nMARTINEZ\n\nYetsa ng KapanganakanDate of Birth \n\na ge 1990\n\nTirahanAddress y\n833 SISA ST BRGY 526 ZONE4 52 SAMPALOK MANILA\nCITY METRO MANILA\n\nJannd');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `AppointmentID` int(11) NOT NULL,
  `StudentID` int(11) DEFAULT NULL,
  `DoctorID` varchar(15) DEFAULT NULL,
  `SlotID` int(11) DEFAULT NULL,
  `AppointmentDate` date DEFAULT NULL,
  `Reason` varchar(50) DEFAULT NULL,
  `statusID` int(11) DEFAULT NULL,
  `TestResultFile` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocked_dates`
--

CREATE TABLE `blocked_dates` (
  `BlockID` int(11) NOT NULL,
  `DoctorID` int(11) NOT NULL,
  `BlockedDate` date NOT NULL,
  `Reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `DoctorID` varchar(15) NOT NULL,
  `FirstName` varchar(100) DEFAULT NULL,
  `LastName` varchar(100) DEFAULT NULL,
  `ContactNumber` varchar(15) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `ImageFile` varchar(255) DEFAULT NULL,
  `Specialization` varchar(100) DEFAULT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Status` varchar(20) DEFAULT 'Active',
  `ProfilePhoto` varchar(255) DEFAULT NULL,
  `Password` varchar(255) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`DoctorID`, `FirstName`, `LastName`, `ContactNumber`, `Email`, `ImageFile`, `Specialization`, `Phone`, `Status`, `ProfilePhoto`, `Password`, `reset_token`, `reset_expires`) VALUES
('DOC-2025-0005', 'Daisy', 'Cos-Suede', '09123456789', 'daisy.cossuede@gmail.com', NULL, 'Dentist', '09123456789', 'Active', 'uploads/doctor_photos/doctor_DOC-2025-0005_1748295079.jpg', '123', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notificationID` int(11) NOT NULL,
  `studentID` int(11) NOT NULL,
  `appointmentID` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_admin_notification` tinyint(1) DEFAULT 0,
  `cancellation_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verification`
--

CREATE TABLE `otp_verification` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `otp_expiry` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_verification`
--

INSERT INTO `otp_verification` (`id`, `email`, `otp`, `otp_expiry`, `created_at`) VALUES
(14, 'pocoh66427@inkight.com', '387378', '2025-05-10 02:59:34', '2025-05-10 02:49:34'),
(15, 'pocoh66427@inkight.com', '341145', '2025-05-10 03:02:08', '2025-05-10 02:52:08'),
(16, 'pocoh66427@inkight.com', '868405', '2025-05-10 03:03:40', '2025-05-10 02:53:40'),
(19, 'niyeloy702@inkight.com', '350722', '2025-05-10 19:00:47', '2025-05-10 18:50:47'),
(20, 'niyeloy702@inkight.com', '308058', '2025-05-10 19:28:23', '2025-05-10 19:18:23'),
(21, 'niyeloy702@inkight.com', '808352', '2025-05-10 19:31:18', '2025-05-10 19:21:18'),
(22, 'niyeloy702@inkight.com', '110802', '2025-05-10 19:35:40', '2025-05-10 19:25:40'),
(23, 'xehatif148@jazipo.com', '999494', '2025-05-10 19:46:53', '2025-05-10 19:36:53'),
(24, 'xehatif148@jazipo.com', '528384', '2025-05-10 19:50:49', '2025-05-10 19:40:49'),
(27, 'xehatif148@jazipo.com', '682551', '2025-05-10 20:09:09', '2025-05-10 19:59:09');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL,
  `staff_name` varchar(100) NOT NULL,
  `role` varchar(100) NOT NULL,
  `availability` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_appointments`
--

CREATE TABLE `staff_appointments` (
  `StaffAppointmentID` int(11) NOT NULL,
  `AppointmentID` int(11) NOT NULL,
  `StaffID` int(11) NOT NULL,
  `StaffRole` varchar(100) NOT NULL,
  `AppointmentDate` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `status`
--

CREATE TABLE `status` (
  `statusID` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `status`
--

INSERT INTO `status` (`statusID`, `status_name`) VALUES
(1, 'Pending'),
(2, 'Approved'),
(3, 'Completed'),
(4, 'Cancelled'),
(5, 'Cancellation Requested');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `studentID` bigint(20) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `parentGuardian` varchar(100) DEFAULT NULL,
  `contactNumber` varchar(20) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `dateOfBirth` date DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `lastLogin` datetime DEFAULT NULL,
  `lastUpdated` datetime DEFAULT NULL,
  `otpCode` varchar(10) DEFAULT NULL,
  `otpExpiry` datetime DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `yearLevel` varchar(20) DEFAULT NULL,
  `year` varchar(10) DEFAULT NULL,
  `profilePhoto` varchar(255) DEFAULT NULL,
  `firstName` varchar(100) DEFAULT NULL,
  `lastName` varchar(100) DEFAULT NULL,
  `parentContact` varchar(20) DEFAULT NULL,
  `emergencyContactName` varchar(100) DEFAULT NULL,
  `emergencyContactRelationship` varchar(50) DEFAULT NULL,
  `emergencyContactNumber` varchar(20) DEFAULT NULL,
  `bloodType` varchar(5) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medicalConditions` text DEFAULT NULL,
  `medications` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`studentID`, `name`, `address`, `parentGuardian`, `contactNumber`, `course`, `dateOfBirth`, `email`, `gender`, `lastLogin`, `lastUpdated`, `otpCode`, `otpExpiry`, `password`, `yearLevel`, `year`, `profilePhoto`, `firstName`, `lastName`, `parentContact`, `emergencyContactName`, `emergencyContactRelationship`, `emergencyContactNumber`, `bloodType`, `allergies`, `medicalConditions`, `medications`) VALUES
(202310704, 'Aljhun A. Abanes', '#15 Nieves st., Mabayuan Olongapo City', 'Mae A. Abanes', '09123456789', 'Bachelor of Science in Information Technology', '2005-08-11', '202310704@gordoncollege.edu.ph', 'Male', '2025-05-26 13:46:55', '0000-00-00 00:00:00', '490461', '2025-05-15 22:59:27', 'Abanes2023', '2nd Year', NULL, 'uploads/profile_202310704_1748238445.jpg', 'Aljhun', 'A. Abanes', '', '', '', '', '', '', '', ''),
(202311173, 'Eunice Gardner', '#15 Nieves st., Mabayuan Olongapo City', 'Zuey Z. Gardner', '09123456789', 'Bachelor of Science in Information Technology', '2005-08-11', '202311173@gordoncollege.edu.ph', 'Female', '2025-05-28 02:36:49', '2025-05-15 22:41:58', '490461', '2025-05-15 22:59:27', 'Gardner2023', '2nd Year', NULL, 'uploads/profile_202311173_1748107391.jpg', 'Eunice', 'Gardner', '09092812308', 'Eui', 'Sis', '09813289183', 'A', 'None', 'None', 'None');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `user_type` varchar(20) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `action`, `user_id`, `user_type`, `details`, `created_at`) VALUES
(1, 'Admin login', 'ADM-2025-0001', 'admin', 'Admin logged in successfully', '2025-05-25 19:32:06'),
(2, 'New doctor added', 'ADM-2025-0001', 'admin', 'Added doctor: DOC-2025-0001', '2025-05-25 19:32:06'),
(3, 'System settings updated', 'ADM-2025-0001', 'admin', 'Email notification settings updated', '2025-05-25 19:32:06'),
(4, 'Student profile updated', 'ADM-2025-0001', 'admin', 'Updated student profile: 202311173', '2025-05-25 19:32:06');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` varchar(50) DEFAULT 'text',
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `updated_at`, `updated_by`) VALUES
(1, 'clinic_name', 'GSIS General Clinic', 'text', 'general', 'Name of the clinic', '2025-05-25 19:47:00', NULL),
(2, 'clinic_address', '123 Medical Center Drive, Healthcare City', 'text', 'general', 'Clinic address', '2025-05-25 19:47:01', NULL),
(3, 'clinic_phone', '+1 (555) 123-4567', 'text', 'general', 'Clinic contact number', '2025-05-25 19:47:01', NULL),
(4, 'clinic_email', 'admin@gsisclinic.com', 'email', 'general', 'Clinic email address', '2025-05-25 19:47:01', NULL),
(5, 'appointment_duration', '30', 'number', 'appointments', 'Default appointment duration in minutes', '2025-05-25 19:47:01', NULL),
(6, 'max_appointments_per_day', '50', 'number', 'appointments', 'Maximum appointments per day', '2025-05-25 19:47:01', NULL),
(7, 'advance_booking_days', '30', 'number', 'appointments', 'How many days in advance can appointments be booked', '2025-05-25 19:47:01', NULL),
(8, 'clinic_open_time', '08:00', 'time', 'schedule', 'Clinic opening time', '2025-05-25 19:47:01', NULL),
(9, 'clinic_close_time', '17:00', 'time', 'schedule', 'Clinic closing time', '2025-05-25 19:47:01', NULL),
(10, 'lunch_start_time', '12:00', 'time', 'schedule', 'Lunch break start time', '2025-05-25 19:47:01', NULL),
(11, 'lunch_end_time', '13:00', 'time', 'schedule', 'Lunch break end time', '2025-05-25 19:47:01', NULL),
(12, 'email_notifications', '1', 'checkbox', 'notifications', 'Enable email notifications', '2025-05-25 19:47:01', NULL),
(13, 'sms_notifications', '0', 'checkbox', 'notifications', 'Enable SMS notifications', '2025-05-25 19:47:01', NULL),
(14, 'auto_confirm_appointments', '0', 'checkbox', 'appointments', 'Automatically confirm appointments', '2025-05-25 19:47:01', NULL),
(15, 'maintenance_mode', '0', 'checkbox', 'system', 'Enable maintenance mode', '2025-05-25 19:47:01', NULL),
(16, 'session_timeout', '30', 'number', 'security', 'Session timeout in minutes', '2025-05-25 19:47:01', NULL),
(17, 'max_login_attempts', '5', 'number', 'security', 'Maximum login attempts before lockout', '2025-05-25 19:47:01', NULL),
(18, 'password_min_length', '6', 'number', 'security', 'Minimum password length', '2025-05-25 19:47:01', NULL),
(19, 'backup_frequency', 'weekly', 'select', 'backup', 'Database backup frequency', '2025-05-25 19:47:01', NULL),
(20, 'timezone', 'Asia/Manila', 'select', 'general', 'System timezone', '2025-05-25 19:47:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `test_results`
--

CREATE TABLE `test_results` (
  `ResultID` int(11) NOT NULL,
  `AppointmentID` int(11) NOT NULL,
  `FilePath` varchar(255) NOT NULL,
  `FileName` varchar(255) NOT NULL,
  `UploadDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timeslots`
--

CREATE TABLE `timeslots` (
  `SlotID` int(11) NOT NULL,
  `DoctorID` varchar(15) DEFAULT NULL,
  `AvailableDay` varchar(10) DEFAULT NULL,
  `StartTime` time DEFAULT NULL,
  `EndTime` time DEFAULT NULL,
  `IsAvailable` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timeslots`
--

INSERT INTO `timeslots` (`SlotID`, `DoctorID`, `AvailableDay`, `StartTime`, `EndTime`, `IsAvailable`) VALUES
(33359, 'DOC-2025-0005', 'Thursday', '13:00:00', '14:00:00', 1),
(33360, 'DOC-2025-0005', 'Friday', '15:00:00', '16:00:00', 1),
(33361, 'DOC-2025-0005', 'Saturday', '17:00:00', '18:00:00', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`adminID`),
  ADD UNIQUE KEY `email` (`adminEmail`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`AppointmentID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `DoctorID` (`DoctorID`),
  ADD KEY `SlotID` (`SlotID`),
  ADD KEY `statusID` (`statusID`);

--
-- Indexes for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  ADD PRIMARY KEY (`BlockID`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`DoctorID`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notificationID`),
  ADD KEY `studentID` (`studentID`),
  ADD KEY `appointmentID` (`appointmentID`);

--
-- Indexes for table `otp_verification`
--
ALTER TABLE `otp_verification`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`);

--
-- Indexes for table `staff_appointments`
--
ALTER TABLE `staff_appointments`
  ADD PRIMARY KEY (`StaffAppointmentID`),
  ADD KEY `AppointmentID` (`AppointmentID`),
  ADD KEY `StaffID` (`StaffID`);

--
-- Indexes for table `status`
--
ALTER TABLE `status`
  ADD PRIMARY KEY (`statusID`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`studentID`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `test_results`
--
ALTER TABLE `test_results`
  ADD PRIMARY KEY (`ResultID`),
  ADD KEY `AppointmentID` (`AppointmentID`);

--
-- Indexes for table `timeslots`
--
ALTER TABLE `timeslots`
  ADD PRIMARY KEY (`SlotID`),
  ADD KEY `DoctorID` (`DoctorID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `AppointmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  MODIFY `BlockID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `otp_verification`
--
ALTER TABLE `otp_verification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_appointments`
--
ALTER TABLE `staff_appointments`
  MODIFY `StaffAppointmentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `status`
--
ALTER TABLE `status`
  MODIFY `statusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `test_results`
--
ALTER TABLE `test_results`
  MODIFY `ResultID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `timeslots`
--
ALTER TABLE `timeslots`
  MODIFY `SlotID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33362;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`SlotID`) REFERENCES `timeslots` (`SlotID`),
  ADD CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`statusID`) REFERENCES `status` (`statusID`),
  ADD CONSTRAINT `fk_appointments_doctor` FOREIGN KEY (`DoctorID`) REFERENCES `doctors` (`DoctorID`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`appointmentID`) REFERENCES `appointments` (`AppointmentID`);

--
-- Constraints for table `staff_appointments`
--
ALTER TABLE `staff_appointments`
  ADD CONSTRAINT `staff_appointments_ibfk_1` FOREIGN KEY (`AppointmentID`) REFERENCES `appointments` (`AppointmentID`),
  ADD CONSTRAINT `staff_appointments_ibfk_2` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`staff_id`);

--
-- Constraints for table `test_results`
--
ALTER TABLE `test_results`
  ADD CONSTRAINT `test_results_ibfk_1` FOREIGN KEY (`AppointmentID`) REFERENCES `appointments` (`AppointmentID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `timeslots`
--
ALTER TABLE `timeslots`
  ADD CONSTRAINT `fk_timeslots_doctor` FOREIGN KEY (`DoctorID`) REFERENCES `doctors` (`DoctorID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
