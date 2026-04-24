# Login System — PHP + MySQLi + MySQL (XAMPP)

A complete, role-based login system with session management,
two user types (admin & user), polished dark-theme UI,
inline SVG icons, and no external PHP dependencies.

---

## File Structure

```
login-system/
├── index.php                  ← Entry point (redirects to login)
├── login.php                  ← Login page
├── logout.php                 ← Session destroy handler
├── seed_passwords.php         ← One-time password re-hasher (delete after use)
├── database.sql               ← Full schema + seed data
│
├── css/
│   └── main.css               ← Complete design system
│
├── includes/
│   ├── db.php                 ← MySQLi singleton connection
│   ├── auth.php               ← Session, login, logout, guard helpers
│   └── icons.php              ← Inline SVG icon library
│
├── admin/
│   ├── dashboard.php          ← Admin dashboard overview
│   ├── users.php              ← User management (create, edit, delete, search)
│   ├── sessions.php           ← Session audit log viewer
│   ├── permissions.php        ← Role & permission management
│   └── database.php           ← Database info & stats
│
└── user/
    └── dashboard.php          ← Regular-user dashboard
```

---

## Setup (XAMPP)

### 1. Copy files

Place the entire `login-system/` folder inside your XAMPP `htdocs`:

```
C:\xampp\htdocs\login-system\
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

Visit: `http://localhost/login-system/seed_passwords.php`

This regenerates the hashes with your local PHP. **Delete the file
immediately after:**

```
Del C:\xampp\htdocs\login-system\seed_passwords.php
```

### 5. Open the app

`http://localhost/login-system/`

---

## Demo Accounts

| Role  | Username | Password     | Redirects to           |
| ----- | -------- | ------------ | ---------------------- |
| Admin | `admin`  | `Admin@1234` | `/admin/dashboard.php` |
| User  | `john`   | `User@1234`  | `/user/dashboard.php`  |

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

### User Management (`/admin/users.php`)

- **Create users** — Add new admin or member accounts with custom roles
- **Search & filter** — Find users by name, username, or email
- **Edit users** — Update profile info and reset passwords
- **Toggle status** — Activate/deactivate accounts without deletion
- **Delete users** — Permanently remove user accounts (with confirmation)
- **Pagination** — Browse users 15 per page with navigation
- **Role assignment** — Assign admin or member roles on creation/edit
- **Stats dashboard** — View totals: all users, active users, admins, members

### Session Audit Log (`/admin/sessions.php`)

- **Login/logout tracking** — Full timestamp history per user
- **IP address logging** — See where each session originated
- **Browser fingerprint** — User-agent string for session verification
- **Active sessions** — Real-time view of currently logged-in users
- **Logout records** — Historical audit trail for compliance

### Permissions & Roles (`/admin/permissions.php`)

- **Role definitions** — Admin vs. Member role capabilities
- **Access control** — Granular page-level permissions

### Database Info (`/admin/database.php`)

- **Table overview** — Schema and record counts
- **Database statistics** — Size and performance metrics

---

## Database Schema

### `users`

| Column          | Type                 | Notes                      |
| --------------- | -------------------- | -------------------------- |
| id              | INT AUTO_INCREMENT   | PK                         |
| username        | VARCHAR(60) UNIQUE   | Login name                 |
| email           | VARCHAR(120) UNIQUE  |                            |
| password        | VARCHAR(255)         | bcrypt hash                |
| role            | ENUM('admin','user') | Controls dashboard routing |
| full_name       | VARCHAR(100)         | Display name               |
| avatar_initials | VARCHAR(3)           | 2-letter sidebar avatar    |
| last_login      | DATETIME             | Updated on each login      |
| created_at      | DATETIME             |                            |
| is_active       | TINYINT(1)           | 0 = disabled account       |

### `session_log`

| Column     | Type               | Notes               |
| ---------- | ------------------ | ------------------- |
| id         | INT AUTO_INCREMENT | PK                  |
| user_id    | INT                | FK → users.id       |
| session_id | VARCHAR(128)       | PHP session ID      |
| ip_address | VARCHAR(45)        | IPv4/IPv6           |
| user_agent | VARCHAR(255)       | Browser string      |
| login_at   | DATETIME           |                     |
| logout_at  | DATETIME           | NULL = still active |

---

## Extending

**Add a new protected page:**

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();          // redirect to login if not authenticated
require_role('admin');    // or 'user' — redirect to correct dash if wrong role
// ... your page code
```

**Add a new user via MySQL:**

```sql
INSERT INTO users (username, email, password, role, full_name, avatar_initials)
VALUES ('jane', 'jane@example.com', '<bcrypt_hash>', 'user', 'Jane Smith', 'JS');
```

Generate the hash in PHP: `echo password_hash('YourPassword', PASSWORD_BCRYPT);`
