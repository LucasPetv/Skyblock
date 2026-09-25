<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

use SkyBlock\Database\Database;
use SkyBlock\Models\Account;
use SkyBlock\Models\Profile;
use SkyBlock\Services\GoalService;
use SkyBlock\Services\ResourceAnalyzer;

$database = db();
$accountModel = new Account($database);
$profileModel = new Profile($database);
$goalService = new GoalService($database);
$resourceAnalyzer = new ResourceAnalyzer($database);

$accounts = $accountModel->findAll();
$selectedAccountId = selected_account_id() ?? (isset($accounts[0]['id']) ? (int) $accounts[0]['id'] : null);
$profiles = $selectedAccountId ? $profileModel->findByAccountId($selectedAccountId) : [];
$selectedProfileId = selected_profile_id() ?? (isset($profiles[0]['id']) ? (int) $profiles[0]['id'] : null);
if ($selectedProfileId === null && isset($profiles[0]['id'])) {
    $selectedProfileId = (int) $profiles[0]['id'];
}
$profile = $selectedProfileId ? $profileModel->findById($selectedProfileId) : null;
$snapshot = $selectedProfileId ? $profileModel->getLatestSnapshot($selectedProfileId) : null;
$goals = $selectedProfileId ? $goalService->getGoalsForProfile($selectedProfileId) : [];
$bottlenecks = $selectedProfileId ? $resourceAnalyzer->getBottlenecks($selectedProfileId) : [];
$history = $selectedProfileId ? $database->fetchAll('SELECT timestamp, networth, skill_average, magical_power FROM profile_snapshots WHERE profile_id = :profile_id ORDER BY timestamp ASC LIMIT 30', ['profile_id' => $selectedProfileId]) : [];
$activity = $selectedAccountId ? $database->fetchAll('SELECT started_at, status, message, profiles_synced FROM sync_logs WHERE account_id = :account_id ORDER BY started_at DESC LIMIT 5', ['account_id' => $selectedAccountId]) : [];

$pageTitle = 'Dashboard';
$pageScripts = ['assets/js/dashboard.js'];
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p>Track your selected Ironman profile across goals, stats, and progression blockers.</p>
    </div>
</section>

<section class="card filters">
    <div class="filter-grid">
        <label>
            <span>Account</span>
            <select data-account-selector>
                <option value="">Select account</option>
                <?php foreach ($accounts as $account): ?>
                    <option value="<?= e((string) $account['id']) ?>" <?= $selectedAccountId === (int) $account['id'] ? 'selected' : '' ?>>
                        <?= e($account['display_name'] ?: $account['minecraft_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Profile</span>
            <select data-profile-selector <?= $profiles === [] ? 'disabled' : '' ?>>
                <option value="">Select profile</option>
                <?php foreach ($profiles as $item): ?>
                    <option value="<?= e((string) $item['id']) ?>" <?= $selectedProfileId === (int) $item['id'] ? 'selected' : '' ?>>
                        <?= e($item['profile_name']) ?> (<?= e($item['game_mode']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
</section>

<?php if (!$selectedAccountId || !$selectedProfileId || !$profile): ?>
    <section class="card empty-state">
        <h3>No active profile selected</h3>
        <p>Add and sync an account first to unlock dashboard metrics.</p>
        <a class="btn btn-primary" href="accounts.php">Manage Accounts</a>
    </section>
<?php else: ?>
    <div id="dashboardData"
         data-profile-id="<?= e((string) $selectedProfileId) ?>"
         data-history='<?= e(json_encode($history, JSON_UNESCAPED_SLASHES)) ?>'
         data-goals='<?= e(json_encode($goals, JSON_UNESCAPED_SLASHES)) ?>'
         data-bottlenecks='<?= e(json_encode($bottlenecks, JSON_UNESCAPED_SLASHES)) ?>'></div>

    <section class="stats-grid">
        <article class="stat-card">
            <span>Networth</span>
            <strong><?= e(number_format((float) ($snapshot['networth'] ?? 0))) ?></strong>
        </article>
        <article class="stat-card">
            <span>Magical Power</span>
            <strong><?= e(number_format((float) ($snapshot['magical_power'] ?? 0), 0)) ?></strong>
        </article>
        <article class="stat-card">
            <span>Skill Average</span>
            <strong><?= e(number_format((float) ($snapshot['skill_average'] ?? 0), 2)) ?></strong>
        </article>
        <article class="stat-card">
            <span>Catacombs</span>
            <strong><?= e(number_format((float) ($snapshot['catacombs_level'] ?? 0), 2)) ?></strong>
        </article>
    </section>

    <div class="grid two-column">
        <section class="card">
            <div class="section-title">
                <h3>Current Goals</h3>
                <a class="btn btn-secondary" href="goals.php?profile_id=<?= e((string) $selectedProfileId) ?>">Manage</a>
            </div>
            <div id="dashboardGoals" class="stack compact">
                <?php foreach (array_slice($goals, 0, 5) as $goal): ?>
                    <?php $progress = (float) (($goal['target_progress'] ?? 100) > 0 ? (($goal['current_progress'] ?? 0) / $goal['target_progress']) * 100 : 0); ?>
                    <div>
                        <div class="row spread"><span><?= e($goal['name']) ?></span><span><?= e(ucfirst($goal['status'])) ?></span></div>
                        <div class="progress"><span style="width: <?= e((string) min(100, round($progress, 1))) ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
                <?php if ($goals === []): ?><p class="muted">No goals created for this profile yet.</p><?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="section-title">
                <h3>Current Bottlenecks</h3>
                <a class="btn btn-secondary" href="resources.php?profile_id=<?= e((string) $selectedProfileId) ?>">View Resources</a>
            </div>
            <div id="dashboardBottlenecks" class="stack compact">
                <?php foreach (array_slice($bottlenecks, 0, 5) as $bottleneck): ?>
                    <div class="resource-row">
                        <span><?= e($bottleneck['item']) ?></span>
                        <strong>Missing <?= e(number_format((float) $bottleneck['missing'], 0)) ?></strong>
                    </div>
                <?php endforeach; ?>
                <?php if ($bottlenecks === []): ?><p class="muted">No bottlenecks detected. Add goals with resource requirements.</p><?php endif; ?>
            </div>
        </section>
    </div>

    <div class="grid two-column">
        <section class="card">
            <div class="section-title"><h3>Recent Progress</h3></div>
            <canvas id="networthChart" height="180"></canvas>
        </section>

        <section class="card">
            <div class="section-title"><h3>Recent Activity</h3></div>
            <div class="stack compact">
                <?php foreach ($activity as $entry): ?>
                    <div class="timeline-entry">
                        <strong><?= e(ucfirst($entry['status'])) ?></strong>
                        <p><?= e($entry['message'] ?? 'No details') ?></p>
                        <span class="muted"><?= e(format_datetime($entry['started_at'] ?? null)) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ($activity === []): ?><p class="muted">No sync activity yet.</p><?php endif; ?>
            </div>
        </section>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
