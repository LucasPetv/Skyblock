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
$profile = $selectedProfileId ? $profileModel->findById($selectedProfileId) : null;
$snapshot = $selectedProfileId ? $profileModel->getLatestSnapshot($selectedProfileId) : null;
$dungeons = $selectedProfileId ? $database->fetchAll('SELECT * FROM profile_dungeons WHERE profile_id = :profile_id ORDER BY dungeon_type ASC, floor ASC', ['profile_id' => $selectedProfileId]) : [];
$rawData = $profile && !empty($profile['raw_data']) ? json_decode((string) $profile['raw_data'], true) : [];
$classLevels = $rawData['members'] ?? [];
$classSummary = [];
foreach ($classLevels as $memberData) {
    if (isset($memberData['dungeons']['player_classes']) && is_array($memberData['dungeons']['player_classes'])) {
        foreach ($memberData['dungeons']['player_classes'] as $class => $payload) {
            $classSummary[$class] = $payload['experience'] ?? 0;
        }
        break;
    }
}

$pageTitle = 'Dungeons';
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header"><div><h2>Dungeons</h2><p>Monitor catacombs, classes, and floor completion progress.</p></div></section>
<?php if (!$selectedProfileId): ?>
    <section class="card empty-state"><h3>No profile selected</h3><p>Sync a profile to view dungeons.</p></section>
<?php else: ?>
    <div class="stats-grid compact-grid">
        <article class="stat-card"><span>Catacombs Level</span><strong><?= e(number_format((float) ($snapshot['catacombs_level'] ?? 0), 2)) ?></strong></article>
        <article class="stat-card"><span>Tracked Floors</span><strong><?= e((string) count($dungeons)) ?></strong></article>
    </div>

    <div class="grid two-column">
        <section class="card">
            <h3>Class Experience</h3>
            <ul class="stack compact">
                <?php foreach ($classSummary as $class => $xp): ?>
                    <li><?= e(ucfirst((string) $class)) ?> — <?= e(number_format((float) $xp, 0)) ?> XP</li>
                <?php endforeach; ?>
                <?php if ($classSummary === []): ?><li class="muted">No class data found in synced profile.</li><?php endif; ?>
            </ul>
        </section>

        <section class="card">
            <h3>Floor Checklist</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Type</th><th>Floor</th><th>Completions</th><th>Best Score</th></tr></thead>
                    <tbody>
                    <?php foreach ($dungeons as $row): ?>
                        <tr>
                            <td><?= e($row['dungeon_type']) ?></td>
                            <td><?= e($row['floor']) ?></td>
                            <td><?= e(number_format((float) $row['completions'], 0)) ?></td>
                            <td><?= e((string) ($row['best_score'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($dungeons === []): ?><tr><td colspan="4" class="muted">No dungeon data available.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
