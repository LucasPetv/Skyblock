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
$skills = $selectedProfileId ? $database->fetchAll('SELECT * FROM profile_skills WHERE profile_id = :profile_id ORDER BY level DESC', ['profile_id' => $selectedProfileId]) : [];
$average = 0.0;
if ($skills !== []) {
    $average = array_sum(array_map(static fn(array $skill): float => (float) $skill['level'], $skills)) / count($skills);
}
$maxLevel = 60;

$pageTitle = 'Skills';
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header"><div><h2>Skills</h2><p>Track levels, XP, and average skill progression for the selected profile.</p></div></section>
<?php if (!$selectedProfileId): ?>
    <section class="card empty-state"><h3>No profile selected</h3><p>Sync a profile to view skills.</p></section>
<?php else: ?>
    <section class="card"><h3>Skill Average: <?= e(number_format($average, 2)) ?></h3></section>
    <section class="card">
        <div class="stack compact">
            <?php foreach ($skills as $skill): ?>
                <?php $progress = min(100, round(((float) $skill['level'] / $maxLevel) * 100, 1)); ?>
                <div>
                    <div class="row spread"><span><?= e(ucfirst($skill['skill_name'])) ?></span><span>Lvl <?= e(number_format((float) $skill['level'], 2)) ?></span></div>
                    <div class="progress"><span style="width: <?= e((string) $progress) ?>%"></span></div>
                    <small class="muted">XP: <?= e(number_format((float) $skill['xp'], 0)) ?> · To next: <?= e(number_format((float) $skill['xp_next_level'], 0)) ?></small>
                </div>
            <?php endforeach; ?>
            <?php if ($skills === []): ?><p class="muted">No skills synced yet.</p><?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
