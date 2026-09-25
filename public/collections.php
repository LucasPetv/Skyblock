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
$category = isset($_GET['category']) ? (string) $_GET['category'] : '';
$sort = isset($_GET['sort']) ? (string) $_GET['sort'] : 'amount_desc';

$sql = 'SELECT * FROM profile_collections WHERE profile_id = :profile_id';
$params = ['profile_id' => $selectedProfileId];
if ($selectedProfileId && $category !== '') {
    $sql .= ' AND category = :category';
    $params['category'] = $category;
}
$sql .= match ($sort) {
    'name_asc' => ' ORDER BY collection_name ASC',
    'tier_desc' => ' ORDER BY tier DESC, amount DESC',
    default => ' ORDER BY amount DESC',
};
$collections = $selectedProfileId ? $database->fetchAll($sql, $params) : [];
$categories = $selectedProfileId ? $database->fetchAll('SELECT DISTINCT category FROM profile_collections WHERE profile_id = :profile_id ORDER BY category ASC', ['profile_id' => $selectedProfileId]) : [];
$maxAmount = 1;
foreach ($collections as $collection) {
    $maxAmount = max($maxAmount, (int) $collection['amount']);
}

$pageTitle = 'Collections';
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header"><div><h2>Collections</h2><p>Review collection totals, tiers, and category progress for your profile.</p></div></section>
<?php if (!$selectedProfileId): ?>
    <section class="card empty-state"><h3>No profile selected</h3><p>Sync a profile to import collections.</p></section>
<?php else: ?>
    <section class="card">
        <form class="filter-grid" method="get">
            <input type="hidden" name="account_id" value="<?= e((string) $selectedAccountId) ?>">
            <input type="hidden" name="profile_id" value="<?= e((string) $selectedProfileId) ?>">
            <label><span>Category</span>
                <select name="category">
                    <option value="">All</option>
                    <?php foreach ($categories as $option): ?>
                        <option value="<?= e($option['category']) ?>" <?= $category === $option['category'] ? 'selected' : '' ?>><?= e(ucfirst($option['category'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Sort</span>
                <select name="sort">
                    <option value="amount_desc" <?= $sort === 'amount_desc' ? 'selected' : '' ?>>Amount (high-low)</option>
                    <option value="tier_desc" <?= $sort === 'tier_desc' ? 'selected' : '' ?>>Tier (high-low)</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                </select>
            </label>
            <button class="btn btn-secondary" type="submit">Apply</button>
        </form>
    </section>

    <section class="card">
        <div class="stack compact">
            <?php foreach ($collections as $collection): ?>
                <?php $progress = min(100, round(((int) $collection['amount'] / $maxAmount) * 100, 1)); ?>
                <div>
                    <div class="row spread">
                        <span><?= e($collection['collection_name']) ?></span>
                        <span>Tier <?= e((string) $collection['tier']) ?> · <?= e(number_format((float) $collection['amount'], 0)) ?></span>
                    </div>
                    <div class="progress"><span style="width: <?= e((string) $progress) ?>%"></span></div>
                </div>
            <?php endforeach; ?>
            <?php if ($collections === []): ?><p class="muted">No collections found for this profile.</p><?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
