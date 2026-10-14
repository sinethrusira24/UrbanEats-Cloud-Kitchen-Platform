<?php
/** 403.php – Forbidden / Access Denied */
$pageTitle = 'Access Denied';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../auth.php';
include __DIR__ . '/../header.php';
?>
<div class="container">
  <div class="error-page">
    <div class="error-code" aria-hidden="true">403</div>
    <h1>Access Denied</h1>
    <p style="max-width:440px;margin:var(--sp-4) auto var(--sp-8)">
      You don't have permission to view this page. If you believe this is a mistake,
      please contact an administrator.
    </p>
    <div class="d-flex gap-4" style="justify-content:center;flex-wrap:wrap">
      <a href="<?= app_url('/index.php') ?>" class="btn btn-primary">Go to Menu</a>
      <?php if (!is_logged_in()): ?>
        <a href="<?= app_url('/auth/login.php') ?>" class="btn btn-secondary">Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
