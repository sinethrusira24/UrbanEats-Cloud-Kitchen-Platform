<?php
/**
 * categories.php – Component B: Staff Category Management.
 *
 * Restricted to Kitchen Staff & Administrator.
 * Allows adding, editing, ordering, and activating/deactivating categories.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role(['Kitchen Staff', 'Administrator']);

$pageTitle = 'Manage Menu Categories';
$db = get_db();
$errors = [];

// ── Handle POST Actions (Create, Update, Toggle Status) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $catId        = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $name         = sanitise_string($_POST['name'] ?? '');
        $description  = sanitise_string($_POST['description'] ?? '');
        $displayOrder = filter_input(INPUT_POST, 'display_order', FILTER_VALIDATE_INT);
        if ($displayOrder === false || $displayOrder < 0) $displayOrder = 0;
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        if ($name === '') {
            $errors['name'] = 'Category name is required.';
        } elseif (mb_strlen($name) > 80) {
            $errors['name'] = 'Category name cannot exceed 80 characters.';
        } else {
            // Check uniqueness
            $checkSql = 'SELECT category_id FROM categories WHERE name = :name';
            $checkParams = [':name' => $name];
            if ($action === 'update' && $catId) {
                $checkSql .= ' AND category_id != :id';
                $checkParams[':id'] = $catId;
            }
            $chk = $db->prepare($checkSql);
            $chk->execute($checkParams);
            if ($chk->fetch()) {
                $errors['name'] = 'A category with this name already exists.';
            }
        }

        if (empty($errors)) {
            try {
                if ($action === 'create') {
                    $stmt = $db->prepare(
                        'INSERT INTO categories (name, description, display_order, is_active)
                         VALUES (:name, :desc, :order, :active)'
                    );
                    $stmt->execute([
                        ':name'   => $name,
                        ':desc'   => $description ?: null,
                        ':order'  => $displayOrder,
                        ':active' => $isActive,
                    ]);
                    $newId = (int)$db->lastInsertId();

                    log_action('category_created', json_encode(['category_id' => $newId, 'name' => $name]));
                    flash('success', "Category &ldquo;{$name}&rdquo; was created successfully.");
                } else {
                    $stmt = $db->prepare(
                        'UPDATE categories 
                         SET name = :name, description = :desc, display_order = :order, is_active = :active
                         WHERE category_id = :id'
                    );
                    $stmt->execute([
                        ':name'   => $name,
                        ':desc'   => $description ?: null,
                        ':order'  => $displayOrder,
                        ':active' => $isActive,
                        ':id'     => $catId,
                    ]);

                    log_action('category_updated', json_encode(['category_id' => $catId, 'name' => $name]));
                    flash('success', "Category &ldquo;{$name}&rdquo; was updated successfully.");
                }
                redirect(app_url('/menu/categories.php'));
            } catch (PDOException $e) {
                error_log('[UrbanEats] Category error: ' . $e->getMessage());
                $errors['general'] = 'Database error occurred while saving category.';
            }
        }

    } elseif ($action === 'toggle_status') {
        $catId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $status = (int)($_POST['new_status'] ?? 0);

        if ($catId) {
            $stmt = $db->prepare('UPDATE categories SET is_active = :status WHERE category_id = :id');
            $stmt->execute([':status' => $status, ':id' => $catId]);

            log_action('category_status_changed', json_encode(['category_id' => $catId, 'active' => $status]));
            flash('info', $status ? 'Category activated.' : 'Category deactivated.');
            redirect(app_url('/menu/categories.php'));
        }
    }
}

// ── Fetch All Categories with Live Dish Counts ─────────────────────────────
$categories = $db->query(
    'SELECT c.category_id, c.name, c.description, c.display_order, c.is_active, c.created_at,
            COUNT(d.dish_id) AS total_dishes,
            SUM(CASE WHEN d.is_active = 1 THEN 1 ELSE 0 END) AS active_dishes
     FROM categories c
     LEFT JOIN dishes d ON c.category_id = d.category_id
     GROUP BY c.category_id, c.name, c.description, c.display_order, c.is_active, c.created_at
     ORDER BY c.display_order ASC, c.name ASC'
)->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="container" style="padding:var(--sp-8) 0 var(--sp-16);">

  <!-- Breadcrumb -->
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= app_url('/menu/manage.php') ?>">Dishes Management</a>
    <span class="breadcrumb-separator" aria-hidden="true">›</span>
    <span style="color:var(--clr-text)" aria-current="page">Categories</span>
  </nav>

  <!-- Header & Actions -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--sp-4);margin-bottom:var(--sp-8);">
    <div>
      <div style="font-size:.8125rem;color:var(--clr-primary);font-weight:700;text-transform:uppercase;letter-spacing:.08em;">
        Catalogue Taxonomy &bull; Component B
      </div>
      <h1 style="font-size:2rem;margin-top:var(--sp-1);">Menu Categories Management</h1>
    </div>

    <div class="d-flex gap-3">
      <button type="button" class="btn btn-primary" onclick="openCategoryModal()">
        ➕ Add New Category
      </button>
      <a href="<?= app_url('/menu/manage.php') ?>" class="btn btn-secondary">
        🍽️ View Dishes
      </a>
    </div>
  </div>

  <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error" style="margin-bottom:var(--sp-6);">
      <?= e($errors['general']) ?>
    </div>
  <?php endif; ?>

  <!-- Categories Table -->
  <div class="table-container card" style="padding:0;overflow:hidden;">
    <table class="table" style="width:100%;margin:0;">
      <thead>
        <tr>
          <th style="width:60px;">Order</th>
          <th>Category Name</th>
          <th>Description</th>
          <th>Dishes Count</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $cat): ?>
          <tr style="<?= !$cat['is_active'] ? 'opacity:.65;background:rgba(0,0,0,.2);' : '' ?>">
            <!-- Display Order -->
            <td style="font-weight:700;color:var(--clr-primary);">
              #<?= (int)$cat['display_order'] ?>
            </td>

            <!-- Name -->
            <td style="font-weight:700;color:var(--clr-text);">
              <?= e($cat['name']) ?>
            </td>

            <!-- Description -->
            <td style="font-size:.85rem;color:var(--clr-text-muted);max-width:320px;">
              <?= e($cat['description'] ?: '—') ?>
            </td>

            <!-- Dish Counts -->
            <td>
              <span class="badge" style="background:var(--clr-surface);color:var(--clr-text);">
                <?= (int)$cat['active_dishes'] ?> active (<?= (int)$cat['total_dishes'] ?> total)
              </span>
            </td>

            <!-- Status -->
            <td>
              <?php if ($cat['is_active']): ?>
                <span class="badge badge-success">Active</span>
              <?php else: ?>
                <span class="badge badge-error">Inactive</span>
              <?php endif; ?>
            </td>

            <!-- Actions -->
            <td style="text-align:right;">
              <div class="d-flex gap-2" style="justify-content:flex-end;">
                <!-- Edit button -->
                <button type="button" 
                        class="btn btn-secondary btn-sm"
                        onclick="editCategory(<?= htmlspecialchars(json_encode([
                          'id'          => (int)$cat['category_id'],
                          'name'        => $cat['name'],
                          'description' => $cat['description'] ?? '',
                          'order'       => (int)$cat['display_order'],
                          'is_active'   => (int)$cat['is_active']
                        ]), ENT_QUOTES, 'UTF-8') ?>)">
                  ✏️ Edit
                </button>

                <!-- Toggle Active/Inactive -->
                <form method="POST" action="<?= app_url('/menu/categories.php') ?>" style="margin:0;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="category_id" value="<?= (int)$cat['category_id'] ?>">
                  <input type="hidden" name="new_status" value="<?= $cat['is_active'] ? '0' : '1' ?>">
                  <button type="submit" 
                          class="btn btn-sm <?= $cat['is_active'] ? 'btn-ghost' : 'btn-primary' ?>"
                          title="<?= $cat['is_active'] ? 'Deactivate category' : 'Activate category' ?>">
                    <?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Create / Edit Category Modal Dialog -->
<div class="modal-backdrop" id="categoryModal" onclick="if(event.target===this) closeCategoryModal()">
  <div class="modal-card">
    <div class="modal-header">
      <h2 id="modalTitle" style="font-size:1.35rem;margin:0;">➕ Add New Category</h2>
      <button type="button" class="modal-close-btn" onclick="closeCategoryModal()" aria-label="Close modal">&times;</button>
    </div>

    <form method="POST" action="<?= app_url('/menu/categories.php') ?>" id="categoryForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="modalAction" value="create">
      <input type="hidden" name="category_id" id="modalCatId" value="">

      <!-- Category Name -->
      <div class="form-group" style="margin-bottom:var(--sp-4);">
        <label for="modalCatName" class="form-label" style="font-weight:600;">Category Name <span style="color:var(--clr-primary)">*</span></label>
        <input type="text" id="modalCatName" name="name" class="form-control" required placeholder="e.g. Seafood Specials" maxlength="80">
      </div>

      <!-- Description -->
      <div class="form-group" style="margin-bottom:var(--sp-4);">
        <label for="modalCatDesc" class="form-label" style="font-weight:600;">Description</label>
        <textarea id="modalCatDesc" name="description" rows="3" class="form-control" placeholder="Brief summary of dishes in this category..."></textarea>
      </div>

      <!-- Display Order -->
      <div class="form-group" style="margin-bottom:var(--sp-4);">
        <label for="modalCatOrder" class="form-label" style="font-weight:600;">Display Order</label>
        <input type="number" id="modalCatOrder" name="display_order" min="0" value="0" class="form-control">
        <span style="font-size:.75rem;color:var(--clr-text-muted);">Lower numbers display earlier on the menu tabs.</span>
      </div>

      <!-- Active Status -->
      <div style="display:flex;align-items:center;gap:var(--sp-3);margin-bottom:var(--sp-6);padding:var(--sp-3) var(--sp-4);background:var(--clr-surface);border-radius:var(--radius-md);">
        <input type="checkbox" id="modalCatActive" name="is_active" value="1" checked style="width:18px;height:18px;cursor:pointer;">
        <label for="modalCatActive" style="cursor:pointer;margin:0;font-size:.9rem;color:var(--clr-text);">
          Active (Visible on public menu)
        </label>
      </div>

      <!-- Actions -->
      <div class="d-flex gap-3" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Create Category</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('categoryModal');
const modalTitle = document.getElementById('modalTitle');
const modalAction = document.getElementById('modalAction');
const modalCatId = document.getElementById('modalCatId');
const modalCatName = document.getElementById('modalCatName');
const modalCatDesc = document.getElementById('modalCatDesc');
const modalCatOrder = document.getElementById('modalCatOrder');
const modalCatActive = document.getElementById('modalCatActive');
const modalSubmitBtn = document.getElementById('modalSubmitBtn');

function openCategoryModal() {
  modalTitle.textContent = '➕ Add New Category';
  modalAction.value = 'create';
  modalCatId.value = '';
  modalCatName.value = '';
  modalCatDesc.value = '';
  modalCatOrder.value = '0';
  modalCatActive.checked = true;
  modalSubmitBtn.textContent = 'Create Category';
  modal.classList.add('open');
  modalCatName.focus();
}

function editCategory(data) {
  modalTitle.textContent = '✏️ Edit Category: ' + data.name;
  modalAction.value = 'update';
  modalCatId.value = data.id;
  modalCatName.value = data.name;
  modalCatDesc.value = data.description || '';
  modalCatOrder.value = data.order;
  modalCatActive.checked = (data.is_active === 1);
  modalSubmitBtn.textContent = 'Save Changes';
  modal.classList.add('open');
  modalCatName.focus();
}

function closeCategoryModal() {
  modal.classList.remove('open');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
