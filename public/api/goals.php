<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use SkyBlock\Services\GoalService;

$database = db();
$service = new GoalService($database);
$method = method_override();
$data = request_data();

if ($method === 'GET') {
    if (isset($data['profile_id']) && ctype_digit((string) $data['profile_id'])) {
        json_response(['success' => true, 'goals' => $service->getGoalsForProfile((int) $data['profile_id'])]);
    }

    if (isset($data['id']) && ctype_digit((string) $data['id'])) {
        $goal = $database->fetchOne('SELECT * FROM goals WHERE id = :id', ['id' => (int) $data['id']]);
        json_response(['success' => true, 'goal' => $goal]);
    }

    json_response(['success' => false, 'message' => 'Missing goal identifier.'], 422);
}

if ($method === 'POST') {
    ensure_csrf((string) ($data['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $requirements = $data['requirements'] ?? [];
    if (!is_array($requirements)) {
        $requirements = [];
    }
    $payload = [
        'profile_id' => (int) ($data['profile_id'] ?? 0),
        'name' => trim((string) ($data['name'] ?? '')),
        'description' => trim((string) ($data['description'] ?? '')),
        'category' => trim((string) ($data['category'] ?? 'general')),
        'priority' => trim((string) ($data['priority'] ?? 'medium')),
        'target_progress' => (float) ($data['target_progress'] ?? 100),
        'status' => trim((string) ($data['status'] ?? 'pending')),
        'notes' => trim((string) ($data['notes'] ?? '')),
        'requirements' => array_values(array_filter(array_map(static function (array $item): array {
            return [
                'type' => trim((string) ($item['type'] ?? 'skill')),
                'key' => trim((string) ($item['key'] ?? '')),
                'value' => trim((string) ($item['value'] ?? '0')),
            ];
        }, $requirements), static fn(array $item): bool => $item['key'] !== '')),
    ];

    if ($payload['profile_id'] <= 0 || $payload['name'] === '') {
        json_response(['success' => false, 'message' => 'profile_id and name are required.'], 422);
    }

    if (isset($data['id']) && ctype_digit((string) $data['id'])) {
        $service->updateGoal((int) $data['id'], $payload);
        json_response(['success' => true, 'message' => 'Goal updated.', 'goal_id' => (int) $data['id']]);
    }

    $goalId = $service->createGoal($payload);
    json_response(['success' => true, 'message' => 'Goal created.', 'goal_id' => $goalId], 201);
}

if ($method === 'DELETE') {
    ensure_csrf((string) ($data['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $goalId = isset($data['id']) && ctype_digit((string) $data['id']) ? (int) $data['id'] : 0;
    if ($goalId <= 0) {
        json_response(['success' => false, 'message' => 'Invalid goal id.'], 422);
    }

    $service->deleteGoal($goalId);
    json_response(['success' => true, 'message' => 'Goal deleted.']);
}

json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
