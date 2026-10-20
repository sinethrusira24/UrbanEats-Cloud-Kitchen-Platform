-- ============================================================
--  UrbanEats Cloud Kitchen Platform – Component B Export
--  Component : Component B (Menu, Catalogue and Inventory)
--  Database  : urbaneats_db (MySQL / MariaDB via phpMyAdmin)
--  Engine    : InnoDB
--  Charset   : utf8mb4 / utf8mb4_unicode_ci
--  Author    : Component B Developer
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ── 1. Categories Table ──────────────────────────────────────
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

-- ── 2. Dishes Table ──────────────────────────────────────────
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

-- ── 3. Inventory Table ───────────────────────────────────────
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

-- ── 4. Stock Movements Table ─────────────────────────────────
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

-- ── Seed Categories (21 Rows) ───────────────────────────────
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
(16, 'Sambols & Pickles',        'Fresh pol sambol, caramelized seeni sambol, and spicy Malay pickles',    16, 1),
(17, 'Desserts & Sweets',        'Decadent chocolate cakes, caramel puddings, and chilled cheesecakes',    17, 1),
(18, 'Traditional Sweets',       'Authentic steamed jaggery watalappan, buffalo curd, and palm treacle',   18, 1),
(19, 'Fresh Juices & Shakes',    'Tropical mango smoothies, lime juice, king coconut, and falooda',        19, 1),
(20, 'Ceylon Teas & Coffees',    'Artisan spiced chai, cold-brewed iced coffee, and pure black Ceylon tea', 20, 1),
(21, 'Family Combos & Packs',    'Value-packed sharing platters and cloud kitchen party meal boxes',        21, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `description`=VALUES(`description`), `display_order`=VALUES(`display_order`), `is_active`=VALUES(`is_active`);

-- ── Seed Dishes (24 Rows) ───────────────────────────────────
INSERT INTO `dishes` (`dish_id`, `category_id`, `name`, `description`, `price`, `image_path`, `is_active`) VALUES
(1,  1,  'Special Village Rice & Curry', 'Steamed red rice served with chicken curry, dhal, tempered beans, pol sambol, and crisp papadam.', 480.00,  'uploads/dishes/biriyani.jpg', 1),
(2,  1,  'Seer Fish Rice Plate',         'White samba rice served with fried seer fish, moringa curry, beetroot mallum, and fried chillies.',   680.00,  'uploads/dishes/bbq.jpg',      1),
(3,  1,  'Creamy Cashew Rice & Curry',   'Vegetarian rice plate with slow-braised cashew nut curry, dhal, gotu kola sambol, and coconut gravy.', 520.00,  'uploads/dishes/biriyani.jpg', 1),
(4,  2,  'Classic Chicken Kottu',        'Fresh godamba roti chopped on griddle with chicken, leeks, onions, scrambled egg, and spicy gravy.',   550.00,  'uploads/dishes/kottu.jpg',    1),
(5,  2,  'Double Cheese Roast Kottu',    'Crispy toasted roti kottu loaded with roast chicken chunks and drenched in melted mozzarella cheese.',  720.00,  'uploads/dishes/kottu.jpg',    1),
(6,  2,  'Egg & Vegetable Kottu',        'Hearty chopped roti stir-fry with double eggs, crisp cabbage, carrots, spring onions, and curry sauce.',420.00,  'uploads/dishes/kottu.jpg',    1),
(7,  3,  'Dum Chicken Biriyani',         'Aromatic basmati rice layered with marinated chicken thigh, hard-boiled egg, mint chutney, and raita.',650.00,  'uploads/dishes/biriyani.jpg', 1),
(8,  3,  'Lagoon Prawn Biriyani',        'Spiced long-grain saffron rice cooked with jumbo lagoon prawns, caramelized onions, and cashew nuts.',  890.00,  'uploads/dishes/biriyani.jpg', 1),
(9,  4,  'Kurakkan Pol Roti Trio',       'Three toasted coconut and finger-millet flatbreads served with fiery lunu miris and coconut milk dhal.',320.00,  'uploads/dishes/kottu.jpg',    1),
(10, 5,  'Yangzhou Mixed Fried Rice',    'Wok-tossed jasmine rice with diced chicken, prawns, scrambled eggs, carrots, and sweet chilli paste.',  620.00,  'uploads/dishes/pasta.jpg',    1),
(11, 6,  'Devilled Chicken Supreme',     'Crisp fried chicken cubes tossed in spicy chilli garlic sauce with capsicum, onions, and tomato wedges.',590.00, 'uploads/dishes/bbq.jpg',      1),
(12, 6,  'Devilled Butter Cuttlefish',   'Tender cuttlefish rings coated in seasoned batter and wok-tossed with green chillies and roasted garlic.',780.00, 'uploads/dishes/bbq.jpg',      1),
(13, 7,  'Egg Hopper & Plain Hopper Set','Two crispy coconut milk bowl hoppers and one sunny egg hopper served with spicy onion-chilli sambol.',   280.00,  'uploads/dishes/biriyani.jpg', 1),
(14, 8,  'Spicy Mutton Rolls (3 Pcs)',   'Golden breaded crepes stuffed with minced spiced mutton and mashed potatoes. Served with tomato dip.', 390.00,  'uploads/dishes/burger.jpg',   1),
(15, 9,  'Crispy Kochchi Chicken Burger','Crunchy buttermilk chicken breast with fiery Kochchi chilli mayonnaise, cheddar cheese, and fresh slaw.',580.00,  'uploads/dishes/burger.jpg',   1),
(16, 9,  'Double Angus Beef Burger',     'Two grilled beef patties with melted cheese, caramelized onions, gherkins, and smoked barbecue sauce.', 790.00,  'uploads/dishes/burger.jpg',   1),
(17, 10, 'Spicy Seafood Spaghetti',      'Al dente spaghetti tossed with fresh prawns, calamari, garlic, bird’s eye chillies, and olive oil.',    750.00,  'uploads/dishes/pasta.jpg',    1),
(18, 11, 'Sweet Corn & Chicken Soup',    'Warm soothing broth thickened with sweet corn kernels, tender shredded chicken, and beaten egg ribbons.',340.00, 'uploads/dishes/soup.jpg',     1),
(19, 12, 'Smoked BBQ Quarter Chicken',   'Charcoal-roasted quarter chicken brushed with sticky glaze, served with seasoned fries and house slaw.', 820.00,  'uploads/dishes/bbq.jpg',      1),
(20, 13, 'Black Pepper Seer Fish Curry', 'Fresh seer fish steaks cooked in a rich, peppery roasted curry gravy seasoned with tamarind and curry leaves.',850.00,'uploads/dishes/bbq.jpg',      1),
(21, 14, 'Jackfruit Polos Ambula',       'Traditional young green jackfruit slow-cooked over firewood with dark roasted spices and coconut cream.',460.00, 'uploads/dishes/biriyani.jpg', 1),
(22, 17, 'Molten Chocolate Lava Cake',   'Warm Belgian chocolate cake with a molten chocolate center, dusted with icing sugar.',                  380.00,  'uploads/dishes/lava_cake.jpg',1),
(23, 18, 'Traditional Kithul Watalappan','Steamed coconut custard pudding sweetened with pure Kithul jaggery, nutmeg, and roasted cashew nuts.', 260.00, 'uploads/dishes/lava_cake.jpg',1),
(24, 19, 'Tropical Mango & Passion Smoothie','Fresh ripe Willard mango blended with tangy passionfruit pulp, fresh yoghurt, and crushed ice.',     320.00,  'uploads/dishes/beverage.jpg', 1)
ON DUPLICATE KEY UPDATE `category_id`=VALUES(`category_id`), `name`=VALUES(`name`), `description`=VALUES(`description`), `price`=VALUES(`price`), `image_path`=VALUES(`image_path`), `is_active`=VALUES(`is_active`);

-- ── Seed Inventory (24 Rows) ────────────────────────────────
INSERT INTO `inventory` (`dish_id`, `qty_available`, `reorder_level`) VALUES
(1,  35, 8),
(2,  14, 5),
(3,  20, 6),
(4,  40, 10),
(5,  18, 5),
(6,  25, 8),
(7,  30, 8),
(8,  12, 5),
(9,  15, 5),
(10, 22, 6),
(11, 28, 8),
(12,  9, 5),
(13, 18, 5),
(14, 25, 10),
(15, 20, 6),
(16, 15, 5),
(17, 16, 5),
(18, 20, 5),
(19, 14, 5),
(20, 10, 4),
(21, 12, 4),
(22, 24, 6),
(23, 30, 8),
(24, 25, 6)
ON DUPLICATE KEY UPDATE `qty_available`=VALUES(`qty_available`), `reorder_level`=VALUES(`reorder_level`);

-- ── Seed Stock Movements (24 Rows) ──────────────────────────
INSERT INTO `stock_movements` (`movement_id`, `dish_id`, `user_id`, `movement_type`, `change_qty`, `previous_stock`, `updated_stock`, `reason`, `moved_at`) VALUES
(1,  1,  3, 'Initial',           40,  0,  40, 'Initial opening inventory setup for lunch shift', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2,  1,  NULL, 'Checkout_Deduction', -5,  40, 35, 'Order checkout deduction - Order #101',       DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3,  2,  3, 'Initial',           20,  0,  20, 'Morning fresh catch seer fish delivery',          DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4,  2,  NULL, 'Checkout_Deduction', -4,  20, 16, 'Customer web order deduction',                 DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5,  2,  4, 'Wastage',           -2,  16, 14, 'End of evening service trimming & quality discard',DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6,  4,  4, 'Initial',           50,  0,  50, 'Kitchen staff morning prep - godamba roti batch', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(7,  4,  NULL, 'Checkout_Deduction', -10, 50, 40, 'Peak dinner orders checkout deduction',       DATE_SUB(NOW(), INTERVAL 2 DAY)),
(8,  5,  4, 'Initial',           25,  0,  25, 'Cheese kottu ingredients prepared and portioned', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(9,  5,  NULL, 'Checkout_Deduction', -7,  25, 18, 'Evening takeout orders processed',             DATE_SUB(NOW(), INTERVAL 1 DAY)),
(10, 7,  3, 'Initial',           35,  0,  35, 'Dum biriyani pot batch cooked for cloud kitchen',  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(11, 7,  NULL, 'Checkout_Deduction', -5,  35, 30, 'Corporate lunch box deliveries',               DATE_SUB(NOW(), INTERVAL 1 DAY)),
(12, 8,  4, 'Initial',           15,  0,  15, 'Lagoon prawns received from Negombo supplier',    DATE_SUB(NOW(), INTERVAL 2 DAY)),
(13, 8,  NULL, 'Checkout_Deduction', -3,  15, 12, 'Dine-in and pickup checkout order deduction',  DATE_SUB(NOW(), INTERVAL 1 DAY)),
(14, 11, 3, 'Initial',           30,  0,  30, 'Devilled chicken base prepared by kitchen staff',  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(15, 11, NULL, 'Checkout_Deduction', -2,  30, 28, 'Online platform delivery deduction',           DATE_SUB(NOW(), INTERVAL 1 DAY)),
(16, 12, 4, 'Initial',           12,  0,  12, 'Fresh cuttlefish supply cleaned and portioned',    DATE_SUB(NOW(), INTERVAL 2 DAY)),
(17, 12, NULL, 'Checkout_Deduction', -3,  12,  9, 'Customer order #118 deduction',               DATE_SUB(NOW(), INTERVAL 1 DAY)),
(18, 14, 5, 'Initial',           30,  0,  30, 'Mutton rolls freshly rolled and breaded',          DATE_SUB(NOW(), INTERVAL 2 DAY)),
(19, 14, NULL, 'Checkout_Deduction', -5,  30, 25, 'Short eats afternoon rush deduction',          DATE_SUB(NOW(), INTERVAL 1 DAY)),
(20, 15, 3, 'Initial',           25,  0,  25, 'Brioche buns and Kochchi patties received',       DATE_SUB(NOW(), INTERVAL 2 DAY)),
(21, 15, NULL, 'Checkout_Deduction', -5,  25, 20, 'Burger combo orders deduction',               DATE_SUB(NOW(), INTERVAL 1 DAY)),
(22, 22, 5, 'Initial',           20,  0,  20, 'Fresh lava cake batch baked in morning',           DATE_SUB(NOW(), INTERVAL 2 DAY)),
(23, 22, 5, 'Restock',            6,  20, 26, 'Mid-day additional baking batch restock',          DATE_SUB(NOW(), INTERVAL 1 DAY)),
(24, 22, NULL, 'Checkout_Deduction', -2,  26, 24, 'Dessert order deduction',                     NOW())
ON DUPLICATE KEY UPDATE `dish_id`=VALUES(`dish_id`), `movement_type`=VALUES(`movement_type`), `change_qty`=VALUES(`change_qty`), `previous_stock`=VALUES(`previous_stock`), `updated_stock`=VALUES(`updated_stock`), `reason`=VALUES(`reason`);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  COMPONENT B DATABASE DOCUMENTATION & INDEX JUSTIFICATION
--  (For Individual Report and Viva Defense)
--
--  1. categories:
--     - idx_categories_active: Fast filtering of visible categories in public catalogue.
--     - idx_categories_order: Enables sorted display order without filesort.
--
--  2. dishes:
--     - idx_dishes_category_active: Speeds up filtered catalogue queries (WHERE category_id=? AND is_active=1).
--     - idx_dishes_price: Enables fast indexed sorting by price ascending and descending.
--     - idx_dishes_name: Supports full-table and prefix searching on dish titles.
--     - is_active column: Implements soft deletion so past order_items in Component C are never orphaned.
--
--  3. inventory:
--     - 1:1 relationship with dishes table, ensuring atomic portion balance tracking.
--     - idx_inventory_qty & idx_inventory_reorder: Instant calculation of low-stock and out-of-stock items.
--
--  4. stock_movements:
--     - 1:N history log tracking who made the change, movement type, previous and updated quantities.
--     - fk_stock_movements_user: References Component A users with ON DELETE SET NULL to preserve movement logs.
-- ============================================================
