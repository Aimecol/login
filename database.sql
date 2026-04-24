-- ============================================================
--  Login System — Full Database Schema
--  Compatible with: MySQL 5.7+ / MariaDB 10.3+
--  Character set: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS login_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE login_system;

-- ============================================================
--  TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id               INT(11)      NOT NULL AUTO_INCREMENT,
  username         VARCHAR(60)  NOT NULL,
  email            VARCHAR(120) NOT NULL,
  password         VARCHAR(255) NOT NULL,
  role             ENUM('admin','user') NOT NULL DEFAULT 'user',
  full_name        VARCHAR(100) NOT NULL DEFAULT '',
  avatar_initials  VARCHAR(3)   NOT NULL DEFAULT '',
  last_login       DATETIME     DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: session_log
-- ============================================================
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
  CONSTRAINT fk_session_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: notifications
--  FIX: Fatal error — Table 'login_system.notifications' doesn't exist
--       (user/notifications.php line 32)
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
  id          INT(11)      NOT NULL AUTO_INCREMENT,
  user_id     INT(11)      NOT NULL,
  type        ENUM('info','success','warning','error','login','message','system')
              NOT NULL DEFAULT 'info',
  title       VARCHAR(120) NOT NULL DEFAULT '',
  message     TEXT         NOT NULL,
  link        VARCHAR(255) NOT NULL DEFAULT '',
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user    (user_id),
  KEY idx_notif_unread  (user_id, is_read),
  KEY idx_notif_created (created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: messages
--  FIX: Fatal error — Table 'login_system.messages' doesn't exist
--       (user/messages.php line 67)
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
  id                   INT(11)      NOT NULL AUTO_INCREMENT,
  from_id              INT(11)      NOT NULL,
  to_id                INT(11)      NOT NULL,
  subject              VARCHAR(200) NOT NULL,
  body                 TEXT         NOT NULL,
  is_read              TINYINT(1)   NOT NULL DEFAULT 0,
  deleted_by_sender    TINYINT(1)   NOT NULL DEFAULT 0,
  deleted_by_receiver  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_msg_inbox   (to_id,   is_read),
  KEY idx_msg_outbox  (from_id),
  KEY idx_msg_created (created_at),
  CONSTRAINT fk_msg_from FOREIGN KEY (from_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_to   FOREIGN KEY (to_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: system_settings
--  Replaces the hard-coded session values in admin/settings.php.
--  One row per configurable option, grouped by section tab.
-- ============================================================
CREATE TABLE IF NOT EXISTS system_settings (
  id            INT(11)       NOT NULL AUTO_INCREMENT,
  setting_group VARCHAR(40)   NOT NULL COMMENT 'general|security|sessions|email|appearance',
  setting_key   VARCHAR(80)   NOT NULL,
  setting_value TEXT          NOT NULL DEFAULT '',
  value_type    ENUM('string','int','bool','json','password')
                NOT NULL DEFAULT 'string',
  label         VARCHAR(120)  NOT NULL DEFAULT '',
  description   VARCHAR(255)  NOT NULL DEFAULT '',
  is_public     TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1=safe for JS, 0=server-only',
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_setting_key (setting_key),
  KEY idx_setting_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings
  (setting_group, setting_key, setting_value, value_type, label, description, is_public)
VALUES
-- General
('general','app_name',          'Login System',         'string',  'Application Name',          'Shown in browser tab and email headers.',                           1),
('general','app_url',           'http://localhost',     'string',  'Application URL',           'Base URL for email links and redirects.',                           1),
('general','timezone',          'Africa/Kigali',        'string',  'Timezone',                  'PHP timezone identifier for all date/time display.',                1),
('general','date_format',       'd M Y',                'string',  'Date Format',               'PHP date() string, e.g. "d M Y" → 24 Apr 2025.',                   1),
('general','maintenance_mode',  '0',                    'bool',    'Maintenance Mode',          'Non-admin users see a maintenance page when enabled.',              0),
('general','allow_registration','1',                    'bool',    'Allow Public Registration', 'When off, only admins can create new accounts.',                    1),
-- Security
('security','min_password_len', '8',  'int',  'Min Password Length',       'Passwords shorter than this are rejected at registration/reset.',    0),
('security','strong_passwords', '1',  'bool', 'Require Strong Passwords',  'Enforce uppercase, number, and symbol in every password.',           0),
('security','max_login_attempts','5', 'int',  'Max Login Attempts',        'Failed attempts before an account is temporarily locked.',           0),
('security','lockout_duration', '15', 'int',  'Lockout Duration (min)',    'Minutes a locked account must wait before retrying.',                0),
('security','force_https',      '1',  'bool', 'Force HTTPS',               'Redirect HTTP to HTTPS. Disable only on local dev.',                 0),
-- Sessions
('sessions','session_lifetime', '1800','int', 'Session Lifetime (sec)',    'Idle timeout before a logged-in session expires.',                   0),
('sessions','remember_me_days', '14', 'int',  'Remember Me (days)',        'Lifespan of a "remember me" cookie.',                               0),
('sessions','log_sessions',     '1',  'bool', 'Log Sessions',              'Record every login/logout in session_log.',                          0),
('sessions','auto_purge_logs',  '1',  'bool', 'Auto-Purge Old Logs',       'Delete session_log entries older than 90 days automatically.',       0),
-- Email / SMTP
('email','smtp_from','noreply@loginsystem.dev','string',  'From Address',  'Sender address for all outgoing mail.',                              0),
('email','smtp_host','smtp.mailgun.org',        'string',  'SMTP Host',    'Hostname of your outgoing mail server.',                             0),
('email','smtp_port','587',                     'int',     'SMTP Port',    '25 (plain), 465 (SSL), 587 (TLS/STARTTLS).',                         0),
('email','smtp_enc', 'tls',                     'string',  'Encryption',   'Transport security: tls, ssl, or none.',                             0),
('email','smtp_user','',                        'string',  'SMTP Username','Auth username; leave blank if not required.',                         0),
('email','smtp_pass','',                        'password','SMTP Password','Auth password. Encrypt in production.',                               0),
-- Appearance
('appearance','default_theme',      'light','string','Default Theme',       'Initial colour scheme: light, dark, or system.',                     1),
('appearance','allow_theme_toggle', '1',   'bool',  'Allow Theme Toggle',  'Show the light/dark toggle button to all users.',                    1),
('appearance','per_page',           '15',  'int',   'Items Per Page',      'Default pagination size for user, session, and message lists.',       1);

-- ============================================================
--  TABLE: permissions
--  One row per (role, permission_key). Replaces the in-memory
--  $default_permissions array in admin/permissions.php so that
--  user-role grants survive across requests.
-- ============================================================
CREATE TABLE IF NOT EXISTS permissions (
  id             INT(11)      NOT NULL AUTO_INCREMENT,
  role           ENUM('admin','user') NOT NULL,
  permission_key VARCHAR(60)  NOT NULL,
  section        VARCHAR(40)  NOT NULL,
  section_label  VARCHAR(80)  NOT NULL DEFAULT '',
  perm_label     VARCHAR(120) NOT NULL DEFAULT '',
  is_granted     TINYINT(1)   NOT NULL DEFAULT 0,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_role_perm (role, permission_key),
  KEY idx_perm_role    (role),
  KEY idx_perm_section (section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions
  (role, permission_key,        section,        section_label,       perm_label,                           is_granted)
VALUES
-- User Management
('admin','view_users',          'users',        'User Management',   'View user list',                     1),
('admin','create_users',        'users',        'User Management',   'Create new users',                   1),
('admin','edit_users',          'users',        'User Management',   'Edit user details',                  1),
('admin','delete_users',        'users',        'User Management',   'Delete users',                       1),
('admin','toggle_users',        'users',        'User Management',   'Activate / Deactivate users',        1),
('user', 'view_users',          'users',        'User Management',   'View user list',                     0),
('user', 'create_users',        'users',        'User Management',   'Create new users',                   0),
('user', 'edit_users',          'users',        'User Management',   'Edit user details',                  0),
('user', 'delete_users',        'users',        'User Management',   'Delete users',                       0),
('user', 'toggle_users',        'users',        'User Management',   'Activate / Deactivate users',        0),
-- Session Logs
('admin','view_sessions',       'sessions',     'Session Logs',      'View session logs',                  1),
('admin','delete_sessions',     'sessions',     'Session Logs',      'Delete session records',             1),
('admin','clear_sessions',      'sessions',     'Session Logs',      'Clear all session logs',             1),
('user', 'view_sessions',       'sessions',     'Session Logs',      'View own session history',           1),
('user', 'delete_sessions',     'sessions',     'Session Logs',      'Delete session records',             0),
('user', 'clear_sessions',      'sessions',     'Session Logs',      'Clear all session logs',             0),
-- Permissions
('admin','view_permissions',    'permissions',  'Permissions',       'View permissions panel',             1),
('admin','edit_permissions',    'permissions',  'Permissions',       'Modify role permissions',            1),
('user', 'view_permissions',    'permissions',  'Permissions',       'View permissions panel',             0),
('user', 'edit_permissions',    'permissions',  'Permissions',       'Modify role permissions',            0),
-- Database
('admin','view_database',       'database',     'Database',          'View database info & table stats',   1),
('admin','export_database',     'database',     'Database',          'Export database / SQL schema',       1),
('admin','run_queries',         'database',     'Database',          'Execute custom SQL queries',         1),
('user', 'view_database',       'database',     'Database',          'View database info & table stats',   0),
('user', 'export_database',     'database',     'Database',          'Export database / SQL schema',       0),
('user', 'run_queries',         'database',     'Database',          'Execute custom SQL queries',         0),
-- System Settings
('admin','view_settings',       'settings',     'System Settings',   'View settings page',                 1),
('admin','edit_settings',       'settings',     'System Settings',   'Modify system settings',             1),
('user', 'view_settings',       'settings',     'System Settings',   'View settings page',                 0),
('user', 'edit_settings',       'settings',     'System Settings',   'Modify system settings',             0),
-- Profile
('admin','view_profile',        'profile',      'Profile',           'View own profile',                   1),
('admin','edit_profile',        'profile',      'Profile',           'Edit own profile',                   1),
('admin','change_password',     'profile',      'Profile',           'Change own password',                1),
('user', 'view_profile',        'profile',      'Profile',           'View own profile',                   1),
('user', 'edit_profile',        'profile',      'Profile',           'Edit own profile',                   1),
('user', 'change_password',     'profile',      'Profile',           'Change own password',                1),
-- Messages
('admin','view_messages',       'messages',     'Messages',          'View inbox and sent messages',       1),
('admin','send_messages',       'messages',     'Messages',          'Send messages to other users',       1),
('admin','delete_messages',     'messages',     'Messages',          'Delete own messages',                1),
('user', 'view_messages',       'messages',     'Messages',          'View inbox and sent messages',       1),
('user', 'send_messages',       'messages',     'Messages',          'Send messages to other users',       1),
('user', 'delete_messages',     'messages',     'Messages',          'Delete own messages',                1),
-- Notifications
('admin','view_notifications',      'notifications','Notifications', 'View own notifications',             1),
('admin','manage_notifications',    'notifications','Notifications', 'Mark read / delete notifications',   1),
('admin','broadcast_notification',  'notifications','Notifications', 'Send system-wide notifications',     1),
('user', 'view_notifications',      'notifications','Notifications', 'View own notifications',             1),
('user', 'manage_notifications',    'notifications','Notifications', 'Mark read / delete notifications',   1),
('user', 'broadcast_notification',  'notifications','Notifications', 'Send system-wide notifications',     0);

-- ============================================================
--  Seed Data — two default accounts
--  Admin  → username: Admin   | password: 123
--  User   → username: Aimecol | password: 123
-- ============================================================
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

-- Sample notifications
INSERT INTO notifications (user_id, type, title, message, link, is_read) VALUES
(2, 'login',   'New login detected',         'Your account was accessed from 127.0.0.1.',         'login_history.php', 0),
(2, 'message', 'New message from Admin',     'You have a new message from Admin User.',             'messages.php',      0),
(2, 'system',  'Scheduled maintenance',      'Maintenance window tonight: 23:00 - 01:00 UTC.',     '',                  0),
(2, 'success', 'Password changed',           'Your password was updated successfully.',              'profile.php',       1),
(1, 'system',  'New user registered',        'Aimecol Mazimpaka just created an account.',          'users.php',         0);

-- Sample messages
INSERT INTO messages (from_id, to_id, subject, body) VALUES
(1, 2, 'Welcome to Login System!',
 'Hi Aimecol,\n\nWelcome aboard! This is the internal messaging system. Reach out if you have any questions.\n\nBest,\nAdmin'),
(2, 1, 'Re: Welcome to Login System!',
 'Hi Admin,\n\nThank you for the warm welcome! Everything looks great.\n\nBest,\nAimecol');