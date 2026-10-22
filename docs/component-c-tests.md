# UrbanEats Cloud Kitchen Platform
## Component C: Test Plan & Execution Report
### Ordering, Payment and Tracking Subsystem

**Module:** IT3113 Advanced Web Technologies  
**Component Owner:** Student 3 (Component C)  
**Execution Environment:** Windows 10/11, PHP 8.x, MariaDB / MySQL 10.4+, Apache (XAMPP)  
**Total Tests Executed:** 45  
**Passed:** 45  
**Failed:** 0  
**Status:** 100% Pass Rate  

---

## 1. Summary of Test Coverage

| Category | Test Case IDs | Scope | Status |
|---|---|---|---|
| **Shopping Cart Management** | TC-C-01 to TC-C-09 | Adding dishes, quantity updates, removal, clearing, over-stock validation, price integrity | 9 / 9 Passed |
| **Checkout & Transaction Atomicity** | TC-C-10 to TC-C-23 | Delivery/pickup fulfillment, phone validation, atomic PDO transactions, stock reduction, idempotency | 14 / 14 Passed |
| **Order Confirmation & Invoicing** | TC-C-24 to TC-C-28 | Post-checkout receipt, printable invoice (@media print), customer ownership isolation, history | 5 / 5 Passed |
| **Kitchen Operations & State Machine** | TC-C-29 to TC-C-35 | Kitchen queue, state machine transitions, illegal jump blocking, cancellation stock restoration | 7 / 7 Passed |
| **Administrator Sales Reporting** | TC-C-36 to TC-C-40 | Date filtering, completed revenue calculations, top-selling dishes, fulfillment breakdown | 5 / 5 Passed |
| **Security & Authorization Controls** | TC-C-41 to TC-C-45 | SQL injection defense, XSS escaping, CSRF enforcement, customer privacy, RBAC route guards | 5 / 5 Passed |

---

## 2. Detailed Test Cases and Execution Evidence

### 2.1 Shopping Cart Testing

#### TC-C-01: Add Valid Dish to Cart
- **Test Objective:** Verify that a customer can add an active in-stock dish to their session cart.
- **Preconditions:** Dish #1 ("Urban Royal Chicken Biryani") is active and in stock.
- **Input / Steps:**
  1. POST to `/orders/cart.php` with `action=add`, `dish_id=1`, `quantity=2`.
  2. Inspect session cart storage and response redirect.
- **Expected Result:** `$_SESSION['cart'][1]` is set to `2`. Flash success notification displayed.
- **Actual Result:** Dish added with quantity 2. Flash message: `"Added 2 × 'Urban Royal Chicken Biryani' to your cart."`
- **Status:** **PASS**
- **Evidence:** Verified by automated suite; `cart_count()` returned 2.

---

#### TC-C-02: Add Same Dish Incrementally
- **Test Objective:** Ensure adding an existing item again increases its quantity rather than overwriting it.
- **Preconditions:** Dish #1 is already in cart with quantity 2.
- **Input / Steps:**
  1. POST to `/orders/cart.php` with `action=add`, `dish_id=1`, `quantity=1`.
- **Expected Result:** Quantity in cart increases from 2 to 3.
- **Actual Result:** `$_SESSION['cart'][1]` updated to 3 portions.
- **Status:** **PASS**
- **Evidence:** Session inspection confirmed `cart_count() === 3`.

---

#### TC-C-03: Update Item Quantity in Cart
- **Test Objective:** Verify updating item quantity recalculates line total and cart count.
- **Preconditions:** Dish #1 is in cart.
- **Input / Steps:**
  1. POST to `/orders/cart.php` with `action=update`, `dish_id=1`, `quantity=4`.
- **Expected Result:** Item quantity updated to 4; line total recalculated using authoritative database price.
- **Actual Result:** Quantity set to 4; subtotal reflected 4 × LKR 1,650.00 = LKR 6,600.00.
- **Status:** **PASS**

---

#### TC-C-04: Remove Single Item from Cart
- **Test Objective:** Verify customer can remove an individual item from the cart.
- **Preconditions:** Cart contains dishes #1 and #2.
- **Input / Steps:**
  1. POST to `/orders/cart.php` with `action=remove`, `dish_id=1`.
- **Expected Result:** Dish #1 removed; Dish #2 remains in cart.
- **Actual Result:** `dish_id=1` removed from session; flash info message displayed.
- **Status:** **PASS**

---

#### TC-C-05: Clear Entire Cart
- **Test Objective:** Verify customer can clear all items from the cart safely.
- **Preconditions:** Cart contains multiple dishes.
- **Input / Steps:**
  1. POST to `/orders/cart.php` with `action=clear`.
- **Expected Result:** Cart emptied completely; empty cart view rendered.
- **Actual Result:** `$_SESSION['cart']` set to empty array; empty state message rendered.
- **Status:** **PASS**

---

#### TC-C-06: Reject Adding Inactive or Non-Existent Dish
- **Test Objective:** Ensure attempts to add an invalid dish ID are rejected.
- **Preconditions:** Dish #99999 does not exist.
- **Input / Steps:**
  1. POST `action=add`, `dish_id=99999`, `quantity=1`.
- **Expected Result:** Rejected with error message; cart unchanged.
- **Actual Result:** Returned `success=false` with message: `"This dish is currently unavailable on the menu."`
- **Status:** **PASS**

---

#### TC-C-07: Reject Negative or Zero Quantity
- **Test Objective:** Verify non-positive quantities are rejected during cart addition.
- **Input / Steps:**
  1. POST `action=add`, `dish_id=1`, `quantity=0`.
  2. POST `action=add`, `dish_id=1`, `quantity=-5`.
- **Expected Result:** Both submissions rejected with validation error.
- **Actual Result:** Rejected: `"Quantity must be between 1 and 50 portions."`
- **Status:** **PASS**

---

#### TC-C-08: Reject Adding Quantities Exceeding In-Stock Portions
- **Test Objective:** Prevent customer from adding more portions than currently available in inventory.
- **Preconditions:** Dish has 10 portions in stock.
- **Input / Steps:**
  1. Attempt to add quantity = 25.
- **Expected Result:** Request rejected with clear explanation of available portions.
- **Actual Result:** Blocked: `"Cannot add 25 portions... Only 10 portions available in total."`
- **Status:** **PASS**

---

#### TC-C-09: Prevent Client-Side Price Tampering
- **Test Objective:** Verify cart totals never trust browser-submitted prices.
- **Preconditions:** Dish #1 database price is LKR 1,650.00.
- **Input / Steps:**
  1. Customer adds dish #1 to cart.
  2. Tamper with request payload to send `price=10.00`.
- **Expected Result:** Server ignores client-supplied price and loads authoritative price from MySQL.
- **Actual Result:** Line total evaluated at LKR 1,650.00; client price completely ignored.
- **Status:** **PASS**

---

### 2.2 Checkout & Transaction Atomicity Testing

#### TC-C-10: Successful Doorstep Delivery Checkout
- **Test Objective:** Place a complete delivery order with address, phone number, and simulated payment.
- **Preconditions:** Customer logged in (user_id=8); cart has valid dishes.
- **Input / Steps:**
  1. Fulfillment: `delivery`, Address: `"45/2 Galle Road, Colombo 03"`, Phone: `"0771234567"`, Payment: `"Cash on Delivery"`.
  2. Submit checkout.
- **Expected Result:** Single database transaction commits order, order_items, payments, status history, and reduces stock.
- **Actual Result:** Order created with status `Placed`, payment `Pending`, stock deducted atomically.
- **Status:** **PASS**
- **Evidence:** Order generated and verified in database table `orders`.

---

#### TC-C-11: Successful Kitchen Counter Pickup Checkout
- **Test Objective:** Place a pickup order without requiring a delivery address.
- **Preconditions:** Customer logged in.
- **Input / Steps:**
  1. Fulfillment: `pickup`, Address: `NULL`, Phone: `"0719876543"`, Payment: `"Card"`.
  2. Submit checkout.
- **Expected Result:** Order created with `fulfillment_method = 'pickup'`, delivery_address = NULL, payment status = `Paid`.
- **Actual Result:** Order placed successfully; pickup hub instructions displayed.
- **Status:** **PASS**

---

#### TC-C-12: Missing Required Delivery Address Validation
- **Test Objective:** Ensure delivery orders cannot be submitted without a valid delivery street address.
- **Input / Steps:**
  1. Fulfillment: `delivery`, Delivery address: `""` (empty).
- **Expected Result:** Submission blocked with inline error: `"Please enter a complete delivery address."`
- **Actual Result:** Validation caught missing address; no database writes executed.
- **Status:** **PASS**

---

#### TC-C-13: Invalid Contact Telephone Number Validation
- **Test Objective:** Reject invalid or malformed contact numbers.
- **Input / Steps:**
  1. Submit phone: `"abc123"`.
- **Expected Result:** Blocked with error: `"Please provide a valid contact phone number."`
- **Actual Result:** Regex validation failed; error displayed to customer.
- **Status:** **PASS**

---

#### TC-C-14: Invalid Payment Method Rejection
- **Test Objective:** Prevent unauthorized payment method values.
- **Input / Steps:**
  1. POST with `payment_method = 'Cryptocurrency'`.
- **Expected Result:** Blocked: `"Please select an accepted payment method."`
- **Actual Result:** Request rejected; database integrity maintained.
- **Status:** **PASS**

---

#### TC-C-15: Out-of-Stock Handling at Checkout
- **Test Objective:** Reject checkout if an item was purchased by another user in the interim.
- **Preconditions:** Dish stock dropped to 0 before customer clicks place order.
- **Input / Steps:**
  1. Submit checkout with 2 portions of depleted dish.
- **Expected Result:** Checkout fails safely; entire transaction rolls back; error message displayed.
- **Actual Result:** `InsufficientStockException` caught; transaction rolled back; friendly message shown.
- **Status:** **PASS**

---

#### TC-C-16: Dish Price Update Between Cart and Checkout
- **Test Objective:** Verify checkout uses the exact live database price at checkout time.
- **Input / Steps:**
  1. Add dish to cart at LKR 1,200.00.
  2. Update dish price in database to LKR 1,350.00.
  3. Load checkout page.
- **Expected Result:** Checkout displays current live price (LKR 1,350.00) and commits snapshot price.
- **Actual Result:** `order_items.unit_price` recorded as LKR 1,350.00.
- **Status:** **PASS**

---

#### TC-C-17: Valid Promotional Coupon Application (Percentage)
- **Test Objective:** Apply percentage discount coupon `WELCOME10` (10% off, min spend LKR 1,500.00).
- **Preconditions:** Cart subtotal = LKR 3,000.00.
- **Input / Steps:**
  1. Enter coupon `WELCOME10`.
- **Expected Result:** Discount = LKR 300.00; Final total = LKR 2,700.00.
- **Actual Result:** Applied accurately; discount recorded on orders table.
- **Status:** **PASS**

---

#### TC-C-18: Valid Promotional Coupon Application (Fixed Amount)
- **Test Objective:** Apply fixed discount coupon `SAVE500` (LKR 500.00 off, min spend LKR 2,500.00).
- **Preconditions:** Cart subtotal = LKR 3,000.00.
- **Expected Result:** Discount = LKR 500.00; Final total = LKR 2,500.00.
- **Actual Result:** Discount deducted accurately; order total persisted as LKR 2,500.00.
- **Status:** **PASS**

---

#### TC-C-19: Expired Coupon Code Rejection
- **Test Objective:** Reject expired promotional coupons.
- **Preconditions:** Coupon `EXPIRED2025` has expiry date in 2025.
- **Expected Result:** Rejected: `"This coupon has expired."`
- **Actual Result:** Blocked; no discount applied.
- **Status:** **PASS**

---

#### TC-C-20: Coupon Usage Limit Rejection
- **Test Objective:** Reject coupon that has exceeded its allowed redemption limit.
- **Preconditions:** Coupon `MAXEDOUT` has `usage_count = 5` and `usage_limit = 5`.
- **Expected Result:** Blocked: `"This coupon has reached its maximum redemption limit."`
- **Actual Result:** Validation caught usage limit; discount rejected.
- **Status:** **PASS**

---

#### TC-C-21: Coupon Minimum Order Threshold Enforcement
- **Test Objective:** Reject coupon when subtotal does not meet minimum order value.
- **Preconditions:** Coupon requires LKR 1,500.00; cart subtotal is LKR 950.00.
- **Expected Result:** Error message stating minimum order requirement.
- **Actual Result:** Blocked: `"Minimum order value of LKR 1,500.00 required..."`
- **Status:** **PASS**

---

#### TC-C-22: Double Checkout Idempotency Defense
- **Test Objective:** Prevent duplicate orders caused by double-clicking the checkout button.
- **Input / Steps:**
  1. Submit checkout request with token `IDEMP-TEST-001`.
  2. Resubmit identical request with same token within 500ms.
- **Expected Result:** Second request detects existing order by unique idempotency token; returns existing order without creating second order or deducting stock twice.
- **Actual Result:** Exactly 1 order created; stock deducted only once.
- **Status:** **PASS**
- **Evidence:** Verified by automated suite; `inventory.qty_available` unchanged on duplicate submission.

---

#### TC-C-23: Complete Transaction Rollback on Stock Deduction Failure
- **Test Objective:** Confirm atomic rollback occurs if stock deduction fails mid-transaction.
- **Preconditions:** Dish inventory artificially locked or insufficient.
- **Expected Result:** No row created in `orders`, `order_items`, `payments`, or `order_status_history`.
- **Actual Result:** `rollBack()` triggered; zero orphaned rows left in database.
- **Status:** **PASS**
- **Evidence:** `COUNT(*)` query confirmed zero rows inserted for failed checkout.

---

### 2.3 Order Confirmation & Invoicing Testing

#### TC-C-24: View Order Confirmation After Placement
- **Test Objective:** Verify customer is presented with a complete summary immediately upon placing order.
- **Preconditions:** Order #1 placed.
- **Input / Steps:**
  1. Navigate to `/orders/confirmation.php?id=1`.
- **Expected Result:** Displays order reference, date, fulfillment method, line items, and simulated payment reference.
- **Actual Result:** All fields rendered correctly with green success styling.
- **Status:** **PASS**

---

#### TC-C-25: Printable Tax Invoice Media Styling (@media print)
- **Test Objective:** Verify printable invoice renders clean black-and-white tax invoice layout.
- **Input / Steps:**
  1. Trigger print preview on `confirmation.php`.
- **Expected Result:** Navigation bars, footer, and interactive buttons hidden; tax invoice header with UrbanEats branding displayed.
- **Actual Result:** Print stylesheet formatted invoice without UI chrome.
- **Status:** **PASS**

---

#### TC-C-26: View Customer Order History List
- **Test Objective:** Authenticated customer views their past orders.
- **Preconditions:** Customer `sachini_k` logged in (user_id=8).
- **Input / Steps:**
  1. Navigate to `/orders/history.php`.
- **Expected Result:** Displays list of orders placed by user #8 only.
- **Actual Result:** Rendered order rows with dates, fulfillment badges, totals, and links to tracking.
- **Status:** **PASS**

---

#### TC-C-27: Cross-Customer Order Isolation Defense
- **Test Objective:** Prevent Customer A from viewing Customer B's order receipt by altering URL ID.
- **Preconditions:** Customer `sachini_k` (user #8) logged in; Order #2 belongs to Customer `tharaka_b` (user #9).
- **Input / Steps:**
  1. User #8 attempts to open `/orders/confirmation.php?id=2`.
- **Expected Result:** Access denied (HTTP 403 or redirect with error notification).
- **Actual Result:** Blocked; flash danger notification: `"Order not found or you are not authorized to view this receipt."`
- **Status:** **PASS**
- **Evidence:** HTTP curl test returned redirect with 403 guard.

---

#### TC-C-28: Real-Time Order Tracking Timeline Rendering
- **Test Objective:** Verify tracking timeline displays chronological stage progression and timestamps.
- **Preconditions:** Order #1 with status `Completed`.
- **Input / Steps:**
  1. Open `/orders/track.php?id=1`.
- **Expected Result:** Displays 5 stages (Placed, Preparing, Ready, Out for Delivery, Completed) with green checkmarks and timestamps.
- **Actual Result:** Stepper rendered all completed steps with timestamps from `order_status_history`.
- **Status:** **PASS**

---

### 2.4 Kitchen Operations & State Machine Testing

#### TC-C-29: Kitchen Queue Access by Kitchen Staff
- **Test Objective:** Verify Kitchen Staff can access the operational queue.
- **Preconditions:** User `kasun_kitchen` logged in (`role = 'kitchen_staff'`).
- **Input / Steps:**
  1. Open `/orders/kitchen.php`.
- **Expected Result:** HTTP 200 OK; order cards displayed with dishes checklist and advance action buttons.
- **Actual Result:** Queue rendered with status filter pills and action buttons.
- **Status:** **PASS**

---

#### TC-C-30: Valid Status Advancement (Placed → Preparing)
- **Test Objective:** Kitchen staff moves a new ticket to Preparing.
- **Input / Steps:**
  1. POST `action=advance_status`, `order_id=22`, `new_status=Preparing`.
- **Expected Result:** Status updated to `Preparing`; history row recorded with staff user ID.
- **Actual Result:** Transition succeeded; status badge changed to purple Preparing.
- **Status:** **PASS**

---

#### TC-C-31: Illegal Status Transition Rejection (Placed → Completed)
- **Test Objective:** Ensure server-side state machine rejects illegal stage jumping.
- **Input / Steps:**
  1. Attempt to POST `new_status=Completed` directly on a `Placed` order.
- **Expected Result:** Rejected: `"Invalid status transition from 'Placed' to 'Completed'..."`
- **Actual Result:** Request rejected; order status remained `Placed`.
- **Status:** **PASS**

---

#### TC-C-32: Delivery-Specific Progression Workflow
- **Test Objective:** Verify delivery order progresses through Ready → Out for Delivery → Completed.
- **Preconditions:** Order fulfillment is `delivery`.
- **Expected Result:** Out for Delivery is the only valid stage after Ready.
- **Actual Result:** State machine offered `Out for Delivery` button; transition executed smoothly.
- **Status:** **PASS**

---

#### TC-C-33: Pickup-Specific Progression Workflow
- **Test Objective:** Verify pickup order progresses through Ready → Collected → Completed.
- **Preconditions:** Order fulfillment is `pickup`.
- **Expected Result:** Collected is offered after Ready (not Out for Delivery).
- **Actual Result:** Action button displayed `"Mark as Collected →"`; transition executed.
- **Status:** **PASS**

---

#### TC-C-34: Order Cancellation with Inventory Restoration
- **Test Objective:** Verify cancelling an order automatically returns deducted portions to stock.
- **Preconditions:** Order #17 cancelled from Placed state.
- **Input / Steps:**
  1. Trigger cancellation.
- **Expected Result:** `orders.status` set to `Cancelled`; Component B's `adjust_stock()` invoked with `Restock` type; payment set to `Refunded`.
- **Actual Result:** Inventory quantity increased back to original level; stock movement audit row inserted.
- **Status:** **PASS**
- **Evidence:** Tested and verified via automated suite assertion.

---

#### TC-C-35: Unauthorized Kitchen Queue Access Blocked
- **Test Objective:** Ensure regular customers cannot access the kitchen management interface.
- **Preconditions:** Customer `sachini_k` logged in (`role = 'customer'`).
- **Input / Steps:**
  1. GET `/orders/kitchen.php`.
- **Expected Result:** HTTP 403 Forbidden.
- **Actual Result:** Served HTTP 403 Access Denied page.
- **Status:** **PASS**

---

### 2.5 Administrator Sales Reporting Testing

#### TC-C-36: Sales Date Range Filtering
- **Test Objective:** Verify administrator can filter analytics by start and end dates.
- **Preconditions:** Administrator `nimali_admin` logged in.
- **Input / Steps:**
  1. Navigate to `/admin/reports.php?start_date=2026-10-12&end_date=2026-10-15`.
- **Expected Result:** KPIs, dishes, and orders recalculate strictly for orders created within that 4-day window.
- **Actual Result:** SQL `BETWEEN` filter applied; calculations matched database records.
- **Status:** **PASS**

---

#### TC-C-37: Completed Sales Revenue Calculation Accuracy
- **Test Objective:** Ensure total sales revenue includes only `Completed` orders.
- **Expected Result:** Cancelled, Placed, and Preparing orders are excluded from revenue totals.
- **Actual Result:** SQL query `SUM(CASE WHEN status = 'Completed' THEN total_amount ELSE 0.00 END)` returned exact verified revenue.
- **Status:** **PASS**

---

#### TC-C-38: Top-Selling Dishes Aggregation
- **Test Objective:** Verify ranking of most popular dishes using `GROUP BY oi.dish_name` and purchase price line totals.
- **Expected Result:** Top dish reflects highest cumulative portions sold with matching revenue.
- **Actual Result:** "Urban Royal Chicken Biryani" ranked #1 with accurate total portions and revenue.
- **Status:** **PASS**

---

#### TC-C-39: Orders by Status Throughput Distribution
- **Test Objective:** Verify calculated percentages and counts for all lifecycle statuses.
- **Expected Result:** Percentage sum equals 100%; bar widths rendered dynamically via CSS.
- **Actual Result:** Status distributions rendered cleanly with colored progress tracks.
- **Status:** **PASS**

---

#### TC-C-40: Unauthorized Sales Report Access Blocked
- **Test Objective:** Prevent non-administrators (Customers, Kitchen Staff) from viewing financial reports.
- **Preconditions:** Kitchen staff or customer logged in.
- **Input / Steps:**
  1. GET `/admin/reports.php`.
- **Expected Result:** HTTP 403 Forbidden.
- **Actual Result:** `require_role('admin')` blocked request with HTTP 403.
- **Status:** **PASS**

---

### 2.6 Security & Transaction Controls Testing

#### TC-C-41: SQL Injection Protection on Order Queries
- **Test Objective:** Verify prepared statements prevent SQL injection attacks.
- **Input / Steps:**
  1. Submit coupon code: `' OR 1=1 --`.
  2. Filter orders by status: `Placed' OR '1'='1`.
- **Expected Result:** Input treated strictly as literal string parameter; no SQL syntax deviation.
- **Actual Result:** PDO prepared statement executed safely; returned coupon not found.
- **Status:** **PASS**

---

#### TC-C-42: Cross-Site Scripting (XSS) Sanitization
- **Test Objective:** Ensure special characters in order notes and customer addresses are escaped.
- **Input / Steps:**
  1. Input order notes: `<script>alert("XSS")</script>`.
- **Expected Result:** Stored safely; escaped via `e()` helper as `&lt;script&gt;` upon HTML output.
- **Actual Result:** No JavaScript executed in browser DOM; characters properly sanitized.
- **Status:** **PASS**

---

#### TC-C-43: CSRF Token Verification on State Mutations
- **Test Objective:** Reject state-changing POST requests submitted without a valid CSRF token.
- **Input / Steps:**
  1. Submit cart add or status transition with missing/tampered `csrf_token`.
- **Expected Result:** HTTP 403 Forbidden; request rejected.
- **Actual Result:** `require_csrf()` halted execution with 403 Forbidden; audit event logged.
- **Status:** **PASS**

---

#### TC-C-44: Customer Self-Cancellation Window Guard
- **Test Objective:** Prevent customer from cancelling an order once kitchen preparation has begun.
- **Preconditions:** Order has status `Preparing`.
- **Input / Steps:**
  1. Customer attempts to cancel order.
- **Expected Result:** Blocked: `"This order has already begun kitchen preparation and cannot be cancelled."`
- **Actual Result:** Cancellation button hidden on UI and blocked on backend.
- **Status:** **PASS**

---

#### TC-C-45: Zero Partial State on System Exceptions
- **Test Objective:** Ensure database never holds orphan records if PHP or MySQL crashes mid-checkout.
- **Input / Steps:**
  1. Simulate runtime exception during payment insertion step.
- **Expected Result:** Catch block triggers `$pdo->rollBack()`.
- **Actual Result:** Verified rollback; all associated tables returned to clean pre-checkout state.
- **Status:** **PASS**

---

## 3. Test Execution Sign-Off

All 45 Component C test cases were executed in the local XAMPP runtime environment against the shared `urbaneats_db` database. Every test passed successfully with zero defects, verifying that Component C is production-ready, secure, and fully compliant with the assignment specifications.
