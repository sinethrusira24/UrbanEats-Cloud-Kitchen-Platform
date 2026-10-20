<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Administrator Dashboard
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Authorization Guard: strictly restricted to Administrator role
require_role('admin');

$pdo = get_db_connection();

// 1. Aggregate Statistics Queries
$statsStmt = $pdo->query(
    'SELECT
        COUNT(*) AS total_users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_users,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_users,
        SUM(CASE WHEN role_id = 1 THEN 1 ELSE 0 END) AS admin_count,
        SUM(CASE WHEN role_id = 2 THEN 1 ELSE 0 END) AS kitchen_count,
        SUM(CASE WHEN role_id = 3 THEN 1 ELSE 0 END) AS customer_count
     FROM users'
);
$stats = $statsStmt->fetch() ?: [
    'total_users'    => 0,
    'active_users'   => 0,
    'inactive_users' => 0,
    'admin_count'    => 0,
    'kitchen_count'  => 0,
    'customer_count' => 0,
];

// 2. Total Security Audit Events
$auditCount = (int)$pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();

// 3. Recent 5 User Registrations
$recentUsersStmt = $pdo->query(
    'SELECT u.user_id, u.full_name, u.username, u.email, u.is_active, u.created_at, r.role_name
     FROM users u
     INNER JOIN roles r ON u.role_id = r.role_id
     ORDER BY u.created_at DESC
     LIMIT 5'
);
$recentUsers = $recentUsersStmt->fetchAll();

// 4. Recent 10 Audit Log Activity Entries
$recentAuditStmt = $pdo->query(
    'SELECT a.audit_id, a.user_id, a.action, a.details, a.ip_address, a.created_at, u.username, r.role_name
     FROM audit_log a
     LEFT JOIN users u ON a.user_id = u.user_id
     LEFT JOIN roles r ON u.role_id = r.role_id
     ORDER BY a.created_at DESC
     LIMIT 10'
);
$recentAudit = $recentAuditStmt->fetchAll();

// 5. Recent 5 Failed Login Attempts
$recentFailedStmt = $pdo->query(
    'SELECT attempt_id, identifier, ip_address, attempted_at
     FROM login_attempts
     WHERE is_success = 0
     ORDER BY attempted_at DESC
     LIMIT 5'
);
$recentFailed = $recentFailedStmt->fetchAll();

$pageTitle = 'Administrator Dashboard';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Administrator Dashboard</h1>
        <p class="page-subtitle">Identity, Access Control, and System Security Governance</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="<?= base_url('admin/users.php') ?>" class="btn btn-primary btn-sm">
            👥 User Management
        </a>
        <a href="<?= base_url('admin/audit-log.php') ?>" class="btn btn-outline btn-sm">
            📜 Security Audit Log
        </a>
    </div>
</div>

<!-- Metric Stat Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon orange">👥</div>
        <div class="stat-content">
            <span class="stat-number"><?= (int)$stats['total_users'] ?></span>
            <span class="stat-label">Total Users</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div class="stat-content">
            <span class="stat-number"><?= (int)$stats['active_users'] ?></span>
            <span class="stat-label">Active Accounts</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon red">🛑</div>
        <div class="stat-content">
            <span class="stat-number"><?= (int)$stats['inactive_users'] ?></span>
            <span class="stat-label">Suspended Accounts</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon slate">👑</div>
        <div class="stat-content">
            <span class="stat-number"><?= (int)$stats['admin_count'] ?></span>
            <span class="stat-label">Administrators</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">👨‍🍳</div>
        <div class="stat-content">
            <span class="stat-number"><?= (int)$stats['kitchen_count'] ?></span>
            <span class="stat-label">Kitchen Staff</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue">🛒</div>
        <div class="stat-content">
            <span class="stat-number"><?= (int)$stats['customer_count'] ?></span>
            <span class="stat-label">Customers</span>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.75rem; margin-bottom: 2rem;">
    <!-- Recent Registrations Card -->
    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 1.15rem; margin: 0;">Recent Registrations</h2>
            <a href="<?= base_url('admin/users.php') ?>" style="font-size: 0.85rem; font-weight: 600;">View All &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentUsers)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No users registered yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentUsers as $ru): ?>
                            <tr>
                                <td>
                                    <strong><?= e($ru['full_name']) ?></strong><br>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);"><?= e($ru['username']) ?></span>
                                </td>
                                <td><span class="badge badge-<?= e($ru['role_name']) ?>"><?= e(str_replace('_', ' ', (string)$ru['role_name'])) ?></span></td>
                                <td>
                                    <?php if ((int)$ru['is_active'] === 1): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.825rem;"><?= date('M d, H:i', strtotime($ru['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Security Audit Card -->
    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 1.15rem; margin: 0;">Recent Failed Logins</h2>
            <span class="badge badge-danger">Throttling Watch</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Identifier</th>
                        <th>IP Address</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentFailed)): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No failed login attempts recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentFailed as $rf): ?>
                            <tr>
                                <td><code><?= e($rf['identifier']) ?></code></td>
                                <td><span style="font-family: var(--font-mono); font-size: 0.825rem;"><?= e($rf['ip_address']) ?></span></td>
                                <td style="font-size: 0.825rem;"><?= date('M d, H:i:s', strtotime($rf['attempted_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent System Audit Trail -->
<div class="card">
    <div class="card-header">
        <h2 style="font-size: 1.15rem; margin: 0;">Security & Administrative Activity Trail</h2>
        <a href="<?= base_url('admin/audit-log.php') ?>" style="font-size: 0.85rem; font-weight: 600;">Full Audit Log &rarr;</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentAudit)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No audit records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentAudit as $log): ?>
                        <tr>
                            <td style="font-size: 0.825rem; white-space: nowrap;"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                            <td>
                                <?php if (!empty($log['username'])): ?>
                                    <strong><?= e($log['username']) ?></strong>
                                    <span class="badge badge-<?= e($log['role_name'] ?? 'customer') ?>" style="font-size: 0.7rem;">
                                        <?= e($log['role_name'] ?? '') ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">System / Guest</span>
                                <?php endif; ?>
                            </td>
                            <td><code style="font-weight: 700; color: var(--primary); background: var(--primary-light); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--primary-border);"><?= e($log['action']) ?></code></td>
                            <td style="font-size: 0.875rem;"><?= e($log['details']) ?></td>
                            <td style="font-family: var(--font-mono); font-size: 0.825rem;"><?= e($log['ip_address'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
