-- LSC (Learn Share Connect) — Postgres schema
--
-- Converted from database/init.sql for Postgres (Render PaaS).
-- MySQL-specific syntax has been replaced:
--   - ENGINE=InnoDB      -> omitted (Postgres has its own storage engine)
--   - CHARSET=utf8mb4    -> ENCODING='UTF8'
--   - AUTO_INCREMENT     -> SERIAL (Postgres identity column)
--   - enum(...)          -> VARCHAR with CHECK constraint
--   - tinyint(1)         -> SMALLINT
--   - datetime           -> TIMESTAMP
--   - timestamp          -> TIMESTAMP
--   - SET SQL_MODE       -> omitted
--   - @@CHARACTER_SET    -> omitted (Postgres handles encoding automatically)

--
-- Database: lsc
--

-- Table structure for table files
CREATE TABLE files (
    file_id SERIAL PRIMARY KEY,
    group_id integer NOT NULL,
    uploaded_by integer NOT NULL,
    file_name varchar(255) NOT NULL,
    file_path varchar(500) NOT NULL,
    file_type varchar(50) NOT NULL,
    file_size integer NOT NULL,
    uploaded_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for table group_invitations
CREATE TABLE group_invitations (
    invitation_id SERIAL PRIMARY KEY,
    group_id integer NOT NULL,
    invited_by integer NOT NULL,
    invited_user_id integer DEFAULT NULL,
    email varchar(150) NOT NULL,
    token char(64) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','accepted','declined')),
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at timestamp NOT NULL
);

-- Table structure for table group_join_requests
CREATE TABLE group_join_requests (
    request_id SERIAL PRIMARY KEY,
    group_id integer NOT NULL,
    user_id integer NOT NULL,
    requested_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for table group_members
CREATE TABLE group_members (
    member_id SERIAL PRIMARY KEY,
    group_id integer NOT NULL,
    user_id integer NOT NULL,
    joined_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for table messages
CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    group_id integer NOT NULL,
    user_id integer NOT NULL,
    message text NOT NULL,
    file_id integer REFERENCES files(file_id) ON DELETE SET NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for table notification_queue
CREATE TABLE notification_queue (
    queue_id SERIAL PRIMARY KEY,
    user_id integer,
    channel varchar(20) NOT NULL DEFAULT 'email' CHECK (channel IN ('email','sms')),
    recipient varchar(190) NOT NULL,
    subject varchar(190) DEFAULT NULL,
    body text NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','sent','failed')),
    attempts smallint NOT NULL DEFAULT 0,
    last_error varchar(500) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at timestamp DEFAULT NULL
);

-- Table structure for table notifications
CREATE TABLE notifications (
    notification_id SERIAL PRIMARY KEY,
    user_id integer NOT NULL,
    type varchar(40) NOT NULL DEFAULT 'info',
    title varchar(180) NOT NULL,
    body varchar(500) DEFAULT NULL,
    link varchar(500) DEFAULT NULL,
    is_read boolean NOT NULL DEFAULT FALSE,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for table password_reset_tokens
CREATE TABLE password_reset_tokens (
    reset_id SERIAL PRIMARY KEY,
    user_id integer NOT NULL,
    token_hash char(64) NOT NULL,
    expires_at timestamp NOT NULL,
    used_at timestamp DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for table rate_limits
CREATE TABLE rate_limits (
    rl_key varchar(191) PRIMARY KEY,
    attempts integer NOT NULL DEFAULT 1,
    expires_at timestamp NOT NULL
);

-- Table structure for table study_groups
CREATE TABLE study_groups (
    group_id SERIAL PRIMARY KEY,
    group_name varchar(100) NOT NULL,
    description text DEFAULT NULL,
    created_by integer NOT NULL,
    is_private boolean NOT NULL DEFAULT FALSE,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for table tasks
CREATE TABLE tasks (
    task_id SERIAL PRIMARY KEY,
    group_id integer NOT NULL,
    assigned_to integer NOT NULL,
    title varchar(200) NOT NULL,
    description text DEFAULT NULL,
    due_date date NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','done')),
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table structure for table users
CREATE TABLE users (
    user_id SERIAL PRIMARY KEY,
    name varchar(100) NOT NULL,
    email varchar(150) NOT NULL,
    email_verified_at timestamp DEFAULT NULL,
    password_hash varchar(255) NOT NULL,
    role varchar(20) NOT NULL DEFAULT 'student' CHECK (role IN ('student','lecturer','admin')),
    is_active boolean NOT NULL DEFAULT TRUE,
    bio text DEFAULT NULL,
    institution varchar(150) DEFAULT NULL,
    course varchar(150) DEFAULT NULL,
    phone_number varchar(20) DEFAULT NULL,
    notify_email boolean NOT NULL DEFAULT TRUE,
    notify_sms boolean NOT NULL DEFAULT FALSE,
    avatar_path varchar(500) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for table files
CREATE INDEX idx_files_group_id ON files(group_id);
CREATE INDEX idx_files_uploaded_by ON files(uploaded_by);

-- Indexes for table group_invitations
CREATE UNIQUE INDEX uq_group_invitation_token ON group_invitations(token);
CREATE INDEX idx_group_invitations_group_id ON group_invitations(group_id);
CREATE INDEX idx_group_invitations_invited_by ON group_invitations(invited_by);
CREATE INDEX idx_group_invitations_invited_user_id ON group_invitations(invited_user_id);
CREATE INDEX idx_group_invitations_email_status ON group_invitations(email, status);

-- Indexes for table group_join_requests
CREATE UNIQUE INDEX uq_join_request_group_user ON group_join_requests(group_id, user_id);
CREATE INDEX idx_join_requests_user_id ON group_join_requests(user_id);

-- Indexes for table group_members
CREATE UNIQUE INDEX uq_group_user ON group_members(group_id, user_id);
CREATE INDEX idx_group_members_user_id ON group_members(user_id);

-- Indexes for table messages
CREATE INDEX idx_messages_group_id ON messages(group_id);
CREATE INDEX idx_messages_user_id ON messages(user_id);
CREATE INDEX idx_messages_file_id ON messages(file_id);

-- Indexes for table notification_queue
CREATE INDEX idx_notification_queue_status ON notification_queue(status);
CREATE INDEX idx_notification_queue_user_id ON notification_queue(user_id);

-- Indexes for table notifications
CREATE INDEX idx_notifications_user_read_created ON notifications(user_id, is_read, created_at);

-- Indexes for table password_reset_tokens
CREATE UNIQUE INDEX uq_password_reset_hash ON password_reset_tokens(token_hash);
CREATE INDEX idx_password_reset_user_id ON password_reset_tokens(user_id);
CREATE INDEX idx_password_reset_expires_at ON password_reset_tokens(expires_at);

-- Indexes for table rate_limits
CREATE INDEX idx_rate_limits_expires_at ON rate_limits(expires_at);

-- Indexes for table study_groups
CREATE INDEX idx_study_groups_created_by ON study_groups(created_by);

-- Indexes for table tasks
CREATE INDEX idx_tasks_group_id ON tasks(group_id);
CREATE INDEX idx_tasks_assigned_to ON tasks(assigned_to);

-- Indexes for table users
CREATE UNIQUE INDEX uq_users_email ON users(email);

-- AUTO_INCREMENT for dumped tables — replaced with SERIAL
-- (SERIAL automatically creates a sequence and sets the default)

-- Constraints for dumped tables
ALTER TABLE files
    ADD CONSTRAINT fk_files_group FOREIGN KEY (group_id) REFERENCES study_groups(group_id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_files_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(user_id);

ALTER TABLE group_invitations
    ADD CONSTRAINT fk_group_invitations_group FOREIGN KEY (group_id) REFERENCES study_groups(group_id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_group_invitations_invited_by FOREIGN KEY (invited_by) REFERENCES users(user_id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_group_invitations_invited_user FOREIGN KEY (invited_user_id) REFERENCES users(user_id) ON DELETE CASCADE;

ALTER TABLE group_join_requests
    ADD CONSTRAINT fk_join_requests_group FOREIGN KEY (group_id) REFERENCES study_groups(group_id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_join_requests_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

ALTER TABLE group_members
    ADD CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES study_groups(group_id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_group_members_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

ALTER TABLE messages
    ADD CONSTRAINT fk_messages_group FOREIGN KEY (group_id) REFERENCES study_groups(group_id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_messages_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

ALTER TABLE notification_queue
    ADD CONSTRAINT fk_notification_queue_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL;

ALTER TABLE notifications
    ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

ALTER TABLE password_reset_tokens
    ADD CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

ALTER TABLE study_groups
    ADD CONSTRAINT fk_study_groups_created_by FOREIGN KEY (created_by) REFERENCES users(user_id) ON UPDATE CASCADE;

ALTER TABLE tasks
    ADD CONSTRAINT fk_tasks_group FOREIGN KEY (group_id) REFERENCES study_groups(group_id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(user_id);