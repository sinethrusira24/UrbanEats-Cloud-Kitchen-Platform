<?php
/**
 * reports.php – Sales reports page (Component C will implement query logic).
 *
 * This page is in the /admin folder (owned by Component A) but the report
 * data queries are written by the Component C student.
 * The guard and layout are provided here.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('Administrator');

$pageTitle = 'Sales Reports';
include __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="dashboard-content">
    <div class="page-header">
      <div>
        <h1>Sales Reports</h1>
        <p>Revenue and order analytics</p>
      </div>
    </div>

    <div class="card text-center" style="padding:var(--sp-16)">
      <div style="font-size:3rem;margin-bottom:var(--sp-4)">📈</div>
      <h2>Coming Soon</h2>
      <p style="margin-top:var(--sp-4);max-width:400px;margin-inline:auto">
        Sales reports are implemented by <strong>Component C</strong> (Ordering & Payment).
        Once integrated, this page will show date-range revenue, order status breakdown and
        top-selling dishes.
      </p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
