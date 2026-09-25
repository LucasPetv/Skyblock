<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use SkyBlock\Api\HypixelApiClient;
use SkyBlock\Services\CacheService;
use SkyBlock\Services\SyncService;

ensure_csrf((string) (request_data()['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
$data = request_data();
$accountId = isset($data['account_id']) && ctype_digit((string) $data['account_id']) ? (int) $data['account_id'] : 0;
if ($accountId <= 0) {
    json_response(['success' => false, 'message' => 'Valid account_id is required.'], 422);
}

$database = db();
$service = new SyncService($database, new HypixelApiClient(), new CacheService($database));
$result = $service->syncAccount($accountId);
json_response($result, !empty($result['success']) ? 200 : 500);
