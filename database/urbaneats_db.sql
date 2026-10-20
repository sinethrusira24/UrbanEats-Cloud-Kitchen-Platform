-- ============================================================
--  UrbanEats Cloud Kitchen Platform – Database Schema & Seed
--  Database : urbaneats_db
--  Engine   : InnoDB
--  Charset  : utf8mb4 / utf8mb4_unicode_ci
--  Export tool : phpMyAdmin
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ── Create database ──────────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS urbaneats_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE urbaneats_db;

-- ============================================================
--  COMPONENT A – Identity, Access & Administration
-- ============================================================

-- 1. Roles
CREATE TABLE IF NOT EXISTS roles (
    role_id     TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name   VARCHAR(50)  NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='User roles: Customer, Kitchen Staff, Administrator';

-- 2. Users
CREATE TABLE IF NOT EXISTS users (
    user_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id       TINYINT UNSIGNED NOT NULL,
    full_name     VARCHAR(120) NOT NULL,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(25)  NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_users_role_active (role_id, is_active),
    INDEX idx_users_created_at  (created_at)
) ENGINE=InnoDB COMMENT='Platform user accounts';

-- 3. Login attempts (throttle & audit)
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NULL,
    login_identifier VARCHAR(254)    NOT NULL,
    ip_address       VARCHAR(45)     NULL,
    was_successful   TINYINT(1)      NOT NULL DEFAULT 0,
    attempted_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_attempts_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_login_identifier_time (login_identifier, attempted_at),
    INDEX idx_login_user_time       (user_id, attempted_at),
    INDEX idx_login_ip_time         (ip_address, attempted_at)
) ENGINE=InnoDB COMMENT='Tracks login attempts for throttling';

-- 4. Audit log
CREATE TABLE IF NOT EXISTS audit_log (
    audit_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NULL,
    action     VARCHAR(100)    NOT NULL,
    details    TEXT            NULL,
    ip_address VARCHAR(45)     NULL,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_log_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_audit_user_time   (user_id, created_at),
    INDEX idx_audit_action_time (action,  created_at)
) ENGINE=InnoDB COMMENT='Immutable audit trail of important events';

-- 5. Password resets
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64)        NOT NULL UNIQUE COMMENT 'SHA-256 of the raw token',
    expires_at DATETIME        NOT NULL,
    used_at    DATETIME        NULL,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_password_reset_expiry (expires_at),
    INDEX idx_password_reset_user   (user_id)
) ENGINE=InnoDB COMMENT='Time-limited password reset tokens (hashed)';


-- ============================================================
--  COMPONENT B – Menu, Catalogue & Inventory  (stubs for FK)
-- ============================================================

-- 6. Categories
CREATE TABLE IF NOT EXISTS categories (
    category_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(80)  NOT NULL UNIQUE,
    description   VARCHAR(255) NULL,
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_categories_active (is_active)
) ENGINE=InnoDB COMMENT='Dish categories (e.g. Rice, Kottu, Drinks)';

-- 7. Dishes
CREATE TABLE IF NOT EXISTS dishes (
    dish_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id  INT UNSIGNED NOT NULL,
    name         VARCHAR(120) NOT NULL,
    description  TEXT         NULL,
    price        DECIMAL(10,2) NOT NULL,
    image_path   VARCHAR(255)  NULL,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Soft delete',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dishes_category
        FOREIGN KEY (category_id) REFERENCES categories(category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_dishes_category_active (category_id, is_active),
    INDEX idx_dishes_price           (price),
    INDEX idx_dishes_name            (name)
) ENGINE=InnoDB COMMENT='Menu items';

-- 8. Inventory
CREATE TABLE IF NOT EXISTS inventory (
    inventory_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dish_id       INT UNSIGNED NOT NULL UNIQUE,
    qty_available INT          NOT NULL DEFAULT 0,
    reorder_level INT          NOT NULL DEFAULT 5,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_dish
        FOREIGN KEY (dish_id) REFERENCES dishes(dish_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_inventory_qty (qty_available)
) ENGINE=InnoDB COMMENT='Current stock levels per dish';

-- 9. Stock movements
CREATE TABLE IF NOT EXISTS stock_movements (
    movement_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dish_id        INT UNSIGNED    NOT NULL,
    user_id        BIGINT UNSIGNED NULL,
    movement_type  ENUM('Initial', 'Restock', 'Wastage', 'Checkout_Deduction', 'Adjustment') NOT NULL DEFAULT 'Restock',
    change_qty     INT             NOT NULL COMMENT 'Positive = restock, negative = usage/wastage',
    previous_stock INT             NOT NULL DEFAULT 0,
    updated_stock  INT             NOT NULL DEFAULT 0,
    reason         VARCHAR(255)    NULL,
    moved_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_movements_dish
        FOREIGN KEY (dish_id) REFERENCES dishes(dish_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_stock_movements_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_stock_movements_dish (dish_id, moved_at),
    INDEX idx_stock_movements_type (movement_type, moved_at),
    INDEX idx_stock_movements_user (user_id)
) ENGINE=InnoDB COMMENT='History of every stock change';


-- ============================================================
--  COMPONENT C – Ordering, Payment & Tracking  (stubs for FK)
-- ============================================================

-- 10. Coupons
CREATE TABLE IF NOT EXISTS coupons (
    coupon_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(30)   NOT NULL UNIQUE,
    discount_pct DECIMAL(5,2)  NULL COMMENT '% off',
    discount_fix DECIMAL(10,2) NULL COMMENT 'Fixed LKR off',
    min_order    DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_uses     INT UNSIGNED  NULL     COMMENT 'NULL = unlimited',
    used_count   INT UNSIGNED  NOT NULL DEFAULT 0,
    expires_at   DATETIME      NULL,
    is_active    TINYINT(1)    NOT NULL DEFAULT 1,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_coupons_code   (code),
    INDEX idx_coupons_expiry (expires_at)
) ENGINE=InnoDB COMMENT='Promotional coupons';

-- 11. Orders
CREATE TABLE IF NOT EXISTS orders (
    order_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    coupon_id     INT UNSIGNED    NULL,
    subtotal      DECIMAL(10,2)   NOT NULL,
    discount      DECIMAL(10,2)   NOT NULL DEFAULT 0,
    total         DECIMAL(10,2)   NOT NULL,
    order_type    ENUM('Delivery','Pickup') NOT NULL DEFAULT 'Delivery',
    address       TEXT            NULL,
    phone         VARCHAR(25)     NULL,
    status        ENUM('Placed','Preparing','Ready','Out for Delivery','Collected','Completed','Cancelled')
                  NOT NULL DEFAULT 'Placed',
    notes         TEXT            NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_orders_coupon
        FOREIGN KEY (coupon_id) REFERENCES coupons(coupon_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_orders_user_status (user_id, status),
    INDEX idx_orders_created_at  (created_at)
) ENGINE=InnoDB COMMENT='Customer orders';

-- 12. Order items
CREATE TABLE IF NOT EXISTS order_items (
    item_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id      BIGINT UNSIGNED NOT NULL,
    dish_id       INT UNSIGNED    NOT NULL,
    dish_name     VARCHAR(120)    NOT NULL COMMENT 'Snapshot at time of purchase',
    unit_price    DECIMAL(10,2)   NOT NULL COMMENT 'Snapshot at time of purchase',
    quantity      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    line_total    DECIMAL(10,2)   NOT NULL,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_order_items_dish
        FOREIGN KEY (dish_id) REFERENCES dishes(dish_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_dish  (dish_id)
) ENGINE=InnoDB COMMENT='Line items within each order';

-- 13. Payments
CREATE TABLE IF NOT EXISTS payments (
    payment_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id       BIGINT UNSIGNED NOT NULL UNIQUE,
    method         ENUM('Cash on Delivery','Card','Bank Transfer') NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    status         ENUM('Pending','Received','Failed') NOT NULL DEFAULT 'Pending',
    reference      VARCHAR(100)  NULL,
    paid_at        DATETIME      NULL,
    CONSTRAINT fk_payments_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Payment records (simulated)';

-- 14. Order status history
CREATE TABLE IF NOT EXISTS order_status_history (
    history_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id   BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NULL,
    old_status VARCHAR(30)     NULL,
    new_status VARCHAR(30)     NOT NULL,
    note       VARCHAR(255)    NULL,
    changed_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_history_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_order_history_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_order_history_order (order_id, changed_at)
) ENGINE=InnoDB COMMENT='Full audit trail of order status changes';


-- ============================================================
--  SEED DATA
-- ============================================================

-- Roles
INSERT IGNORE INTO roles (role_name, description) VALUES
('Customer',       'Can register, browse the menu, place and track orders.'),
('Kitchen Staff',  'Can manage dishes, stock and kitchen order processing.'),
('Administrator',  'Can manage accounts, roles and audit activity.');

-- ── Users (password for all seeds = "Password1!" bcrypt hash) ─────────────
-- Hash generated with: password_hash('Password1!', PASSWORD_BCRYPT, ['cost'=>12])
-- Hash value below is representative; regenerate if needed.

INSERT IGNORE INTO users (role_id, full_name, username, email, password_hash, phone, is_active) VALUES
-- Administrators (role_id=3)
(3, 'Nimali Fernando',   'nimali_admin',  'nimali@urbaneats.lk',   '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 77 100 0001', 1),
(3, 'Ashan Perera',      'ashan_admin',   'ashan@urbaneats.lk',    '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 77 100 0002', 1),

-- Kitchen Staff (role_id=2)
(2, 'Kasun Rajapaksa',   'kasun_kitchen', 'kasun@urbaneats.lk',    '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 76 200 0001', 1),
(2, 'Dilini Wickrama',   'dilini_chef',   'dilini@urbaneats.lk',   '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 76 200 0002', 1),
(2, 'Roshan Gunawardena','roshan_staff',  'roshan@urbaneats.lk',   '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 76 200 0003', 1),

-- Customers (role_id=1)
(1, 'Sachini Kumari',    'sachini_k',     'sachini@gmail.com',     '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 71 300 0001', 1),
(1, 'Tharaka Bandara',   'tharaka_b',     'tharaka@gmail.com',     '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 71 300 0002', 1),
(1, 'Malsha Dissanayake','malsha_d',      'malsha@gmail.com',      '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 71 300 0003', 1),
(1, 'Chathura Seneviratne','chathura_s',  'chathura@gmail.com',    '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 72 300 0004', 1),
(1, 'Nimesha Athukorala', 'nimesha_a',   'nimesha@gmail.com',      '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 72 300 0005', 1),
(1, 'Isuru Liyanage',    'isuru_l',       'isuru@gmail.com',       '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 70 300 0006', 1),
(1, 'Dinusha Fonseka',   'dinusha_f',     'dinusha@gmail.com',     '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 70 300 0007', 1),
(1, 'Ravindu Silva',     'ravindu_s',     'ravindu@gmail.com',     '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 75 300 0008', 1),
(1, 'Amali Jayawardena', 'amali_j',       'amali@gmail.com',       '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 75 300 0009', 1),
(1, 'Hiruni Wijesinghe', 'hiruni_w',      'hiruni@gmail.com',      '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 74 300 0010', 1),
(1, 'Pasindu Gamage',    'pasindu_g',     'pasindu@gmail.com',     '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 74 300 0011', 1),
(1, 'Thilina Abeywickrama','thilina_a',   'thilina@gmail.com',     '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 77 300 0012', 1),
(1, 'Sandali Rathnayake', 'sandali_r',   'sandali@gmail.com',      '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 77 300 0013', 1),
(1, 'Kavitha Subasinghe', 'kavitha_s',   'kavitha@gmail.com',      '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 71 300 0014', 1),
(1, 'Dulshan Mendis',    'dulshan_m',     'dulshan@gmail.com',     '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 71 300 0015', 1),
(1, 'Lasitha Pathirana', 'lasitha_p',     'lasitha@gmail.com',     '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 76 300 0016', 1),
(1, 'Jayani Senanayake', 'jayani_se',     'jayani@gmail.com',      '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 76 300 0017', 1),
(1, 'Chandi Jayasekara', 'chandi_j',      'chandi@gmail.com',      '$2y$12$8K1p/a0FP.8Hm4ZwXQXDIOeGOdQVDf1e3Pv1e.6zjP0QGVuL9x2sa', '+94 72 300 0018', 0);  -- Inactive account example

-- ── Categories (seed for Component B) ─────────────────────────────────────
INSERT IGNORE INTO categories (name, description, display_order, is_active) VALUES
('Rice & Curry',       'Traditional Sri Lankan rice and curry plates',     1, 1),
('Kottu Roti',         'Sri Lankan street food kottu in various styles',   2, 1),
('Burgers & Wraps',    'Western-style burgers and wraps',                  3, 1),
('Pasta & Noodles',    'Pasta, stir-fried noodles and similar dishes',     4, 1),
('Grilled & BBQ',      'Grilled chicken, fish and meats',                  5, 1),
('Soups & Salads',     'Freshly prepared soups and salads',                6, 1),
('Desserts',           'Sweets, cakes, puddings',                          7, 1),
('Beverages',          'Juices, shakes, tea and coffee',                   8, 1);

-- ── Dishes (seed for Component B) ─────────────────────────────────────────
INSERT IGNORE INTO dishes (category_id, name, description, price, image_path, is_active) VALUES
(1, 'Special Rice & Curry',     'White rice with 3 curries, papadum and salad',      450.00, 'uploads/dishes/biriyani.jpg', 1),
(1, 'Chicken Biriyani',         'Fragrant basmati rice with spiced chicken',          550.00, 'uploads/dishes/biriyani.jpg', 1),
(1, 'Vegetable Rice Plate',     'Rice with 3 vegetable curries',                      380.00, 'uploads/dishes/biriyani.jpg', 1),
(1, 'Prawn Biriyani',           'Basmati rice with whole prawns',                     700.00, 'uploads/dishes/biriyani.jpg', 1),
(2, 'Chicken Kottu',            'Shredded gothambu roti with chicken and vegetables', 480.00, 'uploads/dishes/kottu.jpg', 1),
(2, 'Egg Kottu',                'Classic egg kottu with onion and leek',              380.00, 'uploads/dishes/kottu.jpg', 1),
(2, 'Cheese Kottu',             'Kottu loaded with melted cheese',                    520.00, 'uploads/dishes/kottu.jpg', 1),
(3, 'Classic Beef Burger',      'Beef patty, lettuce, tomato, cheese',                550.00, 'uploads/dishes/burger.jpg', 1),
(3, 'Spicy Chicken Burger',     'Crispy fried chicken with chilli mayo',              520.00, 'uploads/dishes/burger.jpg', 1),
(3, 'Chicken Caesar Wrap',      'Grilled chicken, romaine, parmesan, caesar dressing', 490.00, 'uploads/dishes/burger.jpg', 1),
(4, 'Spaghetti Bolognese',      'Slow-cooked beef ragu, parmesan',                    620.00, 'uploads/dishes/pasta.jpg', 1),
(4, 'Pad Thai Noodles',         'Rice noodles, peanuts, spring onion, egg',           540.00, 'uploads/dishes/pasta.jpg', 1),
(5, 'BBQ Chicken Platter',      'Half BBQ chicken with coleslaw and garlic bread',    850.00, 'uploads/dishes/bbq.jpg', 1),
(5, 'Grilled Fish Plate',       'Fresh grilled seer fish with salad and rice',        750.00, 'uploads/dishes/bbq.jpg', 1),
(6, 'Tomato Basil Soup',        'Classic tomato basil soup with garlic croutons',     290.00, 'uploads/dishes/soup.jpg', 1),
(6, 'Grilled Chicken Salad',    'Mixed greens, grilled chicken, vinaigrette',         420.00, 'uploads/dishes/soup.jpg', 1),
(7, 'Chocolate Lava Cake',      'Warm chocolate cake with vanilla ice cream',         350.00, 'uploads/dishes/lava_cake.jpg', 1),
(7, 'Watalappan',               'Traditional Sri Lankan coconut custard pudding',     220.00, 'uploads/dishes/lava_cake.jpg', 1),
(8, 'Fresh Mango Lassi',        'Blended mango with yoghurt',                         220.00, 'uploads/dishes/beverage.jpg', 1),
(8, 'Iced Coffee',              'Cold brew with condensed milk',                      280.00, 'uploads/dishes/beverage.jpg', 1),
(8, 'Fresh Lime Juice',         'Squeezed lime with a pinch of salt',                 150.00, 'uploads/dishes/beverage.jpg', 1),
(8, 'Strawberry Milkshake',     'Thick strawberry milkshake',                         320.00, 'uploads/dishes/beverage.jpg', 1);

-- ── Inventory (seed for Component B) ──────────────────────────────────────
INSERT IGNORE INTO inventory (dish_id, qty_available, reorder_level)
SELECT dish_id, FLOOR(RAND()*50)+10, 5 FROM dishes;

-- ── Coupons (seed for Component C) ────────────────────────────────────────
INSERT IGNORE INTO coupons (code, discount_pct, discount_fix, min_order, max_uses, expires_at, is_active) VALUES
('WELCOME10',  10.00, NULL,   500.00, 100, DATE_ADD(NOW(), INTERVAL 90 DAY),  1),
('FLAT100',    NULL,  100.00, 1000.00,  50, DATE_ADD(NOW(), INTERVAL 30 DAY),  1),
('SAVE20',     20.00, NULL,   800.00,  30, DATE_ADD(NOW(), INTERVAL 60 DAY),  1),
('FREESHIP',   NULL,   50.00,  300.00, 200, DATE_ADD(NOW(), INTERVAL 45 DAY),  1),
('EXPIRED50',  50.00, NULL,     0.00,  10, '2024-01-01 00:00:00',             0);

-- ── Sample audit log seed ──────────────────────────────────────────────────
INSERT INTO audit_log (user_id, action, details, ip_address) VALUES
(1, 'user_registered', 'New administrator account seeded.',         '127.0.0.1'),
(2, 'user_registered', 'Second administrator account seeded.',      '127.0.0.1'),
(3, 'user_registered', 'Kitchen staff account seeded.',             '127.0.0.1'),
(6, 'user_registered', 'Customer account seeded.',                  '127.0.0.1'),
(1, 'user_login',      'Admin nimali_admin signed in.',             '127.0.0.1');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  INDEX DOCUMENTATION
--  (required in the group report)
--
--  idx_users_role_active    – speeds up admin queries filtering
--                             users by role AND active status.
--  idx_login_identifier_time– throttle query looks up recent
--                             failures by identifier + timestamp.
--  idx_login_ip_time        – detect brute-force from one IP.
--  idx_audit_action_time    – quick retrieval of recent events
--                             for a specific action type.
--  idx_dishes_category_active–menu page filters by category
--                             and visibility in one index scan.
--  idx_order_items_dish     – top-selling dishes report
--                             GROUP BY dish_id is index-only.
-- ============================================================
