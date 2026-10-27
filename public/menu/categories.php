<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

require_role(['kitchen_staff', 'admin']);

$pdo = get_db_connection();
$userId = current_user_id();

$errors = [];
$editCategory = null;

// Check if an existing category is being loaded for editing
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId && $editId > 0) {
    $stmtFind = $pdo->prepare('SELECT category_id, name, description, display_order, is_active FROM categories WHERE category_id = :id');
    $stmtFind->execute([':id' => $editId]);
    $editCategory = $stmtFind->fetch() ?: null;
}

// ── Handle POST Mutations (Add, Edit, Toggle) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = trim((string)($_POST['action'] ?? 'save'));

    if ($action === 'toggle_status') {
        $catId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        if ($catId && $catId > 0) {
            $stmtToggle = $pdo->prepare('UPDATE categories SET is_active = NOT is_active, updated_at = NOW() WHERE category_id = :id');
            $stmtToggle->execute([':id' => $catId]);
            log_action('category_status_toggled', sprintf('Toggled active status for category #%d', $catId), $userId, $pdo);
            flash('success', 'Category status updated successfully.');
            redirect('menu/categories.php');
        }
    } elseif ($action === 'save') {
        $catId        = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null;
        $name         = trim((string)($_POST['name'] ?? ''));
        $description  = trim((string)($_POST['description'] ?? ''));
        $displayOrder = max(0, filter_input(INPUT_POST, 'display_order', FILTER_VALIDATE_INT) ?: 0);
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors['name'] = 'Category name must be between 2 and 80 characters.';
        } else {
            // Check duplicate name
            $dupSql = 'SELECT category_id FROM categories WHERE LOWER(name) = LOWER(:name)';
            $dupParams = [':name' => $name];
            if ($catId !== null) {
                $dupSql .= ' AND category_id != :id';
                $dupParams[':id'] = $catId;
            }
            $stmtDup = $pdo->prepare($dupSql);
            $stmtDup->execute($dupParams);
            if ($stmtDup->fetch()) {
                $errors['name'] = sprintf('A category named "%s" already exists.', $name);
            }
        }

        if (mb_strlen($description) > 255) {
            $errors['description'] = 'Description cannot exceed 255 characters.';
        }

        if (empty($errors)) {
            try {
                if ($catId !== null) {
                    // Update
                    $stmtUpdate = $pdo->prepare(
                        'UPDATE categories
                         SET name = :name, description = :description, display_order = :display_order, is_active = :is_active, updated_at = NOW()
                         WHERE category_id = :id'
                    );
                    $stmtUpdate->execute([
                        ':name'          => $name,
                        ':description'   => $description !== '' ? $description : null,
                        ':display_order' => $displayOrder,
                        ':is_active'     => $isActive,
                        ':id'            => $catId,
                    ]);

                    log_action('category_updated', sprintf('Updated category "%s" (#%d)', $name, $catId), $userId, $pdo);
                    flash('success', sprintf('Category "%s" updated successfully.', $name));
                } else {
                    // Insert
                    $stmtInsert = $pdo->prepare(
                        'INSERT INTO categories (name, description, display_order, is_active, created_at)
                         VALUES (:name, :description, :display_order, :is_active, NOW())'
                    );
                    $stmtInsert->execute([
                        ':name'          => $name,
                        ':description'   => $description !== '' ? $description : null,
                        ':display_order' => $displayOrder,
                        ':is_active'     => $isActive,
                    ]);
                    $newId = (int)$pdo->lastInsertId();

                    log_action('category_created', sprintf('Created new category "%s" (#%d)', $name, $newId), $userId, $pdo);
                    flash('success', sprintf('Category "%s" created successfully.', $name));
                }

                redirect('menu/categories.php');
            } catch (Throwable $e) {
                error_log('Category save failed: ' . $e->getMessage());
                $errors['general'] = 'Database error while saving category. Please try again.';
            }
        }
    }
}

// ── Fetch All Categories with Active & Inactive Dish Counts ────────────────
$categoriesList = $pdo->query(
    'SELECT c.category_id, c.name, c.description, c.display_order, c.is_active, c.created_at,
            COUNT(d.dish_id) AS total_dishes,
            SUM(CASE WHEN d.is_active = 1 THEN 1 ELSE 0 END) AS active_dishes
     FROM categories c
     LEFT JOIN dishes d ON c.category_id = d.category_id
     GROUP BY c.category_id, c.name, c.description, c.display_order, c.is_active, c.created_at
     ORDER BY c.display_order ASC, c.name ASC'
)->fetchAll();

$pageTitle = 'Category Management';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-b.css') . '">';

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div style="padding: 1rem 0 3rem;">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-kitchen_staff" style="font-size: 0.75rem; text-transform: uppercase;">Catalogue Taxonomy</span>
            <h1 style="font-size: 2rem; font-weight: 800; margin: 0.25rem 0 0; color: var(--text-main);">Category Management</h1>
            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.9375rem;">Create, reorder, and configure food categories displayed on the public menu.</p>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <a href="<?= base_url('menu/manage.php') ?>" class="btn btn-outline">
                Manage Dishes
            </a>
            <a href="<?= base_url('menu/stock.php') ?>" class="btn btn-outline">
                Stock Dashboard
            </a>
        </div>
    </div>

    <!-- 2-Column Layout: Form on Left, Table on Right -->
    <div style="display: grid; grid-template-columns: 340px 1fr; gap: 2rem; align-items: start;">

        <!-- Category Create/Edit Card -->
        <div class="card" style="padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0 0 1rem; color: var(--text-main);">
                <?= $editCategory ? 'Edit Category' : 'Add Category' ?>
            </h2>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger" role="alert" style="margin-bottom: 1rem;">
                    <?= e($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= base_url('menu/categories.php') ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editCategory): ?>
                    <input type="hidden" name="category_id" value="<?= (int)$editCategory['category_id'] ?>">
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="name" class="form-label">Category Name <span style="color: var(--danger);">*</span></label>
                    <input type="text"
                           name="name"
                           id="name"
                           class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                           value="<?= e($_POST['name'] ?? $editCategory['name'] ?? '') ?>"
                           placeholder="e.g. Biriyani & Ghee Rice"
                           required>
                    <?php if (isset($errors['name'])): ?>
                        <div style="color: var(--danger); font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="description" class="form-label">Description</label>
                    <textarea name="description"
                              id="description"
                              rows="2"
                              class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                              placeholder="Brief culinary summary..."><?= e($_POST['description'] ?? $editCategory['description'] ?? '') ?></textarea>
                    <?php if (isset($errors['description'])): ?>
                        <div style="color: var(--danger); font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['description']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="display_order" class="form-label">Display Order</label>
                    <input type="number"
                           name="display_order"
                           id="display_order"
                           min="0"
                           class="form-control"
                           value="<?= e((string)($_POST['display_order'] ?? $editCategory['display_order'] ?? '0')) ?>">
                    <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 0.25rem;">Lower numbers appear first on the menu.</div>
                </div>

                <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox"
                           name="is_active"
                           id="cat_active"
                           value="1"
                           <?= (!isset($editCategory) || (int)$editCategory['is_active'] === 1) ? 'checked' : '' ?>
                           style="width: 16px; height: 16px; accent-color: var(--primary);">
                    <label for="cat_active" style="font-size: 0.875rem; color: var(--text-main); cursor: pointer; margin: 0;">
                        Active (Visible in menu filter)
                    </label>
                </div>

                <div style="display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <?= $editCategory ? 'Update' : 'Create Category' ?>
                    </button>
                    <?php if ($editCategory): ?>
                        <a href="<?= base_url('menu/categories.php') ?>" class="btn btn-outline">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="card" style="padding: 0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Order</th>
                            <th>Category Name & Description</th>
                            <th>Dishes Count</th>
                            <th>Status</th>
                            <th style="text-align: right; width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categoriesList)): ?>
                            <tr>
                                <td colspan="5" class="text-center" style="padding: 3rem; color: var(--text-muted);">
                                    No categories currently created.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categoriesList as $c): ?>
                                <?php $isActive = (int)$c['is_active'] === 1; ?>
                                <tr style="<?= !$isActive ? 'background: #f8fafc; opacity: 0.75;' : '' ?>">
                                    <td style="font-weight: 700; color: var(--text-dim); font-feature-settings: 'tnum';">
                                        <?= (int)$c['display_order'] ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-main);">
                                            <?= e($c['name']) ?>
                                        </div>
                                        <div style="font-size: 0.8125rem; color: var(--text-muted);">
                                            <?= e($c['description'] ?? 'No description.') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?= base_url('menu/manage.php?cat=' . (int)$c['category_id']) ?>" style="text-decoration: none; font-weight: 600; color: var(--primary);">
                                            <?= (int)$c['active_dishes'] ?> active / <?= (int)$c['total_dishes'] ?> total
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 0.35rem;">
                                            <a href="<?= base_url('menu/categories.php?edit=' . (int)$c['category_id']) ?>"
                                               class="btn btn-outline btn-sm"
                                               title="Edit category">
                                                Edit
                                            </a>

                                            <!-- Toggle Status Mutation -->
                                            <form method="POST" action="<?= base_url('menu/categories.php') ?>" style="display: inline; margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="category_id" value="<?= (int)$c['category_id'] ?>">
                                                <button type="submit"
                                                        class="btn btn-outline btn-sm"
                                                        style="color: <?= $isActive ? 'var(--warning-text)' : 'var(--success)' ?>;"
                                                        title="<?= $isActive ? 'Deactivate category' : 'Activate category' ?>">
                                                    <?= $isActive ? 'Hide' : 'Show' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
