<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: 404 Not Found Page
 */

declare(strict_types=1);

if (!headers_sent()) {
    http_response_code(404);
}

$pageTitle = '404 — Page Not Found';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div style="max-width: 600px; margin: 3rem auto; text-align: center;">
    <div style="font-size: 4rem; line-height: 1; margin-bottom: 1rem;">🔍</div>
    <span class="badge badge-kitchen_staff" style="font-size: 0.85rem; padding: 0.35rem 0.85rem; margin-bottom: 1rem;">
        HTTP 404 — Not Found
    </span>
    <h1 style="font-size: 2.25rem; margin-bottom: 0.75rem; color: var(--secondary);">
        Page Not Found
    </h1>
    <p style="font-size: 1.05rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 2rem;">
        The requested page or resource could not be located on the UrbanEats platform. It may have moved or been temporarily unavailable.
    </p>

    <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
        <a href="<?= base_url('index.php') ?>" class="btn btn-primary">
            Return to Home
        </a>
        <button type="button" class="btn btn-outline" onclick="history.back();">
            &larr; Go Back
        </button>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
