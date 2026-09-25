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
$skills = $selectedProfileId ? $database->fetchAll('SELECT * FROM profile_skills WHERE profile_id = :profile_id ORDER BY skill_name ASC', ['profile_id' => $selectedProfileId]) : [];

$pageTitle = 'Profiles';
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header">
    <div>
        <h2>Profile Overview</h2>
        <p>View a selected SkyBlock profile and its synced high-level stats.</p>
    </div>
</section>

<?php if (!$profile): ?>
    <section class="card empty-state">
        <h3>No profile available</h3>
        <p>Sync an account to populate profile data.</p>
    </section>
<?php else: ?>
    <section class="card">
        <div class="section-title">
            <h3><?= e($profile['profile_name']) ?></h3>
            <span class="badge badge-secondary"><?= e($profile['game_mode']) ?></span>
        </div>
        <div class="stats-grid compact-grid">
            <article class="stat-card"><span>Networth</span><strong><?= e(number_format((float) ($snapshot['networth'] ?? 0))) ?></strong></article>
            <article class="stat-card"><span>Skill Average</span><strong><?= e(number_format((float) ($snapshot['skill_average'] ?? 0), 2)) ?></strong></article>
            <article class="stat-card"><span>Magical Power</span><strong><?= e(number_format((float) ($snapshot['magical_power'] ?? 0))) ?></strong></article>
            <article class="stat-card"><span>Catacombs</span><strong><?= e(number_format((float) ($snapshot['catacombs_level'] ?? 0), 2)) ?></strong></article>
        </div>
    </section>

    <section class="card">
        <h3>Skill Summary</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Skill</th><th>Level</th><th>XP</th><th>XP to Next</th></tr></thead>
                <tbody>
                <?php foreach ($skills as $skill): ?>
                    <tr>
                        <td><?= e(ucfirst($skill['skill_name'])) ?></td>
                        <td><?= e(number_format((float) $skill['level'], 2)) ?></td>
                        <td><?= e(number_format((float) $skill['xp'], 0)) ?></td>
                        <td><?= e(number_format((float) $skill['xp_next_level'], 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
