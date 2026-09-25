<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

use SkyBlock\Models\Account;
use SkyBlock\Models\Profile;
use SkyBlock\Services\GoalService;
use SkyBlock\Services\ProgressionAnalyzer;

$database = db();
$accountModel = new Account($database);
$profileModel = new Profile($database);
$goalService = new GoalService($database);
$progressionAnalyzer = new ProgressionAnalyzer($database);
$accounts = $accountModel->findAll();
$selectedAccountId = selected_account_id() ?? (isset($accounts[0]['id']) ? (int) $accounts[0]['id'] : null);
$profiles = $selectedAccountId ? $profileModel->findByAccountId($selectedAccountId) : [];
$selectedProfileId = selected_profile_id() ?? (isset($profiles[0]['id']) ? (int) $profiles[0]['id'] : null);
$goals = $selectedProfileId ? $goalService->getGoalsForProfile($selectedProfileId) : [];
$suggestions = $selectedProfileId ? $progressionAnalyzer->suggestGoals($selectedProfileId) : [];

$pageTitle = 'Goals';
$pageScripts = ['assets/js/goals.js'];
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header">
    <div>
        <h2>Goals</h2>
        <p>Create structured progression goals and keep requirements synced to your latest profile data.</p>
    </div>
</section>

<?php if (!$selectedProfileId): ?>
    <section class="card empty-state"><h3>No profile selected</h3><p>Select or sync a profile before adding goals.</p></section>
<?php else: ?>
    <div class="grid two-column">
        <section class="card">
            <h3>Add Goal</h3>
            <form id="goalForm" class="stack" method="post" action="api/goals.php">
                <?= csrf_field() ?>
                <input type="hidden" name="profile_id" value="<?= e((string) $selectedProfileId) ?>">
                <label><span>Name</span><input type="text" name="name" maxlength="255" required></label>
                <label><span>Description</span><textarea name="description" rows="3"></textarea></label>
                <div class="filter-grid">
                    <label><span>Category</span><input type="text" name="category" value="general"></label>
                    <label><span>Priority</span>
                        <select name="priority">
                            <option value="high">High</option>
                            <option value="medium" selected>Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </label>
                </div>
                <label><span>Target Progress</span><input type="number" name="target_progress" value="100" min="1"></label>
                <div class="stack" id="requirementsContainer">
                    <div class="requirement-row">
                        <select name="requirements[0][type]">
                            <option value="skill">Skill</option>
                            <option value="collection">Collection</option>
                            <option value="dungeon">Dungeon</option>
                            <option value="item">Item</option>
                            <option value="stat">Stat</option>
                        </select>
                        <input type="text" name="requirements[0][key]" placeholder="Key (e.g. combat, F7, ENCHANTED_DIAMOND_BLOCK)">
                        <input type="number" name="requirements[0][value]" placeholder="Target" min="0" step="0.01">
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" id="addRequirement">Add Requirement</button>
                <button class="btn btn-primary" type="submit">Create Goal</button>
            </form>
        </section>

        <section class="card">
            <h3>Suggested Goals</h3>
            <div class="stack compact">
                <?php foreach ($suggestions as $suggestion): ?>
                    <div class="suggestion-card">
                        <div class="row spread">
                            <strong><?= e($suggestion['name']) ?></strong>
                            <span class="badge badge-<?= e($suggestion['priority']) ?>"><?= e(ucfirst($suggestion['priority'])) ?></span>
                        </div>
                        <p><?= e($suggestion['reason']) ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if ($suggestions === []): ?><p class="muted">No suggestions available yet.</p><?php endif; ?>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="section-title"><h3>Existing Goals</h3></div>
        <div class="stack compact">
            <?php foreach ($goals as $goal): ?>
                <article class="goal-row">
                    <div>
                        <strong><?= e($goal['name']) ?></strong>
                        <p class="muted"><?= e($goal['description'] ?? 'No description') ?></p>
                    </div>
                    <div class="row gap-sm">
                        <span class="badge badge-<?= e($goal['status']) ?>"><?= e(ucfirst($goal['status'])) ?></span>
                        <button class="btn btn-danger btn-small" type="button" data-delete-goal="<?= e((string) $goal['id']) ?>">Delete</button>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($goals === []): ?><p class="muted">No goals yet.</p><?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
