-- Admin accounts. Separate from `users`, so a customer can never gain admin rights.
-- Optional: setup_admin.php creates this table automatically. Use this file only if you prefer phpMyAdmin.
-- Not touched by the shop's schema.sql, so re-importing schema.sql will NOT delete your admins.
USE maison_sucre;

CREATE TABLE IF NOT EXISTS admins (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100) NOT NULL,
  email           VARCHAR(150) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,          -- bcrypt hash from password_hash()
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    INT UNSIGNED NOT NULL DEFAULT 0, -- unix time; login blocked until then
  last_login      DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
