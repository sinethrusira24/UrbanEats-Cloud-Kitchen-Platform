# UrbanEats Cloud Kitchen Platform
## Component A: Test Plan & Execution Report
### Identity, Access and Administration Subsystem

**Module:** IT3113 Advanced Web Technologies  
**Component Owner:** Student 1 (Component A)  
**Execution Environment:** Windows 10/11, PHP 8.x, MariaDB / MySQL 10.4+, Apache (XAMPP)  
**Total Tests Executed:** 26  
**Passed:** 26  
**Failed:** 0  
**Status:** 100% Pass Rate  

---

## 1. Summary of Test Coverage

| Category | Test Case IDs | Scope | Status |
|---|---|---|---|
| **Registration & Validation** | TC-01 to TC-04 | User self-service signup, duplicate prevention, password policy | 4 / 4 Passed |
| **Authentication & Throttling** | TC-05 to TC-08 | Credential verification, failed attempts, 5-attempt rate limiter & 15-min lockout | 4 / 4 Passed |
| **Session & Authorization** | TC-09 to TC-11, TC-24 | Session regeneration, logout destruction, RBAC role guards, 30-min inactivity timeout | 4 / 4 Passed |
| **Profile & Credentials** | TC-12 to TC-15 | Name/email update, duplicate collision, password change & re-hashing | 4 / 4 Passed |
| **Password Reset** | TC-16 to TC-18 | Time-limited SHA-256 tokens, single-use enforcement, expired rejection | 3 / 3 Passed |
| **Administration Governance** | TC-19, TC-20, TC-26 | Role modification, user activation toggle, last active admin protection | 3 / 3 Passed |
| **Security & Auditing** | TC-21 to TC-23, TC-25 | CSRF tokens, SQL injection defense, XSS output sanitization, audit logging | 4 / 4 Passed |

---

## 2. Detailed Test Cases and Execution Evidence

### TC-01: Successful Customer Registration
- **Test Objective:** Verify that a new user can register with valid credentials and is assigned the `customer` role by default.
- **Preconditions:** User is unauthenticated. Username and email are not yet registered.
- **Input / Steps:**
  1. Navigate to `/public/auth/register.php`.
  2. Input Full Name: `"Tharindu Perera"`.
  3. Input Username: `"tharindu_p"`.
  4. Input Email: `"tharindu@gmail.com"`.
  5. Input Password: `"Password1!"`.
  6. Input Confirm Password: `"Password1!"`.
  7. Submit form.
- **Expected Result:** New user record created in `users` with `role_id = 3` (`customer`), `is_active = 1`, password hashed via bcrypt. Flash success message displayed, redirected to login page.
- **Actual Result:** User record created successfully (`user_id` generated, `role_id=3`, `is_active=1`). Redirected to `auth/login.php` with success notification.
- **Status:** **PASS**
- **Evidence:** Audit log entry `#26` inserted: `"Customer account registered: tharindu_p (tharindu@gmail.com)"`.

---

### TC-02: Duplicate Email Validation
- **Test Objective:** Ensure registration rejects duplicate email addresses.
- **Preconditions:** Email `nimali@urbaneats.lk` already exists in `users`.
- **Input / Steps:**
  1. Open registration form.
  2. Enter full name, new username `"nimali_new"`, and email `"nimali@urbaneats.lk"`.
  3. Enter valid password and confirmation. Submit form.
- **Expected Result:** Form submission blocked with error message `"An account with this email address already exists."`.
- **Actual Result:** Server-side duplicate email check returned collision. Form re-rendered with specific inline error message.
- **Status:** **PASS**

---

### TC-03: Duplicate Username Validation
- **Test Objective:** Ensure registration rejects duplicate usernames.
- **Preconditions:** Username `nimali_admin` already exists in `users`.
- **Input / Steps:**
  1. Fill registration form with username `"nimali_admin"` and a distinct new email.
  2. Submit form.
- **Expected Result:** Rejection with `"This username is already taken. Please choose another."`.
- **Actual Result:** Username query detected existing row; submission halted and error highlighted.
- **Status:** **PASS**

---

### TC-04: Invalid Registration Inputs & Password Strength Policy
- **Test Objective:** Enforce password complexity (min 8 characters, uppercase, lowercase, number, special character).
- **Preconditions:** Registration form open.
- **Input / Steps:**
  1. Test with password `"simple"` (violates length, case, number, symbol).
  2. Test with passwords missing special characters or uppercase letters.
- **Expected Result:** Validation failure listing all violated criteria.
- **Actual Result:** `validate_password_strength()` returned informative errors: minimum 8 characters, uppercase required, digit required, special character required.
- **Status:** **PASS**

---

### TC-05: Successful Login
- **Test Objective:** Authenticate user with correct credentials, regenerate session ID, and populate session variables.
- **Preconditions:** User `nimali_admin` exists with password `Password1!`.
- **Input / Steps:**
  1. Navigate to `/public/auth/login.php`.
  2. Enter Identifier: `"nimali_admin"`.
  3. Enter Password: `"Password1!"`.
  4. Submit form.
- **Expected Result:** `password_verify()` succeeds. `session_regenerate_id(true)` called. `$_SESSION['user_id']` and `$_SESSION['role'] = 'admin'` set. Redirected to `admin/index.php`.
- **Actual Result:** Authentication succeeded. Session ID regenerated. Redirected to Administrator Dashboard.
- **Status:** **PASS**

---

### TC-06: Incorrect Password
- **Test Objective:** Reject invalid password attempts with generic error to avoid account enumeration.
- **Preconditions:** Valid username `nimali_admin`.
- **Input / Steps:**
  1. Submit username `"nimali_admin"` with password `"WrongPassword999!"`.
- **Expected Result:** Login denied. Generic error displayed indicating invalid credentials and remaining attempts before lockout. Attempt recorded in `login_attempts` with `is_success = 0`.
- **Actual Result:** Failed attempt logged. Remaining attempts counter decremented. User denied access.
- **Status:** **PASS**

---

### TC-07: Five Consecutive Failed Login Attempts
- **Test Objective:** Accurately record consecutive failures up to the 5-attempt threshold.
- **Preconditions:** Target account or IP identifier.
- **Input / Steps:**
  1. Submit 5 invalid authentication attempts in sequence.
- **Expected Result:** 5 records inserted into `login_attempts` with `is_success = 0`.
- **Actual Result:** All 5 attempts recorded with client IP and timestamps. Throttling query identified 5 consecutive failures.
- **Status:** **PASS**

---

### TC-08: Temporary Account Lockout (15 Minutes)
- **Test Objective:** Enforce a 15-minute lockout once 5 failed attempts are reached.
- **Preconditions:** 5 consecutive failed attempts logged within the last 15 minutes.
- **Input / Steps:**
  1. Attempt 6th login submission with any password.
- **Expected Result:** Authentication blocked before password verification. User alerted with temporary lockout duration.
- **Actual Result:** `check_login_throttling()` returned `throttled = true` and `remaining_seconds > 0`. Error message: `"Account access temporarily locked due to 5 consecutive failed login attempts."`.
- **Status:** **PASS**

---

### TC-09: Secure Logout
- **Test Objective:** Completely invalidate the authenticated session upon user sign-out via POST request with CSRF protection.
- **Preconditions:** Active authenticated session.
- **Input / Steps:**
  1. Click "Sign Out" button (triggers POST to `/public/auth/logout.php` with valid `csrf_token`).
- **Expected Result:** Audit log records `user_logout`. `$_SESSION` cleared. Session cookie expired in browser. User redirected to login page. Accessing protected page subsequently fails.
- **Actual Result:** Session destroyed, cookie expired, redirected to `auth/login.php`.
- **Status:** **PASS**

---

### TC-10: Unauthorized Page Access (Unauthenticated)
- **Test Objective:** Verify unauthenticated users cannot access restricted pages directly via URL.
- **Preconditions:** Guest session (no `$_SESSION['user_id']`).
- **Input / Steps:**
  1. Attempt to browse directly to `/public/admin/index.php`.
- **Expected Result:** `require_login()` catches missing session. Target URL saved in `$_SESSION['redirect_after_login']`. Redirected to `auth/login.php` with prompt to sign in.
- **Actual Result:** Access intercepted; user redirected to login form.
- **Status:** **PASS**

---

### TC-11: Customer Accessing Administrator Pages (RBAC Guard)
- **Test Objective:** Prevent unauthorized role access to administrator pages.
- **Preconditions:** Authenticated user with role `customer` (`sachini_k`).
- **Input / Steps:**
  1. Navigate directly to `/public/admin/users.php`.
- **Expected Result:** `require_role('admin')` rejects user. HTTP 403 status code emitted. Friendly Access Denied page rendered.
- **Actual Result:** HTTP 403 status code returned. Friendly 403 page displayed. Security audit log entry created.
- **Status:** **PASS**

---

### TC-12: Successful Profile Update
- **Test Objective:** Allow authenticated user to update their own full name, username, and email.
- **Preconditions:** User logged in as `sachini_k`.
- **Input / Steps:**
  1. Open `/public/auth/profile.php`.
  2. Change full name to `"Sachini Kulatunga Silva"`.
  3. Submit form.
- **Expected Result:** Database record updated. Success flash message displayed.
- **Actual Result:** Record updated in database; success banner displayed; audit event logged.
- **Status:** **PASS**

---

### TC-13: Invalid Profile Update (Duplicate Collision)
- **Test Objective:** Prevent user from changing their email or username to one already owned by another account.
- **Preconditions:** User logged in as `sachini_k`. Another user has email `nimali@urbaneats.lk`.
- **Input / Steps:**
  1. In profile edit form, set email to `"nimali@urbaneats.lk"`.
  2. Submit form.
- **Expected Result:** Update rejected with `"This email address is already in use by another account."`.
- **Actual Result:** Collision detected excluding current user ID; update blocked.
- **Status:** **PASS**

---

### TC-14: Successful Password Change
- **Test Objective:** Enable user to change password after confirming current password.
- **Preconditions:** User authenticated.
- **Input / Steps:**
  1. Open `/public/auth/change-password.php`.
  2. Enter Current Password: `"Password1!"`.
  3. Enter New Password: `"NewSecureP@ss2026!"`.
  4. Confirm New Password: `"NewSecureP@ss2026!"`.
  5. Submit form.
- **Expected Result:** Current password verified. New password hashed with bcrypt cost 12. Database updated. Session ID regenerated.
- **Actual Result:** Password updated successfully. Authenticated session re-secured.
- **Status:** **PASS**

---

### TC-15: Incorrect Current Password During Password Change
- **Test Objective:** Reject password change when current password is wrong.
- **Preconditions:** User authenticated.
- **Input / Steps:**
  1. Enter Current Password: `"IncorrectCurrent1!"`.
  2. Enter valid new password and confirmation. Submit.
- **Expected Result:** Error: `"The current password you entered is incorrect."`. No database change.
- **Actual Result:** Password mismatch detected by `password_verify()`. Update aborted.
- **Status:** **PASS**

---

### TC-16: Valid Password Reset Request & Token Generation
- **Test Objective:** Generate a cryptographically secure 32-byte token, store its SHA-256 hash in database with a 30-minute expiry, and display demo reset link.
- **Preconditions:** Registered user `nuwan_a` (`nuwan@gmail.com`).
- **Input / Steps:**
  1. Navigate to `/public/auth/reset-request.php`.
  2. Enter identifier: `"nuwan@gmail.com"`.
  3. Submit form.
- **Expected Result:** Unused previous tokens invalidated. New record in `password_resets` with `token_hash`, `expires_at = NOW() + 30 MIN`, `used_at = NULL`. Demo reset link displayed on screen.
- **Actual Result:** Token generated, hashed, persisted. Reset link rendered in demo alert card.
- **Status:** **PASS**

---

### TC-17: Expired Reset Token
- **Test Objective:** Prevent password resets using expired tokens.
- **Preconditions:** Token record in `password_resets` where `expires_at < NOW()`.
- **Input / Steps:**
  1. Access `/public/auth/reset-password.php?token=<expired_token>`.
- **Expected Result:** Token rejected with message indicating token has expired. Request for new link offered.
- **Actual Result:** Expiry check triggered. Form suppressed; error message displayed.
- **Status:** **PASS**

---

### TC-18: Reused / Consumed Reset Token
- **Test Objective:** Prevent re-use of an already consumed password reset token.
- **Preconditions:** Token record in `password_resets` where `used_at IS NOT NULL`.
- **Input / Steps:**
  1. Access `/public/auth/reset-password.php?token=<used_token>`.
- **Expected Result:** Rejection: `"This password reset link has already been used."`.
- **Actual Result:** Query checked `used_at IS NULL`; rejected with single-use notice.
- **Status:** **PASS**

---

### TC-19: Administrator Role Change
- **Test Objective:** Allow administrator to promote or demote user roles between Customer, Kitchen Staff, and Administrator.
- **Preconditions:** Administrator logged in. Target user is `nuwan_a` (`customer`).
- **Input / Steps:**
  1. Open `/public/admin/users.php`.
  2. Select `"Kitchen Staff"` for user `nuwan_a`.
  3. Click "Update".
- **Expected Result:** Target user `role_id` changed to 2 (`kitchen_staff`). Audit log records role change.
- **Actual Result:** Database updated. Role badge updated to "Kitchen Staff". Audit log recorded.
- **Status:** **PASS**

---

### TC-20: User Activation / Deactivation Toggle
- **Test Objective:** Allow administrator to deactivate an active user and re-activate a suspended user.
- **Preconditions:** Target active user `nuwan_a`.
- **Input / Steps:**
  1. In `/public/admin/users.php`, click "Deactivate" for `nuwan_a`.
  2. Confirm dialog.
- **Expected Result:** `is_active` set to 0. User blocked from signing in. Subsequent login attempts rejected.
- **Actual Result:** `is_active` updated to 0. Deactivation audit event recorded.
- **Status:** **PASS**

---

### TC-21: CSRF Protection & Token Mismatch Rejection
- **Test Objective:** Reject state-changing requests when CSRF token is missing or forged.
- **Preconditions:** Any POST form (e.g., profile update, role change, logout).
- **Input / Steps:**
  1. Submit POST request with invalid `csrf_token = "malicious_fake_token"`.
- **Expected Result:** `csrf_verify()` returns false. Request terminated with HTTP 403 Forbidden.
- **Actual Result:** CSRF verification failed via `hash_equals()`. 403 Forbidden served.
- **Status:** **PASS**

---

### TC-22: SQL Injection Prevention via Prepared Statements
- **Test Objective:** Verify SQL injection attempts through form inputs or query parameters are treated as literal strings.
- **Preconditions:** User login and search fields.
- **Input / Steps:**
  1. Submit identifier: `' OR '1'='1' -- `.
  2. Submit password: `' OR '1'='1' -- `.
- **Expected Result:** PDO prepared statement binds parameters safely; injection fails; no unauthorized authentication.
- **Actual Result:** Query executed safely without syntax error or unexpected record leakage.
- **Status:** **PASS**

---

### TC-23: XSS Output Escaping via `e()` Helper
- **Test Objective:** Sanitize user-submitted HTML/JavaScript to prevent Stored and Reflected XSS.
- **Preconditions:** Inputs containing script tags: `<script>alert('XSS')</script>`.
- **Input / Steps:**
  1. Pass string through `e()` helper and render in HTML.
- **Expected Result:** Script tags converted to `&lt;script&gt;alert(&#039;XSS&#039;)&lt;/script&gt;`.
- **Actual Result:** Browser renders text as literal characters without executing script.
- **Status:** **PASS**

---

### TC-24: Session Inactivity Timeout (30 Minutes)
- **Test Objective:** Terminate session if user remains inactive longer than 1800 seconds.
- **Preconditions:** User session with `last_activity` set to 1900 seconds ago.
- **Input / Steps:**
  1. User issues subsequent request to any page.
- **Expected Result:** Inactivity check identifies expired session. Session cleared, cookie destroyed. User redirected to login with inactivity alert.
- **Actual Result:** Session timeout logic triggered; session destroyed; user prompted to sign in again.
- **Status:** **PASS**

---

### TC-25: Audit Log Creation via `log_action()`
- **Test Objective:** Confirm `log_action()` creates records in `audit_log` with actor, IP, timestamp, and details.
- **Preconditions:** Database connection available.
- **Input / Steps:**
  1. Invoke `log_action('dish_created', 'Dish ID 14 added to menu', 1)`.
- **Expected Result:** New record inserted into `audit_log` with `action = 'dish_created'`, user ID 1, and client IP.
- **Actual Result:** Record inserted with primary key generated. Reusable by Components B and C.
- **Status:** **PASS**

---

### TC-26: Prevention of Removing the Last Active Administrator
- **Test Objective:** Prevent system lockout by refusing deactivation or demotion of the final active administrator.
- **Preconditions:** Only 1 active administrator account exists in system.
- **Input / Steps:**
  1. Attempt to demote or deactivate the last remaining administrator.
- **Expected Result:** Action blocked with safety notice: `"Safety Protection: Cannot deactivate/demote the platform's last active administrator."`.
- **Actual Result:** Active administrator count checked before update; action aborted if count <= 1.
- **Status:** **PASS**

---

## 3. Test Conclusion

Component A — Identity, Access and Administration has completed full automated and manual validation across all 26 test cases. All security controls, role-based guards, database constraints, and shared interfaces conform strictly to the IT3113 assignment specification.
