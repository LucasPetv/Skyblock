<?php
declare(strict_types=1);

$navigation = [
    'dashboard.php' => 'Dashboard',
    'accounts.php' => 'Accounts',
    'profile.php' => 'Profiles',
    'progression.php' => 'Progression',
    'goals.php' => 'Goals',
    'gear.php' => 'Gear',
    'collections.php' => 'Collections',
    'skills.php' => 'Skills',
    'dungeons.php' => 'Dungeons',
    'resources.php' => 'Resources',
    'rng.php' => 'RNG / Drops',
    'history.php' => 'History',
    'settings.php' => 'Settings',
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
        <h1>SkyBlock</h1>
        <p>Ironman Progression Assistant</p>
    </div>
    <nav class="sidebar__nav">
        <?php foreach ($navigation as $file => $label): ?>
            <a class="sidebar__link <?= is_active_page($file) ? 'is-active' : '' ?>" href="<?= e($file) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</aside>
<main class="content">
