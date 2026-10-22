<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component C: Administrator Sales & Performance Reports
 *
 * Provides analytical reporting, date range filtering, gross/net sales calculations,
 * status distributions, top-selling dishes analysis, and fulfillment method breakdowns.
 *
 * NOTE: This file is assigned to and owned by Component C within the admin console.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/component_c.php';

// RBAC: Restricted strictly to Administrator accounts
require_role('admin');

// 1. Date Range Filter Validation
$today = date('Y-m-d');
$firstOfMonth = date('Y-m-01');

$startDate = trim((string)($_GET['start_date'] ?? '2026-10-01'));
$endDate = trim((string)($_GET['end_date'] ?? $today));

// Validate date formats
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = '2026-10-01';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $today;
}

if ($startDate > $endDate) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// 2. Fetch Analytical Reports via Component C Query Helpers
$kpis = get_sales_kpi_summary($startDate, $endDate);
$statusBreakdown = get_orders_by_status_report($startDate, $endDate);
$topDishes = get_top_selling_dishes_report($startDate, $endDate, 8);
$fulfillmentBreakdown = get_sales_by_fulfillment_report($startDate, $endDate);

$pageTitle = 'Sales & Revenue Reports';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-c.css') . '">';

require_once dirname(__DIR__, 2) . '/includes/header.php';

// Include Admin sub-navigation bar if present
$adminNavPath = dirname(__DIR__, 2) . '/includes/admin_nav.php';
if (file_exists($adminNavPath)) {
    require $adminNavPath;
}
?>

<div class="c-page-header">
    <div>
        <h1 class="c-page-title">
            <span>📊</span> Sales & Operational Analytics
        </h1>
        <p class="c-page-subtitle">
            Component C Business Reporting: Comprehensive sales volume, dish popularity, and kitchen throughput.
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="btn btn-outline btn-sm" onclick="window.print();">
            🖨️ Export / Print Report
        </button>
    </div>
</div>

<!-- Date Range Filter Form -->
<div class="c-card" style="padding: 1.25rem 1.5rem; margin-bottom: 2rem;">
    <form action="<?= base_url('admin/reports.php') ?>" method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div>
            <label class="c-form-label" for="start_date">From Date</label>
            <input type="date" name="start_date" id="start_date" class="c-form-input" value="<?= e($startDate) ?>" style="padding: 0.5rem 0.75rem;">
        </div>

        <div>
            <label class="c-form-label" for="end_date">To Date</label>
            <input type="date" name="end_date" id="end_date" class="c-form-input" value="<?= e($endDate) ?>" style="padding: 0.5rem 0.75rem;">
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1.25rem;">
                Filter Analytics
            </button>
            <a href="<?= base_url('admin/reports.php?start_date=2026-10-01&end_date=' . $today) ?>" class="btn btn-outline" style="padding: 0.55rem 1rem;">
                Reset
            </a>
        </div>

        <div style="margin-left: auto; font-size: 0.85rem; color: var(--c-muted); align-self: center;">
            Showing performance from <strong><?= date('M j, Y', strtotime($startDate)) ?></strong> to <strong><?= date('M j, Y', strtotime($endDate)) ?></strong>
        </div>
    </form>
</div>

<!-- KPI Summary Cards -->
<div class="c-kpi-grid">
    <div class="c-kpi-card">
        <div class="c-kpi-icon" style="background: #DCFCE7; color: #166534;">
            💰
        </div>
        <div>
            <div class="c-kpi-val"><?= format_lkr($kpis['total_sales_revenue']) ?></div>
            <div class="c-kpi-label">Completed Sales Revenue</div>
        </div>
    </div>

    <div class="c-kpi-card">
        <div class="c-kpi-icon" style="background: #E0E7FF; color: #3730A3;">
            📦
        </div>
        <div>
            <div class="c-kpi-val"><?= $kpis['completed_orders'] ?> <span style="font-size: 1rem; color: var(--c-muted); font-weight: 400;">/ <?= $kpis['total_orders'] ?></span></div>
            <div class="c-kpi-label">Completed Orders</div>
        </div>
    </div>

    <div class="c-kpi-card">
        <div class="c-kpi-icon" style="background: #FEF3C7; color: #92400E;">
            📈
        </div>
        <div>
            <div class="c-kpi-val"><?= format_lkr($kpis['average_order_value']) ?></div>
            <div class="c-kpi-label">Avg Completed Order</div>
        </div>
    </div>

    <div class="c-kpi-card">
        <div class="c-kpi-icon" style="background: #FEE2E2; color: #991B1B;">
            🎟️
        </div>
        <div>
            <div class="c-kpi-val"><?= format_lkr($kpis['total_discount_given']) ?></div>
            <div class="c-kpi-label">Promotional Discounts</div>
        </div>
    </div>
</div>

<div class="c-two-column">
    <!-- Left Column: Top-Selling Dishes -->
    <div>
        <div class="c-card">
            <div class="c-card-header">
                <h3 class="c-card-title">🍲 Top-Selling Menu Dishes</h3>
                <span style="font-size: 0.8rem; color: var(--c-muted);">Completed Orders Only</span>
            </div>

            <p style="font-size: 0.85rem; color: var(--c-muted); margin: 0 0 1rem 0;">
                Ranked by volume sold with exact purchase-price revenue aggregation.
            </p>

            <?php if (empty($topDishes)): ?>
                <div style="text-align: center; padding: 2rem; color: var(--c-muted);">
                    No completed order records found within selected date range.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="c-cart-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">Rank</th>
                                <th>Dish Name</th>
                                <th style="text-align: center; width: 110px;">Portions Sold</th>
                                <th style="text-align: right; width: 130px;">Revenue Generated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($topDishes as $dish): ?>
                                <tr>
                                    <td style="font-weight: 700; color: <?= $rank === 1 ? '#D97706' : 'var(--c-muted)' ?>;">
                                        #<?= $rank++ ?>
                                    </td>
                                    <td>
                                        <strong style="color: var(--c-charcoal);"><?= e($dish['dish_name']) ?></strong>
                                    </td>
                                    <td style="text-align: center; font-weight: 600; color: var(--c-charcoal);">
                                        <?= (int)$dish['total_quantity'] ?> portions
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: var(--c-primary-dark);">
                                        <?= format_lkr($dish['total_revenue']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Fulfillment Method Breakdown -->
        <div class="c-card">
            <h3 class="c-card-title" style="margin-bottom: 1rem;">Fulfillment Channel Breakdown</h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <?php foreach ($fulfillmentBreakdown as $fb): ?>
                    <div style="background: #F8FAF8; border: 1px solid var(--c-border); border-radius: var(--c-radius-sm); padding: 1.25rem;">
                        <div style="font-size: 1.35rem; margin-bottom: 0.35rem;">
                            <?= $fb['fulfillment_method'] === 'delivery' ? '🛵 Doorstep Delivery' : '🛍️ Counter Pickup' ?>
                        </div>
                        <div style="font-size: 1.4rem; font-weight: 700; color: var(--c-charcoal);">
                            <?= (int)$fb['order_count'] ?> <span style="font-size: 0.85rem; color: var(--c-muted); font-weight: normal;">orders</span>
                        </div>
                        <div style="font-size: 0.95rem; font-weight: 600; color: var(--c-primary); margin-top: 0.25rem;">
                            Revenue: <?= format_lkr($fb['total_revenue']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Orders By Status Breakdown -->
    <div>
        <div class="c-card">
            <div class="c-card-header">
                <h3 class="c-card-title">Order Status Distribution</h3>
                <span style="font-size: 0.8rem; color: var(--c-muted);">Total: <?= $kpis['total_orders'] ?></span>
            </div>

            <p style="font-size: 0.85rem; color: var(--c-muted); margin: 0 0 1.25rem 0;">
                Calculated lifecycle throughput across selected timeframe.
            </p>

            <?php if (empty($statusBreakdown)): ?>
                <div style="text-align: center; padding: 2rem; color: var(--c-muted);">
                    No orders in date range.
                </div>
            <?php else: ?>
                <?php foreach ($statusBreakdown as $sb): ?>
                    <?php
                    $barColor = match ($sb['status']) {
                        'Completed'        => '#16A34A',
                        'Cancelled'        => '#DC2626',
                        'Placed'           => '#F59E0B',
                        'Preparing'        => '#6366F1',
                        'Ready'            => '#06B6D4',
                        'Out for Delivery' => '#8B5CF6',
                        'Collected'        => '#10B981',
                        default            => '#6B7280',
                    };
                    ?>
                    <div>
                        <div class="c-bar-container">
                            <span class="c-bar-label">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= $barColor ?>; margin-right: 0.35rem;"></span>
                                <?= e($sb['status']) ?>
                            </span>
                            <div class="c-bar-track">
                                <div class="c-bar-fill" style="width: <?= $sb['percentage'] ?>%; background: <?= $barColor ?>;"></div>
                            </div>
                            <span class="c-bar-meta">
                                <?= $sb['count'] ?> (<?= $sb['percentage'] ?>%)
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--c-border); font-size: 0.8rem; color: var(--c-muted);">
                <strong>Accounting Rule:</strong> Only orders reaching the terminal <code>Completed</code> state are recognized in completed revenue. Cancelled orders are excluded from total sales and had their stock portions automatically returned to inventory.
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
