CREATE DATABASE IF NOT EXISTS `urbaneats_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `urbaneats_db`;

-- Disable foreign key checks during migration
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables in reverse dependency order
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Table 1: roles
-- Defines the RBAC authorization levels for UrbanEats
-- ----------------------------------------------------------------------------
CREATE TABLE `roles` (
  `role_id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(30) NOT NULL,
  `role_description` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uk_roles_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User authorization roles';

-- ----------------------------------------------------------------------------
-- Table 2: users
-- Central user account store for all platform actors
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
  `user_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` TINYINT UNSIGNED NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Deactivated/Suspended',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role_id` (`role_id`),
  KEY `idx_users_is_active` (`is_active`),
  KEY `idx_users_created_at` (`created_at`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`)
    REFERENCES `roles` (`role_id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Authenticated platform user accounts';

-- ----------------------------------------------------------------------------
-- Table 3: login_attempts
-- Captures authentication events for brute-force defense and 5-attempt lockout
-- ----------------------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `attempt_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(120) NOT NULL COMMENT 'Attempted username or email',
  `ip_address` VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 client address',
  `is_success` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Successful authentication, 0 = Failed attempt',
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attempt_id`),
  KEY `idx_login_attempts_lookup` (`identifier`, `attempted_at`),
  KEY `idx_login_attempts_ip` (`ip_address`, `attempted_at`),
  KEY `idx_login_attempts_success` (`is_success`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Authentication logs for rate limiting and lockouts';

-- ----------------------------------------------------------------------------
-- Table 4: audit_log
-- System-wide immutable record of security and administrative actions
-- ----------------------------------------------------------------------------
CREATE TABLE `audit_log` (
  `audit_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Actor user ID or NULL for system/unauthenticated events',
  `action` VARCHAR(100) NOT NULL COMMENT 'Action code (e.g., user_login, role_change, profile_update)',
  `details` TEXT NULL DEFAULT NULL COMMENT 'Structured description or JSON details of change',
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`),
  KEY `idx_audit_user_id` (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created_at` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security audit trail';

-- ----------------------------------------------------------------------------
-- Table 5: password_resets
-- Time-limited cryptographically secure password reset tokens
-- ----------------------------------------------------------------------------
CREATE TABLE `password_resets` (
  `reset_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of the plain reset token',
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reset_id`),
  KEY `idx_resets_token_hash` (`token_hash`),
  KEY `idx_resets_user_id` (`user_id`),
  KEY `idx_resets_expires_at` (`expires_at`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secure password reset requests';

-- ============================================================================
-- SEED DATA
-- Default password for all seed accounts is: Password1!
-- Hashed via password_hash('Password1!', PASSWORD_BCRYPT, ['cost' => 12])
-- Hash: $2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O
-- ============================================================================

-- Roles (Fixed domain enumeration)
INSERT INTO `roles` (`role_id`, `role_name`, `role_description`) VALUES
(1, 'admin', 'Full administrative access to manage users, security settings, and view system audit logs'),
(2, 'kitchen_staff', 'Kitchen operations access to manage inventory, update dishes, and process order stages'),
(3, 'customer', 'Self-service customer account to browse menus, place orders, and track deliveries');

-- Users (22 realistic accounts: 3 Admins, 4 Kitchen Staff, 15 Customers including 2 inactive)
INSERT INTO `users` (`user_id`, `role_id`, `full_name`, `username`, `email`, `password_hash`, `is_active`, `created_at`) VALUES
-- Administrators
(1, 1, 'Nimali Senanayake', 'nimali_admin', 'nimali@urbaneats.lk', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-01 08:00:00'),
(2, 1, 'Ashan Fernando', 'ashan_admin', 'ashan@urbaneats.lk', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-01 08:30:00'),
(3, 1, 'Kavinda Perera', 'kavinda_admin', 'kavinda@urbaneats.lk', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-02 09:15:00'),

-- Kitchen Staff
(4, 2, 'Kasun Jayawardena', 'kasun_kitchen', 'kasun@urbaneats.lk', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-02 10:00:00'),
(5, 2, 'Dilini Gunasekara', 'dilini_chef', 'dilini@urbaneats.lk', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-02 10:30:00'),
(6, 2, 'Roshan De Silva', 'roshan_kitchen', 'roshan@urbaneats.lk', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-03 11:00:00'),
(7, 2, 'Suneth Wickramasinghe', 'suneth_chef', 'suneth@urbaneats.lk', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-03 11:45:00'),

-- Customers (Active)
(8, 3, 'Sachini Kulatunga', 'sachini_k', 'sachini@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-04 12:00:00'),
(9, 3, 'Tharaka Bandara', 'tharaka_b', 'tharaka@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-04 12:30:00'),
(10, 3, 'Nuwan Alwis', 'nuwan_a', 'nuwan@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-05 13:10:00'),
(11, 3, 'Chamari Mendis', 'chamari_m', 'chamari@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-05 14:00:00'),
(12, 3, 'Anoma Rathnayake', 'anoma_r', 'anoma@yahoo.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-06 14:45:00'),
(13, 3, 'Malini Jayasuriya', 'malini_j', 'malini@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-06 15:20:00'),
(14, 3, 'Dhanushka Wijesinghe', 'dhanushka_w', 'dhanushka@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-07 16:00:00'),
(15, 3, 'Hasini Karunaratne', 'hasini_k', 'hasini@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-07 16:30:00'),
(16, 3, 'Ruwan Dissanayake', 'ruwan_d', 'ruwan@outlook.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-08 17:15:00'),
(17, 3, 'Piumi Samaraweera', 'piumi_s', 'piumi@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-08 18:00:00'),
(18, 3, 'Chathura Abeysekara', 'chathura_a', 'chathura@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-09 18:40:00'),
(19, 3, 'Sanduni Fonseka', 'sanduni_f', 'sanduni@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-09 19:10:00'),
(20, 3, 'Bhanuka Rajapaksa', 'bhanuka_r', 'bhanuka@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 1, '2026-10-10 10:00:00'),

-- Inactive Accounts (For testing deactivation & access denial)
(21, 3, 'Saman Kumara', 'saman_inactive', 'saman@gmail.com', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 0, '2026-10-10 11:00:00'),
(22, 2, 'Mahesh Weerakkody', 'mahesh_suspended', 'mahesh@urbaneats.lk', '$2y$12$HpBI4CgUcVHAPlztAa/7O.hKMcCUhgO.QUvHHJAcsvsqQqhCcSG6O', 0, '2026-10-11 09:00:00');

-- Login Attempts (24 realistic rows showing success, failed attempts, and brute-force lock attempts)
INSERT INTO `login_attempts` (`attempt_id`, `identifier`, `ip_address`, `is_success`, `attempted_at`) VALUES
(1, 'nimali_admin', '192.168.1.10', 1, '2026-10-12 08:30:00'),
(2, 'ashan_admin', '192.168.1.11', 1, '2026-10-12 08:45:00'),
(3, 'kasun_kitchen', '192.168.1.20', 1, '2026-10-12 09:00:00'),
(4, 'sachini_k', '203.115.26.15', 1, '2026-10-12 10:15:00'),
(5, 'tharaka_b', '203.115.26.22', 0, '2026-10-12 11:00:00'),
(6, 'tharaka_b', '203.115.26.22', 1, '2026-10-12 11:02:00'),
(7, 'nuwan_a', '203.115.26.40', 1, '2026-10-12 12:30:00'),
(8, 'saman_inactive', '203.115.26.75', 0, '2026-10-12 13:00:00'),
(9, 'unknown_attacker', '198.51.100.12', 0, '2026-10-12 14:00:00'),
(10, 'unknown_attacker', '198.51.100.12', 0, '2026-10-12 14:01:00'),
(11, 'unknown_attacker', '198.51.100.12', 0, '2026-10-12 14:02:00'),
(12, 'unknown_attacker', '198.51.100.12', 0, '2026-10-12 14:03:00'),
(13, 'unknown_attacker', '198.51.100.12', 0, '2026-10-12 14:04:00'),
(14, 'dilini_chef', '192.168.1.21', 1, '2026-10-13 08:00:00'),
(15, 'chamari_m', '203.115.26.60', 1, '2026-10-13 09:30:00'),
(16, 'anoma_r', '203.115.26.88', 1, '2026-10-13 10:15:00'),
(17, 'malini_j', '203.115.26.92', 0, '2026-10-13 11:20:00'),
(18, 'malini_j', '203.115.26.92', 1, '2026-10-13 11:21:00'),
(19, 'nimali_admin', '192.168.1.10', 1, '2026-10-14 08:15:00'),
(20, 'dhanushka_w', '203.115.26.105', 1, '2026-10-14 12:45:00'),
(21, 'hasini_k', '203.115.26.110', 1, '2026-10-14 13:30:00'),
(22, 'chathura_a', '203.115.26.120', 0, '2026-10-14 14:10:00'),
(23, 'chathura_a', '203.115.26.120', 1, '2026-10-14 14:11:00'),
(24, 'ashan_admin', '192.168.1.11', 1, '2026-10-14 15:00:00');

-- Audit Log (25 realistic rows documenting lifecycle events)
INSERT INTO `audit_log` (`audit_id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'system_seed', 'Platform database initialized with initial roles and administrators', '127.0.0.1', 'CLI-Installer', '2026-10-01 08:00:00'),
(2, 1, 'user_login', 'Administrator nimali_admin logged in', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-01 08:05:00'),
(3, 1, 'user_create', 'Created administrator account for Ashan Fernando (ashan_admin)', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-01 08:30:00'),
(4, 2, 'user_create', 'Created kitchen staff account for Kasun Jayawardena (kasun_kitchen)', '192.168.1.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-02 10:00:00'),
(5, 2, 'user_create', 'Created kitchen staff account for Dilini Gunasekara (dilini_chef)', '192.168.1.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-02 10:30:00'),
(6, NULL, 'user_registration', 'Customer self-registration: sachini_k (sachini@gmail.com)', '203.115.26.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-04 12:00:00'),
(7, 8, 'user_login', 'Customer sachini_k logged in', '203.115.26.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-04 12:02:00'),
(8, NULL, 'user_registration', 'Customer self-registration: tharaka_b (tharaka@gmail.com)', '203.115.26.22', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-04 12:30:00'),
(9, 8, 'profile_update', 'Updated profile information for user_id=8', '203.115.26.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-05 09:15:00'),
(10, 9, 'password_change', 'Customer tharaka_b successfully updated account password', '203.115.26.22', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', '2026-10-05 10:00:00'),
(11, 1, 'role_change', 'Updated role for user_id=7 from customer to kitchen_staff', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-06 11:30:00'),
(12, 1, 'account_deactivation', 'Deactivated user_id=21 (saman_inactive) upon security request', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-10 11:05:00'),
(13, 1, 'account_deactivation', 'Suspended kitchen staff user_id=22 (mahesh_suspended)', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-11 09:05:00'),
(14, NULL, 'login_failed', 'Failed login attempt for identifier=saman_inactive (Account deactivated)', '203.115.26.75', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-12 13:00:00'),
(15, NULL, 'login_failed', 'Failed login attempt for identifier=unknown_attacker', '198.51.100.12', 'Python-Requests/2.28.1', '2026-10-12 14:00:00'),
(16, NULL, 'login_lockout', 'Account/IP locked out due to 5 consecutive failed attempts: unknown_attacker', '198.51.100.12', 'Python-Requests/2.28.1', '2026-10-12 14:04:00'),
(17, 4, 'user_login', 'Kitchen staff kasun_kitchen logged in', '192.168.1.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-13 07:45:00'),
(18, 5, 'user_login', 'Kitchen staff dilini_chef logged in', '192.168.1.21', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-13 08:00:00'),
(19, 10, 'password_reset_request', 'Password reset requested for nuwan_a (nuwan@gmail.com)', '203.115.26.40', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-13 14:00:00'),
(20, 10, 'password_reset_complete', 'Password reset successfully executed for user_id=10', '203.115.26.40', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-13 14:15:00'),
(21, 1, 'user_login', 'Administrator nimali_admin logged in', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-14 08:15:00'),
(22, 1, 'account_activation', 'Re-activated customer user_id=18 (chathura_a)', '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-14 09:00:00'),
(23, 14, 'user_login', 'Customer dhanushka_w logged in', '203.115.26.105', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X)', '2026-10-14 12:45:00'),
(24, 14, 'user_logout', 'Customer dhanushka_w signed out cleanly', '203.115.26.105', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X)', '2026-10-14 13:15:00'),
(25, 2, 'user_login', 'Administrator ashan_admin logged in', '192.168.1.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-14 15:00:00');

-- Password Resets (20 realistic rows showing token lifecycle: consumed, expired, and active)
INSERT INTO `password_resets` (`reset_id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 8, SHA2('token_sachini_old_01', 256), '2026-10-04 13:00:00', '2026-10-04 12:20:00', '2026-10-04 12:10:00'),
(2, 9, SHA2('token_tharaka_old_02', 256), '2026-10-05 10:45:00', '2026-10-05 10:00:00', '2026-10-05 09:45:00'),
(3, 10, SHA2('token_nuwan_used_03', 256), '2026-10-06 14:45:00', '2026-10-06 14:15:00', '2026-10-06 14:00:00'),
(4, 11, SHA2('token_chamari_exp_04', 256), '2026-10-06 15:30:00', NULL, '2026-10-06 15:00:00'),
(5, 12, SHA2('token_anoma_exp_05', 256), '2026-10-07 10:30:00', NULL, '2026-10-07 10:00:00'),
(6, 13, SHA2('token_malini_used_06', 256), '2026-10-07 16:30:00', '2026-10-07 16:10:00', '2026-10-07 16:00:00'),
(7, 14, SHA2('token_dhanushka_exp_07', 256), '2026-10-08 11:30:00', NULL, '2026-10-08 11:00:00'),
(8, 15, SHA2('token_hasini_used_08', 256), '2026-10-08 17:00:00', '2026-10-08 16:40:00', '2026-10-08 16:30:00'),
(9, 16, SHA2('token_ruwan_exp_09', 256), '2026-10-09 09:30:00', NULL, '2026-10-09 09:00:00'),
(10, 17, SHA2('token_piumi_used_10', 256), '2026-10-09 18:30:00', '2026-10-09 18:12:00', '2026-10-09 18:00:00'),
(11, 18, SHA2('token_chathura_exp_11', 256), '2026-10-10 12:30:00', NULL, '2026-10-10 12:00:00'),
(12, 19, SHA2('token_sanduni_used_12', 256), '2026-10-10 17:30:00', '2026-10-10 17:15:00', '2026-10-10 17:00:00'),
(13, 20, SHA2('token_bhanuka_exp_13', 256), '2026-10-11 11:30:00', NULL, '2026-10-11 11:00:00'),
(14, 4, SHA2('token_kasun_used_14', 256), '2026-10-11 15:30:00', '2026-10-11 15:10:00', '2026-10-11 15:00:00'),
(15, 5, SHA2('token_dilini_exp_15', 256), '2026-10-12 10:30:00', NULL, '2026-10-12 10:00:00'),
(16, 6, SHA2('token_roshan_used_16', 256), '2026-10-12 16:30:00', '2026-10-12 16:20:00', '2026-10-12 16:00:00'),
(17, 7, SHA2('token_suneth_exp_17', 256), '2026-10-13 11:30:00', NULL, '2026-10-13 11:00:00'),
(18, 8, SHA2('token_sachini_recent_18', 256), '2026-10-13 18:30:00', '2026-10-13 18:15:00', '2026-10-13 18:00:00'),
(19, 9, SHA2('token_tharaka_exp_19', 256), '2026-10-14 09:30:00', NULL, '2026-10-14 09:00:00'),
(20, 10, SHA2('token_nuwan_active_demo', 256), DATE_ADD(NOW(), INTERVAL 30 MINUTE), NULL, NOW());

-- ============================================================================
-- End of Component A Database Schema and Seed Data
-- ============================================================================
-- ============================================================
--  UrbanEats Cloud Kitchen Platform â€“ Component B Export
--  Component : Component B (Menu, Catalogue and Inventory)
--  Database  : urbaneats_db (MySQL / MariaDB via phpMyAdmin)
--  Engine    : InnoDB
--  Charset   : utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE IF NOT EXISTS `urbaneats_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `urbaneats_db`;

-- â”€â”€ 1. Categories Table â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS `categories` (
    `category_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(80)  NOT NULL UNIQUE,
    `description`   VARCHAR(255) NULL,
    `display_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Inactive / Hidden',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_categories_active` (`is_active`),
    INDEX `idx_categories_order` (`display_order`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dish categories for catalogue filtering';

-- â”€â”€ 2. Dishes Table â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS `dishes` (
    `dish_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `category_id`  INT UNSIGNED NOT NULL,
    `name`         VARCHAR(120) NOT NULL,
    `description`  TEXT         NULL,
    `price`        DECIMAL(10,2) NOT NULL,
    `image_path`   VARCHAR(255) NULL COMMENT 'Relative path: uploads/dishes/xxx.ext',
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Soft deleted',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_dishes_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX `idx_dishes_category_active` (`category_id`, `is_active`),
    INDEX `idx_dishes_price` (`price`),
    INDEX `idx_dishes_name` (`name`),
    INDEX `idx_dishes_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Menu catalogue items';

-- â”€â”€ 3. Inventory Table â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS `inventory` (
    `inventory_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `dish_id`       INT UNSIGNED NOT NULL UNIQUE,
    `qty_available` INT          NOT NULL DEFAULT 0 COMMENT 'Portions currently ready to serve',
    `reorder_level` INT          NOT NULL DEFAULT 5 COMMENT 'Threshold below which low-stock triggers',
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inventory_dish`
        FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`dish_id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_inventory_qty` (`qty_available`),
    INDEX `idx_inventory_reorder` (`qty_available`, `reorder_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Real-time stock balance per dish';

-- â”€â”€ 4. Stock Movements Table â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `movement_id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `dish_id`        INT UNSIGNED    NOT NULL,
    `user_id`        BIGINT UNSIGNED NULL COMMENT 'Staff member or customer responsible (Component A users)',
    `movement_type`  ENUM('Initial', 'Restock', 'Wastage', 'Checkout_Deduction', 'Adjustment') NOT NULL DEFAULT 'Restock',
    `change_qty`     INT             NOT NULL COMMENT 'Signed change quantity (+ for restock, - for deduction)',
    `previous_stock` INT             NOT NULL DEFAULT 0,
    `updated_stock`  INT             NOT NULL DEFAULT 0,
    `reason`         VARCHAR(255)    NULL,
    `moved_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_stock_movements_dish`
        FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`dish_id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_stock_movements_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_stock_movements_dish` (`dish_id`, `moved_at`),
    INDEX `idx_stock_movements_type` (`movement_type`, `moved_at`),
    INDEX `idx_stock_movements_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log of every stock change and adjustment';

-- ============================================================
--  COMPONENT B SEED DATA (20+ Rows per Table)
-- ============================================================

-- â”€â”€ Seed Categories (21 Rows) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO `categories` (`category_id`, `name`, `description`, `display_order`, `is_active`) VALUES
(1,  'Rice & Curry',              'Authentic Sri Lankan rice plates with aromatic curries and condiments',  1, 1),
(2,  'Kottu Specialties',        'Freshly chopped godamba roti tossed with spicy meats, eggs and gravy',    2, 1),
(3,  'Biriyani & Ghee Rice',     'Fragrant long-grain basmati rice cooked with caramelized onions & spice', 3, 1),
(4,  'Roti & Parotta',           'Traditional hand-made pol roti, egg roti, and flaky layered parottas',   4, 1),
(5,  'Sri Lankan Chinese',       'Local fusion wok-fried rice, noodles, and devilled chilli delights',      5, 1),
(6,  'Devilled Specialties',     'Spicy wok-tossed meats with banana peppers, onions and sweet chilli sauce',6, 1),
(7,  'Hoppers & String Hoppers', 'Crispy lace hoppers, steamed string hoppers, and traditional sambols',    7, 1),
(8,  'Short Eats & Savouries',   'Crispy mutton rolls, fish cutlets, vegetable patties, and savory bites',  8, 1),
(9,  'Burgers & Wraps',          'Gourmet cloud kitchen burgers, spicy chicken wraps, and toasted buns',    9, 1),
(10, 'Pasta & Noodles',          'Savory pasta dishes and spicy stir-fried egg noodles',                   10, 1),
(11, 'Soups & Broths',           'Slow-simmered chicken corn soups, spicy tom yum, and vegetable broths',   11, 1),
(12, 'Grilled & BBQ',            'Marinated tandoori chicken, pepper steaks, and char-grilled chicken',    12, 1),
(13, 'Seafood Specials',         'Fresh lagoon prawns, devilled cuttlefish, and fried seer fish steaks',   13, 1),
(14, 'Vegetarian Curries',       'Creamy dhal, young jackfruit polos, and rich cashew nut curry plates',   14, 1),
(15, 'Meat & Poultry Curries',   'Slow-cooked Jaffna mutton curry, village black pork, and pepper chicken',15, 1),
(16, 'Desserts & Sweets',        'Traditional watalappan, curd & treacle, and rich caramel puddings',      16, 1),
(17, 'Fresh Juices & Smoothies', 'Freshly pressed tropical papaya, lime, woodapple, and mango nectars',    17, 1),
(18, 'Hot Beverages',            'Ceylon spiced milk tea, black ginger tea, and dark roasted coffee',      18, 1),
(19, 'Traditional Bites',        'Hot spicy vadai, manioc chips, and seasoned fried chickpeas',            19, 1),
(20, 'Healthy Bowls',            'Fresh greens, grilled chicken quinoa bowls, and low-carb keto plates',    20, 1),
(21, 'Chef Specials',            'Exclusive seasonal master creations crafted by our head cloud chef',     21, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `display_order` = VALUES(`display_order`), `is_active` = VALUES(`is_active`);

-- â”€â”€ Seed Dishes (22 Rows) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
INSERT INTO `dishes` (`dish_id`, `category_id`, `name`, `description`, `price`, `image_path`, `is_active`) VALUES
(1,  3,  'Urban Royal Chicken Biryani',       'Fragrant basmati rice slow-cooked with tender chicken leg, boiled egg, mint sambol, and spicy gravy.', 1650.00, 'uploads/dishes/biriyani.jpg', 1),
(2,  2,  'Spicy Roast Chicken Cheese Kottu',  'Finely chopped godamba roti tossed with shredded roast chicken, fresh vegetables, creamy melted cheese, and aromatic gravy.', 1450.00, 'uploads/dishes/cheese_kottu.jpg', 1),
(3,  1,  'Village Style Red Rice & Fish Curry','Steamed wholesome red rice served with spicy ambul thiyal fish, tempered dhal, pol sambol, and crispy papadam.', 950.00, 'uploads/dishes/rice_and_curry.jpg', 1),
(4,  2,  'Egg & Vegetable Kottu Deluxe',      'Crispy roti strips tossed on the griddle with farm eggs, leeks, carrots, onions, and house blend spices.', 980.00, 'uploads/dishes/egg_kottu.jpg', 1),
(5,  5,  'Yangzhou Mixed Fried Rice',         'Wok-tossed premium basmati rice loaded with chicken, prawns, egg ribbons, scallions, and signature sesame chilli paste.', 1550.00, 'uploads/dishes/fried_rice.jpg', 1),
(6,  6,  'Spicy Devilled Chicken (Dry Bowl)', 'Crispy battered chicken chunks tossed in fiery red chilli sauce with capsicum, red onions, and ripe tomatoes.', 1350.00, 'uploads/dishes/devilled_chicken.jpg', 1),
(7,  9,  'Crispy Kochchi Chicken Burger',     'Buttermilk fried chicken breast seasoned with fiery green kochchi chillies, garlic mayo, and lettuce on a brioche bun.', 1250.00, 'uploads/dishes/chicken_burger.jpg', 1),
(8,  13, 'Lagoon Prawns Devilled Bowl',       'Succulent fresh lagoon prawns wok-seared with chilli paste, banana peppers, garlic, and sweet-sour tomato glaze.', 2100.00, 'uploads/dishes/prawn_curry.jpg', 1),
(9,  10, 'Creamy Chicken & Mushroom Penne',   'Al dente penne pasta tossed in rich parmesan garlic cream sauce, grilled chicken strips, and sautÃ©ed button mushrooms.', 1750.00, 'uploads/dishes/pasta_penne.jpg', 1),
(10, 4,  'Layered Godamba Parotta (Pack of 3)','Flaky, golden-crisp layered parottas served with rich chicken curry gravy and lunumiris condiment.', 450.00, 'uploads/dishes/parotta.jpg', 1),
(11, 8,  'Spicy Mutton Rolls (3 Pieces)',     'Panko-crumbed crispy crepes stuffed with slow-cooked spiced mutton mince and curried potatoes, served with ketchup.', 650.00, 'uploads/dishes/mutton_rolls.jpg', 1),
(12, 12, 'Smoky BBQ Half Chicken Platter',    'Char-grilled half chicken basted in smoky barbecue glaze, accompanied by seasoned potato wedges and coleslaw.', 1950.00, 'uploads/dishes/bbq_chicken.jpg', 1),
(13, 14, 'Rich Cashew Nut & Green Pea Curry', 'Plump whole cashew nuts simmered in mild coconut milk gravy with fresh garden green peas, served with rice.', 1400.00, 'uploads/dishes/cashew_curry.jpg', 1),
(14, 15, 'Jaffna Spiced Black Mutton Curry',  'Tender mutton chunks slow-simmered in roasted black curry powder, lemongrass, and tamarind extract.', 2400.00, 'uploads/dishes/mutton_curry.jpg', 1),
(15, 11, 'Sweet Corn & Shredded Chicken Soup','Comforting thick chicken broth with creamed sweet corn, beaten egg drops, white pepper, and scallions.', 750.00, 'uploads/dishes/chicken_soup.jpg', 1),
(16, 7,  'Steamed String Hoppers Set (10 Pcs)','Delicate red and white steamed rice noodle nests served with creamy coconut kiri hodi and freshly ground pol sambol.', 550.00, 'uploads/dishes/string_hoppers.jpg', 1),
(17, 16, 'Steamed Jaggery Watalappan Bowl',   'Rich steamed coconut custard infused with dark kithul jaggery, cardamom, nutmeg, and crunchy cashew nuts.', 480.00, 'uploads/dishes/watalappan.jpg', 1),
(18, 17, 'Fresh Island Lime & Mint Krush',     'Refreshing chilled fresh lime cooler crushed with garden mint leaves and organic bee honey.', 420.00, 'uploads/dishes/lime_juice.jpg', 1),
(19, 18, 'Spiced Ceylon Cardamom Milk Tea',   'Strong brewed Ceylon BOPF tea infused with crushed green cardamom pods, steamed milk, and brown sugar.', 280.00, 'uploads/dishes/ceylon_tea.jpg', 1),
(20, 20, 'Grilled Chicken Garden Quinoa Bowl','Herb-marinated grilled chicken breast slices served over quinoa, avocado, cherry tomatoes, and lemon olive vinaigrette.', 1600.00, 'uploads/dishes/healthy_bowl.jpg', 1),
(21, 5,  'Spicy Seafood Noodle Wok Box',      'Egg noodles stir-fried on high flame with calamari rings, baby prawns, egg, and spicy homemade chilli paste.', 1500.00, 'uploads/dishes/seafood_noodles.jpg', 1),
(22, 16, 'Classic Caramel Custard Pudding',   'Silky smooth baked egg custard coated with golden bittersweet amber caramel glaze.', 450.00, 'uploads/dishes/caramel_pudding.jpg', 1)
ON DUPLICATE KEY UPDATE `category_id` = VALUES(`category_id`), `name` = VALUES(`name`), `description` = VALUES(`description`), `price` = VALUES(`price`), `image_path` = VALUES(`image_path`), `is_active` = VALUES(`is_active`);

-- â”€â”€ Seed Inventory (22 Rows) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- Initial realistic portion quantities and reorder safety buffers
INSERT INTO `inventory` (`dish_id`, `qty_available`, `reorder_level`) VALUES
(1,  45, 10), -- Royal Biryani: High stock
(2,  30, 8),  -- Cheese Kottu: High stock
(3,  25, 6),  -- Rice & Curry: Moderate stock
(4,  18, 5),  -- Egg Kottu: Moderate stock
(5,  35, 10), -- Fried Rice: High stock
(6,  20, 5),  -- Devilled Chicken: Moderate stock
(7,  15, 5),  -- Chicken Burger: Moderate stock
(8,  8,  5),  -- Lagoon Prawns: Near reorder buffer
(9,  12, 4),  -- Pasta Penne: Moderate stock
(10, 50, 15), -- Parotta Pack: High stock
(11, 4,  5),  -- Mutton Rolls: LOW STOCK WARNING (<= reorder 5)
(12, 14, 4),  -- BBQ Chicken: Moderate stock
(13, 3,  5),  -- Cashew Curry: LOW STOCK WARNING (<= reorder 5)
(14, 10, 3),  -- Jaffna Mutton: Moderate stock
(15, 22, 6),  -- Chicken Soup: Moderate stock
(16, 28, 8),  -- String Hoppers: Moderate stock
(17, 18, 6),  -- Watalappan: Moderate stock
(18, 40, 10), -- Lime Krush: High stock
(19, 60, 15), -- Ceylon Tea: High stock
(20, 2,  5),  -- Quinoa Bowl: LOW STOCK WARNING (<= reorder 5)
(21, 16, 5),  -- Seafood Noodles: Moderate stock
(22, 0,  5)   -- Caramel Pudding: OUT OF STOCK (qty = 0)
ON DUPLICATE KEY UPDATE `qty_available` = VALUES(`qty_available`), `reorder_level` = VALUES(`reorder_level`);

-- â”€â”€ Seed Stock Movements (22 Rows) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
-- Staff user_id references valid Kitchen Staff users (4, 5, 6, 7) from component_a.sql
INSERT INTO `stock_movements` (`movement_id`, `dish_id`, `user_id`, `movement_type`, `change_qty`, `previous_stock`, `updated_stock`, `reason`, `moved_at`) VALUES
(1,  1,  4, 'Initial',  50, 0,  50, 'Morning kitchen batch production setup',        '2026-10-18 07:30:00'),
(2,  1,  4, 'Checkout_Deduction', -5, 50, 45, 'Lunch rush customer orders fulfilled', '2026-10-18 12:45:00'),
(3,  2,  5, 'Initial',  30, 0,  30, 'Evening kottu station mise-en-place',           '2026-10-18 16:00:00'),
(4,  3,  4, 'Initial',  30, 0,  30, 'Daily lunch set batch prepared',                '2026-10-18 10:30:00'),
(5,  3,  4, 'Wastage',  -5, 30, 25, 'Excess curry past safety holding window',       '2026-10-18 14:30:00'),
(6,  4,  5, 'Initial',  20, 0,  20, 'Godamba roti dough portioning completed',       '2026-10-18 16:30:00'),
(7,  4,  5, 'Checkout_Deduction', -2, 20, 18, 'Online takeaway orders dispatched',   '2026-10-18 18:15:00'),
(8,  5,  6, 'Initial',  40, 0,  40, 'Wok station prep for dinner shift',             '2026-10-18 17:00:00'),
(9,  5,  6, 'Checkout_Deduction', -5, 40, 35, 'Customer checkout order #1042',       '2026-10-18 19:20:00'),
(10, 6,  6, 'Initial',  25, 0,  25, 'Pre-marinated chicken portions cooked',         '2026-10-18 17:30:00'),
(11, 6,  6, 'Checkout_Deduction', -5, 25, 20, 'Customer dinner orders fulfilled',     '2026-10-18 20:00:00'),
(12, 7,  4, 'Initial',  20, 0,  20, 'Brioche buns and patties stock check',          '2026-10-19 11:00:00'),
(13, 7,  4, 'Checkout_Deduction', -5, 20, 15, 'App food orders dispatched',          '2026-10-19 13:00:00'),
(14, 8,  7, 'Initial',  12, 0,  12, 'Fresh lagoon prawn market delivery prep',       '2026-10-19 11:30:00'),
(15, 8,  7, 'Checkout_Deduction', -4, 12, 8,  'Order batch #1058 deducted',          '2026-10-19 13:45:00'),
(16, 9,  5, 'Initial',  15, 0,  15, 'Penne pasta batch boiled and chilled',          '2026-10-19 12:00:00'),
(17, 9,  5, 'Checkout_Deduction', -3, 15, 12, 'Express delivery order deduction',    '2026-10-19 14:10:00'),
(18, 10, 4, 'Initial',  50, 0,  50, 'Godamba parotta freshly rolled and frozen',     '2026-10-19 09:00:00'),
(19, 11, 4, 'Restock',  10, 4,  14, 'Fresh morning roll batch deep fried',           '2026-10-20 08:30:00'),
(20, 11, 4, 'Checkout_Deduction', -10, 14, 4, 'Catering short-eats box delivery',    '2026-10-20 10:15:00'),
(21, 20, 7, 'Wastage',  -3, 5,  2,  'Avocado oxidation beyond serving quality',      '2026-10-20 11:00:00'),
(22, 22, 5, 'Checkout_Deduction', -8, 8, 0, 'Last available portions sold at lunch', '2026-10-20 13:30:00')
ON DUPLICATE KEY UPDATE `change_qty` = VALUES(`change_qty`), `previous_stock` = VALUES(`previous_stock`), `updated_stock` = VALUES(`updated_stock`), `reason` = VALUES(`reason`);

SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================================
-- UrbanEats Cloud Kitchen Platform
-- Component C: Ordering, Payment and Tracking
-- Database Schema and Seed Data
-- ============================================================================
-- Technology: MySQL 8.x / MariaDB 10.4+
-- Character Set: utf8mb4 / utf8mb4_unicode_ci
-- Complies with 3NF (Third Normal Form), Foreign Key Constraints, and Indexes.
-- Depends on: Component A (users, roles) and Component B (dishes, inventory).
-- ============================================================================

USE `urbaneats_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables in reverse dependency order
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `coupons`;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Table 1: coupons
-- Promotional discounts applied at checkout with validity & usage limits
-- ----------------------------------------------------------------------------
CREATE TABLE `coupons` (
  `coupon_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coupon_code` VARCHAR(30) NOT NULL,
  `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` DECIMAL(10,2) NOT NULL COMMENT 'Percentage (e.g. 10.00 for 10%) or Fixed LKR amount',
  `minimum_order_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Minimum cart subtotal to apply',
  `valid_from` DATETIME NOT NULL,
  `expiry_date` DATETIME NOT NULL,
  `usage_limit` INT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Maximum times coupon can be redeemed',
  `usage_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Times redeemed so far',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Inactive/Disabled',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`coupon_id`),
  UNIQUE KEY `uk_coupons_code` (`coupon_code`),
  KEY `idx_coupons_active_dates` (`is_active`, `valid_from`, `expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Promotional coupon codes and discount policies';

-- ----------------------------------------------------------------------------
-- Table 2: orders
-- Primary order record capturing customer, fulfillment, totals and status
-- ----------------------------------------------------------------------------
CREATE TABLE `orders` (
  `order_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(32) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `idempotency_token` VARCHAR(64) NOT NULL COMMENT 'Cryptographic checkout token to prevent double-order submission',
  `fulfillment_method` ENUM('delivery', 'pickup') NOT NULL DEFAULT 'delivery',
  `delivery_address` TEXT NULL COMMENT 'Full physical delivery address (required if delivery, NULL if pickup)',
  `contact_phone` VARCHAR(25) NOT NULL COMMENT 'Customer contact number for order fulfillment',
  `order_notes` TEXT NULL COMMENT 'Optional kitchen preparation instructions or delivery directions',
  `subtotal` DECIMAL(10,2) NOT NULL COMMENT 'Gross sum of order items before discount',
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Discount value applied via coupon',
  `total_amount` DECIMAL(10,2) NOT NULL COMMENT 'Net payable amount (subtotal - discount)',
  `coupon_id` INT UNSIGNED NULL DEFAULT NULL,
  `status` ENUM('Placed', 'Preparing', 'Ready', 'Out for Delivery', 'Collected', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Placed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uk_orders_order_number` (`order_number`),
  UNIQUE KEY `uk_orders_idempotency` (`idempotency_token`),
  KEY `idx_orders_user_created` (`user_id`, `created_at`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created` (`created_at`),
  KEY `idx_orders_fulfillment` (`fulfillment_method`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`)
    REFERENCES `coupons` (`coupon_id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer orders store';

-- ----------------------------------------------------------------------------
-- Table 3: order_items
-- Itemized line items preserving historical purchase prices and quantities
-- ----------------------------------------------------------------------------
CREATE TABLE `order_items` (
  `order_item_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `dish_id` INT UNSIGNED NOT NULL,
  `dish_name` VARCHAR(120) NOT NULL COMMENT 'Snapshot of dish name at time of order placement',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL COMMENT 'Authoritative unit price in LKR at time of purchase',
  `line_total` DECIMAL(10,2) NOT NULL COMMENT 'Quantity * Unit Price',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_dish` (`dish_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`order_id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_dish` FOREIGN KEY (`dish_id`)
    REFERENCES `dishes` (`dish_id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items for customer orders';

-- ----------------------------------------------------------------------------
-- Table 4: payments
-- Simulated payment records capturing chosen method and transaction state
-- ----------------------------------------------------------------------------
CREATE TABLE `payments` (
  `payment_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `payment_method` ENUM('Cash on Delivery', 'Card', 'Bank Transfer') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Amount charged in LKR',
  `payment_status` ENUM('Pending', 'Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
  `transaction_reference` VARCHAR(64) NOT NULL COMMENT 'Simulated payment reference (e.g. SIM-CARD-xxxx)',
  `payment_notes` VARCHAR(255) NULL COMMENT 'Simulated gateway response or bank transfer note',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `uk_payments_order` (`order_id`),
  KEY `idx_payments_status` (`payment_status`),
  KEY `idx_payments_method` (`payment_method`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`order_id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Simulated order payment records';

-- ----------------------------------------------------------------------------
-- Table 5: order_status_history
-- Immutable audit timeline of order stage progressions
-- ----------------------------------------------------------------------------
CREATE TABLE `order_status_history` (
  `history_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `previous_status` VARCHAR(30) NULL COMMENT 'Previous order state or NULL for initial placement',
  `new_status` VARCHAR(30) NOT NULL COMMENT 'Target order state',
  `changed_by` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'User ID of staff/customer actor, or NULL for system',
  `notes` VARCHAR(255) NULL COMMENT 'Context reason or status change notes',
  `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_history_order` (`order_id`, `changed_at`),
  KEY `idx_history_user` (`changed_by`),
  CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`order_id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`)
    REFERENCES `users` (`user_id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Order lifecycle audit history';

-- ============================================================================
-- SEED DATA (20+ realistic rows per main table)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. Seed Coupons (21 realistic promotional coupons)
-- ----------------------------------------------------------------------------
INSERT INTO `coupons` (`coupon_id`, `coupon_code`, `discount_type`, `discount_value`, `minimum_order_value`, `valid_from`, `expiry_date`, `usage_limit`, `usage_count`, `is_active`) VALUES
(1,  'WELCOME10',    'percentage', 10.00,  1500.00, '2026-09-01 00:00:00', '2026-12-31 23:59:59', 500,  12, 1),
(2,  'URBANEATS20',  'percentage', 20.00,  3000.00, '2026-10-01 00:00:00', '2026-11-30 23:59:59', 200,   8, 1),
(3,  'SAVE500',      'fixed',      500.00, 2500.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59', 150,  15, 1),
(4,  'KOTTULOVE',    'fixed',      300.00, 1500.00, '2026-10-05 00:00:00', '2026-11-15 23:59:59', 300,   6, 1),
(5,  'BIRYANI15',    'percentage', 15.00,  2000.00, '2026-10-01 00:00:00', '2026-12-15 23:59:59', 250,   9, 1),
(6,  'WEEKEND25',    'percentage', 25.00,  4000.00, '2026-10-01 00:00:00', '2026-10-31 23:59:59', 100,   4, 1),
(7,  'FEAST1000',    'fixed',     1000.00, 5000.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59',  50,   3, 1),
(8,  'LUNCHDEAL',    'fixed',      250.00, 1200.00, '2026-10-01 00:00:00', '2026-11-30 23:59:59', 400,   7, 1),
(9,  'CHEF5',        'percentage',  5.00,  1000.00, '2026-09-15 00:00:00', '2026-12-31 23:59:59', 500,   2, 1),
(10, 'COLOMBO15',    'percentage', 15.00,  2500.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59', 200,   5, 1),
(11, 'FAMILYMEAL',   'fixed',      750.00, 4500.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59', 100,   2, 1),
(12, 'SEAFOOD20',    'percentage', 20.00,  3500.00, '2026-10-01 00:00:00', '2026-11-30 23:59:59', 150,   1, 1),
(13, 'SPECIAL350',   'fixed',      350.00, 1800.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59', 300,   3, 1),
(14, 'OFFICE500',    'fixed',      500.00, 3000.00, '2026-10-05 00:00:00', '2026-11-20 23:59:59', 120,   1, 1),
(15, 'FLASH30',      'percentage', 30.00,  5000.00, '2026-10-10 00:00:00', '2026-10-25 23:59:59',  50,   0, 1),
(16, 'TASTY100',     'fixed',      100.00,  800.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59', 600,   2, 1),
(17, 'EXPIRED2025',  'percentage', 20.00,  1000.00, '2025-01-01 00:00:00', '2025-12-31 23:59:59', 100,  10, 0),
(18, 'MAXEDOUT',     'fixed',      400.00, 1500.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59',   5,   5, 1),
(19, 'FUTUREDEAL',   'percentage', 15.00,  1500.00, '2027-01-01 00:00:00', '2027-12-31 23:59:59', 100,   0, 1),
(20, 'INACTIVE50',   'percentage', 50.00,  2000.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59',  50,   0, 0),
(21, 'NIGHTOWL',     'percentage', 12.00,  1800.00, '2026-10-01 00:00:00', '2026-12-31 23:59:59', 250,   4, 1);

-- ----------------------------------------------------------------------------
-- 2. Seed Orders (23 realistic orders across customers, dates & states)
-- ----------------------------------------------------------------------------
INSERT INTO `orders` (`order_id`, `order_number`, `user_id`, `idempotency_token`, `fulfillment_method`, `delivery_address`, `contact_phone`, `order_notes`, `subtotal`, `discount_amount`, `total_amount`, `coupon_id`, `status`, `created_at`, `updated_at`) VALUES
(1,  'ORD-20261012-0001', 8,  'IDEMP-SEED-001', 'delivery', '45/2 Galle Road, Colombo 03',         '0771234567', 'Please ring the front bell', 3100.00, 310.00, 2790.00, 1,    'Completed',        '2026-10-12 11:30:00', '2026-10-12 12:20:00'),
(2,  'ORD-20261012-0002', 9,  'IDEMP-SEED-002', 'delivery', '128 Havelock Road, Colombo 05',      '0719876543', 'Extra spicy gravy please',   2900.00, 500.00, 2400.00, 3,    'Completed',        '2026-10-12 12:15:00', '2026-10-12 13:05:00'),
(3,  'ORD-20261012-0003', 10, 'IDEMP-SEED-003', 'pickup',   NULL,                                 '0765551234', 'Will pick up in 20 minutes', 1650.00,   0.00, 1650.00, NULL, 'Completed',        '2026-10-12 13:00:00', '2026-10-12 13:40:00'),
(4,  'ORD-20261013-0004', 11, 'IDEMP-SEED-004', 'delivery', '88 Duplication Road, Colombo 04',   '0723344556', 'Leave at security gate',     3400.00, 510.00, 2890.00, 5,    'Completed',        '2026-10-13 11:45:00', '2026-10-13 12:35:00'),
(5,  'ORD-20261013-0005', 12, 'IDEMP-SEED-005', 'delivery', '14 Temple Lane, Kollupitiya',       '0778899001', NULL,                         2300.00, 300.00, 2000.00, 4,    'Completed',        '2026-10-13 12:30:00', '2026-10-13 13:20:00'),
(6,  'ORD-20261013-0006', 13, 'IDEMP-SEED-006', 'pickup',   NULL,                                 '0712233445', 'Please pack with utensils',  1950.00,   0.00, 1950.00, NULL, 'Completed',        '2026-10-13 13:10:00', '2026-10-13 13:45:00'),
(7,  'ORD-20261014-0007', 14, 'IDEMP-SEED-007', 'delivery', '302 Nawala Road, Rajagiriya',       '0756677889', 'Call on arrival',            3200.00, 320.00, 2880.00, 1,    'Completed',        '2026-10-14 11:20:00', '2026-10-14 12:10:00'),
(8,  'ORD-20261014-0008', 15, 'IDEMP-SEED-008', 'delivery', '19 Park Street, Colombo 02',         '0779988776', 'No onions in salad',         4200.00, 840.00, 3360.00, 2,    'Completed',        '2026-10-14 12:40:00', '2026-10-14 13:30:00'),
(9,  'ORD-20261014-0009', 16, 'IDEMP-SEED-009', 'pickup',   NULL,                                 '0781122334', 'Self pickup at 7 PM',       1450.00,   0.00, 1450.00, NULL, 'Completed',        '2026-10-14 18:00:00', '2026-10-14 18:40:00'),
(10, 'ORD-20261015-0010', 17, 'IDEMP-SEED-010', 'delivery', '77 Bauddhaloka Mawatha, Colombo 07','0764455667', 'Apartment 4B',              2700.00, 250.00, 2450.00, 8,    'Completed',        '2026-10-15 12:00:00', '2026-10-15 12:50:00'),
(11, 'ORD-20261015-0011', 18, 'IDEMP-SEED-011', 'delivery', '54 Flower Road, Colombo 07',        '0713322114', NULL,                         2100.00,   0.00, 2100.00, NULL, 'Completed',        '2026-10-15 12:45:00', '2026-10-15 13:35:00'),
(12, 'ORD-20261015-0012', 19, 'IDEMP-SEED-012', 'pickup',   NULL,                                 '0776655443', 'Car pickup',                 2600.00, 390.00, 2210.00, 5,    'Completed',        '2026-10-15 13:30:00', '2026-10-15 14:15:00'),
(13, 'ORD-20261016-0013', 20, 'IDEMP-SEED-013', 'delivery', '90 High Level Road, Nugegoda',       '0727788990', 'Deliver before 1 PM',        5350.00,1000.00, 4350.00, 7,    'Completed',        '2026-10-16 11:30:00', '2026-10-16 12:25:00'),
(14, 'ORD-20261016-0014', 8,  'IDEMP-SEED-014', 'delivery', '45/2 Galle Road, Colombo 03',         '0771234567', 'Repeat order',               3100.00, 500.00, 2600.00, 3,    'Completed',        '2026-10-16 12:15:00', '2026-10-16 13:00:00'),
(15, 'ORD-20261016-0015', 9,  'IDEMP-SEED-015', 'pickup',   NULL,                                 '0719876543', NULL,                         1650.00,   0.00, 1650.00, NULL, 'Completed',        '2026-10-16 13:00:00', '2026-10-16 13:35:00'),
(16, 'ORD-20261017-0016', 10, 'IDEMP-SEED-016', 'delivery', '33 Cotta Road, Borella',             '0765551234', 'Ring phone when outside',    3000.00, 450.00, 2550.00, 10,   'Completed',        '2026-10-17 12:00:00', '2026-10-17 12:55:00'),
(17, 'ORD-20261017-0017', 11, 'IDEMP-SEED-017', 'delivery', '88 Duplication Road, Colombo 04',   '0723344556', NULL,                         2400.00,   0.00, 2400.00, NULL, 'Cancelled',        '2026-10-17 12:30:00', '2026-10-17 12:45:00'),
(18, 'ORD-20261018-0018', 12, 'IDEMP-SEED-018', 'delivery', '14 Temple Lane, Kollupitiya',       '0778899001', 'Cancelled by customer',      1950.00,   0.00, 1950.00, NULL, 'Cancelled',        '2026-10-18 13:00:00', '2026-10-18 13:10:00'),
(19, 'ORD-20261019-0019', 13, 'IDEMP-SEED-019', 'delivery', '12 Ward Place, Colombo 07',         '0712233445', 'Please deliver ASAP',        3500.00, 350.00, 3150.00, 13,   'Out for Delivery', '2026-10-19 12:00:00', '2026-10-19 12:30:00'),
(20, 'ORD-20261019-0020', 14, 'IDEMP-SEED-020', 'pickup',   NULL,                                 '0756677889', 'Order ready for collection', 2200.00,   0.00, 2200.00, NULL, 'Ready',            '2026-10-19 12:15:00', '2026-10-19 12:35:00'),
(21, 'ORD-20261020-0021', 15, 'IDEMP-SEED-021', 'delivery', '19 Park Street, Colombo 02',         '0779988776', 'Chef preparing now',         4100.00, 410.00, 3690.00, 1,    'Preparing',        '2026-10-20 12:20:00', '2026-10-20 12:30:00'),
(22, 'ORD-20261021-0022', 16, 'IDEMP-SEED-022', 'delivery', '76 Stanley Thilakarathne Mawatha',   '0781122334', 'Fresh order',                1650.00,   0.00, 1650.00, NULL, 'Placed',           '2026-10-21 11:30:00', '2026-10-21 11:30:00'),
(23, 'ORD-20261021-0023', 8,  'IDEMP-SEED-023', 'pickup',   NULL,                                 '0771234567', 'Pickup at counter',          1450.00,   0.00, 1450.00, NULL, 'Placed',           '2026-10-21 12:00:00', '2026-10-21 12:00:00');

-- ----------------------------------------------------------------------------
-- 3. Seed Order Items (45 itemized records preserving dish pricing)
-- ----------------------------------------------------------------------------
INSERT INTO `order_items` (`order_item_id`, `order_id`, `dish_id`, `dish_name`, `quantity`, `unit_price`, `line_total`, `created_at`) VALUES
-- Order 1: Biryani + Kottu
(1,  1,  1,  'Urban Royal Chicken Biryani',       1, 1650.00, 1650.00, '2026-10-12 11:30:00'),
(2,  1,  2,  'Spicy Roast Chicken Cheese Kottu',  1, 1450.00, 1450.00, '2026-10-12 11:30:00'),
-- Order 2: Cheese Kottu (2)
(3,  2,  2,  'Spicy Roast Chicken Cheese Kottu',  2, 1450.00, 2900.00, '2026-10-12 12:15:00'),
-- Order 3: Biryani
(4,  3,  1,  'Urban Royal Chicken Biryani',       1, 1650.00, 1650.00, '2026-10-12 13:00:00'),
-- Order 4: Biryani + Penne
(5,  4,  1,  'Urban Royal Chicken Biryani',       1, 1650.00, 1650.00, '2026-10-13 11:45:00'),
(6,  4,  9,  'Creamy Chicken & Mushroom Penne',   1, 1750.00, 1750.00, '2026-10-13 11:45:00'),
-- Order 5: Devilled Chicken + Rice & Curry
(7,  5,  6,  'Spicy Devilled Chicken (Dry Bowl)', 1, 1350.00, 1350.00, '2026-10-13 12:30:00'),
(8,  5,  3,  'Village Style Red Rice & Fish Curry', 1, 950.00,  950.00, '2026-10-13 12:30:00'),
-- Order 6: BBQ Chicken
(9,  6,  12, 'Smoky BBQ Half Chicken Platter',    1, 1950.00, 1950.00, '2026-10-13 13:10:00'),
-- Order 7: Biryani + Mixed Rice
(10, 7,  1,  'Urban Royal Chicken Biryani',       1, 1650.00, 1650.00, '2026-10-14 11:20:00'),
(11, 7,  5,  'Yangzhou Mixed Fried Rice',         1, 1550.00, 1550.00, '2026-10-14 11:20:00'),
-- Order 8: Lagoon Prawns (2)
(12, 8,  8,  'Lagoon Prawns Devilled Bowl',       2, 2100.00, 4200.00, '2026-10-14 12:40:00'),
-- Order 9: Cheese Kottu
(13, 9,  2,  'Spicy Roast Chicken Cheese Kottu',  1, 1450.00, 1450.00, '2026-10-14 18:00:00'),
-- Order 10: Rice & Curry + Devilled Chicken + Mutton Rolls
(14, 10, 3,  'Village Style Red Rice & Fish Curry', 1, 950.00,  950.00, '2026-10-15 12:00:00'),
(15, 10, 6,  'Spicy Devilled Chicken (Dry Bowl)', 1, 1350.00, 1350.00, '2026-10-15 12:00:00'),
(16, 10, 11, 'Spicy Mutton Rolls (3 Pieces)',     1,  400.00,  400.00, '2026-10-15 12:00:00'),
-- Order 11: Lagoon Prawns
(17, 11, 8,  'Lagoon Prawns Devilled Bowl',       1, 2100.00, 2100.00, '2026-10-15 12:45:00'),
-- Order 12: Chicken Burger (1) + Devilled Chicken (1)
(18, 12, 7,  'Crispy Kochchi Chicken Burger',     1, 1250.00, 1250.00, '2026-10-15 13:30:00'),
(19, 12, 6,  'Spicy Devilled Chicken (Dry Bowl)', 1, 1350.00, 1350.00, '2026-10-15 13:30:00'),
-- Order 13: Biryani (2) + BBQ Chicken (1) + Parotta (1)
(20, 13, 1,  'Urban Royal Chicken Biryani',       2, 1650.00, 3300.00, '2026-10-16 11:30:00'),
(21, 13, 12, 'Smoky BBQ Half Chicken Platter',    1, 1950.00, 1950.00, '2026-10-16 11:30:00'),
(22, 13, 10, 'Layered Godamba Parotta (Pack of 3)',1, 100.00,  100.00, '2026-10-16 11:30:00'),
-- Order 14: Biryani + Cheese Kottu
(23, 14, 1,  'Urban Royal Chicken Biryani',       1, 1650.00, 1650.00, '2026-10-16 12:15:00'),
(24, 14, 2,  'Spicy Roast Chicken Cheese Kottu',  1, 1450.00, 1450.00, '2026-10-16 12:15:00'),
-- Order 15: Biryani
(25, 15, 1,  'Urban Royal Chicken Biryani',       1, 1650.00, 1650.00, '2026-10-16 13:00:00'),
-- Order 16: Mixed Fried Rice + Cheese Kottu
(26, 16, 5,  'Yangzhou Mixed Fried Rice',         1, 1550.00, 1550.00, '2026-10-17 12:00:00'),
(27, 16, 2,  'Spicy Roast Chicken Cheese Kottu',  1, 1450.00, 1450.00, '2026-10-17 12:00:00'),
-- Order 17: Rice & Curry + Cheese Kottu (Cancelled)
(28, 17, 3,  'Village Style Red Rice & Fish Curry', 1, 950.00,  950.00, '2026-10-17 12:30:00'),
(29, 17, 2,  'Spicy Roast Chicken Cheese Kottu',  1, 1450.00, 1450.00, '2026-10-17 12:30:00'),
-- Order 18: BBQ Chicken (Cancelled)
(30, 18, 12, 'Smoky BBQ Half Chicken Platter',    1, 1950.00, 1950.00, '2026-10-18 13:00:00'),
-- Order 19: Penne + BBQ Chicken
(31, 19, 9,  'Creamy Chicken & Mushroom Penne',   1, 1750.00, 1750.00, '2026-10-19 12:00:00'),
(32, 19, 9,  'Creamy Chicken & Mushroom Penne',   1, 1750.00, 1750.00, '2026-10-19 12:00:00'),
-- Order 20: Egg Kottu (1) + Chicken Burger (1)
(33, 20, 4,  'Egg & Vegetable Kottu Deluxe',      1,  950.00,  950.00, '2026-10-19 12:15:00'),
(34, 20, 7,  'Crispy Kochchi Chicken Burger',     1, 1250.00, 1250.00, '2026-10-19 12:15:00'),
-- Order 21: Biryani + Lagoon Prawns
(35, 21, 1,  'Urban Royal Chicken Biryani',       1, 1650.00, 1650.00, '2026-10-20 12:20:00'),
(36, 21, 8,  'Lagoon Prawns Devilled Bowl',       1, 2450.00, 2450.00, '2026-10-20 12:20:00'),
-- Order 22: Biryani (Placed)
(37, 22, 1,  'Urban Royal Chicken Biryani',       1, 1650.00, 1650.00, '2026-10-21 11:30:00'),
-- Order 23: Cheese Kottu (Placed)
(38, 23, 2,  'Spicy Roast Chicken Cheese Kottu',  1, 1450.00, 1450.00, '2026-10-21 12:00:00');

-- ----------------------------------------------------------------------------
-- 4. Seed Payments (23 simulated payment records matching orders)
-- ----------------------------------------------------------------------------
INSERT INTO `payments` (`payment_id`, `order_id`, `payment_method`, `amount`, `payment_status`, `transaction_reference`, `payment_notes`, `created_at`, `updated_at`) VALUES
(1,  1,  'Card',             2790.00, 'Paid',    'SIM-CARD-883101', 'Simulated Visa authorization approved',        '2026-10-12 11:30:00', '2026-10-12 11:30:00'),
(2,  2,  'Cash on Delivery', 2400.00, 'Paid',    'SIM-COD-910243',  'Payment collected in cash upon delivery',       '2026-10-12 12:15:00', '2026-10-12 13:05:00'),
(3,  3,  'Bank Transfer',    1650.00, 'Paid',    'SIM-BANK-443210', 'Commercial Bank transfer slip verified',       '2026-10-12 13:00:00', '2026-10-12 13:10:00'),
(4,  4,  'Card',             2890.00, 'Paid',    'SIM-CARD-552918', 'Simulated Mastercard authorization approved',  '2026-10-13 11:45:00', '2026-10-13 11:45:00'),
(5,  5,  'Cash on Delivery', 2000.00, 'Paid',    'SIM-COD-119283',  'Paid on delivery',                             '2026-10-13 12:30:00', '2026-10-13 13:20:00'),
(6,  6,  'Card',             1950.00, 'Paid',    'SIM-CARD-667129', 'Simulated contactless card payment',           '2026-10-13 13:10:00', '2026-10-13 13:10:00'),
(7,  7,  'Bank Transfer',    2880.00, 'Paid',    'SIM-BANK-772819', 'HNB online transfer receipt attached',         '2026-10-14 11:20:00', '2026-10-14 11:25:00'),
(8,  8,  'Card',             3360.00, 'Paid',    'SIM-CARD-991203', 'Simulated Visa debit approved',                 '2026-10-14 12:40:00', '2026-10-14 12:40:00'),
(9,  9,  'Cash on Delivery', 1450.00, 'Paid',    'SIM-COD-339182',  'Cash received at kitchen pickup counter',       '2026-10-14 18:00:00', '2026-10-14 18:40:00'),
(10, 10, 'Card',             2450.00, 'Paid',    'SIM-CARD-449182', 'Simulated online payment approved',             '2026-10-15 12:00:00', '2026-10-15 12:00:00'),
(11, 11, 'Cash on Delivery', 2100.00, 'Paid',    'SIM-COD-882710',  'Paid on delivery to rider',                    '2026-10-15 12:45:00', '2026-10-15 13:35:00'),
(12, 12, 'Card',             2210.00, 'Paid',    'SIM-CARD-229183', 'Simulated Card charge authorized',             '2026-10-15 13:30:00', '2026-10-15 13:30:00'),
(13, 13, 'Bank Transfer',    4350.00, 'Paid',    'SIM-BANK-339281', 'Sampath Bank transfer verified',                '2026-10-16 11:30:00', '2026-10-16 11:35:00'),
(14, 14, 'Card',             2600.00, 'Paid',    'SIM-CARD-773829', 'Simulated credit payment approved',             '2026-10-16 12:15:00', '2026-10-16 12:15:00'),
(15, 15, 'Cash on Delivery', 1650.00, 'Paid',    'SIM-COD-662819',  'Cash paid at counter',                         '2026-10-16 13:00:00', '2026-10-16 13:35:00'),
(16, 16, 'Card',             2550.00, 'Paid',    'SIM-CARD-119284', 'Simulated Visa approved',                       '2026-10-17 12:00:00', '2026-10-17 12:00:00'),
(17, 17, 'Card',             2400.00, 'Refunded','SIM-CARD-992810', 'Simulated reversal processed for cancellation', '2026-10-17 12:30:00', '2026-10-17 12:45:00'),
(18, 18, 'Bank Transfer',    1950.00, 'Failed',  'SIM-BANK-002918', 'Customer cancelled before transfer submission','2026-10-18 13:00:00', '2026-10-18 13:10:00'),
(19, 19, 'Card',             3150.00, 'Paid',    'SIM-CARD-552910', 'Simulated Card payment authorized',             '2026-10-19 12:00:00', '2026-10-19 12:00:00'),
(20, 20, 'Cash on Delivery', 2200.00, 'Pending', 'SIM-COD-772810',  'Payment due upon collection at counter',        '2026-10-19 12:15:00', '2026-10-19 12:15:00'),
(21, 21, 'Card',             3690.00, 'Paid',    'SIM-CARD-449102', 'Simulated payment completed',                   '2026-10-20 12:20:00', '2026-10-20 12:20:00'),
(22, 22, 'Cash on Delivery', 1650.00, 'Pending', 'SIM-COD-338192',  'Pending payment on delivery',                  '2026-10-21 11:30:00', '2026-10-21 11:30:00'),
(23, 23, 'Bank Transfer',    1450.00, 'Pending', 'SIM-BANK-119203', 'Awaiting transfer confirmation',               '2026-10-21 12:00:00', '2026-10-21 12:00:00');

-- ----------------------------------------------------------------------------
-- 5. Seed Order Status History (60+ lifecycle audit timeline events)
-- ----------------------------------------------------------------------------
INSERT INTO `order_status_history` (`history_id`, `order_id`, `previous_status`, `new_status`, `changed_by`, `notes`, `changed_at`) VALUES
-- Order 1 (Delivery - Completed)
(1,  1, NULL,               'Placed',           8, 'Order placed by customer via checkout',   '2026-10-12 11:30:00'),
(2,  1, 'Placed',           'Preparing',        4, 'Kitchen accepted order, cooking started', '2026-10-12 11:35:00'),
(3,  1, 'Preparing',        'Ready',            5, 'Food boxed and ready for dispatch',       '2026-10-12 11:55:00'),
(4,  1, 'Ready',            'Out for Delivery', 4, 'Handed to delivery driver',               '2026-10-12 12:02:00'),
(5,  1, 'Out for Delivery', 'Completed',        4, 'Delivered to customer at doorstep',       '2026-10-12 12:20:00'),

-- Order 2 (Delivery - Completed)
(6,  2, NULL,               'Placed',           9, 'Order placed via checkout',               '2026-10-12 12:15:00'),
(7,  2, 'Placed',           'Preparing',        4, 'Cooking chicken cheese kottu',            '2026-10-12 12:20:00'),
(8,  2, 'Preparing',        'Ready',            5, 'Packaged with extra gravy',               '2026-10-12 12:40:00'),
(9,  2, 'Ready',            'Out for Delivery', 4, 'Dispatched with rider',                  '2026-10-12 12:45:00'),
(10, 2, 'Out for Delivery', 'Completed',        4, 'Payment collected and order completed',   '2026-10-12 13:05:00'),

-- Order 3 (Pickup - Completed)
(11, 3, NULL,               'Placed',          10, 'Pickup order placed',                     '2026-10-12 13:00:00'),
(12, 3, 'Placed',           'Preparing',        4, 'Biryani portion preparation',             '2026-10-12 13:05:00'),
(13, 3, 'Preparing',        'Ready',            5, 'Order ready for customer collection',     '2026-10-12 13:25:00'),
(14, 3, 'Ready',            'Collected',        4, 'Customer arrived at counter and collected','2026-10-12 13:38:00'),
(15, 3, 'Collected',        'Completed',        4, 'Pickup transaction finalized',            '2026-10-12 13:40:00'),

-- Order 4 (Delivery - Completed)
(16, 4, NULL,               'Placed',          11, 'Checkout completed',                      '2026-10-13 11:45:00'),
(17, 4, 'Placed',           'Preparing',        5, 'Preparing biryani and pasta',             '2026-10-13 11:50:00'),
(18, 4, 'Preparing',        'Ready',            4, 'Order ready for dispatch',                '2026-10-13 12:10:00'),
(19, 4, 'Ready',            'Out for Delivery', 5, 'Rider on the way',                        '2026-10-13 12:15:00'),
(20, 4, 'Out for Delivery', 'Completed',        4, 'Delivered to Duplication Road',           '2026-10-13 12:35:00'),

-- Order 5 (Delivery - Completed)
(21, 5, NULL,               'Placed',          12, 'Order confirmed',                         '2026-10-13 12:30:00'),
(22, 5, 'Placed',           'Preparing',        4, 'Cooking devilled chicken & red rice',     '2026-10-13 12:35:00'),
(23, 5, 'Preparing',        'Ready',            5, 'Packed neatly',                           '2026-10-13 12:55:00'),
(24, 5, 'Ready',            'Out for Delivery', 4, 'Out for delivery',                        '2026-10-13 13:00:00'),
(25, 5, 'Out for Delivery', 'Completed',        4, 'Delivered and cash collected',            '2026-10-13 13:20:00'),

-- Order 6 (Pickup - Completed)
(26, 6, NULL,               'Placed',          13, 'Pickup order placed',                     '2026-10-13 13:10:00'),
(27, 6, 'Placed',           'Preparing',        5, 'Grilling half chicken platter',           '2026-10-13 13:15:00'),
(28, 6, 'Preparing',        'Ready',            4, 'Ready at counter',                        '2026-10-13 13:35:00'),
(29, 6, 'Ready',            'Collected',        5, 'Collected by customer',                   '2026-10-13 13:42:00'),
(30, 6, 'Collected',        'Completed',        4, 'Completed',                               '2026-10-13 13:45:00'),

-- Order 7 (Delivery - Completed)
(31, 7, NULL,               'Placed',          14, 'Customer placed order',                   '2026-10-14 11:20:00'),
(32, 7, 'Placed',           'Preparing',        4, 'Cooking biryani and mixed rice',          '2026-10-14 11:25:00'),
(33, 7, 'Preparing',        'Ready',            4, 'Packed and sealed',                       '2026-10-14 11:45:00'),
(34, 7, 'Ready',            'Out for Delivery', 5, 'Driver assigned',                         '2026-10-14 11:50:00'),
(35, 7, 'Out for Delivery', 'Completed',        4, 'Delivered successfully',                  '2026-10-14 12:10:00'),

-- Order 8 (Delivery - Completed)
(36, 8, NULL,               'Placed',          15, 'Order placed with coupon',                '2026-10-14 12:40:00'),
(37, 8, 'Placed',           'Preparing',        5, 'Wok tossed lagoon prawns',                '2026-10-14 12:45:00'),
(38, 8, 'Preparing',        'Ready',            5, 'Ready for driver',                        '2026-10-14 13:05:00'),
(39, 8, 'Ready',            'Out for Delivery', 4, 'Driver dispatched',                       '2026-10-14 13:10:00'),
(40, 8, 'Out for Delivery', 'Completed',        4, 'Delivered to Park Street',                '2026-10-14 13:30:00'),

-- Order 9 (Pickup - Completed)
(41, 9, NULL,               'Placed',          16, 'Evening pickup order placed',             '2026-10-14 18:00:00'),
(42, 9, 'Placed',           'Preparing',        4, 'Cooking roast chicken cheese kottu',      '2026-10-14 18:05:00'),
(43, 9, 'Preparing',        'Ready',            4, 'Ready at front desk',                     '2026-10-14 18:25:00'),
(44, 9, 'Ready',            'Collected',        4, 'Customer arrived and collected',          '2026-10-14 18:38:00'),
(45, 9, 'Collected',        'Completed',        4, 'Order closed',                            '2026-10-14 18:40:00'),

-- Order 17 (Cancelled with stock restoration)
(46, 17, NULL,              'Placed',          11, 'Order placed via checkout',               '2026-10-17 12:30:00'),
(47, 17, 'Placed',          'Cancelled',        4, 'Customer requested cancellation; stock restored', '2026-10-17 12:45:00'),

-- Order 18 (Cancelled immediately)
(48, 18, NULL,              'Placed',          12, 'Order placed',                            '2026-10-18 13:00:00'),
(49, 18, 'Placed',          'Cancelled',       12, 'Customer cancelled order before kitchen preparation', '2026-10-18 13:10:00'),

-- Order 19 (Out for Delivery)
(50, 19, NULL,              'Placed',          13, 'Checkout done',                           '2026-10-19 12:00:00'),
(51, 19, 'Placed',          'Preparing',        4, 'Preparing pasta orders',                  '2026-10-19 12:05:00'),
(52, 19, 'Preparing',       'Ready',            5, 'Ready for driver',                        '2026-10-19 12:20:00'),
(53, 19, 'Ready',           'Out for Delivery', 4, 'Driver dispatched to Ward Place',         '2026-10-19 12:30:00'),

-- Order 20 (Ready for Pickup)
(54, 20, NULL,              'Placed',          14, 'Pickup order placed',                     '2026-10-19 12:15:00'),
(55, 20, 'Placed',          'Preparing',        5, 'Preparing kottu and burger',              '2026-10-19 12:18:00'),
(56, 20, 'Preparing',       'Ready',            4, 'Order waiting for customer collection',   '2026-10-19 12:35:00'),

-- Order 21 (Preparing)
(57, 21, NULL,              'Placed',          15, 'Checkout done',                           '2026-10-20 12:20:00'),
(58, 21, 'Placed',          'Preparing',        4, 'Cooking biryani and prawns in kitchen',   '2026-10-20 12:30:00'),

-- Order 22 (Placed - Delivery)
(59, 22, NULL,              'Placed',          16, 'New order placed by customer',            '2026-10-21 11:30:00'),

-- Order 23 (Placed - Pickup)
(60, 23, NULL,              'Placed',           8, 'New pickup order placed by customer',     '2026-10-21 12:00:00');
