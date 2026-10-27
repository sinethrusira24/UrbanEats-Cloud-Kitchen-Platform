SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE IF NOT EXISTS `urbaneats_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `urbaneats_db`;

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

-- ── Seed Dishes (22 Rows) ───────────────────────────────────
INSERT INTO `dishes` (`dish_id`, `category_id`, `name`, `description`, `price`, `image_path`, `is_active`) VALUES
(1,  3,  'Urban Royal Chicken Biryani',       'Fragrant basmati rice slow-cooked with tender chicken leg, boiled egg, mint sambol, and spicy gravy.', 1650.00, 'uploads/dishes/biriyani.jpg', 1),
(2,  2,  'Spicy Roast Chicken Cheese Kottu',  'Finely chopped godamba roti tossed with shredded roast chicken, fresh vegetables, creamy melted cheese, and aromatic gravy.', 1450.00, 'uploads/dishes/cheese_kottu.jpg', 1),
(3,  1,  'Village Style Red Rice & Fish Curry','Steamed wholesome red rice served with spicy ambul thiyal fish, tempered dhal, pol sambol, and crispy papadam.', 950.00, 'uploads/dishes/rice_and_curry.jpg', 1),
(4,  2,  'Egg & Vegetable Kottu Deluxe',      'Crispy roti strips tossed on the griddle with farm eggs, leeks, carrots, onions, and house blend spices.', 980.00, 'uploads/dishes/egg_kottu.jpg', 1),
(5,  5,  'Yangzhou Mixed Fried Rice',         'Wok-tossed premium basmati rice loaded with chicken, prawns, egg ribbons, scallions, and signature sesame chilli paste.', 1550.00, 'uploads/dishes/fried_rice.jpg', 1),
(6,  6,  'Spicy Devilled Chicken (Dry Bowl)', 'Crispy battered chicken chunks tossed in fiery red chilli sauce with capsicum, red onions, and ripe tomatoes.', 1350.00, 'uploads/dishes/devilled_chicken.jpg', 1),
(7,  9,  'Crispy Kochchi Chicken Burger',     'Buttermilk fried chicken breast seasoned with fiery green kochchi chillies, garlic mayo, and lettuce on a brioche bun.', 1250.00, 'uploads/dishes/chicken_burger.jpg', 1),
(8,  13, 'Lagoon Prawns Devilled Bowl',       'Succulent fresh lagoon prawns wok-seared with chilli paste, banana peppers, garlic, and sweet-sour tomato glaze.', 2100.00, 'uploads/dishes/prawn_curry.jpg', 1),
(9,  10, 'Creamy Chicken & Mushroom Penne',   'Al dente penne pasta tossed in rich parmesan garlic cream sauce, grilled chicken strips, and sautéed button mushrooms.', 1750.00, 'uploads/dishes/pasta_penne.jpg', 1),
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

-- ── Seed Inventory (22 Rows) ────────────────────────────────
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

-- ── Seed Stock Movements (22 Rows) ──────────────────────────
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
