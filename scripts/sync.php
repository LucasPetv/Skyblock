#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

use SkyBlock\Api\HypixelApiClient;
use SkyBlock\Models\Account;
use SkyBlock\Services\CacheService;
use SkyBlock\Services\SyncService;

$database = db();
$accountModel = new Account($database);
$service = new SyncService($database, new HypixelApiClient(), new CacheService($database));
$accounts = $accountModel->findAll();

if ($accounts === []) {
    fwrite(STDOUT, "No accounts configured.
");
    exit(0);
}

$failed = false;
foreach ($accounts as $account) {
    $result = $service->syncAccount((int) $account['id']);
    fwrite(STDOUT, sprintf("[%s] %s
", $result['success'] ? 'OK' : 'FAIL', $result['message']));
    if (!$result['success']) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);
