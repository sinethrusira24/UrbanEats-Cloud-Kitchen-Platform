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
