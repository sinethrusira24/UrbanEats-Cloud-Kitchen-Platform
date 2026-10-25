<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: 500 Internal Server Error Page
 */

declare(strict_types=1);

if (!headers_sent()) {
    http_response_code(500);
}

$pageTitle = '500 — Service Interruption';
// Check if header can be cleanly included
$headerPath = dirname(__DIR__, 2) . '/includes/header.php';
$footerPath = dirname(__DIR__, 2) . '/includes/footer.php';

if (file_exists($headerPath) && function_exists('base_url')) {
    require_once $headerPath;
} else {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>500 — Service Error</title><link rel="stylesheet" href="../css/style.css"></head><body><div class="container" style="padding-top: 3rem;">';
}
?>

<div style="max-width: 600px; margin: 3rem auto; text-align: center;">
    <div style="font-size: 4rem; line-height: 1; margin-bottom: 1rem;">⚙️</div>
    <span class="badge badge-inactive" style="font-size: 0.85rem; padding: 0.35rem 0.85rem; margin-bottom: 1rem;">
        HTTP 500 — Internal Server Error
    </span>
    <h1 style="font-size: 2.25rem; margin-bottom: 0.75rem; color: var(--secondary);">
        Service Interruption
    </h1>
    <p style="font-size: 1.05rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 2rem;">
        An unexpected application error occurred. For your security, raw technical diagnostics have been suppressed and safely recorded to the internal system log.
    </p>

    <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
        <a href="<?= function_exists('base_url') ? base_url('index.php') : '../index.php' ?>" class="btn btn-primary">
            Return to Home
        </a>
        <button type="button" class="btn btn-outline" onclick="location.reload();">
            Retry Request
        </button>
    </div>
</div>

<?php
if (file_exists($footerPath) && function_exists('base_url')) {
    require_once $footerPath;
} else {
    echo '</div></body></html>';
}
?>
