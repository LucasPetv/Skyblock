<?php
declare(strict_types=1);

namespace SkyBlock\Services;

use SkyBlock\Database\Database;
use SkyBlock\Models\Goal;

class ProgressionAnalyzer
{
    public function __construct(private readonly Database $database)
    {
    }

    public function analyzeGoal(int $goalId): array
    {
        $goal = (new Goal($this->database))->findById($goalId);
        if (!$goal) {
            return ['goal' => null, 'completed_requirements' => [], 'missing_requirements' => [], 'progress' => 0.0];
        }

        $requirements = $this->database->fetchAll(
            'SELECT * FROM goal_requirements WHERE goal_id = :goal_id ORDER BY id ASC',
            ['goal_id' => $goalId]
        );

        if ($requirements === []) {
            return ['goal' => $goal, 'completed_requirements' => [], 'missing_requirements' => [], 'progress' => 100.0];
        }

        $completed = [];
        $missing = [];
        foreach ($requirements as $requirement) {
            $evaluation = $this->evaluateRequirement((int) $goal['profile_id'], $requirement);
            $target = (string) $requirement['requirement_value'];
            $this->database->execute(
                'UPDATE goal_requirements SET is_met = :is_met, checked_at = :checked_at WHERE id = :id',
                [
                    'id' => $requirement['id'],
                    'is_met' => $evaluation['is_met'] ? 1 : 0,
                    'checked_at' => date('Y-m-d H:i:s'),
                ]
            );

            $payload = [
                'id' => (int) $requirement['id'],
                'type' => $requirement['requirement_type'],
                'key' => $requirement['requirement_key'],
                'target' => $target,
                'current' => $evaluation['current'],
                'reason' => $evaluation['reason'],
            ];

            if ($evaluation['is_met']) {
                $completed[] = $payload;
            } else {
                $missing[] = $payload;
            }
        }

        $progress = round((count($completed) / max(1, count($requirements))) * 100, 2);
        $status = $progress >= 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'pending');
        $this->database->execute(
            'UPDATE goals SET current_progress = :progress, status = :status, completed_at = :completed_at, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [
                'id' => $goalId,
                'progress' => round(($progress / 100) * (float) ($goal['target_progress'] ?? 100), 2),
                'status' => $status,
                'completed_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null,
            ]
        );

        return [
            'goal' => $goal,
            'completed_requirements' => $completed,
            'missing_requirements' => $missing,
            'progress' => $progress,
        ];
    }

    public function suggestGoals(int $profileId): array
    {
        $suggestions = [];
        $snapshot = $this->database->fetchOne(
            'SELECT * FROM profile_snapshots WHERE profile_id = :profile_id ORDER BY timestamp DESC LIMIT 1',
            ['profile_id' => $profileId]
        ) ?? [];
        $skills = $this->database->fetchAll('SELECT skill_name, level FROM profile_skills WHERE profile_id = :profile_id', ['profile_id' => $profileId]);
        $skillLevels = [];
        foreach ($skills as $skill) {
            $skillLevels[$skill['skill_name']] = (float) $skill['level'];
        }

        $rules = $this->database->fetchAll(
            'SELECT * FROM progression_rules WHERE requirement_type IN (\'skill\', \'dungeon\', \'collection\') ORDER BY goal_key ASC LIMIT 100'
        );
        $existing = $this->database->fetchAll('SELECT name FROM goals WHERE profile_id = :profile_id', ['profile_id' => $profileId]);
        $existingNames = array_map(static fn(array $goal): string => strtolower((string) $goal['name']), $existing);

        foreach ($rules as $rule) {
            $name = ucwords(str_replace('_', ' ', (string) $rule['goal_key']));
            if (in_array(strtolower($name), $existingNames, true)) {
                continue;
            }
            $suggestions[] = [
                'name' => $name,
                'category' => $rule['requirement_type'],
                'reason' => $rule['description'] ?: sprintf('Progress toward %s %s.', $rule['requirement_key'], $rule['requirement_value']),
                'priority' => 'medium',
            ];
        }

        $defaults = [
            ['skill' => 'combat', 'target' => 25, 'name' => 'Reach Combat 25', 'reason' => 'Improves survivability and combat unlocks.'],
            ['skill' => 'mining', 'target' => 25, 'name' => 'Reach Mining 25', 'reason' => 'Unlocks efficient ore progression and dwarven content.'],
            ['skill' => 'farming', 'target' => 30, 'name' => 'Reach Farming 30', 'reason' => 'Boosts crop yields and unlocks stronger tools.'],
        ];

        foreach ($defaults as $default) {
            $current = $skillLevels[$default['skill']] ?? 0;
            if ($current < $default['target'] && !in_array(strtolower($default['name']), $existingNames, true)) {
                $suggestions[] = [
                    'name' => $default['name'],
                    'category' => 'skill',
                    'reason' => sprintf('%s Current level: %.1f / %d.', $default['reason'], $current, $default['target']),
                    'priority' => $current < ($default['target'] / 2) ? 'high' : 'medium',
                ];
            }
        }

        $magicalPower = (float) ($snapshot['magical_power'] ?? 0);
        if ($magicalPower < 300 && !in_array('increase magical power', $existingNames, true)) {
            $suggestions[] = [
                'name' => 'Increase Magical Power',
                'category' => 'gear',
                'reason' => sprintf('More accessories and enrichment will help; current magical power is %.0f.', $magicalPower),
                'priority' => 'high',
            ];
        }

        $catacombs = (float) ($snapshot['catacombs_level'] ?? 0);
        if ($catacombs < 20 && !in_array('reach catacombs 20', $existingNames, true)) {
            $suggestions[] = [
                'name' => 'Reach Catacombs 20',
                'category' => 'dungeons',
                'reason' => sprintf('Floor access and gear usage improve substantially after early cata progression; current level %.1f.', $catacombs),
                'priority' => 'medium',
            ];
        }

        return array_slice($suggestions, 0, 8);
    }

    public function getProgressPercentage(int $goalId): float
    {
        $analysis = $this->analyzeGoal($goalId);
        return (float) ($analysis['progress'] ?? 0.0);
    }

    private function evaluateRequirement(int $profileId, array $requirement): array
    {
        $type = (string) $requirement['requirement_type'];
        $key = (string) $requirement['requirement_key'];
        $target = is_numeric($requirement['requirement_value']) ? (float) $requirement['requirement_value'] : (string) $requirement['requirement_value'];

        return match ($type) {
            'skill' => $this->evaluateSkillRequirement($profileId, $key, (float) $target),
            'collection' => $this->evaluateCollectionRequirement($profileId, $key, (float) $target),
            'dungeon' => $this->evaluateDungeonRequirement($profileId, $key, (float) $target),
            'item' => $this->evaluateItemRequirement($profileId, $key, (float) $target),
            'stat' => $this->evaluateSnapshotStatRequirement($profileId, $key, (float) $target),
            default => ['is_met' => false, 'current' => 0, 'reason' => 'Unsupported requirement type.'],
        };
    }

    private function evaluateSkillRequirement(int $profileId, string $skill, float $target): array
    {
        $row = $this->database->fetchOne(
            'SELECT level FROM profile_skills WHERE profile_id = :profile_id AND skill_name = :skill LIMIT 1',
            ['profile_id' => $profileId, 'skill' => $skill]
        );
        $current = (float) ($row['level'] ?? 0);

        return [
            'is_met' => $current >= $target,
            'current' => $current,
            'reason' => sprintf('Requires %s level %.1f. Current level %.1f.', ucfirst($skill), $target, $current),
        ];
    }

    private function evaluateCollectionRequirement(int $profileId, string $collectionKey, float $target): array
    {
        $row = $this->database->fetchOne(
            'SELECT amount, tier FROM profile_collections WHERE profile_id = :profile_id AND collection_key = :collection_key LIMIT 1',
            ['profile_id' => $profileId, 'collection_key' => $collectionKey]
        );
        $current = max((float) ($row['amount'] ?? 0), (float) ($row['tier'] ?? 0));

        return [
            'is_met' => $current >= $target,
            'current' => $current,
            'reason' => sprintf('Requires collection %s at %.0f. Current progress %.0f.', $collectionKey, $target, $current),
        ];
    }

    private function evaluateDungeonRequirement(int $profileId, string $floor, float $target): array
    {
        $row = $this->database->fetchOne(
            'SELECT completions FROM profile_dungeons WHERE profile_id = :profile_id AND floor = :floor ORDER BY synced_at DESC LIMIT 1',
            ['profile_id' => $profileId, 'floor' => $floor]
        );
        $current = (float) ($row['completions'] ?? 0);

        return [
            'is_met' => $current >= $target,
            'current' => $current,
            'reason' => sprintf('Requires %s completions %.0f. Current completions %.0f.', $floor, $target, $current),
        ];
    }

    private function evaluateItemRequirement(int $profileId, string $itemKey, float $target): array
    {
        $row = $this->database->fetchOne(
            'SELECT COALESCE(SUM(quantity), 0) AS owned FROM profile_items WHERE profile_id = :profile_id AND (item_id = :item_id OR item_name = :item_name)',
            ['profile_id' => $profileId, 'item_id' => $itemKey, 'item_name' => $itemKey]
        );
        $current = (float) ($row['owned'] ?? 0);

        return [
            'is_met' => $current >= $target,
            'current' => $current,
            'reason' => sprintf('Requires %s x%.0f. Owned %.0f.', $itemKey, $target, $current),
        ];
    }

    private function evaluateSnapshotStatRequirement(int $profileId, string $stat, float $target): array
    {
        $allowed = [
            'networth', 'magical_power', 'skill_average', 'combat_level', 'mining_level', 'farming_level',
            'fishing_level', 'foraging_level', 'enchanting_level', 'alchemy_level', 'taming_level',
            'carpentry_level', 'runecrafting_level', 'catacombs_level',
        ];
        if (!in_array($stat, $allowed, true)) {
            return ['is_met' => false, 'current' => 0, 'reason' => 'Unsupported stat requirement.'];
        }

        $row = $this->database->fetchOne(
            'SELECT ' . $stat . ' FROM profile_snapshots WHERE profile_id = :profile_id ORDER BY timestamp DESC LIMIT 1',
            ['profile_id' => $profileId]
        );
        $current = (float) ($row[$stat] ?? 0);

        return [
            'is_met' => $current >= $target,
            'current' => $current,
            'reason' => sprintf('Requires %s %.1f. Current %.1f.', str_replace('_', ' ', $stat), $target, $current),
        ];
    }
}
