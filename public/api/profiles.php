<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use SkyBlock\Models\Profile;
use SkyBlock\Services\GoalService;
use SkyBlock\Services\ProgressionAnalyzer;
use SkyBlock\Services\ResourceAnalyzer;

$database = db();
$profileModel = new Profile($database);
$goalService = new GoalService($database);
$progressionAnalyzer = new ProgressionAnalyzer($database);
$resourceAnalyzer = new ResourceAnalyzer($database);
$data = request_data();

$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$pathId = $pathInfo !== '' ? basename($pathInfo) : null;
$profileId = null;
if (isset($data['profile_id']) && ctype_digit((string) $data['profile_id'])) {
    $profileId = (int) $data['profile_id'];
} elseif (isset($data['id']) && ctype_digit((string) $data['id'])) {
    $profileId = (int) $data['id'];
} elseif ($pathId && ctype_digit($pathId)) {
    $profileId = (int) $pathId;
}

if (isset($data['account_id']) && ctype_digit((string) $data['account_id']) && !isset($data['view'])) {
    json_response(['success' => true, 'profiles' => $profileModel->findByAccountId((int) $data['account_id'])]);
}

if (($data['view'] ?? '') === 'dashboard' && $profileId) {
    $snapshot = $profileModel->getLatestSnapshot($profileId);
    $goals = $goalService->getGoalsForProfile($profileId);
    foreach ($goals as &$goal) {
        $goal['analysis'] = $progressionAnalyzer->analyzeGoal((int) $goal['id']);
    }
    unset($goal);
    $history = $database->fetchAll('SELECT timestamp, networth, skill_average, magical_power, catacombs_level FROM profile_snapshots WHERE profile_id = :profile_id ORDER BY timestamp ASC LIMIT 30', ['profile_id' => $profileId]);
    $activity = $database->fetchAll('SELECT completed_at, dungeon_type, floor, result, drop_obtained, notes FROM dungeon_runs WHERE profile_id = :profile_id ORDER BY completed_at DESC LIMIT 10', ['profile_id' => $profileId]);
    $bottlenecks = $resourceAnalyzer->getBottlenecks($profileId);
    json_response([
        'success' => true,
        'stats' => [
            'networth' => (float) ($snapshot['networth'] ?? 0),
            'magical_power' => (float) ($snapshot['magical_power'] ?? 0),
            'skill_average' => (float) ($snapshot['skill_average'] ?? 0),
            'catacombs_level' => (float) ($snapshot['catacombs_level'] ?? 0),
        ],
        'goals' => $goals,
        'history' => $history,
        'bottlenecks' => $bottlenecks,
        'activity' => $activity,
    ]);
}

if ($profileId) {
    $profile = $profileModel->findById($profileId);
    if (!$profile) {
        json_response(['success' => false, 'message' => 'Profile not found.'], 404);
    }

    json_response([
        'success' => true,
        'profile' => $profile,
        'snapshot' => $profileModel->getLatestSnapshot($profileId),
        'skills' => $database->fetchAll('SELECT * FROM profile_skills WHERE profile_id = :profile_id ORDER BY skill_name ASC', ['profile_id' => $profileId]),
        'collections' => $database->fetchAll('SELECT * FROM profile_collections WHERE profile_id = :profile_id ORDER BY amount DESC LIMIT 100', ['profile_id' => $profileId]),
        'dungeons' => $database->fetchAll('SELECT * FROM profile_dungeons WHERE profile_id = :profile_id ORDER BY dungeon_type ASC, floor ASC', ['profile_id' => $profileId]),
    ]);
}

json_response(['success' => false, 'message' => 'Missing profile request parameters.'], 422);
