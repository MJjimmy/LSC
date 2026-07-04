-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 31, 2026 at 04:31 PM
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
-- Database: `studysync_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `file_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `member_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_join_requests`
--

CREATE TABLE `group_join_requests` (
  `request_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `file_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_invitations`
--

CREATE TABLE `group_invitations` (
  `invitation_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `invited_by` int(11) NOT NULL,
  `invited_user_id` int(11) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `token` char(64) NOT NULL,
  `status` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(40) NOT NULL DEFAULT 'info',
  `title` varchar(180) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `reset_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `study_groups`
--

CREATE TABLE `study_groups` (
  `group_id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','done') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('student','admin') NOT NULL DEFAULT 'student',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `bio` text DEFAULT NULL,
  `institution` varchar(150) DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `avatar_path` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`member_id`),
  ADD UNIQUE KEY `uq_group_user` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `group_join_requests`
--
ALTER TABLE `group_join_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `uq_join_request_group_user` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `file_id` (`file_id`);

--
-- Indexes for table `group_invitations`
--
ALTER TABLE `group_invitations`
  ADD PRIMARY KEY (`invitation_id`),
  ADD UNIQUE KEY `uq_group_invitation_token` (`token`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `invited_by` (`invited_by`),
  ADD KEY `invited_user_id` (`invited_user_id`),
  ADD KEY `email_status` (`email`,`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_read_created` (`user_id`,`is_read`,`created_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`reset_id`),
  ADD UNIQUE KEY `uq_password_reset_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indexes for table `study_groups`
--
ALTER TABLE `study_groups`
  ADD PRIMARY KEY (`group_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_join_requests`
--
ALTER TABLE `group_join_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_invitations`
--
ALTER TABLE `group_invitations`
  MODIFY `invitation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `study_groups`
--
ALTER TABLE `study_groups`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `study_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `study_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `group_join_requests`
--
ALTER TABLE `group_join_requests`
  ADD CONSTRAINT `group_join_requests_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `study_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_join_requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `study_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE SET NULL;

--
-- Constraints for table `group_invitations`
--
ALTER TABLE `group_invitations`
  ADD CONSTRAINT `group_invitations_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `study_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_invitations_ibfk_2` FOREIGN KEY (`invited_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_invitations_ibfk_3` FOREIGN KEY (`invited_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `study_groups`
--
ALTER TABLE `study_groups`
  ADD CONSTRAINT `study_groups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `study_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
