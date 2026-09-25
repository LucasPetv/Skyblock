<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use SkyBlock\Api\HypixelApiClient;
use SkyBlock\Models\Account;

$database = db();
$accountModel = new Account($database);
$client = new HypixelApiClient();
$method = method_override();
$data = request_data();

if ($method === 'GET') {
    json_response(['success' => true, 'accounts' => $accountModel->findAll()]);
}

if ($method === 'POST') {
    ensure_csrf((string) ($data['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

    $accountId = isset($data['account_id']) && ctype_digit((string) $data['account_id']) ? (int) $data['account_id'] : null;
    $username = trim((string) ($data['username'] ?? ''));
    if ($username === '') {
        json_response(['success' => false, 'message' => 'Username is required.'], 422);
    }

    $resolved = $client->resolveUsernameToUuid($username);
    if (($resolved['id'] ?? '') === '') {
        json_response(['success' => false, 'message' => 'Unable to resolve Minecraft username.'], 404);
    }

    if ($accountId) {
        $accountModel->update($accountId, [
            'minecraft_uuid' => strtolower((string) $resolved['id']),
            'minecraft_name' => $resolved['name'] ?? $username,
            'display_name' => trim((string) ($data['display_name'] ?? '')) ?: ($resolved['name'] ?? $username),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'last_synced_at' => $data['last_synced_at'] ?? null,
        ]);
    } else {
        $existing = $database->fetchOne('SELECT id FROM accounts WHERE minecraft_uuid = :uuid LIMIT 1', ['uuid' => strtolower((string) $resolved['id'])]);
        if ($existing) {
            json_response(['success' => false, 'message' => 'That account is already tracked.'], 409);
        }

        $accountId = $accountModel->create([
            'minecraft_uuid' => strtolower((string) $resolved['id']),
            'minecraft_name' => $resolved['name'] ?? $username,
            'display_name' => trim((string) ($data['display_name'] ?? '')) ?: ($resolved['name'] ?? $username),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);
    }

    json_response(['success' => true, 'message' => 'Account saved successfully.', 'account_id' => $accountId]);
}

if ($method === 'DELETE') {
    ensure_csrf((string) ($data['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $id = isset($data['id']) && ctype_digit((string) $data['id']) ? (int) $data['id'] : 0;
    if ($id <= 0) {
        json_response(['success' => false, 'message' => 'Invalid account id.'], 422);
    }

    $accountModel->delete($id);
    json_response(['success' => true, 'message' => 'Account deleted.']);
}

json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
