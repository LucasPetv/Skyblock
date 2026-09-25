<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'SkyBlock Ironman Progression Assistant';
$pageStyles = $pageStyles ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <?php foreach ($pageStyles as $style): ?>
        <link rel="stylesheet" href="<?= e($style) ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="app-shell">
    <button class="mobile-nav-toggle" type="button" data-sidebar-toggle aria-label="Toggle navigation">☰</button>
