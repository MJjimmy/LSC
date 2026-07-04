-- StudySync collaboration features migration
-- Run once against an existing StudySync database before deploying this release.

START TRANSACTION;

ALTER TABLE users
  ADD COLUMN bio text DEFAULT NULL AFTER is_active,
  ADD COLUMN institution varchar(150) DEFAULT NULL AFTER bio,
  ADD COLUMN course varchar(150) DEFAULT NULL AFTER institution,
  ADD COLUMN avatar_path varchar(500) DEFAULT NULL AFTER course;

ALTER TABLE messages
  ADD COLUMN file_id int(11) DEFAULT NULL AFTER message,
  ADD KEY file_id (file_id),
  ADD CONSTRAINT messages_ibfk_3
    FOREIGN KEY (file_id) REFERENCES files (file_id) ON DELETE SET NULL;

CREATE TABLE group_invitations (
  invitation_id int(11) NOT NULL AUTO_INCREMENT,
  group_id int(11) NOT NULL,
  invited_by int(11) NOT NULL,
  invited_user_id int(11) DEFAULT NULL,
  email varchar(150) NOT NULL,
  token char(64) NOT NULL,
  status enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  expires_at datetime NOT NULL,
  PRIMARY KEY (invitation_id),
  UNIQUE KEY uq_group_invitation_token (token),
  KEY group_id (group_id),
  KEY invited_by (invited_by),
  KEY invited_user_id (invited_user_id),
  KEY email_status (email, status),
  CONSTRAINT group_invitations_ibfk_1 FOREIGN KEY (group_id) REFERENCES study_groups (group_id) ON DELETE CASCADE,
  CONSTRAINT group_invitations_ibfk_2 FOREIGN KEY (invited_by) REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT group_invitations_ibfk_3 FOREIGN KEY (invited_user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE notifications (
  notification_id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  type varchar(40) NOT NULL DEFAULT 'info',
  title varchar(180) NOT NULL,
  body varchar(500) DEFAULT NULL,
  link varchar(500) DEFAULT NULL,
  is_read tinyint(1) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (notification_id),
  KEY user_read_created (user_id, is_read, created_at),
  CONSTRAINT notifications_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE password_reset_tokens (
  reset_id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  token_hash char(64) NOT NULL,
  expires_at datetime NOT NULL,
  used_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (reset_id),
  UNIQUE KEY uq_password_reset_hash (token_hash),
  KEY user_id (user_id),
  KEY expires_at (expires_at),
  CONSTRAINT password_reset_tokens_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
