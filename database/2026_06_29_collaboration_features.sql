-- Adds the collaboration feature set on top of the ORIGINAL StudySync schema:
--   * messages.file_id         (chat messages can reference a shared file)
--   * group_invitations        (seven-day email + link invitations)
--   * notifications            (in-app notification centre)
--   * password_reset_tokens    (60-minute, single-use reset links)
--   * users profile fields     (bio, institution, course, avatar_path)
--
-- Run this EXACTLY ONCE against an existing database that predates the
-- collaboration features. New installations should import
-- database/init.sql instead and skip this file entirely (it already
-- contains everything below).

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- messages: optional link to a shared file
-- --------------------------------------------------------

ALTER TABLE `messages`
  ADD COLUMN `file_id` int(11) DEFAULT NULL AFTER `message`,
  ADD KEY `file_id` (`file_id`);

-- --------------------------------------------------------
-- New tables
-- --------------------------------------------------------

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

CREATE TABLE `password_reset_tokens` (
  `reset_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- users: profile fields (queried during sign-in and layout rendering)
-- --------------------------------------------------------

ALTER TABLE `users`
  ADD COLUMN `bio` text DEFAULT NULL AFTER `is_active`,
  ADD COLUMN `institution` varchar(150) DEFAULT NULL AFTER `bio`,
  ADD COLUMN `course` varchar(150) DEFAULT NULL AFTER `institution`,
  ADD COLUMN `avatar_path` varchar(500) DEFAULT NULL AFTER `course`;

-- --------------------------------------------------------
-- Indexes, auto-increment and foreign keys
-- --------------------------------------------------------

ALTER TABLE `group_invitations`
  ADD PRIMARY KEY (`invitation_id`),
  ADD UNIQUE KEY `uq_group_invitation_token` (`token`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `invited_by` (`invited_by`),
  ADD KEY `invited_user_id` (`invited_user_id`),
  ADD KEY `email_status` (`email`,`status`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_read_created` (`user_id`,`is_read`,`created_at`);

ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`reset_id`),
  ADD UNIQUE KEY `uq_password_reset_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires_at` (`expires_at`);

ALTER TABLE `group_invitations`
  MODIFY `invitation_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_reset_tokens`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE SET NULL;

ALTER TABLE `group_invitations`
  ADD CONSTRAINT `group_invitations_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `study_groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_invitations_ibfk_2` FOREIGN KEY (`invited_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_invitations_ibfk_3` FOREIGN KEY (`invited_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

COMMIT;
