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
$compareProfileId = isset($_GET['compare_profile_id']) && ctype_digit((string) $_GET['compare_profile_id']) ? (int) $_GET['compare_profile_id'] : null;
$items = $selectedProfileId ? $database->fetchAll('SELECT * FROM profile_items WHERE profile_id = :profile_id ORDER BY category ASC, item_name ASC LIMIT 100', ['profile_id' => $selectedProfileId]) : [];
$compareItems = $compareProfileId ? $database->fetchAll('SELECT * FROM profile_items WHERE profile_id = :profile_id ORDER BY category ASC, item_name ASC LIMIT 100', ['profile_id' => $compareProfileId]) : [];

$pageTitle = 'Gear';
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header"><div><h2>Gear</h2><p>Review synced item records and compare one profile against another.</p></div></section>
<?php if (!$selectedProfileId): ?>
    <section class="card empty-state"><h3>No profile selected</h3><p>Sync a profile to populate tracked gear and items.</p></section>
<?php else: ?>
    <section class="card">
        <form class="filter-grid" method="get">
            <input type="hidden" name="account_id" value="<?= e((string) $selectedAccountId) ?>">
            <input type="hidden" name="profile_id" value="<?= e((string) $selectedProfileId) ?>">
            <label><span>Compare Against</span>
                <select name="compare_profile_id">
                    <option value="">None</option>
                    <?php foreach ($profiles as $profile): ?>
                        <?php if ((int) $profile['id'] === $selectedProfileId) { continue; } ?>
                        <option value="<?= e((string) $profile['id']) ?>" <?= $compareProfileId === (int) $profile['id'] ? 'selected' : '' ?>><?= e($profile['profile_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-secondary" type="submit">Compare</button>
        </form>
    </section>

    <div class="grid two-column">
        <section class="card">
            <h3>Tracked Items</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Name</th><th>Category</th><th>Quantity</th><th>Rarity</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['item_name']) ?></td>
                            <td><?= e($item['category']) ?></td>
                            <td><?= e(number_format((float) $item['quantity'], 0)) ?></td>
                            <td><?= e((string) ($item['rarity'] ?? 'Unknown')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($items === []): ?><tr><td colspan="4" class="muted">No synced items available.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h3>Comparison</h3>
            <?php if ($compareProfileId && $compareItems !== []): ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Item</th><th>Current Qty</th><th>Compared Qty</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($items, 0, 25) as $item): ?>
                            <?php $match = array_values(array_filter($compareItems, static fn(array $candidate): bool => $candidate['item_name'] === $item['item_name'])); ?>
                            <tr>
                                <td><?= e($item['item_name']) ?></td>
                                <td><?= e(number_format((float) $item['quantity'], 0)) ?></td>
                                <td><?= e(number_format((float) ($match[0]['quantity'] ?? 0), 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="muted">Select another synced profile to compare tracked items.</p>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
