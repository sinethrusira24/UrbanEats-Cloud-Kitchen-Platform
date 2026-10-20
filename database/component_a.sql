-- ============================================================================
-- UrbanEats Cloud Kitchen Platform
-- Component A: Identity, Access and Administration
-- Database Schema and Seed Data
-- ============================================================================
-- Technology: MySQL 8.x / MariaDB 10.4+
-- Character Set: utf8mb4 / utf8mb4_unicode_ci
-- Complies with 3NF (Third Normal Form), Foreign Key Constraints, and Indexes.
-- ============================================================================

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
