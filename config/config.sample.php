<?php
/**
 * config.sample.php  –  copy this to config.local.php and fill in your values.
 * config.local.php is listed in .gitignore and must NEVER be committed.
 *
 * To use, set the environment variables below in your web-server vhost / .env,
 * OR edit config/db.php to read from this file directly.
 */

// putenv('URBANEATS_DB_HOST=localhost');
// putenv('URBANEATS_DB_PORT=3306');
// putenv('URBANEATS_DB_NAME=urbaneats_db');
// putenv('URBANEATS_DB_USER=your_db_user');
// putenv('URBANEATS_DB_PASS=your_db_password');

// Application settings
define('APP_NAME',    'UrbanEats');
define('APP_URL',     'http://localhost/UrbanEats-Cloud-Kitchen-Platform/public');
define('APP_VERSION', '1.0.0');

// Session settings
define('SESSION_TIMEOUT',  1800);   // 30 minutes idle timeout (seconds)
define('SESSION_NAME',     'urbaneats_session');

// Login throttle settings
define('MAX_LOGIN_ATTEMPTS', 5);     // failed attempts before lockout
define('LOCKOUT_DURATION',   900);   // seconds (15 minutes)

// Password reset token lifetime
define('RESET_TOKEN_EXPIRY', 3600);  // 1 hour in seconds

// Upload settings (Component B uses these too)
define('UPLOAD_MAX_SIZE',   2097152);              // 2 MB in bytes
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('UPLOAD_DIR',  __DIR__ . '/../public/uploads/');
