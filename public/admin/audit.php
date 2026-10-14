<?php
/**
 * audit.php – Administrator audit log viewer.
 *
 * Paginated, filterable view of all audit_log entries.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('Administrator');

$db = get_db();

// ── Filters ────────────────────────────────────────────────────────────────
$search     = sanitise_string($_GET['q']      ?? '');
$filterUser = sanitise_string($_GET['user']   ?? '');
$dateFrom   = sanitise_string($_GET['from']   ?? '');
$dateTo     = sanitise_string($_GET['to']     ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;

// ── WHERE ──────────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]     = '(al.action LIKE :q OR al.details LIKE :q)';
    $params[':q'] = "%$search%";
}
if ($filterUser) {
    $where[]            = 'al.user_id = :uid';
    $params[':uid']     = (int)$filterUser;
}
if ($dateFrom) {
    $where[]            = 'DATE(al.created_at) >= :from';
    $params[':from']    = $dateFrom;
}
if ($dateTo) {
    $where[]            = 'DATE(al.created_at) <= :to';
    $params[':to']      = $dateTo;
}

$whereSQL = implode(' AND ', $where);

// ── Count ──────────────────────────────────────────────────────────────────
$countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log al WHERE $whereSQL");
$countStmt->execute($params);
$total   = (int)$countStmt->fetchColumn();
$pagInfo = paginate($total, $perPage, $page);

// ── Fetch ──────────────────────────────────────────────────────────────────
$params[':limit']  = $perPage;
$params[':offset'] = $pagInfo['offset'];

$stmt = $db->prepare(
    "SELECT al.audit_id, al.action, al.details, al.ip_address, al.created_at,
            al.user_id, COALESCE(u.full_name, 'Guest') AS actor, u.username
       FROM audit_log al
       LEFT JOIN users u ON u.user_id = al.user_id
      WHERE $whereSQL
      ORDER BY al.created_at DESC
      LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    if ($k === ':limit' || $k === ':offset') {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($k, $v);
    }
}
$stmt->execute();
$logs = $stmt->fetchAll();

// ── Users for filter dropdown ──────────────────────────────────────────────
$userList = $db->query('SELECT user_id, full_name, username FROM users ORDER BY full_name')->fetchAll();

$pageTitle = 'Audit Log';
include __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="dashboard-content">
    <div class="page-header">
      <div>
        <h1>Audit Log</h1>
        <p><?= e($total) ?> events recorded</p>
      </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="" class="filter-bar">
      <input type="search"
             name="q"
             value="<?= e($search) ?>"
             placeholder="Search action or details…"
             aria-label="Search audit log">

      <select name="user" aria-label="Filter by user">
        <option value="">All Users</option>
        <?php foreach ($userList as $u): ?>
          <option value="<?= e($u['user_id']) ?>" <?= $filterUser == $u['user_id'] ? 'selected' : '' ?>>
            <?= e($u['full_name']) ?> (@<?= e($u['username']) ?>)
          </option>
        <?php endforeach; ?>
      </select>

      <input type="date" name="from" value="<?= e($dateFrom) ?>" aria-label="From date">
      <input type="date" name="to"   value="<?= e($dateTo)   ?>" aria-label="To date">

      <button type="submit" class="btn btn-secondary btn-sm">🔍 Filter</button>
      <a href="<?= app_url('/admin/audit.php') ?>" class="btn btn-secondary btn-sm">✕ Clear</a>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th scope="col">Time</th>
            <th scope="col">Actor</th>
            <th scope="col">Action</th>
            <th scope="col">Details</th>
            <th scope="col">IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="5" class="text-center" style="padding:var(--sp-8);color:var(--clr-text-muted)">
                No audit events match your search.
              </td>
            </tr>
          <?php endif; ?>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td style="white-space:nowrap;font-size:.85rem;color:var(--clr-text-muted)">
                <?= date('d M Y H:i:s', strtotime($log['created_at'])) ?>
              </td>
              <td>
                <?php if ($log['user_id']): ?>
                  <span><?= e($log['actor']) ?></span><br>
                  <span style="font-size:.8rem;color:var(--clr-text-muted)">@<?= e($log['username'] ?? '') ?></span>
                <?php else: ?>
                  <span style="color:var(--clr-text-subtle)">Guest</span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-info"><?= e($log['action']) ?></span></td>
              <td style="font-size:.875rem;color:var(--clr-text-muted);max-width:300px">
                <?= e($log['details'] ?? '—') ?>
              </td>
              <td style="font-size:.8rem;color:var(--clr-text-muted)"><?= e($log['ip_address'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagInfo['last_page'] > 1): ?>
      <div class="pagination" role="navigation" aria-label="Audit log pagination">
        <?php
          $qParts = array_filter(['q' => $search, 'user' => $filterUser, 'from' => $dateFrom, 'to' => $dateTo]);
          for ($p = 1; $p <= $pagInfo['last_page']; $p++):
            $url      = app_url('/admin/audit.php?' . http_build_query($qParts) . '&page=' . $p);
            $isActive = $p === $pagInfo['current_page'];
        ?>
          <a href="<?= e($url) ?>"
             class="page-link <?= $isActive ? 'active' : '' ?>"
             <?= $isActive ? 'aria-current="page"' : '' ?>
             aria-label="Page <?= $p ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
