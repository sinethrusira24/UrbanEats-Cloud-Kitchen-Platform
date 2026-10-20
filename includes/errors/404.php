<?php
/** 404.php – Page or Resource Not Found */
$pageTitle = 'Page Not Found';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../auth.php';
http_response_code(404);
include __DIR__ . '/../header.php';
?>
<div class="container">
  <div class="error-page">
    <div class="error-code" aria-hidden="true">404</div>
    <h1>Dish or Page Not Found</h1>
    <p style="max-width:440px;margin:var(--sp-4) auto var(--sp-8);color:var(--clr-text-muted)">
      The item or page you are looking for doesn't exist, has been removed, or is currently unavailable.
    </p>
    <div class="d-flex gap-4" style="justify-content:center;flex-wrap:wrap">
      <a href="<?= app_url('/menu/index.php') ?>" class="btn btn-primary">🍽️ Explore Menu</a>
      <a href="<?= app_url('/index.php') ?>" class="btn btn-secondary">Home</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
