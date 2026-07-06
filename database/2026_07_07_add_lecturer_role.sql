-- Allow public registration as either a student or lecturer.
-- Administrator access remains database-controlled.
ALTER TABLE users
  MODIFY COLUMN role ENUM('student', 'lecturer', 'admin') NOT NULL DEFAULT 'student';
