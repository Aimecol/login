# Login System — PHP + MySQLi + MySQL (XAMPP)

A complete, enterprise-grade role-based login system with session management,
two user types (admin & user), notifications, messaging, granular permissions,
polished dark-theme UI, inline SVG icons, and zero external PHP dependencies.

**Live Demo:** [https://login.aimecol.com](https://login.aimecol.com)

**Developer:** Aimecol  
**Email:** aimecol314@gmail.com

---

## File Structure

```
login/
├── index.php                       ← Entry point (redirects to login)
├── login.php                       ← Login page (username/password)
├── logout.php                      ← Session destroy handler
├── seed_passwords.php              ← One-time password re-hasher (delete after use)
├── database.sql                    ← Full schema + seed data
│
├── css/
│   └── main.css                    ← Complete design system
│
├── includes/
│   ├── db.php                      ← MySQLi singleton connection
│   ├── auth.php                    ← Session, login, logout, guard helpers
│   └── icons.php                   ← Inline SVG icon library (40+ icons)
│
├── admin/
│   ├── dashboard.php               ← Admin dashboard with stats & quick actions
│   ├── users.php                   ← User CRUD, search, filter, pagination
│   ├── sessions.php                ← Session audit log & IP tracking
│   ├── messages.php                ← Admin inbox & message management
│   ├── notifications.php           ← System notifications (for admins)
│   ├── permissions.php             ← Role-based permissions editor
│   ├── database.php                ← DB info, query runner, SQL export
│   ├── settings.php                ← System configuration (database-backed)
│   └── profile.php                 ← Admin profile & password change
│
└── user/
    ├── dashboard.php               ← User dashboard with stats
    ├── profile.php                 ← User profile & password change
    ├── messages.php                ← User inbox & sent messages
    ├── notifications.php           ← User notifications (email, message, system)
    ├── login_history.php           ← User's own session history
    └── change_password.php         ← Standalone password reset page
```

---

## Setup (XAMPP)

### 1. Copy files

Place the entire `login/` folder inside your XAMPP `htdocs`:

```
C:\xampp\htdocs\login\
```

### 2. Start XAMPP

Start **Apache** and **MySQL** from the XAMPP Control Panel.

### 3. Import the database

1. Open **phpMyAdmin**: `http://localhost/phpmyadmin`
2. Click **Import** (top tab)
3. Choose `login-system/database.sql`
4. Click **Go**

The database `login_system` is created with:

- A `users` table (seeded with 2 accounts)
- A `session_log` table (audit log)

### 4. (Optional) Re-seed passwords

If login fails with "Invalid username or password," the pre-hashed
bcrypt strings in the SQL may not match your PHP version.

Visit: `http://localhost/login/seed_passwords.php`

This regenerates the hashes with your local PHP. **Delete the file
immediately after:**

```
Del C:\xampp\htdocs\login\seed_passwords.php
```

### 5. Open the app

`http://localhost/login/`

---

## Demo Accounts

| Role  | Username  | Password | Redirects to           |
| ----- | --------- | -------- | ---------------------- |
| Admin | `Admin`   | `123`    | `/admin/dashboard.php` |
| User  | `Aimecol` | `123`    | `/user/dashboard.php`  |

---

## Security Features

| Feature                 | Detail                                                         |
| ----------------------- | -------------------------------------------------------------- |
| **Password hashing**    | `password_hash()` with bcrypt (cost 12)                        |
| **Prepared statements** | All DB queries use `$db->prepare()` + `bind_param()`           |
| **CSRF protection**     | Double-submit token on login & logout forms                    |
| **Session fixation**    | `session_regenerate_id(true)` on login                         |
| **Session destroy**     | Full cookie + data wipe on logout                              |
| **Role guards**         | `require_login()` + `require_role()` on every protected page   |
| **Session audit log**   | `session_log` table records IP, user-agent, login/logout times |
| **HTML escaping**       | `e()` helper (`htmlspecialchars`) on all output                |
| **No PDO**              | Pure MySQLi as requested                                       |

---

## Admin Features

The admin panel provides comprehensive system management tools:

### Dashboard (`/admin/dashboard.php`)

- **System overview** — User count, active sessions, and system stats at a glance
- **Quick actions** — Add new users, view recent logins, system health check
- **Key metrics** — Total users, active admins, session count, database size

### User Management (`/admin/users.php`)

- **Create users** — Add new admin or member accounts with custom roles
- **Search & filter** — Find users by name, username, or email
- **Edit users** — Update profile, role, and reset passwords
- **Toggle status** — Activate/deactivate accounts without deletion
- **Delete users** — Permanently remove accounts (with safeguards)
- **Pagination** — Browse users 15 per page with full navigation
- **Role assignment** — Assign admin or member roles on creation/edit
- **Stats dashboard** — View totals: all users, active users, admins, members
- **Bulk actions** — Coming soon

### Session Audit Log (`/admin/sessions.php`)

- **Login/logout tracking** — Full timestamp history per user
- **IP address logging** — See where each session originated from
- **Browser fingerprint** — User-agent string for device identification
- **Active sessions** — Real-time view of who's logged in
- **Session history** — Complete audit trail for compliance
- **Session purge** — Delete old session records manually

### Messages (`/admin/messages.php`)

- **Inbox management** — Receive and reply to messages from users
- **Unread counter** — Sidebar badge shows unread message count
- **Send to users** — Compose and send direct messages
- **Delete messages** — Remove messages from inbox or sent folder
- **Search messages** — Find messages by subject or body text
- **Conversation view** — Thread-like message display

### Permissions & Roles (`/admin/permissions.php`)

- **Granular permissions** — 50+ permission flags across 7 sections
- **Role definitions** — Admin vs. Member role capabilities matrix
- **Permission sections** — Users, Sessions, Permissions, Database, Settings, Profile, Messages, Notifications
- **Interactive toggle** — Check/uncheck permissions per role in real-time
- **Database-backed** — Permissions persist in the `permissions` table

### Database Info (`/admin/database.php`)

- **Table overview** — Schema, row counts, engine type for each table
- **Database statistics** — Total size, character set, collation, MySQL version
- **Query runner** — Execute SELECT/SHOW/DESCRIBE queries safely
- **SQL shortcuts** — Quick buttons for common queries
- **Schema export** — Download database structure as SQL
- **Optimize tables** — Defragment InnoDB tables for performance
- **Live metrics** — User count, session records, database size

### System Settings (`/admin/settings.php`)

- **General settings** — App name, URL, timezone, date format
- **Security config** — Password policy, login attempts, lockout duration, HTTPS enforcement
- **Session management** — Timeout, remember-me duration, session logging
- **Email/SMTP** — Configure outgoing mail server and sender address
- **Appearance** — Default theme, theme toggle option, pagination size
- **Database-backed** — All settings stored in `system_settings` table
- **Hot reload** — Changes apply immediately without restart

### Profile & Account (`/admin/profile.php`)

- **Profile view** — Display admin full name, username, email
- **Edit profile** — Update name, email, avatar
- **Change password** — With current password verification
- **Account security** — Last login, account status

---

## User Features

Regular users have a simplified dashboard focused on personal management:

### Dashboard (`/user/dashboard.php`)

- **Profile card** — Display name, email, role, avatar
- **Account stats** — Last login time, account creation date
- **Quick links** — Fast access to messages, notifications, profile
- **Recent activity** — Latest login history

### Messages (`/user/messages.php`)

- **Inbox** — Receive messages from admins and other users
- **Sent folder** — View messages you've sent
- **Read/unread** — Track message status
- **Reply to messages** — Compose replies to incoming messages
- **Delete messages** — Remove unwanted messages
- **Search** — Find messages by subject or body

### Notifications (`/user/notifications.php`)

- **Notification center** — View all system notifications
- **Types** — Info, success, warning, error, login, message, system
- **Mark as read** — Track which notifications you've seen
- **Delete notifications** — Clean up old notifications
- **Unread count** — Badge shows unread notification count
- **Notification links** — Click-through to related pages

### Login History (`/user/login_history.php`)

- **Session list** — All your past and active login sessions
- **IP & location** — IP address for each login
- **Device info** — Browser and user-agent details
- **Login/logout times** — Exact timestamps for security audit
- **Active sessions** — See all currently logged-in sessions
- **Logout session** — Sign out other sessions remotely

### Profile & Security (`/user/profile.php`)

- **Profile info** — View and edit your full name, email
- **Avatar initials** — Display your 2-letter avatar
- **Account status** — Check if your account is active
- **Password change** — `/user/change_password.php` — Change your password securely

---

## Database Schema

The system uses 6 coordinated tables with foreign key relationships and comprehensive indexing:

### `users` — Core user accounts

| Column          | Type                 | Notes                      |
| --------------- | -------------------- | -------------------------- |
| id              | INT AUTO_INCREMENT   | PK                         |
| username        | VARCHAR(60) UNIQUE   | Login identifier           |
| email           | VARCHAR(120) UNIQUE  | Contact address            |
| password        | VARCHAR(255)         | bcrypt hash (cost 12)      |
| role            | ENUM('admin','user') | Controls dashboard route   |
| full_name       | VARCHAR(100)         | Display name               |
| avatar_initials | VARCHAR(3)           | 2-letter sidebar avatar    |
| last_login      | DATETIME             | Updated on each login      |
| created_at      | DATETIME             | Account creation timestamp |
| is_active       | TINYINT(1)           | 0 = disabled, 1 = active   |

### `session_log` — Authentication audit trail

| Column     | Type               | Notes                       |
| ---------- | ------------------ | --------------------------- |
| id         | INT AUTO_INCREMENT | PK                          |
| user_id    | INT                | FK → users.id (CASCADE)     |
| session_id | VARCHAR(128)       | PHP SESSIONID               |
| ip_address | VARCHAR(45)        | IPv4 or IPv6 address        |
| user_agent | VARCHAR(255)       | Browser/device string       |
| login_at   | DATETIME           | Login timestamp             |
| logout_at  | DATETIME           | Logout timestamp (nullable) |

### `notifications` — In-app notifications

| Column     | Type                                                                | Notes                   |
| ---------- | ------------------------------------------------------------------- | ----------------------- |
| id         | INT AUTO_INCREMENT                                                  | PK                      |
| user_id    | INT                                                                 | FK → users.id (CASCADE) |
| type       | ENUM('info','success','warning','error','login','message','system') | Notification category   |
| title      | VARCHAR(120)                                                        | Short heading           |
| message    | TEXT                                                                | Full notification body  |
| link       | VARCHAR(255)                                                        | URL to navigate to      |
| is_read    | TINYINT(1)                                                          | 0 = unread, 1 = read    |
| created_at | DATETIME                                                            | Creation timestamp      |

### `messages` — Direct user-to-user messaging

| Column              | Type               | Notes                    |
| ------------------- | ------------------ | ------------------------ |
| id                  | INT AUTO_INCREMENT | PK                       |
| from_id             | INT                | FK → users.id (CASCADE)  |
| to_id               | INT                | FK → users.id (CASCADE)  |
| subject             | VARCHAR(200)       | Message subject          |
| body                | TEXT               | Message body             |
| is_read             | TINYINT(1)         | 0 = unread, 1 = read     |
| deleted_by_sender   | TINYINT(1)         | Soft delete for sender   |
| deleted_by_receiver | TINYINT(1)         | Soft delete for receiver |
| created_at          | DATETIME           | Sent timestamp           |

### `system_settings` — Configuration store

| Column        | Type                                          | Notes                                          |
| ------------- | --------------------------------------------- | ---------------------------------------------- |
| id            | INT AUTO_INCREMENT                            | PK                                             |
| setting_group | VARCHAR(40)                                   | general\|security\|sessions\|email\|appearance |
| setting_key   | VARCHAR(80)                                   | Unique config key                              |
| setting_value | TEXT                                          | Config value (as string)                       |
| value_type    | ENUM('string','int','bool','json','password') | Data type for casting                          |
| label         | VARCHAR(120)                                  | UI display label                               |
| description   | VARCHAR(255)                                  | Longer explanation                             |
| is_public     | TINYINT(1)                                    | 1 = safe for JS, 0 = server-only               |
| updated_at    | DATETIME                                      | Last modification time                         |

**Pre-loaded settings include:**

- General: app_name, app_url, timezone, date_format, maintenance_mode, allow_registration
- Security: min_password_len, strong_passwords, max_login_attempts, lockout_duration, force_https
- Sessions: session_lifetime, remember_me_days, log_sessions, auto_purge_logs
- Email: smtp_from, smtp_host, smtp_port, smtp_enc, smtp_user, smtp_pass
- Appearance: default_theme, allow_theme_toggle, per_page

### `permissions` — Fine-grained role-based access control

| Column         | Type                 | Notes                   |
| -------------- | -------------------- | ----------------------- |
| id             | INT AUTO_INCREMENT   | PK                      |
| role           | ENUM('admin','user') | Role assignment         |
| permission_key | VARCHAR(60)          | Unique permission flag  |
| section        | VARCHAR(40)          | Permission category     |
| section_label  | VARCHAR(80)          | Display label           |
| perm_label     | VARCHAR(120)         | Permission description  |
| is_granted     | TINYINT(1)           | 1 = granted, 0 = denied |
| updated_at     | DATETIME             | Last change timestamp   |

**Permission sections & flags:**

- **Users** — view_users, create_users, edit_users, delete_users, toggle_users
- **Sessions** — view_sessions, delete_sessions, clear_sessions
- **Permissions** — view_permissions, edit_permissions
- **Database** — view_database, export_database, run_queries
- **Settings** — view_settings, edit_settings
- **Profile** — view_profile, edit_profile, change_password
- **Messages** — view_messages, send_messages, delete_messages
- **Notifications** — view_notifications, manage_notifications, broadcast_notification

---

## Technology Stack

- **Language** — PHP 7.4+ (tested on 8.1+)
- **Database** — MySQL 5.7+ / MariaDB 10.3+
- **Authentication** — bcrypt password hashing, session-based
- **Front-end** — Vanilla HTML/CSS/JavaScript (no dependencies)
- **Icons** — Inline SVG library (40+ icons)
- **Styling** — CSS variables + dark/light theme support
- **ORM** — Raw MySQLi with prepared statements (no Doctrine/Eloquent)

---

## Security Features

| Feature                 | Implementation                                              |
| ----------------------- | ----------------------------------------------------------- |
| **Password hashing**    | `password_hash()` with bcrypt (cost 12)                     |
| **Prepared statements** | All DB queries use `$db->prepare()` + `bind_param()`        |
| **CSRF protection**     | Double-submit token on all forms                            |
| **Session fixation**    | `session_regenerate_id(true)` on login                      |
| **Session destroy**     | Full cookie + data wipe on logout                           |
| **Role guards**         | `require_login()` + `require_role()` on all protected pages |
| **Session audit log**   | Records IP, user-agent, login/logout times                  |
| **HTML escaping**       | `e()` helper wraps all user output                          |
| **HTTPS enforcement**   | Configurable via system_settings (force_https)              |
| **Lockout policy**      | Configurable max login attempts + lockout duration          |
| **SQL injection proof** | Parameterized queries throughout                            |
| **Zero dependencies**   | No composer packages, no npm, no vulnerability exposure     |

---

## Helper Functions

The system provides these convenience functions in `includes/auth.php`:

```php
// Session & Auth
session_user_id()              // Get current user ID
current_user_id()              // Alias for session_user_id()
current_user()                 // Get current user array
current_name()                 // Get current user's full_name
current_initials()             // Get current user's avatar_initials
is_logged_in()                 // Check if user is authenticated
is_admin()                      // Check if current user is admin

// Guards (throw redirect or exit)
require_login()                // Redirect to login if not authenticated
require_role($role)            // Redirect if wrong role

// Flash messages
set_flash($type, $msg)         // Set one-time message
get_flash($type)               // Retrieve and clear flash message

// Database
db()                           // Get MySQLi singleton connection

// Output
e($string)                     // Escape HTML (htmlspecialchars)
```

Example usage:

```php
<?php
require_once __DIR__ . '/../includes/auth.php';

require_login();               // Redirect to /login.php if not authenticated
require_role('admin');         // Redirect to user dashboard if not admin

$user = current_user();        // Get array of authenticated user
$name = current_name();        // Get user's full name
$is_admin = is_admin();        // Check role

// Start a flash message
set_flash('success', 'User created!');
header('Location: users.php');
?>
```

---

## Extending the System

### Add a new protected admin page

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

require_login();
require_role('admin');  // Only admins can access

$db = db();
$csrf = $_SESSION['csrf_token'] ?? '';

// ... your page logic
?>
```

### Add a new user account via SQL

```sql
-- Generate hash with: echo password_hash('SecurePassword', PASSWORD_BCRYPT);
INSERT INTO users (username, email, password, role, full_name, avatar_initials, is_active)
VALUES (
  'jane_doe',
  'jane@example.com',
  '$2y$10$...',        -- bcrypt hash here
  'user',
  'Jane Doe',
  'JD',
  1
);
```

### Modify permissions for a role

```sql
-- Grant message viewing to regular users
UPDATE permissions
SET is_granted = 1
WHERE role = 'user' AND permission_key = 'view_messages';
```

### Add a new system setting

```sql
INSERT INTO system_settings
  (setting_group, setting_key, setting_value, value_type, label, description, is_public)
VALUES
  ('general', 'max_file_upload', '10485760', 'int', 'Max File Upload (bytes)', 'Maximum file size in bytes', 0);
```

### Send a notification to a user

```sql
INSERT INTO notifications (user_id, type, title, message, link)
VALUES (
  3,
  'success',
  'Welcome!',
  'Your account has been created. Please log in to continue.',
  '/login.php'
);
```

---

## API Reference

Key function signatures from `includes/auth.php`:

```php
// Authentication
function session_user_id() : ?int
function current_user() : ?array
function is_logged_in() : bool
function require_login() : void
function require_role(string $role) : void

// Session Flash
function set_flash(string $type, string $msg) : void
function get_flash(string $type) : ?string

// Database
function db() : mysqli

// HTML Safety
function e(string $str) : string
```

---

## Troubleshooting

### "Table 'login_system.X' doesn't exist"

- **Cause:** Database schema not imported
- **Fix:** Run `database.sql` via phpMyAdmin → Import

### "Invalid username or password" (correct credentials)

- **Cause:** bcrypt hashes from SQL don't match PHP version
- **Fix:** Visit `/seed_passwords.php` once, then delete the file

### Dark theme not working

- **Cause:** Browser cache or theme setting not saved
- **Fix:** Open DevTools → Application → Local Storage → Clear `theme`

### Session expires immediately

- **Cause:** `session_lifetime` in system_settings is 0
- **Fix:** Update via `/admin/settings.php` or SQL

### Emails not sending

- **Cause:** SMTP not configured or server unreachable
- **Fix:** Check `/admin/settings.php` email section + test with `mail()` first

---

## License

This project is provided as-is for educational and development purposes.

---

## Support

**Developer:** Aimecol  
**Email:** aimecol314@gmail.com  
**Live Demo:** [https://login.aimecol.com](https://login.aimecol.com)

For issues, feature requests, or contributions, please reach out via email.
