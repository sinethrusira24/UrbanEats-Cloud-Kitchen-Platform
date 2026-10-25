<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component B: Menu, Catalogue and Inventory
 *
 * Add & Edit Dish Form with Multi-Tier Secure Image Upload
 *
 * Requirements:
 * - Dual Mode: CREATE and UPDATE
 * - Multi-tier Image Validation:
 *   - MIME type inspection via PHP Fileinfo (image/jpeg, image/png, image/webp)
 *   - File extension whitelist check (.jpg, .jpeg, .png, .webp)
 *   - Image content verification via getimagesize()
 *   - Strict size limit enforcement (max 3 MB)
 *   - Cryptographically random filename generation (bin2hex(random_bytes(16)))
 *   - Secure storage in non-executable uploads/dishes/ directory
 * - Minimal vanilla JavaScript live preview
 * - Transactional creation linking dish, inventory, and initial stock movements
 * - CSRF verification and audit logging via Component A's log_action()
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/stock.php';
require_once dirname(__DIR__, 2) . '/includes/component_b.php';

require_role(['kitchen_staff', 'admin']);

$pdo = get_db_connection();

$dishId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$isEdit = ($dishId !== null && $dishId > 0);

$dish = null;
if ($isEdit) {
    $dish = get_dish_details($dishId, $pdo);
    if (!$dish) {
        flash('danger', 'The requested dish does not exist.');
        redirect('menu/manage.php');
    }
}

// Fetch available categories for dropdown
$categories = $pdo->query('SELECT category_id, name FROM categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC')->fetchAll();

$errors = [];
$formData = [
    'name'          => $dish['name'] ?? '',
    'category_id'   => $dish['category_id'] ?? '',
    'description'   => $dish['description'] ?? '',
    'price'         => $dish['price'] ?? '',
    'initial_stock' => $dish['qty_available'] ?? '20',
    'reorder_level' => $dish['reorder_level'] ?? '5',
    'is_active'     => $dish['is_active'] ?? '1',
];

// ── Handle POST Submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $formData['name']          = trim((string)($_POST['name'] ?? ''));
    $formData['category_id']   = (int)($_POST['category_id'] ?? 0);
    $formData['description']   = trim((string)($_POST['description'] ?? ''));
    $formData['price']         = trim((string)($_POST['price'] ?? ''));
    $formData['initial_stock'] = (int)($_POST['initial_stock'] ?? 0);
    $formData['reorder_level'] = (int)($_POST['reorder_level'] ?? 5);
    $formData['is_active']     = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if ($formData['name'] === '' || mb_strlen($formData['name']) < 2 || mb_strlen($formData['name']) > 120) {
        $errors['name'] = 'Dish name must be between 2 and 120 characters.';
    }

    if ($formData['category_id'] <= 0) {
        $errors['category_id'] = 'Please select a valid category.';
    }

    $priceFloat = filter_var($formData['price'], FILTER_VALIDATE_FLOAT);
    if ($priceFloat === false || $priceFloat <= 0) {
        $errors['price'] = 'Price must be a valid positive amount in LKR.';
    }

    if (!$isEdit && $formData['initial_stock'] < 0) {
        $errors['initial_stock'] = 'Initial stock cannot be negative.';
    }

    if ($formData['reorder_level'] < 1) {
        $errors['reorder_level'] = 'Reorder level must be at least 1 portion.';
    }

    // ── Secure Image Upload Handling ───────────────────────────────────────
    $newImagePath = null;
    if (isset($_FILES['dish_image']) && $_FILES['dish_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['dish_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['image'] = 'File upload error occurred. Please try again.';
        } else {
            // Check size (Max 3 MB)
            $maxBytes = 3 * 1024 * 1024;
            if ($file['size'] > $maxBytes) {
                $errors['image'] = 'Image size exceeds the maximum allowed limit of 3 MB.';
            } else {
                // Check MIME type using PHP Fileinfo
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
                if ($finfo) finfo_close($finfo);

                $allowedMimes = [
                    'image/jpeg' => 'jpg',
                    'image/pjpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

                if (!array_key_exists($mimeType, $allowedMimes) || !in_array($ext, $allowedExts, true)) {
                    $errors['image'] = 'Invalid image format. Only JPG, PNG, and WEBP images are permitted.';
                } else {
                    // Check actual image dimensions
                    $imageInfo = @getimagesize($file['tmp_name']);
                    if ($imageInfo === false) {
                        $errors['image'] = 'The uploaded file is not a valid image.';
                    } else {
                        // Generate unpredictable cryptographic filename
                        $targetDir = dirname(__DIR__) . '/uploads/dishes';
                        if (!is_dir($targetDir)) {
                            @mkdir($targetDir, 0755, true);
                        }

                        $safeExt = $allowedMimes[$mimeType];
                        $randomFilename = bin2hex(random_bytes(16)) . '.' . $safeExt;
                        $destinationPath = $targetDir . '/' . $randomFilename;

                        if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
                            $newImagePath = 'uploads/dishes/' . $randomFilename;
                        } else {
                            $errors['image'] = 'Failed to store uploaded image on the server.';
                        }
                    }
                }
            }
        }
    }

    // ── Execute Database Changes if Validation Passed ──────────────────────
    if (empty($errors)) {
        $userId = current_user_id();

        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                // Update Existing Dish
                $updateFields = [
                    'name = :name',
                    'category_id = :category_id',
                    'description = :description',
                    'price = :price',
                    'is_active = :is_active',
                ];
                $params = [
                    ':name'        => $formData['name'],
                    ':category_id' => $formData['category_id'],
                    ':description' => $formData['description'],
                    ':price'       => $priceFloat,
                    ':is_active'   => $formData['is_active'],
                    ':dish_id'     => $dishId,
                ];

                if ($newImagePath !== null) {
                    $updateFields[] = 'image_path = :image_path';
                    $params[':image_path'] = $newImagePath;
                }

                $stmtDishUpdate = $pdo->prepare('UPDATE dishes SET ' . implode(', ', $updateFields) . ' WHERE dish_id = :dish_id');
                $stmtDishUpdate->execute($params);

                // Update inventory reorder level
                $stmtInvUpd = $pdo->prepare('UPDATE inventory SET reorder_level = :reorder WHERE dish_id = :dish_id');
                $stmtInvUpd->execute([
                    ':reorder' => $formData['reorder_level'],
                    ':dish_id' => $dishId,
                ]);

                log_action('dish_updated', sprintf('Updated dish "%s" (ID #%d)', $formData['name'], $dishId), $userId, $pdo);

                $pdo->commit();
                flash('success', sprintf('Dish "%s" updated successfully.', $formData['name']));
                redirect('menu/manage.php');
            } else {
                // Insert New Dish
                $stmtDishInsert = $pdo->prepare(
                    'INSERT INTO dishes (category_id, name, description, price, image_path, is_active, created_at)
                     VALUES (:category_id, :name, :description, :price, :image_path, :is_active, NOW())'
                );
                $stmtDishInsert->execute([
                    ':category_id' => $formData['category_id'],
                    ':name'        => $formData['name'],
                    ':description' => $formData['description'],
                    ':price'       => $priceFloat,
                    ':image_path'  => $newImagePath,
                    ':is_active'   => $formData['is_active'],
                ]);
                $newDishId = (int)$pdo->lastInsertId();

                // Create initial inventory record
                $stmtInvInsert = $pdo->prepare(
                    'INSERT INTO inventory (dish_id, qty_available, reorder_level)
                     VALUES (:dish_id, :qty, :reorder)'
                );
                $stmtInvInsert->execute([
                    ':dish_id' => $newDishId,
                    ':qty'     => $formData['initial_stock'],
                    ':reorder' => $formData['reorder_level'],
                ]);

                // Record initial stock movement
                if ($formData['initial_stock'] > 0) {
                    $stmtMove = $pdo->prepare(
                        'INSERT INTO stock_movements (dish_id, user_id, movement_type, change_qty, previous_stock, updated_stock, reason, moved_at)
                         VALUES (:dish_id, :user_id, :type, :change_qty, :prev_stock, :upd_stock, :reason, NOW())'
                    );
                    $stmtMove->execute([
                        ':dish_id'    => $newDishId,
                        ':user_id'    => $userId,
                        ':type'       => 'Initial',
                        ':change_qty' => $formData['initial_stock'],
                        ':prev_stock' => 0,
                        ':upd_stock'  => $formData['initial_stock'],
                        ':reason'     => 'Initial stock provision upon dish creation',
                    ]);
                }

                log_action('dish_created', sprintf('Created new dish "%s" (ID #%d)', $formData['name'], $newDishId), $userId, $pdo);

                $pdo->commit();
                flash('success', sprintf('Dish "%s" added to menu successfully.', $formData['name']));
                redirect('menu/manage.php');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Failed saving dish: ' . $e->getMessage());
            $errors['general'] = 'An unexpected database error occurred while saving the dish. Please try again.';
        }
    }
}

$pageTitle = $isEdit ? 'Edit Dish' : 'Add New Dish';
$extraHead = '<link rel="stylesheet" href="' . base_url('css/component-b.css') . '">';

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container" style="max-width: 760px; padding: 2rem 0 4rem;">

    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="margin-bottom: 1.5rem; font-size: 0.875rem;">
        <a href="<?= base_url('menu/manage.php') ?>" style="color: var(--text-muted); text-decoration: none;">&larr; Back to Catalogue</a>
    </nav>

    <div class="card" style="padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04);">
        <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 2rem;">
            <span class="badge badge-kitchen_staff" style="font-size: 0.75rem; text-transform: uppercase;">Kitchen Staff Portal</span>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0.35rem 0 0; color: var(--text-main);">
                <?= $isEdit ? 'Edit Dish: ' . e($dish['name']) : 'Add New Menu Item' ?>
            </h1>
            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">
                <?= $isEdit ? 'Update culinary specifications, pricing, and portion availability.' : 'Configure a fresh dish offering, link inventory, and upload high-resolution imagery.' ?>
            </p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger" role="alert" style="margin-bottom: 1.5rem;">
                <?= e($errors['general']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>

            <!-- Dish Name -->
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="name" class="form-label">Dish Name <span style="color: var(--danger);">*</span></label>
                <input type="text"
                       name="name"
                       id="name"
                       class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       value="<?= e($formData['name']) ?>"
                       placeholder="e.g. Royal Chicken Biryani"
                       required>
                <?php if (isset($errors['name'])): ?>
                    <div style="color: var(--danger); font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Category & Price Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label for="category_id" class="form-label">Category <span style="color: var(--danger);">*</span></label>
                    <select name="category_id"
                            id="category_id"
                            class="form-control <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>"
                            required>
                        <option value="">-- Choose Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['category_id'] ?>" <?= (int)$formData['category_id'] === (int)$cat['category_id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?>
                        <div style="color: var(--danger); font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['category_id']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="price" class="form-label">Price in LKR <span style="color: var(--danger);">*</span></label>
                    <input type="number"
                           name="price"
                           id="price"
                           step="0.01"
                           min="1"
                           class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>"
                           value="<?= e($formData['price']) ?>"
                           placeholder="1650.00"
                           required>
                    <?php if (isset($errors['price'])): ?>
                        <div style="color: var(--danger); font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['price']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="description" class="form-label">Description & Ingredients</label>
                <textarea name="description"
                          id="description"
                          rows="3"
                          class="form-control"
                          placeholder="Describe the dish ingredients, preparation style, and portion size..."><?= e($formData['description']) ?></textarea>
            </div>

            <!-- Inventory Settings Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem; background: var(--bg-subtle); padding: 1.25rem; border-radius: 10px; border: 1px solid var(--border-color);">
                <?php if (!$isEdit): ?>
                    <div class="form-group">
                        <label for="initial_stock" class="form-label">Initial Portions Ready <span style="color: var(--danger);">*</span></label>
                        <input type="number"
                               name="initial_stock"
                               id="initial_stock"
                               min="0"
                               class="form-control <?= isset($errors['initial_stock']) ? 'is-invalid' : '' ?>"
                               value="<?= e($formData['initial_stock']) ?>"
                               required>
                        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 0.25rem;">Initial inventory balance created in stock log.</div>
                        <?php if (isset($errors['initial_stock'])): ?>
                            <div style="color: var(--danger); font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['initial_stock']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div>
                        <div style="font-size: 0.8125rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem;">Current Stock Balance</div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: var(--primary);">
                            <?= (int)$dish['qty_available'] ?> portions
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-dim);">To adjust quantities, use the <a href="<?= base_url('menu/stock.php') ?>">Stock Dashboard</a>.</div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="reorder_level" class="form-label">Low Stock Reorder Threshold <span style="color: var(--danger);">*</span></label>
                    <input type="number"
                           name="reorder_level"
                           id="reorder_level"
                           min="1"
                           class="form-control <?= isset($errors['reorder_level']) ? 'is-invalid' : '' ?>"
                           value="<?= e($formData['reorder_level']) ?>"
                           required>
                    <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 0.25rem;">Alerts kitchen when portions fall to or below this.</div>
                    <?php if (isset($errors['reorder_level'])): ?>
                        <div style="color: var(--danger); font-size: 0.8125rem; margin-top: 0.25rem;"><?= e($errors['reorder_level']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Secure Image Upload Dropzone -->
            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="form-label">Dish Presentation Image (JPG, PNG, WEBP &bull; Max 3 MB)</label>

                <div class="image-dropzone" id="dropzone" onclick="document.getElementById('dish_image').click();">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📸</div>
                    <div style="font-weight: 600; font-size: 0.9375rem; color: var(--text-main);">Click or drag & drop to upload image</div>
                    <div style="font-size: 0.8125rem; color: var(--text-dim); margin-top: 0.25rem;">Files are verified by PHP MIME inspection and stored securely.</div>

                    <input type="file"
                           name="dish_image"
                           id="dish_image"
                           accept="image/jpeg,image/png,image/webp"
                           style="display: none;"
                           onchange="previewImage(this)">

                    <!-- Live Image Preview Box -->
                    <div class="image-preview-box" id="previewBox" style="<?= $isEdit && !empty($dish['image_path']) ? 'display: block;' : '' ?>">
                        <img id="previewImg"
                             src="<?= $isEdit && !empty($dish['image_path']) ? base_url($dish['image_path']) : '' ?>"
                             alt="Image Preview">
                    </div>
                </div>

                <?php if (isset($errors['image'])): ?>
                    <div style="color: var(--danger); font-size: 0.8125rem; margin-top: 0.5rem;"><?= e($errors['image']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Active Status Checkbox -->
            <div style="margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px;">
                <input type="checkbox"
                       name="is_active"
                       id="is_active"
                       value="1"
                       <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>
                       style="width: 18px; height: 18px; accent-color: var(--primary);">
                <label for="is_active" style="font-weight: 600; font-size: 0.875rem; color: var(--text-main); margin: 0; cursor: pointer;">
                    Active in Public Catalogue (Unchecking hides this dish from customers)
                </label>
            </div>

            <!-- Submit Button Row -->
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="<?= base_url('menu/manage.php') ?>" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <?= $isEdit ? 'Update Dish' : 'Save & Publish Dish' ?>
                </button>
            </div>
        </form>
    </div>

</div>

<!-- Minimal Vanilla JavaScript for Live Image Preview -->
<script>
function previewImage(input) {
    const previewBox = document.getElementById('previewBox');
    const previewImg = document.getElementById('previewImg');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Client-side quick type check
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            alert('Please select a JPG, PNG, or WEBP image.');
            input.value = '';
            return;
        }

        // Client-side quick size check
        if (file.size > 3 * 1024 * 1024) {
            alert('Image exceeds 3 MB limit.');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewBox.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
