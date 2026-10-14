<?php
/**
 * sidebar.php – Admin sidebar navigation.
 * Included by all admin pages.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="admin-sidebar" aria-label="Admin navigation">

  <div class="sidebar-section">
    <p class="sidebar-label">Overview</p>
    <nav class="sidebar-nav">
      <a href="<?= app_url('/admin/dashboard.php') ?>"
         class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <span class="nav-icon">📊</span> Dashboard
      </a>
    </nav>
  </div>

  <div class="sidebar-section">
    <p class="sidebar-label">User Management</p>
    <nav class="sidebar-nav">
      <a href="<?= app_url('/admin/users.php') ?>"
         class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">
        <span class="nav-icon">👥</span> All Users
      </a>
      <a href="<?= app_url('/admin/users.php?role=Customer') ?>"
         class="<?= ($currentPage === 'users.php' && ($_GET['role'] ?? '') === 'Customer') ? 'active' : '' ?>">
        <span class="nav-icon">🛒</span> Customers
      </a>
      <a href="<?= app_url('/admin/users.php?role=Kitchen+Staff') ?>"
         class="<?= ($currentPage === 'users.php' && ($_GET['role'] ?? '') === 'Kitchen Staff') ? 'active' : '' ?>">
        <span class="nav-icon">👨‍🍳</span> Kitchen Staff
      </a>
    </nav>
  </div>

  <div class="sidebar-section">
    <p class="sidebar-label">Monitoring</p>
    <nav class="sidebar-nav">
      <a href="<?= app_url('/admin/audit.php') ?>"
         class="<?= $currentPage === 'audit.php' ? 'active' : '' ?>">
        <span class="nav-icon">📋</span> Audit Log
      </a>
      <a href="<?= app_url('/admin/reports.php') ?>"
         class="<?= $currentPage === 'reports.php' ? 'active' : '' ?>">
        <span class="nav-icon">📈</span> Sales Reports
      </a>
    </nav>
  </div>

  <div class="sidebar-section">
    <p class="sidebar-label">Account</p>
    <nav class="sidebar-nav">
      <a href="<?= app_url('/auth/profile.php') ?>">
        <span class="nav-icon">👤</span> My Profile
      </a>
      <form method="POST" action="<?= app_url('/auth/logout.php') ?>" style="margin:0">
        <?= csrf_field() ?>
        <button type="submit" style="background:none;border:none;width:100%;text-align:left;display:flex;align-items:center;gap:var(--sp-3);padding:var(--sp-3) var(--sp-6);color:var(--clr-text-muted);font-size:inherit;cursor:pointer;font-family:var(--font-body);font-weight:500;border-left:2px solid transparent;transition:all var(--trans-fast);"
                onmouseover="this.style.color='var(--clr-text)';this.style.background='var(--clr-surface)'"
                onmouseout="this.style.color='var(--clr-text-muted)';this.style.background='none'">
          <span class="nav-icon">🚪</span> Sign Out
        </button>
      </form>
    </nav>
  </div>

</aside>
