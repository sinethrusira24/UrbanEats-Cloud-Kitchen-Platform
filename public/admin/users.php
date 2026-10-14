<?php
/**
 * users.php – Administrator user management.
 *
 * Features:
 *  - Paginated list with search (name/email/username) and role filter
 *  - Activate / deactivate toggle
 *  - Role change (to any role except own account to prevent self-lockout)
 *  - Add new staff / admin accounts
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('Administrator');

$db         = get_db();
$adminUser  = current_user();
$errors     = [];
$actionDone = '';

// ── POST actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action     = $_POST['action'] ?? '';
    $targetId   = (int)($_POST['user_id'] ?? 0);

    // Prevent acting on own account for sensitive actions
    if ($targetId === $adminUser['user_id'] && in_array($action, ['deactivate', 'change_role'])) {
        flash('error', 'You cannot modify your own account status/role here.');
        redirect(app_url('/admin/users.php'));
    }

    if ($action === 'toggle_active' && $targetId) {
        $stmt = $db->prepare('SELECT is_active, full_name FROM users WHERE user_id = :id');
        $stmt->execute([':id' => $targetId]);
        $target = $stmt->fetch();

        if ($target) {
            $newStatus = $target['is_active'] ? 0 : 1;
            $db->prepare('UPDATE users SET is_active = :s WHERE user_id = :id')
               ->execute([':s' => $newStatus, ':id' => $targetId]);
            $label = $newStatus ? 'activated' : 'deactivated';
            log_action("user_{$label}", "Admin {$adminUser['username']} {$label} user: {$target['full_name']} (ID $targetId)");
            flash('success', "Account has been $label.");
        }
        redirect(app_url('/admin/users.php'));
    }

    if ($action === 'change_role' && $targetId) {
        $newRoleId = (int)($_POST['new_role_id'] ?? 0);
        $stmt = $db->prepare('SELECT role_name FROM roles WHERE role_id = :id LIMIT 1');
        $stmt->execute([':id' => $newRoleId]);
        $roleRow = $stmt->fetch();

        if ($roleRow) {
            $db->prepare('UPDATE users SET role_id = :rid WHERE user_id = :uid')
               ->execute([':rid' => $newRoleId, ':uid' => $targetId]);
            log_action('user_role_changed', "User $targetId role changed to {$roleRow['role_name']} by {$adminUser['username']}");
            flash('success', "Role updated to {$roleRow['role_name']}.");
        }
        redirect(app_url('/admin/users.php'));
    }

    if ($action === 'create_user') {
        // Admin creating a staff / admin account
        $fullName  = sanitise_string($_POST['full_name'] ?? '');
        $username  = sanitise_string($_POST['username']  ?? '');
        $email     = sanitise_string($_POST['email']     ?? '');
        $phone     = sanitise_string($_POST['phone']     ?? '');
        $newRoleId = (int)($_POST['role_id'] ?? 0);
        $password  = $_POST['password'] ?? '';

        if (!$fullName)  $errors['cn_full_name'] = 'Full name required.';
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) $errors['cn_username'] = 'Invalid username.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errors['cn_email']    = 'Invalid email.';
        if (strlen($password) < 8)                             $errors['cn_password'] = 'Password must be ≥8 chars.';
        if (!$newRoleId)                                        $errors['cn_role']     = 'Select a role.';

        if (empty($errors)) {
            $c = $db->prepare('SELECT COUNT(*) FROM users WHERE email=:e OR username=:u');
            $c->execute([':e' => $email, ':u' => $username]);
            if ($c->fetchColumn() > 0) {
                $errors['cn_email'] = 'Email or username already exists.';
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare(
                'INSERT INTO users (role_id, full_name, username, email, password_hash, phone)
                 VALUES (:rid, :fn, :un, :em, :pw, :ph)'
            )->execute([
                ':rid' => $newRoleId, ':fn' => $fullName, ':un' => $username,
                ':em'  => $email,     ':pw' => $hash,     ':ph' => $phone ?: null,
            ]);
            log_action('user_created', "Admin {$adminUser['username']} created user: $username ($email)");
            flash('success', "Account for $fullName created.");
            redirect(app_url('/admin/users.php'));
        }
    }
}

// ── Query params ─────────────────────────────────────────────────────────────
$search      = sanitise_string($_GET['q']      ?? '');
$filterRole  = sanitise_string($_GET['role']   ?? '');
$filterStatus= sanitise_string($_GET['status'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 15;

// ── Build WHERE ──────────────────────────────────────────────────────────────
$where   = ['1=1'];
$params  = [];

if ($search) {
    $where[]   = '(u.full_name LIKE :q OR u.email LIKE :q OR u.username LIKE :q)';
    $params[':q'] = "%$search%";
}
if ($filterRole) {
    $where[]   = 'r.role_name = :role';
    $params[':role'] = $filterRole;
}
if ($filterStatus === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($filterStatus === 'inactive') {
    $where[] = 'u.is_active = 0';
}

$whereSQL = implode(' AND ', $where);

// ── Count ────────────────────────────────────────────────────────────────────
$countStmt = $db->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id = u.role_id WHERE $whereSQL");
$countStmt->execute($params);
$total   = (int)$countStmt->fetchColumn();
$pagInfo = paginate($total, $perPage, $page);

// ── Fetch users ───────────────────────────────────────────────────────────────
$params[':limit']  = $perPage;
$params[':offset'] = $pagInfo['offset'];

$stmt = $db->prepare(
    "SELECT u.user_id, u.full_name, u.username, u.email, u.phone,
            u.is_active, u.created_at, r.role_name, u.role_id
       FROM users u
       JOIN roles r ON r.role_id = u.role_id
      WHERE $whereSQL
      ORDER BY u.created_at DESC
      LIMIT :limit OFFSET :offset"
);
// Bind ints explicitly
foreach ($params as $k => $v) {
    if ($k === ':limit' || $k === ':offset') {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($k, $v);
    }
}
$stmt->execute();
$users = $stmt->fetchAll();

// ── Roles for dropdowns ────────────────────────────────────────────────────
$roles = $db->query('SELECT role_id, role_name FROM roles ORDER BY role_id')->fetchAll();

$pageTitle = 'User Management';
include __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-layout">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="dashboard-content">
    <div class="page-header">
      <div>
        <h1>User Management</h1>
        <p><?= e($total) ?> users found</p>
      </div>
      <button class="btn btn-primary" id="open-create-modal" aria-controls="create-modal" aria-expanded="false">
        ➕ Add User
      </button>
    </div>

    <!-- Filter bar -->
    <form method="GET" action="" class="filter-bar" id="filter-form">
      <input type="search"
             name="q"
             value="<?= e($search) ?>"
             placeholder="Search name, email or username…"
             aria-label="Search users">

      <select name="role" aria-label="Filter by role">
        <option value="">All Roles</option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= e($r['role_name']) ?>" <?= $filterRole === $r['role_name'] ? 'selected' : '' ?>>
            <?= e($r['role_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="status" aria-label="Filter by status">
        <option value="">All Status</option>
        <option value="active"   <?= $filterStatus === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>

      <button type="submit" class="btn btn-secondary btn-sm">🔍 Filter</button>
      <a href="<?= app_url('/admin/users.php') ?>" class="btn btn-secondary btn-sm">✕ Clear</a>
    </form>

    <!-- Users table -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th scope="col">User</th>
            <th scope="col">Username</th>
            <th scope="col">Email</th>
            <th scope="col">Role</th>
            <th scope="col">Status</th>
            <th scope="col">Joined</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="7" class="text-center" style="padding:var(--sp-8);color:var(--clr-text-muted)">
                No users found matching your search.
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <div class="d-flex align-center gap-2">
                  <div class="avatar" style="width:32px;height:32px;font-size:.8rem" aria-hidden="true">
                    <?= e(mb_substr($u['full_name'], 0, 1)) ?>
                  </div>
                  <span><?= e($u['full_name']) ?></span>
                </div>
              </td>
              <td style="color:var(--clr-text-muted)">@<?= e($u['username']) ?></td>
              <td style="font-size:.9rem"><?= e($u['email']) ?></td>
              <td>
                <!-- Inline role change form -->
                <form method="POST" action="" style="display:inline-flex;gap:var(--sp-2);align-items:center">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action"  value="change_role">
                  <input type="hidden" name="user_id" value="<?= e($u['user_id']) ?>">
                  <select name="new_role_id"
                          aria-label="Change role for <?= e($u['username']) ?>"
                          onchange="this.form.submit()"
                          <?= $u['user_id'] === $adminUser['user_id'] ? 'disabled' : '' ?>
                          style="width:auto;padding:var(--sp-1) var(--sp-3);font-size:.85rem">
                    <?php foreach ($roles as $r): ?>
                      <option value="<?= e($r['role_id']) ?>"
                              <?= $r['role_id'] == $u['role_id'] ? 'selected' : '' ?>>
                        <?= e($r['role_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td>
                <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-error' ?>">
                  <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td style="font-size:.85rem;color:var(--clr-text-muted);white-space:nowrap">
                <?= date('d M Y', strtotime($u['created_at'])) ?>
              </td>
              <td>
                <?php if ($u['user_id'] !== $adminUser['user_id']): ?>
                  <form method="POST" action="" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"  value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= e($u['user_id']) ?>">
                    <button type="submit"
                            class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>"
                            data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this account?"
                            aria-label="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> <?= e($u['full_name']) ?>">
                      <?= $u['is_active'] ? '🚫 Deactivate' : '✅ Activate' ?>
                    </button>
                  </form>
                <?php else: ?>
                  <span style="font-size:.85rem;color:var(--clr-text-subtle)">You</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagInfo['last_page'] > 1): ?>
      <div class="pagination" role="navigation" aria-label="Users pagination">
        <?php
          $qParts = array_filter(['q' => $search, 'role' => $filterRole, 'status' => $filterStatus]);
          $qStr   = http_build_query($qParts);

          for ($p = 1; $p <= $pagInfo['last_page']; $p++):
            $url = app_url('/admin/users.php?' . $qStr . '&page=' . $p);
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

  </div><!-- /.dashboard-content -->
</div>

<!-- ── Create User Modal ─────────────────────────────────────────────────── -->
<div id="create-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="modal-title"
     style="display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.7);place-items:center;padding:var(--sp-6)">
  <div class="card" style="width:100%;max-width:520px;max-height:90vh;overflow-y:auto">
    <h2 id="modal-title" class="card-title" style="margin-bottom:var(--sp-6)">Create New Account</h2>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $e_msg): ?>
          <div><?= e($e_msg) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_user">

      <div class="form-group">
        <label for="cn_full_name">Full Name</label>
        <input type="text" id="cn_full_name" name="full_name" required
               value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="Jane Perera">
        <?php if (!empty($errors['cn_full_name'])): ?>
          <span class="field-error" role="alert">⚠ <?= e($errors['cn_full_name']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="cn_username">Username</label>
        <input type="text" id="cn_username" name="username" required
               value="<?= e($_POST['username'] ?? '') ?>" placeholder="jane_perera">
        <?php if (!empty($errors['cn_username'])): ?>
          <span class="field-error" role="alert">⚠ <?= e($errors['cn_username']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="cn_email">Email</label>
        <input type="email" id="cn_email" name="email" required
               value="<?= e($_POST['email'] ?? '') ?>" placeholder="jane@example.com">
        <?php if (!empty($errors['cn_email'])): ?>
          <span class="field-error" role="alert">⚠ <?= e($errors['cn_email']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="cn_phone">Phone <span style="color:var(--clr-text-subtle)">(optional)</span></label>
        <input type="tel" id="cn_phone" name="phone"
               value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+94 71 234 5678">
      </div>

      <div class="form-group">
        <label for="cn_role">Role</label>
        <select id="cn_role" name="role_id" required>
          <option value="">Select a role…</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= e($r['role_id']) ?>"
                    <?= ($_POST['role_id'] ?? '') == $r['role_id'] ? 'selected' : '' ?>>
              <?= e($r['role_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['cn_role'])): ?>
          <span class="field-error" role="alert">⚠ <?= e($errors['cn_role']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="cn_password">Temporary Password</label>
        <input type="password" id="cn_password" name="password" required
               placeholder="Minimum 8 characters" autocomplete="new-password">
        <?php if (!empty($errors['cn_password'])): ?>
          <span class="field-error" role="alert">⚠ <?= e($errors['cn_password']) ?></span>
        <?php endif; ?>
      </div>

      <div class="d-flex gap-4" style="flex-wrap:wrap">
        <button type="submit" class="btn btn-primary" id="create-user-btn">
          ➕ Create Account
        </button>
        <button type="button" class="btn btn-secondary" id="close-modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScripts = <<<JS
<script>
(function() {
  const modal    = document.getElementById('create-modal');
  const openBtn  = document.getElementById('open-create-modal');
  const closeBtn = document.getElementById('close-modal');

  function openModal() {
    modal.style.display = 'grid';
    openBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.style.display = 'none';
    openBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  if (openBtn)  openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  // Close on backdrop click
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closeModal();
    });
  }

  // Re-open on validation error
  const hasErrors = document.querySelector('#create-modal .alert-error');
  if (hasErrors) openModal();
})();
</script>
JS;

include __DIR__ . '/../../includes/footer.php';
?>
