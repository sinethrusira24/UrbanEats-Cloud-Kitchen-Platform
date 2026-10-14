<?php
/** 500.php – Internal Server Error */
$pageTitle = 'Something went wrong';
// Minimal includes – avoid re-triggering the same error
$base = dirname(__DIR__, 2);
$appUrl = 'http://localhost/UrbanEats-Cloud-Kitchen-Platform/public';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Server Error | UrbanEats</title>
  <link rel="stylesheet" href="<?= $appUrl ?>/css/style.css">
</head>
<body>
  <div class="container" style="min-height:100vh;display:grid;place-items:center;">
    <div class="error-page">
      <div class="error-code" aria-hidden="true">500</div>
      <h1>Something Went Wrong</h1>
      <p style="max-width:440px;margin:var(--sp-4) auto var(--sp-8)">
        We hit an unexpected error. Our team has been notified.
        Please try again in a moment.
      </p>
      <a href="<?= $appUrl ?>/index.php" class="btn btn-primary">Back to Menu</a>
    </div>
  </div>
</body>
</html>
