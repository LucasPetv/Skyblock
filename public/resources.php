<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

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
$goals = $selectedProfileId ? $goalService->getGoalsForProfile($selectedProfileId) : [];
$bottlenecks = $selectedProfileId ? $resourceAnalyzer->getBottlenecks($selectedProfileId) : [];

$pageTitle = 'Resources';
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header"><div><h2>Resources</h2><p>Inspect missing materials and collection requirements across active goals.</p></div></section>
<?php if (!$selectedProfileId): ?>
    <section class="card empty-state"><h3>No profile selected</h3><p>Select a profile first.</p></section>
<?php else: ?>
    <section class="card">
        <h3>Bottlenecks</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Resource</th><th>Required</th><th>Owned</th><th>Missing</th><th>Goals</th></tr></thead>
                <tbody>
                <?php foreach ($bottlenecks as $row): ?>
                    <tr>
                        <td><?= e($row['item']) ?></td>
                        <td><?= e(number_format((float) $row['required'], 0)) ?></td>
                        <td><?= e(number_format((float) $row['owned'], 0)) ?></td>
                        <td><?= e(number_format((float) $row['missing'], 0)) ?></td>
                        <td><?= e(implode(', ', array_map(static fn(int $goalId): string => '#' . $goalId, $row['goals'] ?? []))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($bottlenecks === []): ?><tr><td colspan="5" class="muted">No resource bottlenecks detected.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php foreach ($goals as $goal): ?>
        <?php $requirements = $resourceAnalyzer->getResourceRequirements((int) $goal['id']); ?>
        <section class="card">
            <h3><?= e($goal['name']) ?></h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Resource</th><th>Required</th><th>Owned</th><th>Missing</th></tr></thead>
                    <tbody>
                    <?php foreach ($requirements as $requirement): ?>
                        <tr>
                            <td><?= e($requirement['item']) ?></td>
                            <td><?= e(number_format((float) $requirement['required'], 0)) ?></td>
                            <td><?= e(number_format((float) $requirement['owned'], 0)) ?></td>
                            <td><?= e(number_format((float) $requirement['missing'], 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($requirements === []): ?><tr><td colspan="4" class="muted">No resource requirements recorded for this goal.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
