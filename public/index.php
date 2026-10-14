<?php
/**
 * index.php – Public landing page / menu home.
 * Component B (Menu & Catalogue) will expand this page.
 * Component A provides the shared layout and user context.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Home';
include __DIR__ . '/../includes/header.php';
?>

<section style="
  padding: var(--sp-16) 0;
  text-align: center;
  background:
    radial-gradient(ellipse 70% 50% at 50% -10%, rgba(249,115,22,.18) 0%, transparent 60%),
    var(--clr-bg);
  border-bottom: 1px solid var(--clr-border);
">
  <div class="container">
    <h1 style="font-size:clamp(2.5rem,6vw,4rem);margin-bottom:var(--sp-5)">
      Cloud Kitchen, <span style="color:var(--clr-primary)">Delivered.</span>
    </h1>
    <p style="font-size:1.2rem;max-width:560px;margin:0 auto var(--sp-8);color:var(--clr-text-muted)">
      Fresh, chef-crafted meals from UrbanEats delivered to your door across Sri Lanka.
      Order online, track in real time.
    </p>
    <div class="d-flex gap-4" style="justify-content:center;flex-wrap:wrap">
      <?php if (!is_logged_in()): ?>
        <a href="<?= app_url('/auth/register.php') ?>" class="btn btn-primary btn-lg">
          🍽️ Get Started
        </a>
        <a href="<?= app_url('/auth/login.php') ?>" class="btn btn-secondary btn-lg">
          Sign In
        </a>
      <?php else: ?>
        <a href="<?= app_url('/menu/index.php') ?>" class="btn btn-primary btn-lg">
          🍽️ Browse Menu
        </a>
        <?php if (has_role('Customer')): ?>
          <a href="<?= app_url('/orders/cart.php') ?>" class="btn btn-secondary btn-lg">
            🛒 My Cart
          </a>
        <?php elseif (has_role('Administrator')): ?>
          <a href="<?= app_url('/admin/dashboard.php') ?>" class="btn btn-secondary btn-lg">
            📊 Dashboard
          </a>
        <?php elseif (has_role('Kitchen Staff')): ?>
          <a href="<?= app_url('/orders/kitchen.php') ?>" class="btn btn-secondary btn-lg">
            👨‍🍳 Kitchen Queue
          </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Feature highlights -->
<section style="padding:var(--sp-16) 0">
  <div class="container">
    <h2 class="text-center" style="margin-bottom:var(--sp-10)">Why UrbanEats?</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:var(--sp-6)">

      <div class="card text-center">
        <div style="font-size:3rem;margin-bottom:var(--sp-4)">🏃</div>
        <h3>Fast Delivery</h3>
        <p>Real-time order tracking from kitchen to your door.</p>
      </div>

      <div class="card text-center">
        <div style="font-size:3rem;margin-bottom:var(--sp-4)">🍛</div>
        <h3>Fresh Dishes</h3>
        <p>Chef-crafted Sri Lankan and international cuisine, prepared fresh daily.</p>
      </div>

      <div class="card text-center">
        <div style="font-size:3rem;margin-bottom:var(--sp-4)">💳</div>
        <h3>Easy Payment</h3>
        <p>Cash on delivery, card, or bank transfer — your choice.</p>
      </div>

      <div class="card text-center">
        <div style="font-size:3rem;margin-bottom:var(--sp-4)">📦</div>
        <h3>Order History</h3>
        <p>All your past orders in one place, reorder in one click.</p>
      </div>

    </div>
  </div>
</section>

<!-- CTA -->
<?php if (!is_logged_in()): ?>
<section style="padding:var(--sp-12) 0;background:var(--clr-bg-card);border-top:1px solid var(--clr-border);border-bottom:1px solid var(--clr-border)">
  <div class="container text-center">
    <h2 style="margin-bottom:var(--sp-4)">Ready to order?</h2>
    <p style="margin-bottom:var(--sp-6)">Create your free account and start enjoying UrbanEats today.</p>
    <a href="<?= app_url('/auth/register.php') ?>" class="btn btn-primary btn-lg">
      Create Free Account
    </a>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
