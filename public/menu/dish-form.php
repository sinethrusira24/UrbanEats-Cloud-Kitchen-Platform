<?php
/**
 * dish-form.php – Component B: Add & Edit Dish with Secure Image Upload & Preview.
 *
 * Restricted to Kitchen Staff & Administrator.
 * Full server-side validation, CSRF verification, and file upload security.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/stock.php';

require_role(['Kitchen Staff', 'Administrator']);

$db = get_db();
$dishId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEditing = ($dishId !== null && $dishId > 0);

// Load categories for dropdown
$categories = $db->query(
    'SELECT category_id, name FROM categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC'
)->fetchAll();

// Existing dish details if in edit mode
$dish = null;
if ($isEditing) {
    $dish = get_dish($dishId, false);
    if (!$dish) {
        flash('error', 'The requested dish was not found.');
        redirect(app_url('/menu/manage.php'));
    }
}

$pageTitle = $isEditing ? "Edit Dish: {$dish['name']}" : 'Add New Dish';

// Form state variables
$name         = $dish['name'] ?? '';
$categoryId   = $dish['category_id'] ?? ($categories[0]['category_id'] ?? 1);
$price        = $dish['price'] ?? '';
$description  = $dish['description'] ?? '';
$isActive     = isset($dish['is_active']) ? (int)$dish['is_active'] : 1;
$initialStock = 20;
$reorderLevel = $dish['reorder_level'] ?? 5;
$errors       = [];

// ── Handle Form Submission (POST) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // 1. Sanitize & Validate Text Inputs
    $name         = sanitise_string($_POST['name'] ?? '');
    $categoryId   = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $priceRaw     = trim($_POST['price'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $isActive     = isset($_POST['is_active']) ? 1 : 0;
    $reorderLevel = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_INT);
    if ($reorderLevel === false || $reorderLevel < 0) {
        $reorderLevel = 5;
    }

    if ($name === '') {
        $errors['name'] = 'Dish name is required.';
    } elseif (mb_strlen($name) > 120) {
        $errors['name'] = 'Dish name cannot exceed 120 characters.';
    }

    $validCategoryIds = array_column($categories, 'category_id');
    if (!$categoryId || !in_array($categoryId, $validCategoryIds, true)) {
        $errors['category_id'] = 'Please select a valid menu category.';
    }

    if ($priceRaw === '' || !is_numeric($priceRaw)) {
        $errors['price'] = 'Price must be a valid number.';
    } else {
        $priceFloat = (float)$priceRaw;
        if ($priceFloat <= 0) {
            $errors['price'] = 'Price must be greater than zero.';
        } elseif ($priceFloat > 99999.99) {
            $errors['price'] = 'Price exceeds maximum allowed limit.';
        }
    }

    if (!$isEditing) {
        $initialStock = filter_input(INPUT_POST, 'initial_stock', FILTER_VALIDATE_INT);
        if ($initialStock === false || $initialStock < 0) {
            $errors['initial_stock'] = 'Initial stock must be zero or a positive whole number.';
        }
    }

    // 2. Handle Secure Image Upload
    $imagePath = $dish['image_path'] ?? null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = handle_dish_image_upload($_FILES['image'], $imagePath);
        if (!$uploadResult['success']) {
            $errors['image'] = $uploadResult['error'];
        } else {
            $imagePath = $uploadResult['path'];
        }
    }

    // 3. Save to Database if Validation Passes
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            if ($isEditing) {
                // Update Dish
                $upStmt = $db->prepare(
                    'UPDATE dishes 
                     SET category_id = :cat_id,
                         name        = :name,
                         description = :desc,
                         price       = :price,
                         image_path  = :img,
                         is_active   = :active
                     WHERE dish_id   = :dish_id'
                );
                $upStmt->execute([
                    ':cat_id'  => $categoryId,
                    ':name'    => $name,
                    ':desc'    => $description ?: null,
                    ':price'   => $priceFloat,
                    ':img'     => $imagePath,
                    ':active'  => $isActive,
                    ':dish_id' => $dishId,
                ]);

                // Update Reorder Level in Inventory
                $invUp = $db->prepare('UPDATE inventory SET reorder_level = :reorder WHERE dish_id = :dish_id');
                $invUp->execute([
                    ':reorder' => $reorderLevel,
                    ':dish_id' => $dishId,
                ]);

                log_action('dish_updated', json_encode([
                    'dish_id' => $dishId,
                    'name'    => $name,
                    'price'   => $priceFloat,
                ]));

                $db->commit();
                flash('success', "Dish &ldquo;{$name}&rdquo; was updated successfully.");
                redirect(app_url('/menu/manage.php'));

            } else {
                // Insert New Dish
                $inStmt = $db->prepare(
                    'INSERT INTO dishes (category_id, name, description, price, image_path, is_active)
                     VALUES (:cat_id, :name, :desc, :price, :img, :active)'
                );
                $inStmt->execute([
                    ':cat_id' => $categoryId,
                    ':name'   => $name,
                    ':desc'   => $description ?: null,
                    ':price'  => $priceFloat,
                    ':img'    => $imagePath,
                    ':active' => $isActive,
                ]);
                $newDishId = (int)$db->lastInsertId();

                // Insert into Inventory
                $invIn = $db->prepare(
                    'INSERT INTO inventory (dish_id, qty_available, reorder_level)
                     VALUES (:dish_id, :qty, :reorder)'
                );
                $invIn->execute([
                    ':dish_id' => $newDishId,
                    ':qty'     => $initialStock,
                    ':reorder' => $reorderLevel,
                ]);

                // Record initial stock movement
                if ($initialStock > 0) {
                    $smStmt = $db->prepare(
                        'INSERT INTO stock_movements (dish_id, user_id, change_qty, reason)
                         VALUES (:dish_id, :user_id, :qty, :reason)'
                    );
                    $smStmt->execute([
                        ':dish_id' => $newDishId,
                        ':user_id' => $_SESSION['user_id'] ?? null,
                        ':qty'     => $initialStock,
                        ':reason'  => 'Initial stock setup upon dish creation',
                    ]);
                }

                log_action('dish_created', json_encode([
                    'dish_id'       => $newDishId,
                    'name'          => $name,
                    'initial_stock' => $initialStock,
                ]));

                $db->commit();
                flash('success', "New dish &ldquo;{$name}&rdquo; has been added to the catalogue.");
                redirect(app_url('/menu/manage.php'));
            }

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[UrbanEats] Dish save error: ' . $e->getMessage());
            $errors['general'] = 'Failed to save dish due to a database error. Please try again.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="container" style="padding:var(--sp-8) 0 var(--sp-16);">

  <!-- Breadcrumb -->
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= app_url('/menu/manage.php') ?>">Dishes Management</a>
    <span class="breadcrumb-separator" aria-hidden="true">›</span>
    <span style="color:var(--clr-text)" aria-current="page"><?= $isEditing ? 'Edit Dish' : 'Add New Dish' ?></span>
  </nav>

  <div style="max-width:760px;margin:0 auto;">

    <!-- Header -->
    <div style="margin-bottom:var(--sp-8);">
      <h1 style="font-size:2rem;margin-bottom:var(--sp-2);">
        <?= $isEditing ? '✏️ Edit Dish Details' : '➕ Create New Menu Dish' ?>
      </h1>
      <p style="color:var(--clr-text-muted);">
        <?= $isEditing ? 'Update pricing, imagery, category, or availability status for this dish.' : 'Add a new mouth-watering dish to the UrbanEats cloud kitchen catalogue.' ?>
      </p>
    </div>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert-error" style="margin-bottom:var(--sp-6);">
        <?= e($errors['general']) ?>
      </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="card" style="padding:var(--sp-8);">
      <form method="POST" action="<?= e($_SERVER['REQUEST_URI']) ?>" enctype="multipart/form-data" novalidate id="dishForm">
        <?= csrf_field() ?>

        <!-- Dish Name -->
        <div class="form-group" style="margin-bottom:var(--sp-6);">
          <label for="name" class="form-label" style="font-weight:600;">Dish Name <span style="color:var(--clr-primary)">*</span></label>
          <input type="text" 
                 id="name" 
                 name="name" 
                 class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                 value="<?= e($name) ?>" 
                 placeholder="e.g. Seafood Cheese Kottu" 
                 required
                 maxlength="120">
          <?php if (isset($errors['name'])): ?>
            <div style="color:var(--clr-error);font-size:.8125rem;margin-top:4px;"><?= e($errors['name']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Category & Price Two-Column Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-6);margin-bottom:var(--sp-6);">
          
          <!-- Category -->
          <div class="form-group">
            <label for="category_id" class="form-label" style="font-weight:600;">Category <span style="color:var(--clr-primary)">*</span></label>
            <select id="category_id" 
                    name="category_id" 
                    class="form-control <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>"
                    required>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>" <?= (int)$categoryId === (int)$cat['category_id'] ? 'selected' : '' ?>>
                  <?= e($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category_id'])): ?>
              <div style="color:var(--clr-error);font-size:.8125rem;margin-top:4px;"><?= e($errors['category_id']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Price (LKR) -->
          <div class="form-group">
            <label for="price" class="form-label" style="font-weight:600;">Price (LKR) <span style="color:var(--clr-primary)">*</span></label>
            <input type="number" 
                   id="price" 
                   name="price" 
                   step="0.01" 
                   min="0.01" 
                   class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>"
                   value="<?= e($price) ?>" 
                   placeholder="e.g. 650.00" 
                   required>
            <?php if (isset($errors['price'])): ?>
              <div style="color:var(--clr-error);font-size:.8125rem;margin-top:4px;"><?= e($errors['price']) ?></div>
            <?php endif; ?>
          </div>

        </div>

        <!-- Description -->
        <div class="form-group" style="margin-bottom:var(--sp-6);">
          <label for="description" class="form-label" style="font-weight:600;">Culinary Description</label>
          <textarea id="description" 
                    name="description" 
                    rows="4" 
                    class="form-control" 
                    placeholder="Describe the dish ingredients, preparation style, and flavor profile..."><?= e($description) ?></textarea>
        </div>

        <!-- Stock Settings -->
        <div style="background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-md);padding:var(--sp-5);margin-bottom:var(--sp-6);">
          <h3 style="font-size:1rem;margin-bottom:var(--sp-3);display:flex;align-items:center;gap:var(--sp-2);">
            <span>📦</span> Inventory & Stock Configuration
          </h3>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp-6);">
            <?php if (!$isEditing): ?>
              <div class="form-group">
                <label for="initial_stock" class="form-label" style="font-weight:600;">Initial Stock Quantity <span style="color:var(--clr-primary)">*</span></label>
                <input type="number" 
                       id="initial_stock" 
                       name="initial_stock" 
                       min="0" 
                       class="form-control <?= isset($errors['initial_stock']) ? 'is-invalid' : '' ?>"
                       value="<?= e($initialStock) ?>" 
                       required>
                <span style="font-size:.75rem;color:var(--clr-text-muted);">Number of portions ready to order.</span>
                <?php if (isset($errors['initial_stock'])): ?>
                  <div style="color:var(--clr-error);font-size:.8125rem;margin-top:4px;"><?= e($errors['initial_stock']) ?></div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div>
                <label class="form-label" style="font-weight:600;">Current Stock Level</label>
                <div style="display:flex;align-items:center;gap:var(--sp-3);padding-top:var(--sp-2);">
                  <?= render_stock_badge((int)$dish['qty_available'], (int)$dish['reorder_level'], true) ?>
                  <a href="<?= app_url('/menu/stock.php?q=' . urlencode($dish['name'])) ?>" class="btn btn-ghost btn-sm" style="font-size:.8rem;">
                    Adjust in Dashboard &rarr;
                  </a>
                </div>
              </div>
            <?php endif; ?>

            <div class="form-group">
              <label for="reorder_level" class="form-label" style="font-weight:600;">Low Stock Alert Threshold</label>
              <input type="number" 
                     id="reorder_level" 
                     name="reorder_level" 
                     min="0" 
                     class="form-control"
                     value="<?= e($reorderLevel) ?>">
              <span style="font-size:.75rem;color:var(--clr-text-muted);">Trigger low-stock warning when stock falls to this number.</span>
            </div>
          </div>
        </div>

        <!-- Secure Image Upload & Instant Preview -->
        <div class="form-group" style="margin-bottom:var(--sp-8);">
          <label class="form-label" style="font-weight:600;">Dish Photo</label>
          <span style="font-size:.8125rem;color:var(--clr-text-muted);display:block;margin-bottom:var(--sp-2);">
            Upload JPG, PNG, or WebP (max 2MB). Our system cryptographically validates and renames all files.
          </span>

          <div class="upload-dropzone" id="uploadDropzone" onclick="document.getElementById('imageInput').click()">
            <input type="file" 
                   id="imageInput" 
                   name="image" 
                   accept="image/jpeg,image/png,image/webp" 
                   style="display:none;" 
                   onchange="handleImageSelection(this)">
            
            <div style="font-size:2rem;margin-bottom:var(--sp-2);">📸</div>
            <div style="font-weight:600;color:var(--clr-text);margin-bottom:var(--sp-1);">
              Click to browse or drag & drop dish image here
            </div>
            <div style="font-size:.8125rem;color:var(--clr-text-subtle);">
              Supported: JPEG, PNG, WebP &bull; Max 2MB
            </div>
          </div>

          <!-- Existing or Selected Image Preview -->
          <div id="imagePreviewContainer" class="upload-preview-container" style="<?= !empty($dish['image_path']) ? '' : 'display:none;' ?>">
            <img id="imagePreviewImg" 
                 src="<?= !empty($dish['image_path']) ? app_url('/' . ltrim($dish['image_path'], '/')) : '' ?>" 
                 alt="Image preview" 
                 class="upload-preview-thumb">
            
            <div style="flex:1;">
              <div id="imagePreviewName" style="font-weight:600;font-size:.9rem;">
                <?= !empty($dish['image_path']) ? basename($dish['image_path']) : 'Selected Image' ?>
              </div>
              <div style="font-size:.8rem;color:var(--clr-text-muted);">
                <?= !empty($dish['image_path']) ? 'Current image on server' : 'Ready for upload' ?>
              </div>
            </div>

            <button type="button" class="btn btn-ghost btn-sm" onclick="clearSelectedImage(event)" title="Clear / change image">
              ✕
            </button>
          </div>

          <?php if (isset($errors['image'])): ?>
            <div style="color:var(--clr-error);font-size:.8125rem;margin-top:var(--sp-2);"><?= e($errors['image']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Active Status Toggle -->
        <div style="display:flex;align-items:center;gap:var(--sp-3);margin-bottom:var(--sp-8);padding:var(--sp-4);background:var(--clr-bg-input);border-radius:var(--radius-md);border:1px solid var(--clr-border);">
          <input type="checkbox" 
                 id="is_active" 
                 name="is_active" 
                 value="1" 
                 <?= $isActive ? 'checked' : '' ?> 
                 style="width:20px;height:20px;cursor:pointer;">
          <label for="is_active" style="cursor:pointer;margin:0;">
            <strong style="color:var(--clr-text);">Active on Customer Menu</strong>
            <div style="font-size:.8125rem;color:var(--clr-text-muted);">
              When checked, this dish is immediately visible and orderable by customers.
            </div>
          </label>
        </div>

        <!-- Form Action Buttons -->
        <div class="d-flex gap-4" style="justify-content:flex-end;">
          <a href="<?= app_url('/menu/manage.php') ?>" class="btn btn-secondary">
            Cancel
          </a>
          <button type="submit" class="btn btn-primary btn-lg">
            💾 <?= $isEditing ? 'Save Changes' : 'Create Dish' ?>
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<!-- Image Preview & Drag-and-Drop JavaScript -->
<script>
const dropzone = document.getElementById('uploadDropzone');
const fileInput = document.getElementById('imageInput');
const previewContainer = document.getElementById('imagePreviewContainer');
const previewImg = document.getElementById('imagePreviewImg');
const previewName = document.getElementById('imagePreviewName');

// Drag and drop event handlers
['dragenter', 'dragover'].forEach(name => {
  dropzone.addEventListener(name, (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropzone.classList.add('dragover');
  });
});

['dragleave', 'drop'].forEach(name => {
  dropzone.addEventListener(name, (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropzone.classList.remove('dragover');
  });
});

dropzone.addEventListener('drop', (e) => {
  if (e.dataTransfer.files && e.dataTransfer.files[0]) {
    fileInput.files = e.dataTransfer.files;
    handleImageSelection(fileInput);
  }
});

function handleImageSelection(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    
    // Quick client-side size check (2MB)
    if (file.size > 2 * 1024 * 1024) {
      alert('Selected image exceeds the 2MB limit. Please select a smaller photo.');
      input.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
      previewImg.src = e.target.result;
      previewName.textContent = file.name;
      previewContainer.style.display = 'flex';
    };
    reader.readAsDataURL(file);
  }
}

function clearSelectedImage(e) {
  e.preventDefault();
  e.stopPropagation();
  fileInput.value = '';
  previewContainer.style.display = 'none';
  previewImg.src = '';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
