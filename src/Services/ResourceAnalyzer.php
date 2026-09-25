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
                'SELECT id FROM goals WHERE profile_id = :profile_id AND status IN (\'pending\', \'in_progress\')',
                ['profile_id' => $profileId]
            );
            $goalIds = array_map(static fn(array $goal): int => (int) $goal['id'], $goals);
        }

        if ($goalIds === []) {
            return [];
        }

        // Load all item/collection requirements for all goals in a single query
        $placeholders = implode(',', array_fill(0, count($goalIds), '?'));
        $requirements = $this->database->fetchAll(
            "SELECT goal_id, requirement_type, requirement_key, requirement_value
             FROM goal_requirements
             WHERE goal_id IN ({$placeholders}) AND requirement_type IN ('item', 'collection')",
            array_values($goalIds)
        );

        if ($requirements === []) {
            return [];
        }

        // Collect all item keys and collection keys in one pass, then batch-fetch owned amounts
        $itemKeys       = [];
        $collectionKeys = [];
        foreach ($requirements as $req) {
            if ($req['requirement_type'] === 'item') {
                $itemKeys[] = $req['requirement_key'];
            } else {
                $collectionKeys[] = $req['requirement_key'];
            }
        }

        $itemOwned       = $this->fetchOwnedItems($profileId, array_unique($itemKeys));
        $collectionOwned = $this->fetchOwnedCollections($profileId, array_unique($collectionKeys));

        $aggregated = [];
        foreach ($requirements as $req) {
            $key      = strtolower((string) $req['requirement_key']);
            $required = (float) $req['requirement_value'];
            $goalId   = (int) $req['goal_id'];
            $owned    = $req['requirement_type'] === 'item'
                ? ($itemOwned[$key] ?? 0)
                : ($collectionOwned[$key] ?? 0);

            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'item'     => $req['requirement_key'],
                    'required' => $required,
                    'owned'    => $owned,
                    'missing'  => max(0, $required - $owned),
                    'goals'    => [$goalId],
                ];
            } else {
                $aggregated[$key]['required'] += $required;
                // owned is a constant (how many the player has), not dependent on goal count
                $aggregated[$key]['missing']   = max(0, $aggregated[$key]['required'] - $aggregated[$key]['owned']);
                $aggregated[$key]['goals'][]   = $goalId;
            }
        }

        $results = array_values(array_filter($aggregated, static fn(array $row): bool => $row['missing'] > 0));
        usort($results, static fn(array $a, array $b): int => $b['missing'] <=> $a['missing']);

        return $results;
    }

    /** @return array<string, float> keyed by lower-case item id/name */
    private function fetchOwnedItems(int $profileId, array $keys): array
    {
        if ($keys === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = $this->database->fetchAll(
            "SELECT item_id, item_name, COALESCE(SUM(quantity), 0) AS owned
             FROM profile_items
             WHERE profile_id = ? AND (item_id IN ({$placeholders}) OR item_name IN ({$placeholders}))
             GROUP BY item_id, item_name",
            array_merge([$profileId], $keys, $keys)
        );
        $result = [];
        foreach ($rows as $row) {
            $result[strtolower((string) $row['item_id'])]   = (float) $row['owned'];
            $result[strtolower((string) $row['item_name'])] = (float) $row['owned'];
        }
        return $result;
    }

    /** @return array<string, float> keyed by lower-case collection_key */
    private function fetchOwnedCollections(int $profileId, array $keys): array
    {
        if ($keys === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = $this->database->fetchAll(
            "SELECT collection_key, COALESCE(MAX(amount), 0) AS owned
             FROM profile_collections
             WHERE profile_id = ? AND collection_key IN ({$placeholders})
             GROUP BY collection_key",
            array_merge([$profileId], $keys)
        );
        $result = [];
        foreach ($rows as $row) {
            $result[strtolower((string) $row['collection_key'])] = (float) $row['owned'];
        }
        return $result;
    }

    /**
     * Returns resource requirements for multiple goals in one batch,
     * keyed by goal_id. Use this instead of calling getResourceRequirements()
     * in a loop to avoid N+1 query patterns.
     *
     * @param int[]       $goalIds
     * @return array<int, list<array{item:string,required:float,owned:float,missing:float,goal:string,type:string}>>
     */
    public function getResourceRequirementsGroupedByGoal(array $goalIds, int $profileId): array
    {
        if ($goalIds === []) {
            return [];
        }

        // Fetch goal names
        $ph = implode(',', array_fill(0, count($goalIds), '?'));
        $goalRows = $this->database->fetchAll(
            "SELECT id, name FROM goals WHERE id IN ({$ph})",
            array_values($goalIds)
        );
        $goalNames = [];
        foreach ($goalRows as $row) {
            $goalNames[(int) $row['id']] = (string) $row['name'];
        }

        // Fetch all requirements in one query
        $requirements = $this->database->fetchAll(
            "SELECT goal_id, requirement_type, requirement_key, requirement_value
             FROM goal_requirements
             WHERE goal_id IN ({$ph}) AND requirement_type IN ('item', 'collection')",
            array_values($goalIds)
        );

        if ($requirements === []) {
            return array_fill_keys($goalIds, []);
        }

        $itemKeys = $collectionKeys = [];
        foreach ($requirements as $req) {
            if ($req['requirement_type'] === 'item') {
                $itemKeys[] = $req['requirement_key'];
            } else {
                $collectionKeys[] = $req['requirement_key'];
            }
        }

        $itemOwned       = $this->fetchOwnedItems($profileId, array_unique($itemKeys));
        $collectionOwned = $this->fetchOwnedCollections($profileId, array_unique($collectionKeys));

        $grouped = array_fill_keys($goalIds, []);
        foreach ($requirements as $req) {
            $goalId  = (int) $req['goal_id'];
            $type    = (string) $req['requirement_type'];
            $key     = (string) $req['requirement_key'];
            $required = (float) $req['requirement_value'];
            $owned   = $type === 'item'
                ? ($itemOwned[strtolower($key)] ?? 0)
                : ($collectionOwned[strtolower($key)] ?? 0);

            $grouped[$goalId][] = [
                'item'     => $key,
                'required' => $required,
                'owned'    => $owned,
                'missing'  => max(0, $required - $owned),
                'goal'     => $goalNames[$goalId] ?? '',
                'type'     => $type,
            ];
        }

        foreach ($grouped as &$resources) {
            usort($resources, static fn(array $a, array $b): int => $b['missing'] <=> $a['missing']);
        }
        unset($resources);

        return $grouped;
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

        $itemKeys = $collectionKeys = [];
        foreach ($requirements as $req) {
            if ($req['requirement_type'] === 'item') {
                $itemKeys[] = $req['requirement_key'];
            } elseif ($req['requirement_type'] === 'collection') {
                $collectionKeys[] = $req['requirement_key'];
            }
        }

        $profileId       = (int) $goal['profile_id'];
        $itemOwned       = $this->fetchOwnedItems($profileId, array_unique($itemKeys));
        $collectionOwned = $this->fetchOwnedCollections($profileId, array_unique($collectionKeys));

        $resources = [];
        foreach ($requirements as $requirement) {
            $type = (string) $requirement['requirement_type'];
            if (!in_array($type, ['item', 'collection'], true)) {
                continue;
            }

            $required = (float) $requirement['requirement_value'];
            $key      = (string) $requirement['requirement_key'];
            $owned    = $type === 'item'
                ? ($itemOwned[strtolower($key)] ?? 0)
                : ($collectionOwned[strtolower($key)] ?? 0);

            $resources[] = [
                'item'     => $key,
                'required' => $required,
                'owned'    => $owned,
                'missing'  => max(0, $required - $owned),
                'goal'     => $goal['name'],
                'type'     => $type,
            ];
        }

        usort($resources, static fn(array $a, array $b): int => $b['missing'] <=> $a['missing']);
        return $resources;
    }
}
