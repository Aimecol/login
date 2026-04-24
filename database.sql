CREATE DATABASE IF NOT EXISTS login_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE login_system;

CREATE TABLE IF NOT EXISTS users (
  id          INT(11)      NOT NULL AUTO_INCREMENT,
  username    VARCHAR(60)  NOT NULL,
  email       VARCHAR(120) NOT NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin','user') NOT NULL DEFAULT 'user',
  full_name   VARCHAR(100) NOT NULL DEFAULT '',
  avatar_initials VARCHAR(3) NOT NULL DEFAULT '',
  last_login  DATETIME     DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS session_log (
  id          INT(11)      NOT NULL AUTO_INCREMENT,
  user_id     INT(11)      NOT NULL,
  session_id  VARCHAR(128) NOT NULL,
  ip_address  VARCHAR(45)  NOT NULL DEFAULT '',
  user_agent  VARCHAR(255) NOT NULL DEFAULT '',
  login_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  logout_at   DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY fk_session_user (user_id),
  CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed Data — two default accounts
--   Admin  → username: Admin   | password: 123
--   User   → username: Aimecol    | password: 123
-- --------------------------------------------------------
INSERT INTO users (username, email, password, role, full_name, avatar_initials) VALUES
(
  'Admin',
  'admin@loginsystem.dev',
  '$2y$10$ccGzwflXMwJIBT262muJz.2oZZwetImm63B96Ug9jFLxR2D39JE3W',
  'admin',
  'Admin User',
  'AU'
),
(
  'Aimecol',
  'aimecol@loginsystem.dev',
  '$2y$10$ccGzwflXMwJIBT262muJz.2oZZwetImm63B96Ug9jFLxR2D39JE3W',
  'user',
  'Aimecol Mazimpaka',
  'AM'
);
