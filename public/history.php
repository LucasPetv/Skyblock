<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

use SkyBlock\Models\Account;
use SkyBlock\Models\Profile;

$database = db();
$accountModel = new Account($database);
$profileModel = new Profile($database);
$accounts = $accountModel->findAll();
$selectedAccountId = selected_account_id() ?? (isset($accounts[0]['id']) ? (int) $accounts[0]['id'] : null);
$profiles = $selectedAccountId ? $profileModel->findByAccountId($selectedAccountId) : [];
$selectedProfileId = selected_profile_id() ?? (isset($profiles[0]['id']) ? (int) $profiles[0]['id'] : null);
$history = $selectedProfileId ? $database->fetchAll('SELECT * FROM profile_snapshots WHERE profile_id = :profile_id ORDER BY timestamp ASC LIMIT 60', ['profile_id' => $selectedProfileId]) : [];

$pageTitle = 'History';
$pageScripts = ['assets/js/dashboard.js'];
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header"><div><h2>History</h2><p>Review snapshot history and trend lines for your selected profile.</p></div></section>
<?php if (!$selectedProfileId): ?>
    <section class="card empty-state"><h3>No profile selected</h3><p>Sync a profile to populate history charts.</p></section>
<?php else: ?>
    <div id="dashboardData" data-history='<?= e(json_encode($history, JSON_UNESCAPED_SLASHES)) ?>'></div>
    <section class="card"><canvas id="networthChart" height="180"></canvas></section>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Timestamp</th><th>Networth</th><th>Skill Avg</th><th>Magical Power</th><th>Catacombs</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($history) as $snapshot): ?>
                    <tr>
                        <td><?= e(format_datetime($snapshot['timestamp'])) ?></td>
                        <td><?= e(number_format((float) $snapshot['networth'], 0)) ?></td>
                        <td><?= e(number_format((float) $snapshot['skill_average'], 2)) ?></td>
                        <td><?= e(number_format((float) $snapshot['magical_power'], 0)) ?></td>
                        <td><?= e(number_format((float) $snapshot['catacombs_level'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($history === []): ?><tr><td colspan="5" class="muted">No snapshots available.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
