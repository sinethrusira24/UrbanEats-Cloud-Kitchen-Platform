<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: 403 Forbidden / Access Denied Page
 */

declare(strict_types=1);

if (!headers_sent()) {
    http_response_code(403);
}

$pageTitle = '403 — Access Denied';
$customMessage = $customMessage ?? 'You do not have permission to view or execute actions on this resource.';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div style="max-width: 600px; margin: 3rem auto; text-align: center;">
    <div style="font-size: 4rem; line-height: 1; margin-bottom: 1rem;">🛡️</div>
    <span class="badge badge-inactive" style="font-size: 0.85rem; padding: 0.35rem 0.85rem; margin-bottom: 1rem;">
        HTTP 403 — Forbidden
    </span>
    <h1 style="font-size: 2.25rem; margin-bottom: 0.75rem; color: var(--secondary);">
        Access Denied
    </h1>
    <p style="font-size: 1.05rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 2rem;">
        <?= e($customMessage) ?>
    </p>

    <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
        <a href="<?= base_url('index.php') ?>" class="btn btn-primary">
            🏠 Return to Home
        </a>
        <?php if (!is_logged_in()): ?>
            <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline">
                🔑 Sign In with Authorized Account
            </a>
        <?php else: ?>
            <a href="<?= base_url('auth/profile.php') ?>" class="btn btn-outline">
                👤 Go to My Profile
            </a>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
