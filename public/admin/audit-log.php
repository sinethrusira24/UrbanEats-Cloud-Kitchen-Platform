<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Administrator Audit Log Viewer
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Authorization Guard: strictly restricted to Administrator role
require_role('admin');

$pdo = get_db_connection();

// Filter parameters
$searchAction = trim((string)($_GET['action_filter'] ?? ''));
$searchQuery  = trim((string)($_GET['search'] ?? ''));
$dateFrom     = trim((string)($_GET['date_from'] ?? ''));
$dateTo       = trim((string)($_GET['date_to'] ?? ''));
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;

// Fetch distinct actions for filter dropdown
$distinctActions = $pdo->query('SELECT DISTINCT action FROM audit_log ORDER BY action ASC')->fetchAll(PDO::FETCH_COLUMN);

// Build parameterized query
$where = ['1=1'];
$params = [];

if ($searchAction !== '') {
    $where[] = 'a.action = :action';
    $params[':action'] = $searchAction;
}

if ($searchQuery !== '') {
    $where[] = '(a.details LIKE :query OR u.username LIKE :query_user OR a.ip_address LIKE :query_ip)';
    $params[':query']      = "%{$searchQuery}%";
    $params[':query_user'] = "%{$searchQuery}%";
    $params[':query_ip']   = "%{$searchQuery}%";
}

if ($dateFrom !== '') {
    $where[] = 'a.created_at >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $where[] = 'a.created_at <= :date_to';
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$whereSql = implode(' AND ', $where);

// Count total matching records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON a.user_id = u.user_id WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

// Compute pagination
$pagination = paginate($totalRecords, $perPage, $page, 'audit-log.php', [
    'action_filter' => $searchAction,
    'search'        => $searchQuery,
    'date_from'     => $dateFrom,
    'date_to'       => $dateTo,
]);

// Fetch audit records
$sql = "SELECT a.audit_id, a.user_id, a.action, a.details, a.ip_address, a.user_agent, a.created_at,
               u.username, u.full_name, r.role_name
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.user_id
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE {$whereSql}
        ORDER BY a.created_at DESC, a.audit_id DESC
        LIMIT :offset, :per_page";

$logStmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $logStmt->bindValue($key, $val);
}
$logStmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$logStmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
$logStmt->execute();
$logs = $logStmt->fetchAll();

$pageTitle = 'Audit Log Viewer';
require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/includes/admin_nav.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Security Audit Logs</h1>
        <p class="page-subtitle">Immutable chronological trail of security, authentication, and administrative operations</p>
    </div>
    <span class="badge badge-admin" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">
        Total Records: <?= number_format($totalRecords) ?>
    </span>
</div>

<!-- Filtering Toolbar -->
<form action="audit-log.php" method="GET" class="filter-bar">
    <div class="filter-item" style="min-width: 200px;">
        <label for="search" class="form-label" style="font-size: 0.8rem;">Search Keyword (Details, User, IP)</label>
        <input type="text"
               id="search"
               name="search"
               class="form-control"
               placeholder="Search..."
               value="<?= e($searchQuery) ?>">
    </div>

    <div class="filter-item" style="max-width: 220px;">
        <label for="action_filter" class="form-label" style="font-size: 0.8rem;">Action Type</label>
        <select id="action_filter" name="action_filter" class="form-control" onchange="this.form.submit()">
            <option value="">All Action Types</option>
            <?php foreach ($distinctActions as $act): ?>
                <option value="<?= e($act) ?>" <?= $searchAction === $act ? 'selected' : '' ?>>
                    <?= e($act) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-item" style="max-width: 160px;">
        <label for="date_from" class="form-label" style="font-size: 0.8rem;">Date From</label>
        <input type="date" id="date_from" name="date_from" class="form-control" value="<?= e($dateFrom) ?>" onchange="this.form.submit()">
    </div>

    <div class="filter-item" style="max-width: 160px;">
        <label for="date_to" class="form-label" style="font-size: 0.8rem;">Date To</label>
        <input type="date" id="date_to" name="date_to" class="form-control" value="<?= e($dateTo) ?>" onchange="this.form.submit()">
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($searchQuery !== '' || $searchAction !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
            <a href="audit-log.php" class="btn btn-outline" title="Reset all filters">Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- Audit Log Table -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th style="width: 70px;">ID</th>
                <th style="width: 160px;">Timestamp</th>
                <th style="width: 160px;">Actor / User</th>
                <th style="width: 180px;">Action</th>
                <th>Details</th>
                <th style="width: 130px;">IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                        No audit records matched your filter criteria.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $row): ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--text-muted); font-size: 0.825rem;">
                            #<?= (int)$row['audit_id'] ?>
                        </td>
                        <td style="font-size: 0.825rem; white-space: nowrap;">
                            <?= date('Y-m-d H:i:s', strtotime($row['created_at'])) ?>
                        </td>
                        <td>
                            <?php if (!empty($row['username'])): ?>
                                <strong><?= e($row['username']) ?></strong><br>
                                <span class="badge badge-<?= e($row['role_name'] ?? 'customer') ?>" style="font-size: 0.7rem;">
                                    <?= e(str_replace('_', ' ', (string)($row['role_name'] ?? ''))) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-style: italic; font-size: 0.85rem;">System / Guest</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code style="font-weight: 700; color: var(--primary); font-size: 0.825rem; background: var(--primary-light); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--primary-border);">
                                <?= e($row['action']) ?>
                            </code>
                        </td>
                        <td style="font-size: 0.875rem; word-break: break-word;">
                            <?= e($row['details']) ?>
                        </td>
                        <td>
                            <span style="font-family: var(--font-mono); font-size: 0.825rem; color: var(--text-muted);">
                                <?= e($row['ip_address'] ?? '—') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
    <ul class="pagination">
        <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
            <a href="<?= $pagination['prev_url'] ?? '#' ?>" class="page-link">&laquo; Prev</a>
        </li>

        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
            <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                <a href="<?= ($pagination['build_url'])($p) ?>" class="page-link"><?= $p ?></a>
            </li>
        <?php endfor; ?>

        <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
            <a href="<?= $pagination['next_url'] ?? '#' ?>" class="page-link">Next &raquo;</a>
        </li>
    </ul>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
