-- Run this after registering jimmyadmin@gmail.com through the normal form.
-- The account keeps its existing password; only its role changes.
UPDATE users
SET role = 'admin'
WHERE LOWER(email) = LOWER('jimmyadmin@gmail.com');
