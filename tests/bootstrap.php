<?php
declare(strict_types=1);

use SkyBlock\Database\Database;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

$vendorAutoload = APP_ROOT . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefixes = [
            'SkyBlock\\' => APP_ROOT . '/src/',
            'Dotenv\\' => APP_ROOT . '/src/Fallback/Dotenv/',
        ];

        foreach ($prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $path = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }
    });
}

function createTestDatabase(): Database
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $schema = [
        'CREATE TABLE api_cache (id INTEGER PRIMARY KEY AUTOINCREMENT, cache_key TEXT UNIQUE, response_json TEXT NOT NULL, expires_at TEXT NOT NULL, created_at TEXT)',
        'CREATE TABLE goals (id INTEGER PRIMARY KEY AUTOINCREMENT, profile_id INTEGER NOT NULL, name TEXT NOT NULL, description TEXT, category TEXT, priority TEXT, target_progress REAL DEFAULT 100, current_progress REAL DEFAULT 0, status TEXT DEFAULT "pending", notes TEXT, created_at TEXT, updated_at TEXT, completed_at TEXT)',
        'CREATE TABLE goal_requirements (id INTEGER PRIMARY KEY AUTOINCREMENT, goal_id INTEGER NOT NULL, requirement_type TEXT NOT NULL, requirement_key TEXT NOT NULL, requirement_value TEXT NOT NULL, is_met INTEGER DEFAULT 0, checked_at TEXT)',
        'CREATE TABLE profile_skills (id INTEGER PRIMARY KEY AUTOINCREMENT, profile_id INTEGER NOT NULL, skill_name TEXT NOT NULL, level REAL DEFAULT 0, xp REAL DEFAULT 0, xp_next_level REAL DEFAULT 0, synced_at TEXT)',
        'CREATE TABLE profile_collections (id INTEGER PRIMARY KEY AUTOINCREMENT, profile_id INTEGER NOT NULL, collection_key TEXT NOT NULL, collection_name TEXT, category TEXT, amount INTEGER DEFAULT 0, tier INTEGER DEFAULT 0, synced_at TEXT)',
        'CREATE TABLE profile_dungeons (id INTEGER PRIMARY KEY AUTOINCREMENT, profile_id INTEGER NOT NULL, dungeon_type TEXT NOT NULL, floor TEXT NOT NULL, completions INTEGER DEFAULT 0, best_score TEXT, fastest_time INTEGER, synced_at TEXT)',
        'CREATE TABLE profile_items (id INTEGER PRIMARY KEY AUTOINCREMENT, profile_id INTEGER NOT NULL, item_id TEXT NOT NULL, item_name TEXT NOT NULL, category TEXT, quantity INTEGER DEFAULT 1, rarity TEXT, extra_data TEXT, synced_at TEXT)',
        'CREATE TABLE profile_snapshots (id INTEGER PRIMARY KEY AUTOINCREMENT, profile_id INTEGER NOT NULL, timestamp TEXT, networth REAL DEFAULT 0, magical_power REAL DEFAULT 0, skill_average REAL DEFAULT 0, combat_level REAL DEFAULT 0, mining_level REAL DEFAULT 0, farming_level REAL DEFAULT 0, fishing_level REAL DEFAULT 0, foraging_level REAL DEFAULT 0, enchanting_level REAL DEFAULT 0, alchemy_level REAL DEFAULT 0, taming_level REAL DEFAULT 0, carpentry_level REAL DEFAULT 0, runecrafting_level REAL DEFAULT 0, catacombs_level REAL DEFAULT 0, raw_stats TEXT)',
    ];

    foreach ($schema as $statement) {
        $pdo->exec($statement);
    }

    return new Database($pdo);
}
