<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

$appConfig = app_config('app');
$pageTitle = 'Settings';
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header"><div><h2>Settings</h2><p>Environment and maintenance overview for this installation.</p></div></section>
<section class="grid two-column">
    <article class="card">
        <h3>Application</h3>
        <ul class="stack compact">
            <li>Environment: <strong><?= e((string) ($appConfig['env'] ?? 'production')) ?></strong></li>
            <li>Debug Mode: <strong><?= !empty($appConfig['debug']) ? 'Enabled' : 'Disabled' ?></strong></li>
            <li>Profile Cache TTL: <strong><?= e((string) ($appConfig['cache']['profile_seconds'] ?? 300)) ?>s</strong></li>
            <li>Bazaar Cache TTL: <strong><?= e((string) ($appConfig['cache']['bazaar_seconds'] ?? 60)) ?>s</strong></li>
        </ul>
    </article>
    <article class="card">
        <h3>Storage</h3>
        <ul class="stack compact">
            <li>Cache Path: <code><?= e(APP_ROOT . '/storage/cache') ?></code></li>
            <li>Log Path: <code><?= e(APP_ROOT . '/storage/logs') ?></code></li>
            <li>Rotate or archive logs externally if syncing frequently.</li>
        </ul>
    </article>
</section>
<?php require __DIR__ . '/layout/footer.php'; ?>
