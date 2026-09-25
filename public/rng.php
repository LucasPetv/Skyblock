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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensure_csrf();
    if ($selectedProfileId) {
        $database->execute(
            'INSERT INTO dungeon_runs (profile_id, dungeon_type, floor, completed_at, result, drop_obtained, notes, is_manual, created_at)
             VALUES (:profile_id, :dungeon_type, :floor, :completed_at, :result, :drop_obtained, :notes, 1, CURRENT_TIMESTAMP)',
            [
                'profile_id' => $selectedProfileId,
                'dungeon_type' => trim((string) ($_POST['dungeon_type'] ?? 'catacombs')),
                'floor' => trim((string) ($_POST['floor'] ?? 'F1')),
                'completed_at' => str_replace('T', ' ', trim((string) ($_POST['completed_at'] ?? date('Y-m-d H:i:s')))),
                'result' => trim((string) ($_POST['result'] ?? 'complete')),
                'drop_obtained' => trim((string) ($_POST['drop_obtained'] ?? '')) ?: null,
                'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
            ]
        );
    }

    redirect('rng.php?account_id=' . (int) $selectedAccountId . '&profile_id=' . (int) $selectedProfileId);
}

$runs = $selectedProfileId
    ? $database->fetchAll('SELECT * FROM dungeon_runs WHERE profile_id = :profile_id ORDER BY completed_at DESC LIMIT 100', ['profile_id' => $selectedProfileId])
    : [];

$pageTitle = 'RNG / Drops';
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header"><div><h2>RNG / Drops</h2><p>Track dungeon drop history and manual run results for the selected profile.</p></div></section>
<?php if (!$selectedProfileId): ?>
    <section class="card empty-state"><h3>No profile selected</h3><p>Select a profile before logging drops.</p></section>
<?php else: ?>
    <div class="grid two-column">
        <section class="card">
            <h3>Log Dungeon Run</h3>
            <form class="stack" method="post">
                <?= csrf_field() ?>
                <div class="filter-grid">
                    <label><span>Dungeon Type</span><input type="text" name="dungeon_type" value="catacombs"></label>
                    <label><span>Floor</span><input type="text" name="floor" value="F1"></label>
                </div>
                <div class="filter-grid">
                    <label><span>Completed At</span><input type="datetime-local" name="completed_at" value="<?= e(date('Y-m-d\TH:i')) ?>"></label>
                    <label><span>Result</span><input type="text" name="result" value="complete"></label>
                </div>
                <label><span>Drop Obtained</span><input type="text" name="drop_obtained" placeholder="e.g. Shadow Assassin Chestplate"></label>
                <label><span>Notes</span><textarea name="notes" rows="3"></textarea></label>
                <button class="btn btn-primary" type="submit">Save Run</button>
            </form>
        </section>

        <section class="card">
            <h3>Recent Drops</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>Floor</th><th>Result</th><th>Drop</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($runs as $run): ?>
                        <tr>
                            <td><?= e(format_datetime($run['completed_at'])) ?></td>
                            <td><?= e($run['dungeon_type'] . ' ' . $run['floor']) ?></td>
                            <td><?= e($run['result']) ?></td>
                            <td><?= e((string) ($run['drop_obtained'] ?? '—')) ?></td>
                            <td><?= e((string) ($run['notes'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($runs === []): ?><tr><td colspan="5" class="muted">No run history yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
