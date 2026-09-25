<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

$database = db();
$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '') {
    json_response(['success' => false, 'message' => 'Search query is required.'], 422);
}

$needle = '%' . $query . '%';
$accounts = $database->fetchAll('SELECT id, minecraft_name, display_name FROM accounts WHERE minecraft_name LIKE :q OR display_name LIKE :q LIMIT 10', ['q' => $needle]);
$profiles = $database->fetchAll('SELECT id, profile_name, game_mode FROM profiles WHERE profile_name LIKE :q LIMIT 10', ['q' => $needle]);
$goals = $database->fetchAll('SELECT id, name, status FROM goals WHERE name LIKE :q OR description LIKE :q LIMIT 10', ['q' => $needle]);

json_response(['success' => true, 'accounts' => $accounts, 'profiles' => $profiles, 'goals' => $goals]);
