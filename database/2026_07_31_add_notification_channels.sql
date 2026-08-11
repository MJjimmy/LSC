-- Adds phone number + per-channel notification preferences to users,
-- and a queue table for outbound email/SMS deliveries.
-- Run this against your existing database (phpMyAdmin > Import, or via CLI).

ALTER TABLE `users`
  ADD COLUMN `phone_number` VARCHAR(20) DEFAULT NULL AFTER `course`,
  ADD COLUMN `notify_email` TINYINT(1) NOT NULL DEFAULT 1 AFTER `phone_number`,
  ADD COLUMN `notify_sms` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notify_email`;

CREATE TABLE `notification_queue` (
  `queue_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `channel` ENUM('email','sms') NOT NULL,
  `recipient` VARCHAR(190) NOT NULL,
  `subject` VARCHAR(190) DEFAULT NULL,
  `body` TEXT NOT NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` TINYINT(3) NOT NULL DEFAULT 0,
  `last_error` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`queue_id`),
  KEY `status_idx` (`status`),
  KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
