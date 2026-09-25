<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

$database = db();
$method = method_override();
$data = request_data();

if ($method === 'GET') {
    $profileId = isset($data['profile_id']) && ctype_digit((string) $data['profile_id']) ? (int) $data['profile_id'] : 0;
    if ($profileId <= 0) {
        json_response(['success' => false, 'message' => 'profile_id is required.'], 422);
    }

    $runs = $database->fetchAll('SELECT * FROM dungeon_runs WHERE profile_id = :profile_id ORDER BY completed_at DESC LIMIT 100', ['profile_id' => $profileId]);
    json_response(['success' => true, 'runs' => $runs]);
}

if ($method === 'POST') {
    ensure_csrf((string) ($data['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $profileId = isset($data['profile_id']) && ctype_digit((string) $data['profile_id']) ? (int) $data['profile_id'] : 0;
    if ($profileId <= 0) {
        json_response(['success' => false, 'message' => 'profile_id is required.'], 422);
    }

    $database->execute(
        'INSERT INTO dungeon_runs (profile_id, dungeon_type, floor, completed_at, result, drop_obtained, notes, is_manual, created_at)
         VALUES (:profile_id, :dungeon_type, :floor, :completed_at, :result, :drop_obtained, :notes, 1, CURRENT_TIMESTAMP)',
        [
            'profile_id' => $profileId,
            'dungeon_type' => trim((string) ($data['dungeon_type'] ?? 'catacombs')),
            'floor' => trim((string) ($data['floor'] ?? 'F1')),
            'completed_at' => trim((string) ($data['completed_at'] ?? date('Y-m-d H:i:s'))),
            'result' => trim((string) ($data['result'] ?? 'complete')),
            'drop_obtained' => trim((string) ($data['drop_obtained'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ]
    );

    json_response(['success' => true, 'message' => 'Dungeon run saved.', 'id' => (int) $database->lastInsertId()], 201);
}

json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
