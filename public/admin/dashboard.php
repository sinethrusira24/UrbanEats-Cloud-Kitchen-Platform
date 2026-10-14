<?php
/**
 * dashboard.php – Administrator overview dashboard.
 *
 * Shows key statistics: total users by role, recent logins, recent audit entries.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('Administrator');

$db = get_db();

// ── Stats ──────────────────────────────────────────────────────────────────
$userStats = $db->query(
    'SELECT r.role_name, COUNT(u.user_id) AS total
       FROM roles r
       LEFT JOIN users u ON u.role_id = r.role_id
      GROUP BY r.role_id, r.role_name
      ORDER BY r.role_id'
)->fetchAll();

$totalUsers   = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeUsers  = (int)$db->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
$todayLogins  = (int)$db->query("SELECT COUNT(*) FROM login_attempts WHERE was_successful = 1 AND DATE(attempted_at) = CURDATE()")->fetchColumn();
$auditToday   = (int)$db->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// ── Recent audit log ───────────────────────────────────────────────────────
$recentAudit  = $db->query(
    'SELECT al.action, al.details, al.created_at, al.ip_address,
            COALESCE(u.full_name, "Guest") AS actor
       FROM audit_log al
       LEFT JOIN users u ON u.user_id = al.user_id
      ORDER BY al.created_at DESC
      LIMIT 10'
)->fetchAll();

// ── Recent failed logins ───────────────────────────────────────────────────
$failedLogins = $db->query(
    'SELECT login_identifier, ip_address, attempted_at
       FROM login_attempts
      WHERE was_successful = 0
      ORDER BY attempted_at DESC
      LIMIT 8'
)->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-layout">

  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="dashboard-content">
    <div class="page-header">
      <div>
        <h1>Dashboard</h1>
        <p>UrbanEats platform overview</p>
      </div>
      <span style="color:var(--clr-text-muted);font-size:.9rem">
        <?= date('l, j F Y') ?>
      </span>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= e($totalUsers) ?></div>
        <div class="stat-label">Total Users</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= e($activeUsers) ?></div>
        <div class="stat-label">Active Accounts</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🔑</div>
        <div class="stat-value"><?= e($todayLogins) ?></div>
        <div class="stat-label">Logins Today</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-value"><?= e($auditToday) ?></div>
        <div class="stat-label">Audit Events Today</div>
      </div>
    </div>

    <!-- Users by role -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-6);margin-bottom:var(--sp-8)">
      <div class="card">
        <h3 class="card-title">Users by Role</h3>
        <?php foreach ($userStats as $row): ?>
          <div class="d-flex align-center" style="justify-content:space-between;padding:var(--sp-3) 0;border-bottom:1px solid var(--clr-border)">
            <span><?= e($row['role_name']) ?></span>
            <strong class="text-primary"><?= e($row['total']) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Recent failed logins -->
      <div class="card">
        <h3 class="card-title">⚠ Recent Failed Logins</h3>
        <?php if (empty($failedLogins)): ?>
          <p style="font-size:.9rem">No recent failed attempts.</p>
        <?php else: ?>
          <?php foreach ($failedLogins as $attempt): ?>
            <div style="border-bottom:1px solid var(--clr-border);padding:var(--sp-3) 0">
              <div style="font-size:.875rem;color:var(--clr-text)"><?= e($attempt['login_identifier']) ?></div>
              <div style="font-size:.8rem;color:var(--clr-text-muted)">
                <?= e($attempt['ip_address']) ?> &bull; <?= date('d M H:i', strtotime($attempt['attempted_at'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent audit log -->
    <div class="card">
      <div class="d-flex align-center" style="justify-content:space-between;margin-bottom:var(--sp-6)">
        <h3 class="card-title" style="margin:0">Recent Activity</h3>
        <a href="<?= app_url('/admin/audit.php') ?>" class="btn btn-secondary btn-sm">View All</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th scope="col">Time</th>
              <th scope="col">Actor</th>
              <th scope="col">Action</th>
              <th scope="col">Details</th>
              <th scope="col">IP</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentAudit as $log): ?>
              <tr>
                <td style="white-space:nowrap;font-size:.85rem;color:var(--clr-text-muted)">
                  <?= date('d M H:i', strtotime($log['created_at'])) ?>
                </td>
                <td><?= e($log['actor']) ?></td>
                <td><span class="badge badge-info"><?= e($log['action']) ?></span></td>
                <td style="font-size:.875rem;color:var(--clr-text-muted);max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                  <?= e($log['details'] ?? '—') ?>
                </td>
                <td style="font-size:.8rem;color:var(--clr-text-muted)"><?= e($log['ip_address'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /.dashboard-content -->
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
