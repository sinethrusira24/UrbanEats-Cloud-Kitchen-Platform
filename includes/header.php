<?php
/**
 * header.php – Shared site header & navigation.
 *
 * Expects $pageTitle to be defined by the including page.
 * Navigation items shown depend on $_SESSION['role'].
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

$user      = current_user();
$role      = $user['role'] ?? '';
$pageTitle = $pageTitle ?? 'UrbanEats';

// Build nav items based on role
$navItems = [];

if (!$user) {
    $navItems[] = ['label' => 'Menu',     'href' => app_url('/index.php')];
    $navItems[] = ['label' => 'Sign In',  'href' => app_url('/auth/login.php')];
    $navItems[] = ['label' => 'Register', 'href' => app_url('/auth/register.php'), 'highlight' => true];
} elseif ($role === 'Customer') {
    $navItems[] = ['label' => 'Menu',     'href' => app_url('/index.php')];
    $navItems[] = ['label' => '🛒 Cart',  'href' => app_url('/orders/cart.php')];
    $navItems[] = ['label' => 'My Orders','href' => app_url('/orders/history.php')];
} elseif ($role === 'Kitchen Staff') {
    $navItems[] = ['label' => 'Dishes',   'href' => app_url('/menu/manage.php')];
    $navItems[] = ['label' => 'Stock',    'href' => app_url('/menu/stock.php')];
    $navItems[] = ['label' => 'Orders',   'href' => app_url('/orders/kitchen.php')];
} elseif ($role === 'Administrator') {
    $navItems[] = ['label' => 'Dashboard','href' => app_url('/admin/dashboard.php')];
    $navItems[] = ['label' => 'Users',    'href' => app_url('/admin/users.php')];
    $navItems[] = ['label' => 'Audit Log','href' => app_url('/admin/audit.php')];
    $navItems[] = ['label' => 'Reports',  'href' => app_url('/admin/reports.php')];
}

$currentUri = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="UrbanEats Cloud Kitchen – Order fresh food for delivery or pickup across Sri Lanka.">
  <title><?= e($pageTitle) ?> | UrbanEats</title>

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🍽️</text></svg>">

  <!-- Shared stylesheet -->
  <link rel="stylesheet" href="<?= app_url('/css/style.css') ?>">

  <!-- Page-specific head content hook -->
  <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>

<header class="site-header" role="banner">
  <div class="container">
    <div class="header-inner">

      <!-- Logo -->
      <a href="<?= app_url('/index.php') ?>" class="site-logo" aria-label="UrbanEats – Home">
        🍽️ Urban<span>Eats</span>
      </a>

      <!-- Main nav -->
      <nav class="site-nav" id="main-nav" aria-label="Main navigation">
        <?php foreach ($navItems as $item): ?>
          <?php
            $isActive = str_contains($currentUri, parse_url($item['href'], PHP_URL_PATH));
            $classes  = $isActive ? 'active' : '';
            if (!empty($item['highlight'])) $classes .= ' btn btn-primary btn-sm';
          ?>
          <a href="<?= e($item['href']) ?>"
             class="<?= trim($classes) ?>"
             <?= $isActive ? 'aria-current="page"' : '' ?>>
            <?= e($item['label']) ?>
          </a>
        <?php endforeach; ?>

        <?php if ($user): ?>
          <!-- User dropdown -->
          <div class="dropdown" id="user-dropdown">
            <button class="d-flex align-center gap-2"
                    style="background:none;border:none;cursor:pointer;color:var(--clr-text);font-family:var(--font-body);font-size:1rem;"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-controls="user-menu"
                    id="user-menu-btn">
              <div class="avatar" aria-hidden="true"><?= e(mb_substr($user['full_name'], 0, 1)) ?></div>
              <span style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($user['full_name']) ?></span>
              <span aria-hidden="true">▾</span>
            </button>
            <div class="dropdown-menu" id="user-menu" role="menu">
              <a href="<?= app_url('/auth/profile.php') ?>" role="menuitem">
                <span>👤</span> My Profile
              </a>
              <a href="<?= app_url('/auth/change-password.php') ?>" role="menuitem">
                <span>🔐</span> Change Password
              </a>
              <div class="dropdown-divider"></div>
              <form method="POST" action="<?= app_url('/auth/logout.php') ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" role="menuitem">
                  <span>🚪</span> Sign Out
                </button>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </nav>

      <!-- Hamburger -->
      <button class="nav-hamburger" id="nav-hamburger"
              aria-label="Toggle navigation" aria-expanded="false" aria-controls="main-nav">
        ☰
      </button>

    </div>
  </div>
</header>

<!-- Flash messages -->
<?php
$flashTypes = ['success', 'error', 'warning', 'info'];
foreach ($flashTypes as $type):
  $msgs = get_flash($type);
  if (!empty($msgs)):
?>
<div class="container" role="alert" aria-live="polite">
  <?php foreach ($msgs as $msg): ?>
    <div class="alert alert-<?= e($type) ?>" style="margin-top:var(--sp-4)">
      <?= e($msg) ?>
    </div>
  <?php endforeach; ?>
</div>
<?php
  endif;
endforeach;
?>

<!-- Toast container -->
<div id="toast-container" aria-live="polite" aria-atomic="false"></div>

<main id="main-content">
