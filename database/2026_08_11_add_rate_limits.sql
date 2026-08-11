-- Backs LSC\Core\RateLimiter. Import via phpMyAdmin same as the other
-- migration files in this folder.

CREATE TABLE `rate_limits` (
  `rl_key` VARCHAR(191) NOT NULL,
  `attempts` INT(11) NOT NULL DEFAULT 1,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`rl_key`),
  KEY `expires_at_idx` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
