<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

use SkyBlock\Models\Account;

$accountModel = new Account(db());
$accounts = $accountModel->findAll();
$editAccount = null;
if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $editAccount = $accountModel->findById((int) $_GET['edit']);
}

$pageTitle = 'Accounts';
$pageScripts = ['assets/js/accounts.js'];
require __DIR__ . '/layout/header.php';
require __DIR__ . '/layout/sidebar.php';
?>
<section class="page-header">
    <div>
        <h2>Accounts</h2>
        <p>Add Minecraft accounts, sync data from Hypixel, and manage profile access.</p>
    </div>
</section>

<div class="grid two-column">
    <section class="card" id="account-form">
        <h3><?= $editAccount ? 'Edit Account' : 'Add Account' ?></h3>
        <form id="accountForm" class="stack" method="post" action="api/accounts.php">
            <?= csrf_field() ?>
            <?php if ($editAccount): ?>
                <input type="hidden" name="account_id" value="<?= e((string) $editAccount['id']) ?>">
            <?php endif; ?>
            <label>
                <span>Minecraft Username</span>
                <input type="text" name="username" maxlength="32" required value="<?= e($editAccount['minecraft_name'] ?? '') ?>">
            </label>
            <label>
                <span>Display Name</span>
                <input type="text" name="display_name" maxlength="100" value="<?= e($editAccount['display_name'] ?? '') ?>">
            </label>
            <label>
                <span>Notes</span>
                <textarea name="notes" rows="4"><?= e($editAccount['notes'] ?? '') ?></textarea>
            </label>
            <button class="btn btn-primary" type="submit"><?= $editAccount ? 'Update Account' : 'Add Account' ?></button>
        </form>
    </section>

    <section class="card">
        <h3>Tips</h3>
        <ul class="stack compact">
            <li>Use the in-game name of the player you want to track.</li>
            <li>Sync after adding an account to pull profiles, skills, and collections.</li>
            <li>Selected profiles appear across dashboard and progression pages.</li>
        </ul>
    </section>
</div>

<section class="card">
    <div class="section-title">
        <h3>Tracked Accounts</h3>
        <span class="badge badge-secondary"><?= e((string) count($accounts)) ?> tracked</span>
    </div>

    <?php if ($accounts === []): ?>
        <div class="empty-state">
            <h4>No accounts yet</h4>
            <p>Add your first Ironman account to start building progression goals.</p>
        </div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($accounts as $account): ?>
                <article class="account-card">
                    <div class="account-card__header">
                        <div>
                            <h4><?= e($account['display_name'] ?: $account['minecraft_name']) ?></h4>
                            <p class="muted">IGN: <?= e($account['minecraft_name']) ?></p>
                        </div>
                        <span class="badge badge-secondary"><?= e((string) ($account['profile_count'] ?? 0)) ?> profiles</span>
                    </div>
                    <p class="muted">Last sync: <?= e(format_datetime($account['last_synced_at'] ?? null)) ?></p>
                    <div class="actions">
                        <a class="btn btn-secondary" href="dashboard.php?account_id=<?= e((string) $account['id']) ?>">Open</a>
                        <button class="btn btn-primary" type="button" data-sync-account="<?= e((string) $account['id']) ?>">Sync</button>
                        <a class="btn btn-secondary" href="accounts.php?edit=<?= e((string) $account['id']) ?>#account-form">Edit</a>
                        <button class="btn btn-danger" type="button" data-delete-account="<?= e((string) $account['id']) ?>">Delete</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/layout/footer.php'; ?>
