<?php
declare(strict_types=1);

namespace SkyBlock\Services;

use RuntimeException;
use SkyBlock\Api\HypixelApiClient;
use SkyBlock\Database\Database;

class SyncService
{
    private array $cacheConfig;

    public function __construct(
        private readonly Database $database,
        private readonly HypixelApiClient $apiClient,
        private readonly CacheService $cacheService
    ) {
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $this->cacheConfig = $appConfig['cache'] ?? [];
    }

    public function syncAccount(int $accountId): array
    {
        $logId = $this->startSyncLog($accountId);

        try {
            $account = $this->database->fetchOne('SELECT * FROM accounts WHERE id = :id LIMIT 1', ['id' => $accountId]);
            if (!$account) {
                throw new RuntimeException('Account not found.');
            }

            $uuid = strtolower((string) $account['minecraft_uuid']);
            $player = $this->remember('player_' . $uuid, fn(): array => $this->apiClient->getPlayerByUuid($uuid), (int) ($this->cacheConfig['player_seconds'] ?? 300));
            $profiles = $this->remember('profiles_' . $uuid, fn(): array => $this->apiClient->getProfilesByUuid($uuid), (int) ($this->cacheConfig['profile_seconds'] ?? 300));

            $displayName = $player['displayname'] ?? $account['minecraft_name'];
            $this->database->execute(
                'UPDATE accounts SET minecraft_name = :minecraft_name, display_name = :display_name, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                [
                    'id' => $accountId,
                    'minecraft_name' => $displayName,
                    'display_name' => $account['display_name'] ?: $displayName,
                ]
            );

            $this->database->execute('UPDATE profiles SET is_active = 0, is_selected = 0 WHERE account_id = :account_id', ['account_id' => $accountId]);

            $synced = 0;
            foreach ($profiles as $index => $profile) {
                $member = $this->extractMember($profile, $uuid);
                if ($member === []) {
                    continue;
                }

                $profileDbId = $this->upsertProfile($accountId, $profile, $index === 0);
                $skills = $this->syncSkills($profileDbId, $member);
                $this->syncCollections($profileDbId, $profile, $member);
                $this->syncDungeons($profileDbId, $member);
                $this->syncItems($profileDbId, $member);
                $this->createSnapshot($profileDbId, $profile, $member, $skills);
                $synced++;
            }

            $this->database->execute(
                'UPDATE accounts SET last_synced_at = :last_synced_at, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['id' => $accountId, 'last_synced_at' => date('Y-m-d H:i:s')]
            );

            $message = sprintf('Successfully synced %d profile(s).', $synced);
            $this->finishSyncLog($logId, 'success', $message, $synced);

            return ['success' => true, 'message' => $message, 'profiles_synced' => $synced];
        } catch (\Throwable $exception) {
            $message = 'Sync failed: ' . $exception->getMessage();
            $this->finishSyncLog($logId, 'failed', $message, 0);
            return ['success' => false, 'message' => $message, 'profiles_synced' => 0];
        }
    }

    private function remember(string $key, callable $resolver, int $ttl): array
    {
        $cached = $this->cacheService->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $data = $resolver();
        $this->cacheService->set($key, $data, $ttl);
        return $data;
    }

    private function startSyncLog(int $accountId): int
    {
        $this->database->execute(
            'INSERT INTO sync_logs (account_id, started_at, status, message, profiles_synced) VALUES (:account_id, CURRENT_TIMESTAMP, :status, :message, 0)',
            ['account_id' => $accountId, 'status' => 'running', 'message' => 'Sync started.']
        );

        return (int) $this->database->lastInsertId();
    }

    private function finishSyncLog(int $logId, string $status, string $message, int $profilesSynced): void
    {
        $this->database->execute(
            'UPDATE sync_logs SET finished_at = :finished_at, status = :status, message = :message, profiles_synced = :profiles_synced WHERE id = :id',
            [
                'id' => $logId,
                'finished_at' => date('Y-m-d H:i:s'),
                'status' => $status,
                'message' => $message,
                'profiles_synced' => $profilesSynced,
            ]
        );
    }

    private function extractMember(array $profile, string $uuid): array
    {
        $members = $profile['members'] ?? [];
        if (isset($members[$uuid]) && is_array($members[$uuid])) {
            return $members[$uuid];
        }

        foreach ($members as $member) {
            if (is_array($member)) {
                return $member;
            }
        }

        return [];
    }

    private function upsertProfile(int $accountId, array $profile, bool $selected): int
    {
        $existing = $this->database->fetchOne('SELECT id FROM profiles WHERE profile_id = :profile_id LIMIT 1', ['profile_id' => $profile['profile_id'] ?? '']);
        $payload = [
            'account_id' => $accountId,
            'profile_id' => $profile['profile_id'] ?? '',
            'profile_name' => $profile['cute_name'] ?? ($profile['profile_name'] ?? 'Unknown Profile'),
            'game_mode' => $profile['game_mode'] ?? 'normal',
            'is_selected' => $selected ? 1 : 0,
            'is_active' => 1,
            'raw_data' => json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        if ($existing) {
            $this->database->execute(
                'UPDATE profiles SET profile_name = :profile_name, game_mode = :game_mode, is_selected = :is_selected, is_active = :is_active, raw_data = :raw_data, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                $payload + ['id' => $existing['id']]
            );
            return (int) $existing['id'];
        }

        $this->database->execute(
            'INSERT INTO profiles (account_id, profile_id, profile_name, game_mode, is_selected, is_active, raw_data, created_at, updated_at)
             VALUES (:account_id, :profile_id, :profile_name, :game_mode, :is_selected, :is_active, :raw_data, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            $payload
        );
        return (int) $this->database->lastInsertId();
    }

    private function syncSkills(int $profileId, array $member): array
    {
        $skills = [
            'combat', 'mining', 'farming', 'fishing', 'foraging', 'enchanting', 'alchemy', 'taming', 'carpentry', 'runecrafting',
        ];
        $levels = [];
        foreach ($skills as $skill) {
            $xp = (float) ($member['experience_skill_' . $skill] ?? 0);
            [$level, $xpNext] = $this->xpToLevel($xp, $skill);
            $levels[$skill] = $level;
            $this->database->execute(
                'DELETE FROM profile_skills WHERE profile_id = :profile_id AND skill_name = :skill_name',
                ['profile_id' => $profileId, 'skill_name' => $skill]
            );
            $this->database->execute(
                'INSERT INTO profile_skills (profile_id, skill_name, level, xp, xp_next_level, synced_at)
                 VALUES (:profile_id, :skill_name, :level, :xp, :xp_next_level, :synced_at)',
                [
                    'profile_id' => $profileId,
                    'skill_name' => $skill,
                    'level' => $level,
                    'xp' => $xp,
                    'xp_next_level' => $xpNext,
                    'synced_at' => date('Y-m-d H:i:s'),
                ]
            );
        }

        return $levels;
    }

    private function syncCollections(int $profileId, array $profile, array $member): void
    {
        $collections = $member['collection'] ?? $member['collections'] ?? $profile['collection'] ?? [];
        $this->database->execute('DELETE FROM profile_collections WHERE profile_id = :profile_id', ['profile_id' => $profileId]);

        foreach ($collections as $key => $value) {
            $amount = is_array($value) ? (int) ($value['amount'] ?? $value['total'] ?? 0) : (int) $value;
            $tier = is_array($value) ? (int) ($value['tier'] ?? 0) : 0;
            $parts = explode('_', (string) $key);
            $category = strtolower($parts[0] ?? 'misc');
            $name = ucwords(strtolower(str_replace('_', ' ', (string) $key)));
            $this->database->execute(
                'INSERT INTO profile_collections (profile_id, collection_key, collection_name, category, amount, tier, synced_at)
                 VALUES (:profile_id, :collection_key, :collection_name, :category, :amount, :tier, :synced_at)',
                [
                    'profile_id' => $profileId,
                    'collection_key' => (string) $key,
                    'collection_name' => $name,
                    'category' => $category,
                    'amount' => $amount,
                    'tier' => $tier,
                    'synced_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
    }

    private function syncDungeons(int $profileId, array $member): void
    {
        $dungeons = $member['dungeons']['dungeon_types'] ?? [];
        $this->database->execute('DELETE FROM profile_dungeons WHERE profile_id = :profile_id', ['profile_id' => $profileId]);

        foreach ($dungeons as $type => $data) {
            $floors = $data['tier_completions'] ?? $data['floors'] ?? [];
            foreach ($floors as $floor => $completions) {
                $score = $data['best_score'][$floor] ?? null;
                $time = $data['fastest_time'][$floor] ?? null;
                $this->database->execute(
                    'INSERT INTO profile_dungeons (profile_id, dungeon_type, floor, completions, best_score, fastest_time, synced_at)
                     VALUES (:profile_id, :dungeon_type, :floor, :completions, :best_score, :fastest_time, :synced_at)',
                    [
                        'profile_id' => $profileId,
                        'dungeon_type' => (string) $type,
                        'floor' => strtoupper((string) $floor),
                        'completions' => (int) $completions,
                        'best_score' => $score !== null ? (string) $score : null,
                        'fastest_time' => $time !== null ? (int) $time : null,
                        'synced_at' => date('Y-m-d H:i:s'),
                    ]
                );
            }
        }
    }

    private function syncItems(int $profileId, array $member): void
    {
        $items = [];
        $this->collectItems($member, $items);
        $this->database->execute('DELETE FROM profile_items WHERE profile_id = :profile_id', ['profile_id' => $profileId]);

        $count = 0;
        foreach ($items as $item) {
            if ($count >= 300) {
                break;
            }

            $this->database->execute(
                'INSERT INTO profile_items (profile_id, item_id, item_name, category, quantity, rarity, extra_data, synced_at)
                 VALUES (:profile_id, :item_id, :item_name, :category, :quantity, :rarity, :extra_data, :synced_at)',
                [
                    'profile_id' => $profileId,
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'category' => $item['category'],
                    'quantity' => $item['quantity'],
                    'rarity' => $item['rarity'],
                    'extra_data' => json_encode($item['extra_data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'synced_at' => date('Y-m-d H:i:s'),
                ]
            );
            $count++;
        }
    }

    private function createSnapshot(int $profileId, array $profile, array $member, array $skills): void
    {
        [$catacombsLevel] = $this->xpToDungeonLevel((float) ($member['dungeons']['dungeon_types']['catacombs']['experience'] ?? 0));
        $skillAverage = count($skills) > 0 ? array_sum($skills) / count($skills) : 0;
        $purse = (float) ($member['coin_purse'] ?? $member['currencies']['coin_purse'] ?? 0);
        $bank = (float) ($profile['banking']['balance'] ?? 0);
        $networth = (float) ($member['networth'] ?? ($purse + $bank));
        $magicalPower = (float) ($member['accessory_bag_storage']['highest_magical_power'] ?? $member['highest_magical_power'] ?? 0);

        $this->database->execute(
            'INSERT INTO profile_snapshots (profile_id, timestamp, networth, magical_power, skill_average, combat_level, mining_level, farming_level, fishing_level, foraging_level, enchanting_level, alchemy_level, taming_level, carpentry_level, runecrafting_level, catacombs_level, raw_stats)
             VALUES (:profile_id, :timestamp, :networth, :magical_power, :skill_average, :combat_level, :mining_level, :farming_level, :fishing_level, :foraging_level, :enchanting_level, :alchemy_level, :taming_level, :carpentry_level, :runecrafting_level, :catacombs_level, :raw_stats)',
            [
                'profile_id' => $profileId,
                'timestamp' => date('Y-m-d H:i:s'),
                'networth' => $networth,
                'magical_power' => $magicalPower,
                'skill_average' => round($skillAverage, 2),
                'combat_level' => $skills['combat'] ?? 0,
                'mining_level' => $skills['mining'] ?? 0,
                'farming_level' => $skills['farming'] ?? 0,
                'fishing_level' => $skills['fishing'] ?? 0,
                'foraging_level' => $skills['foraging'] ?? 0,
                'enchanting_level' => $skills['enchanting'] ?? 0,
                'alchemy_level' => $skills['alchemy'] ?? 0,
                'taming_level' => $skills['taming'] ?? 0,
                'carpentry_level' => $skills['carpentry'] ?? 0,
                'runecrafting_level' => $skills['runecrafting'] ?? 0,
                'catacombs_level' => $catacombsLevel,
                'raw_stats' => json_encode([
                    'purse' => $purse,
                    'bank' => $bank,
                    'fairy_souls' => $member['fairy_souls_collected'] ?? 0,
                    'slayer' => $member['slayer_bosses'] ?? [],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private function collectItems(array $source, array &$items, string $category = 'inventory'): void
    {
        foreach ($source as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            if (isset($value['id']) || isset($value['item_id']) || isset($value['tag'])) {
                $itemId = (string) ($value['item_id'] ?? $value['id'] ?? $value['tag']['ExtraAttributes']['id'] ?? $key);
                $name = (string) ($value['display_name'] ?? $value['name'] ?? $value['tag']['display']['Name'] ?? $itemId);
                $items[] = [
                    'item_id' => $itemId,
                    'item_name' => trim(strip_tags($name)),
                    'category' => $category,
                    'quantity' => (int) ($value['count'] ?? $value['quantity'] ?? 1),
                    'rarity' => $value['rarity'] ?? $value['tag']['ExtraAttributes']['rarity_upgrades'] ?? null,
                    'extra_data' => $value,
                ];
                continue;
            }

            $nextCategory = is_string($key) ? strtolower($key) : $category;
            $this->collectItems($value, $items, $nextCategory);
        }
    }

    private function xpToLevel(float $xp, string $skill): array
    {
        $tables = [
            'default' => [50, 125, 200, 300, 500, 750, 1000, 1500, 2000, 3500, 5000, 7500, 10000, 15000, 20000, 30000, 50000, 75000, 100000, 200000, 300000, 400000, 500000, 600000, 700000, 800000, 900000, 1000000, 1100000, 1200000, 1300000, 1400000, 1500000, 1600000, 1700000, 1800000, 1900000, 2000000, 2100000, 2200000, 2300000, 2400000, 2500000, 2600000, 2750000, 2900000, 3100000, 3400000, 3700000, 4000000, 4300000, 4600000, 4900000, 5200000, 5500000, 5800000, 6100000, 6400000, 6700000, 7000000],
            'runecrafting' => [50, 100, 125, 160, 200, 250, 315, 400, 500, 625, 785, 1000, 1250, 1600, 2000, 2465, 3125, 4000, 5000, 6200, 7800, 9800, 12200, 15300, 19050],
            'carpentry' => [50, 125, 200, 300, 500, 750, 1000, 1500, 2000, 3500, 5000, 7500, 10000, 15000, 20000, 30000, 50000, 75000, 100000, 200000, 300000, 400000, 500000, 600000, 700000, 800000, 900000, 1000000, 1100000, 1200000, 1300000, 1400000, 1500000, 1600000, 1700000, 1800000, 1900000, 2000000, 2100000, 2200000, 2300000, 2400000, 2500000, 2600000, 2750000, 2900000, 3100000, 3400000, 3700000, 4000000],
        ];
        $table = $tables[$skill] ?? $tables['default'];
        $remaining = $xp;
        $level = 0.0;
        $next = 0.0;

        foreach ($table as $index => $requirement) {
            if ($remaining < $requirement) {
                $level = $index + ($requirement > 0 ? $remaining / $requirement : 0);
                $next = max(0, $requirement - $remaining);
                return [round($level, 2), $next];
            }
            $remaining -= $requirement;
        }

        return [count($table), 0.0];
    }

    private function xpToDungeonLevel(float $xp): array
    {
        $table = [50, 75, 110, 160, 230, 330, 470, 670, 950, 1340, 1890, 2665, 3760, 5260, 7380, 10300, 14400, 20000, 27600, 38000, 52500, 71500, 97000, 132000, 180000, 243000, 328000, 445000, 600000, 800000, 1065000, 1410000, 1900000, 2500000, 3300000, 4300000, 5600000, 7200000, 9200000, 12000000, 15000000, 19000000, 24000000, 30000000, 38000000, 48000000, 60000000, 75000000, 93000000, 116250000];
        $remaining = $xp;
        foreach ($table as $index => $requirement) {
            if ($remaining < $requirement) {
                return [round($index + ($requirement > 0 ? $remaining / $requirement : 0), 2), max(0, $requirement - $remaining)];
            }
            $remaining -= $requirement;
        }

        return [50.0, 0.0];
    }
}
