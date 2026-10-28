# UrbanEats Cloud Kitchen Platform

> A database-driven cloud kitchen web application built with HTML5, CSS3, PHP 8.x and MySQL.

---

## Table of Contents
1. [Setup Instructions](#setup-instructions)
2. [Test Accounts](#test-accounts)
3. [Features by Component](#features-by-component)
4. [Project Structure](#project-structure)

---

## Setup Instructions

### Prerequisites
- XAMPP / WAMP / LAMP with **PHP 8.x** and **MySQL / MariaDB**
- A web browser

### Steps

1. **Clone / copy the project** into your web-server root:
   ```
   C:\xampp\htdocs\UrbanEats-Cloud-Kitchen-Platform\
   ```

2. **Import the database**:
   - Open phpMyAdmin → *Import* → choose `database/urbaneats_db.sql`
   - The script creates the `urbaneats_db` database and all tables automatically.

3. **Configure credentials**:
   ```bash
   cp config/config.sample.php config/config.local.php
   ```
   Edit `config/config.local.php` and set `URBANEATS_DB_USER` / `URBANEATS_DB_PASS`.  
   *(If using XAMPP defaults: user = `root`, pass = `""`).*

4. **Create the uploads directory placeholder**:
   ```bash
   touch public/uploads/.gitkeep
   ```

5. **Create the log file**:
   ```bash
   touch logs/error.log
   ```

6. **Visit the app**:
   ```
   http://localhost/UrbanEats-Cloud-Kitchen-Platform/public/
   ```

---

## Test Accounts

> **All seed accounts use the password: `Password1!`**  
> *(You should change these immediately in a production environment.)*

| Role            | Username       | Email                    | Password    |
|-----------------|----------------|--------------------------|-------------|
| Administrator   | `nimali_admin` | nimali@urbaneats.lk      | Password1!  |
| Administrator   | `ashan_admin`  | ashan@urbaneats.lk       | Password1!  |
| Kitchen Staff   | `kasun_kitchen`| kasun@urbaneats.lk       | Password1!  |
| Kitchen Staff   | `dilini_chef`  | dilini@urbaneats.lk      | Password1!  |
| Customer        | `sachini_k`    | sachini@gmail.com        | Password1!  |
| Customer        | `tharaka_b`    | tharaka@gmail.com        | Password1!  |

---

## Features by Component

### Component A – Identity, Access & Administration *(Student 1)*
- ✅ Customer self-registration with validation and bcrypt hashing
- ✅ Login with throttling (locks after 5 failed attempts for 15 min)
- ✅ `session_regenerate_id()` on login to prevent session fixation
- ✅ Secure logout (POST-only, destroys session completely)
- ✅ Profile edit and change-password pages
- ✅ Password reset via time-limited token (displayed on-screen)
- ✅ Role-based navigation (Customer / Kitchen Staff / Administrator)
- ✅ Administrator dashboard with stats, recent audit events, failed logins
- ✅ User management: search, filter by role/status, paginated list
- ✅ Inline role change and activate/deactivate toggle
- ✅ Admin can create new accounts (Kitchen Staff, Administrator)
- ✅ Audit log viewer with search, user filter, and date range
- ✅ `log_action()` function – used by Components B and C
- ✅ `require_login()` and `require_role()` guard functions
- ✅ CSRF tokens on all POST forms
- ✅ Custom 403 and 500 error pages
- ✅ Shared header/footer with role-aware navigation
- ✅ Hand-written CSS with CSS variables, responsive at 360/768/1200px

### Component B – Menu, Catalogue & Inventory *(Student 2)*
- 📋 Public menu page with category filter, search, sort, pagination
- 📋 Dish detail page with image, price, availability
- 📋 Staff: add/edit/delete dishes and categories (image upload)
- 📋 Stock dashboard with low-stock / out-of-stock highlights
- 📋 `reduce_stock($dish_id, $qty)` function used by Component C

### Component C – Ordering, Payment & Tracking *(Student 3)*
- 📋 Session-based cart (add, update, remove)
- 📋 Checkout with delivery/pickup, payment method, coupon
- 📋 Order tracking and customer order history
- 📋 Kitchen queue for staff – order workflow state machine
- 📋 Administrator sales reports with date range and top dishes

---

## Project Structure

```
urbaneats/
├── config/
│   ├── db.php                # PDO singleton
│   └── config.sample.php     # Copy to config.local.php
├── includes/
│   ├── bootstrap.php         # Session, error handling, config
│   ├── auth.php              # require_login(), require_role()
│   ├── csrf.php              # csrf_field(), csrf_verify()
│   ├── functions.php         # log_action(), flash(), e(), paginate()
│   ├── header.php            # Shared page header + nav
│   ├── footer.php            # Shared footer + JS
│   └── errors/
│       ├── 403.php
│       └── 500.php
├── public/
│   ├── index.php             # Landing page
│   ├── css/style.css         # Shared stylesheet
│   ├── uploads/              # Dish images (gitignored)
│   ├── auth/
│   │   ├── register.php
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── profile.php
│   │   ├── change-password.php
│   │   ├── reset-request.php
│   │   └── reset-password.php
│   ├── admin/
│   │   ├── dashboard.php
│   │   ├── users.php
│   │   ├── audit.php
│   │   ├── reports.php       # Implemented by Component C
│   │   └── sidebar.php
│   ├── menu/                 # Component B
│   └── orders/               # Component C
├── database/
│   └── urbaneats_db.sql
├── logs/
│   └── error.log             # Gitignored
└── README.md
```

---

## Security Measures
| Threat | Mitigation |
|---|---|
| SQL Injection | PDO prepared statements with parameter binding throughout |
| XSS | `htmlspecialchars()` via `e()` on all output |
| CSRF | `csrf_token()` hidden field on every POST form |
| Session Fixation | `session_regenerate_id(true)` on login |
| Brute Force | Login throttle: 5 attempts → 15-minute lockout |
| Password Storage | `password_hash()` with bcrypt cost 12 |
| Credential Leakage | `config.local.php` in `.gitignore`; `config.sample.php` committed |
| Privilege Escalation | `require_role()` on every protected page |
| Session Hijack | `httponly`, `samesite=Lax` cookie flags; idle timeout |
