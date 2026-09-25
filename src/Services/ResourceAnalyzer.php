<?php
declare(strict_types=1);

namespace SkyBlock\Services;

use SkyBlock\Database\Database;

class ResourceAnalyzer
{
    public function __construct(private readonly Database $database)
    {
    }

    public function getBottlenecks(int $profileId, array $goalIds = []): array
    {
        if ($goalIds === []) {
            $goals = $this->database->fetchAll(
                'SELECT id FROM goals WHERE profile_id = :profile_id AND status IN ("pending", "in_progress")',
                ['profile_id' => $profileId]
            );
            $goalIds = array_map(static fn(array $goal): int => (int) $goal['id'], $goals);
        }

        $aggregated = [];
        foreach ($goalIds as $goalId) {
            foreach ($this->getResourceRequirements((int) $goalId) as $resource) {
                $key = strtolower($resource['item']);
                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = $resource;
                    $aggregated[$key]['goals'] = [(int) $goalId];
                } else {
                    $aggregated[$key]['required'] += $resource['required'];
                    $aggregated[$key]['owned'] = max($aggregated[$key]['owned'], $resource['owned']);
                    $aggregated[$key]['missing'] = max(0, $aggregated[$key]['required'] - $aggregated[$key]['owned']);
                    $aggregated[$key]['goals'][] = (int) $goalId;
                }
            }
        }

        $results = array_values(array_filter($aggregated, static fn(array $row): bool => $row['missing'] > 0));
        usort($results, static fn(array $a, array $b): int => $b['missing'] <=> $a['missing']);

        return $results;
    }

    public function getResourceRequirements(int $goalId): array
    {
        $goal = $this->database->fetchOne('SELECT profile_id, name FROM goals WHERE id = :id', ['id' => $goalId]);
        if (!$goal) {
            return [];
        }

        $requirements = $this->database->fetchAll(
            'SELECT requirement_type, requirement_key, requirement_value FROM goal_requirements WHERE goal_id = :goal_id',
            ['goal_id' => $goalId]
        );

        $resources = [];
        foreach ($requirements as $requirement) {
            $type = (string) $requirement['requirement_type'];
            if (!in_array($type, ['item', 'collection'], true)) {
                continue;
            }

            $required = (float) $requirement['requirement_value'];
            $key = (string) $requirement['requirement_key'];
            if ($type === 'item') {
                $ownedRow = $this->database->fetchOne(
                    'SELECT COALESCE(SUM(quantity), 0) AS owned FROM profile_items WHERE profile_id = :profile_id AND (item_id = :item_key OR item_name = :item_key)',
                    ['profile_id' => $goal['profile_id'], 'item_key' => $key]
                );
            } else {
                $ownedRow = $this->database->fetchOne(
                    'SELECT COALESCE(MAX(amount), 0) AS owned FROM profile_collections WHERE profile_id = :profile_id AND collection_key = :collection_key',
                    ['profile_id' => $goal['profile_id'], 'collection_key' => $key]
                );
            }

            $owned = (float) ($ownedRow['owned'] ?? 0);
            $resources[] = [
                'item' => $key,
                'required' => $required,
                'owned' => $owned,
                'missing' => max(0, $required - $owned),
                'goal' => $goal['name'],
                'type' => $type,
            ];
        }

        usort($resources, static fn(array $a, array $b): int => $b['missing'] <=> $a['missing']);
        return $resources;
    }
}
