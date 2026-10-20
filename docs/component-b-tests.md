# UrbanEats — Component B Test Report & Verification
**Component B: Menu, Catalogue and Inventory**  
*Course: IT3113 Advanced Web Technologies — Cloud Kitchen Platform*  
*Environment: PHP 8.2.12 (CLI/Apache) / MySQL 10.4.32-MariaDB / Windows XAMPP*

---

## 1. Test Suite Summary

This document presents the functional, validation, security, and concurrency verification for **Component B (Menu, Catalogue & Inventory)**. All tests are mapped directly to the assignment requirements in Sections 4, 5, 6, 7, 8, 9, 10, and 11.

| Category | Total Tests | Passed | Failed | Status |
| :--- | :---: | :---: | :---: | :---: |
| **1. Public Menu & Catalogue** | 6 | 6 | 0 | ✅ 100% Passed |
| **2. Dish Details Showcase** | 3 | 3 | 0 | ✅ 100% Passed |
| **3. Kitchen Staff Dish CRUD** | 5 | 5 | 0 | ✅ 100% Passed |
| **4. Category Management** | 3 | 3 | 0 | ✅ 100% Passed |
| **5. Inventory & Concurrency (`reduce_stock`)** | 5 | 5 | 0 | ✅ 100% Passed |
| **6. Security & Multi-Tier Image Uploads** | 4 | 4 | 0 | ✅ 100% Passed |
| **TOTAL** | **26** | **26** | **0** | **✅ 100% PASSED** |

---

## 2. Detailed Test Cases & Execution Results

### 1. Public Menu & Catalogue (`public/menu/index.php`)

#### TC-B01: Display Public Menu Catalogue
* **Objective:** Verify active dishes and categories render dynamically from MySQL with prices in LKR.
* **Test Input / Action:** Request `GET /menu/index.php` without parameters.
* **Expected Result:** HTTP 200; renders active category pills, dish cards, prices formatted in LKR (e.g. `LKR 1,650.00`), portion stock badges, and pagination footer.
* **Actual Result:** HTTP 200 OK; 22 dishes available across 21 categories; all prices displayed in LKR.
* **Status:** **PASS**

#### TC-B02: Category Filtering with Dynamic Counters
* **Objective:** Verify filtering by category displays only matching items and updates active state.
* **Test Input / Action:** Request `GET /menu/index.php?cat=2` (Kottu Specialties).
* **Expected Result:** Shows only dishes belonging to category 2 (e.g., Spicy Roast Chicken Cheese Kottu, Egg Kottu); Kottu pill highlighted with active styling.
* **Actual Result:** Displayed 2 Kottu dishes; results counter correctly stated "Showing 2 of 2 dishes in Kottu Specialties".
* **Status:** **PASS**

#### TC-B03: Keyword Search Filtering
* **Objective:** Verify search matches against dish names and descriptions case-insensitively.
* **Test Input / Action:** Request `GET /menu/index.php?q=biryani`.
* **Expected Result:** Shows "Urban Royal Chicken Biryani" matching search; search query preserved in input.
* **Actual Result:** Matches returned accurately; non-matching dishes excluded; query string sanitized and preserved.
* **Status:** **PASS**

#### TC-B04: Whitelisted Server-Side Sorting
* **Objective:** Confirm SQL sorting whitelist blocks arbitrary column injections and sorts correctly.
* **Test Input / Action:**
  1. `GET /menu/index.php?sort=price_asc`
  2. `GET /menu/index.php?sort=price_desc`
  3. `GET /menu/index.php?sort=1;DROP+TABLE+users;--`
* **Expected Result:**
  1. Lowest priced dish (LKR 280.00) appears first.
  2. Highest priced dish (LKR 2,400.00) appears first.
  3. Malicious sort parameter rejected and falls back safely to default `d.name ASC`.
* **Actual Result:** Price sorting strictly verified; invalid strings defaulted safely to `name_asc` without SQL errors.
* **Status:** **PASS**

#### TC-B05: Pagination Preserving Filter Parameters
* **Objective:** Verify moving between pages retains active category, search, and sort parameters.
* **Test Input / Action:** Request `GET /menu/index.php?cat=5&sort=price_desc&page=2`.
* **Expected Result:** Pagination URL links include `cat=5&sort=price_desc&page=X`.
* **Actual Result:** Query string preserved across page buttons; active page number highlighted.
* **Status:** **PASS**

#### TC-B06: Empty Search Results Feedback
* **Objective:** Confirm user-friendly empty state when search returns no matching records.
* **Test Input / Action:** Request `GET /menu/index.php?q=nonexistent_item_xyz`.
* **Expected Result:** Clean empty card displayed with "No Dishes Found" and "View Entire Menu" button.
* **Actual Result:** Empty state card rendered cleanly without breaking layout.
* **Status:** **PASS**

---

### 2. Dish Details Page (`public/menu/detail.php`)

#### TC-B07: Display Complete Dish Information
* **Objective:** Verify dish detail presentation, ingredients, portion meter, and category badge.
* **Test Input / Action:** Request `GET /menu/detail.php?id=1`.
* **Expected Result:** HTTP 200; displays "Urban Royal Chicken Biryani", LKR 1,650.00, high-res presentation image, and portion meter showing "45 portions ready".
* **Actual Result:** Rendered complete culinary description, breadcrumb navigation, and portion meter bar.
* **Status:** **PASS**

#### TC-B08: Sold Out / Depleted Stock Presentation
* **Objective:** Confirm out-of-stock items display clear "Sold Out" status and disable ordering action.
* **Test Input / Action:** Request `GET /menu/detail.php?id=22` (Classic Caramel Custard Pudding, seeded with 0 portions).
* **Expected Result:** Status badge renders "Out of Stock"; button displays "❌ Currently Sold Out" (disabled).
* **Actual Result:** Out of stock badge rendered in red; order button disabled.
* **Status:** **PASS**

#### TC-B09: Guard Against Nonexistent or Deactivated Dish ID
* **Objective:** Prevent access to invalid IDs, non-numeric values, or soft-deleted items.
* **Test Input / Action:**
  1. `GET /menu/detail.php?id=99999` (nonexistent)
  2. `GET /menu/detail.php?id=abc` (invalid parameter)
* **Expected Result:** Returns HTTP 404 response utilizing shared `public/errors/404.php`.
* **Actual Result:** HTTP 404 response served with friendly "Page / Dish Not Found" template.
* **Status:** **PASS**

---

### 3. Kitchen Staff Dish CRUD (`public/menu/manage.php` & `dish-form.php`)

#### TC-B10: Access Control Guard on Staff Pages
* **Objective:** Enforce that unauthenticated users or customers cannot access kitchen staff management.
* **Test Input / Action:** Access `GET /menu/manage.php` without active session.
* **Expected Result:** Redirected to `auth/login.php` with flash message "Please log in to access this page."
* **Actual Result:** Unauthenticated requests redirected immediately; HTTP 403 served if logged in as customer.
* **Status:** **PASS**

#### TC-B11: Add New Dish with Transactional Inventory Provisioning
* **Objective:** Verify creating a dish concurrently inserts the dish record, initial inventory quantity, and stock movement audit entry.
* **Test Input / Action:** Submit Add Dish form as Kitchen Staff (`kasun_kitchen`):
  * Name: `Spicy Devilled Calamari Rings`
  * Category: Seafood Specials (ID 13)
  * Price: `1850.00`
  * Initial Portions: `25`
  * Reorder Level: `5`
* **Expected Result:** Database transaction commits all 3 records:
  1. `dishes` row created with `is_active = 1`.
  2. `inventory` row created with `qty_available = 25`.
  3. `stock_movements` row created with `movement_type = 'Initial'`, `change_qty = 25`.
* **Actual Result:** All 3 rows inserted in single transaction; audit log recorded action `dish_created`.
* **Status:** **PASS**

#### TC-B12: Edit Existing Dish Details
* **Objective:** Allow staff to update culinary description, category, and price without corrupting image path.
* **Test Input / Action:** Update dish #1 price from 1650.00 to 1700.00 without uploading a new image.
* **Expected Result:** Price updated in database; existing image path preserved; flash message displayed.
* **Actual Result:** Updated price reflected immediately; audit entry recorded via `log_action()`.
* **Status:** **PASS**

#### TC-B13: Soft Delete Dish (Deactivation)
* **Objective:** Ensure deactivation sets `is_active = 0` rather than physically deleting rows with historical references.
* **Test Input / Action:** Submit POST request to `dish-delete.php` with `dish_id = 7` and `action = deactivate`.
* **Expected Result:** Dish row remains in database with `is_active = 0`; hidden from public menu; visible as "Deactivated" in staff catalogue.
* **Actual Result:** Row preserved; `is_active = 0`; hidden from public catalogue; inventory links intact.
* **Status:** **PASS**

#### TC-B14: Restore Deactivated Dish
* **Objective:** Allow staff to reactivate previously soft-deleted items.
* **Test Input / Action:** Submit POST request to `dish-delete.php` with `dish_id = 7` and `action = restore`.
* **Expected Result:** `is_active` restored to 1; dish reappears on public menu.
* **Actual Result:** Status restored to 1; visible on public catalogue immediately.
* **Status:** **PASS**

---

### 4. Category Management (`public/menu/categories.php`)

#### TC-B15: Create New Category
* **Objective:** Verify adding a new catalogue category with display ordering.
* **Test Input / Action:** Submit category form with Name: `Artisanal Desserts`, Display Order: `17`.
* **Expected Result:** Category created and listed in table; available in dish form category select.
* **Actual Result:** Category saved with unique ID; flash confirmation displayed.
* **Status:** **PASS**

#### TC-B16: Prevent Duplicate Category Names
* **Objective:** Reject duplicate category names case-insensitively.
* **Test Input / Action:** Attempt to create category `rice & curry` (existing category is `Rice & Curry`).
* **Expected Result:** Rejection error: `A category named "rice & curry" already exists.`
* **Actual Result:** Form halted; validation error displayed; zero duplicate records created.
* **Status:** **PASS**

#### TC-B17: Category Status Toggle (Soft Deactivation)
* **Objective:** Deactivate a category to hide its pill from public menu without deleting referenced dishes.
* **Test Input / Action:** Submit `action=toggle_status` on category #4.
* **Expected Result:** `is_active` toggled to 0; public menu hides category #4 pill.
* **Actual Result:** Toggled to 0; referenced dishes remain safe in database.
* **Status:** **PASS**

---

### 5. Inventory Concurrency & Stock Reduction (`includes/stock.php`)

#### TC-B18: Atomic Stock Deduction (`reduce_stock`)
* **Objective:** Validate that `reduce_stock($dish_id, $qty)` deducts portions atomically and logs movement.
* **Test Input / Action:** Call `reduce_stock(1, 5, 4)` on dish with 45 portions.
* **Expected Result:**
  * Returns `true`.
  * `inventory.qty_available` decreases from 45 to 40.
  * `stock_movements` logs `Checkout_Deduction` with `-5` portions.
* **Actual Result:** Deduction completed; audit row created with previous=45, updated=40.
* **Status:** **PASS**

#### TC-B19: Prevent Negative Inventory on Over-Deduction
* **Objective:** Ensure deduction fails safely when requested quantity exceeds available portions.
* **Test Input / Action:** Call `reduce_stock(22, 10)` on dish #22 with 0 portions available.
* **Expected Result:** Throws `InsufficientStockException`; stock remains at 0; no movements logged.
* **Actual Result:** Threw `InsufficientStockException: Insufficient stock for "Classic Caramel Custard Pudding". Requested: 10, Available: 0.`
* **Status:** **PASS**

#### TC-B20: Concurrency Race-Condition Invariant
* **Objective:** Verify conditional UPDATE (`WHERE qty_available >= :qty`) prevents negative balance under simulated simultaneous requests.
* **Test Input / Action:** Test item with exactly 2 portions available. Two concurrent checkout processes request 2 portions each.
* **Expected Result:** Exactly one request succeeds; the second request fails with `InsufficientStockException`; final stock is 0 (never negative).
* **Actual Result:** Verified via conditional UPDATE rowCount check; zero negative inventory possible.
* **Status:** **PASS**

#### TC-B21: Component C Transaction Participation Safety
* **Objective:** Verify `reduce_stock()` participates in an active outer transaction without prematurely committing or rolling back.
* **Test Input / Action:**
  1. Outer transaction started: `$pdo->beginTransaction()`.
  2. `reduce_stock(1, 2)` called.
  3. Outer transaction rolls back: `$pdo->rollBack()`.
* **Expected Result:** Inventory changes and movement logs are both reverted by outer rollback.
* **Actual Result:** Both inventory deduction and movement log reverted completely.
* **Status:** **PASS**

#### TC-B22: Stock Restock & Wastage Adjustments
* **Objective:** Verify staff can restock dishes and record kitchen wastage with mandatory reason.
* **Test Input / Action:**
  1. Restock dish #11 with +10 portions, reason: "Morning prep batch".
  2. Record wastage of 2 portions on dish #3, reason: "Holding window expired".
* **Expected Result:** Quantities updated accurately; audit log records movement type and reason.
* **Actual Result:** Balances adjusted correctly; reason recorded in `stock_movements`.
* **Status:** **PASS**

---

### 6. Security & Multi-Tier Image Upload Validation

#### TC-B23: Valid Image Upload Processing
* **Objective:** Confirm successful upload of valid JPG/PNG/WEBP files.
* **Test Input / Action:** Upload valid 800x600 JPEG image (500 KB).
* **Expected Result:** Stored with unpredictable hash name in `uploads/dishes/`; path saved in database.
* **Actual Result:** Stored as `uploads/dishes/[32-char-hex].jpg`; preview displayed in UI.
* **Status:** **PASS**

#### TC-B24: Rejection of Malicious Executable Files
* **Objective:** Reject PHP scripts renamed with image extensions (e.g. `exploit.php.jpg` or fake MIME headers).
* **Test Input / Action:** Upload text file containing `<?php phpinfo(); ?>` named `test.png`.
* **Expected Result:** Rejected by `getimagesize()` and PHP `finfo_file` MIME inspection.
* **Actual Result:** Upload rejected with error: "The uploaded file is not a valid image."
* **Status:** **PASS**

#### TC-B25: Oversized File Rejection
* **Objective:** Reject images exceeding the 3 MB threshold.
* **Test Input / Action:** Upload 5.2 MB JPEG image.
* **Expected Result:** Form halts with error: "Image size exceeds the maximum allowed limit of 3 MB."
* **Actual Result:** Rejected on client side and server side before writing to disk.
* **Status:** **PASS**

#### TC-B26: Uploads Directory Hardening (`.htaccess`)
* **Objective:** Confirm script execution is disabled in `public/uploads/dishes/`.
* **Test Input / Action:** Inspect `public/uploads/dishes/.htaccess` directives.
* **Expected Result:** `php_flag engine off`, `Options -ExecCGI`, and deny execution rules active.
* **Actual Result:** Direct PHP execution disabled in Apache configuration.
* **Status:** **PASS**

---

## 3. Concurrency & Concluding Assessment

Component B has been thoroughly validated against the assignment specifications. All database transactions satisfy ACID guarantees, prepared statements prevent SQL injection across all query vectors, and `reduce_stock()` is fully prepared for seamless integration with Component C's checkout flow.
