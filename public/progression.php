<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

use SkyBlock\Models\Account;
use SkyBlock\Models\Profile;
use SkyBlock\Services\GoalService;
use SkyBlock\Services\ProgressionAnalyzer;
use SkyBlock\Services\ResourceAnalyzer;

$database = db();
$accountModel = new Account($database);
$profileModel = new Profile($database);
$goalService = new GoalService($database);
$progressionAnalyzer = new ProgressionAnalyzer($database);
$resourceAnalyzer = new ResourceAnalyzer($database);

$accounts = $accountModel->findAll();
$selectedAccountId = selected_account_id() ?? (isset($accounts[0]['id']) ? (int) $accounts[0]['id'] : null);
$profiles = $selectedAccountId ? $profileModel->findByAccountId($selectedAccountId) : [];
$selectedProfileId = selected_profile_id() ?? (isset($profiles[0]['id']) ? (int) $profiles[0]['id'] : null);
$goals = $selectedProfileId ? $goalService->getGoalsForProfile($selectedProfileId) : [];

$pageTitle = 'Progression';
$pageScripts = ['assets/js/progression.js'];
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header">
    <div>
        <h2>Progression</h2>
        <p>Review goal dependencies, completed milestones, and outstanding resource blockers.</p>
    </div>
</section>

<?php if (!$selectedProfileId): ?>
    <section class="card empty-state"><h3>No profile selected</h3><p>Choose a profile from the dashboard first.</p></section>
<?php else: ?>
    <section class="stack">
        <?php foreach ($goals as $goal): ?>
            <?php $analysis = $progressionAnalyzer->analyzeGoal((int) $goal['id']); ?>
            <?php $resources = $resourceAnalyzer->getResourceRequirements((int) $goal['id']); ?>
            <article class="card progression-card">
                <div class="section-title">
                    <div>
                        <h3><?= e($goal['name']) ?></h3>
                        <p class="muted"><?= e($goal['description'] ?? 'No description') ?></p>
                    </div>
                    <span class="badge badge-<?= e($goal['status']) ?>"><?= e(ucfirst($goal['status'])) ?></span>
                </div>
                <div class="progress"><span style="width: <?= e((string) min(100, $analysis['progress'])) ?>%"></span></div>
                <div class="grid two-column">
                    <div>
                        <h4>Completed Requirements</h4>
                        <ul class="stack compact">
                            <?php foreach ($analysis['completed_requirements'] as $item): ?>
                                <li>✓ <?= e($item['reason']) ?></li>
                            <?php endforeach; ?>
                            <?php if ($analysis['completed_requirements'] === []): ?><li class="muted">No completed requirements yet.</li><?php endif; ?>
                        </ul>
                    </div>
                    <div>
                        <h4>Missing Requirements</h4>
                        <ul class="stack compact">
                            <?php foreach ($analysis['missing_requirements'] as $item): ?>
                                <li>• <?= e($item['reason']) ?></li>
                            <?php endforeach; ?>
                            <?php if ($analysis['missing_requirements'] === []): ?><li class="muted">All requirements met.</li><?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div>
                    <h4>Resources</h4>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Resource</th><th>Required</th><th>Owned</th><th>Missing</th></tr></thead>
                            <tbody>
                            <?php foreach ($resources as $resource): ?>
                                <tr>
                                    <td><?= e($resource['item']) ?></td>
                                    <td><?= e(number_format((float) $resource['required'], 0)) ?></td>
                                    <td><?= e(number_format((float) $resource['owned'], 0)) ?></td>
                                    <td><?= e(number_format((float) $resource['missing'], 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($resources === []): ?><tr><td colspan="4" class="muted">No resource requirements for this goal.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($goals === []): ?><section class="card empty-state"><h3>No goals created</h3><p>Create goals to populate the dependency view.</p></section><?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
