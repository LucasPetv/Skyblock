<?php
declare(strict_types=1);

namespace SkyBlock\Services;

use SkyBlock\Database\Database;

class GoalService
{
    public function __construct(private readonly Database $database)
    {
    }

    public function createGoal(array $data): int
    {
        $this->database->execute(
            'INSERT INTO goals (profile_id, name, description, category, priority, target_progress, current_progress, status, notes, created_at, updated_at)
             VALUES (:profile_id, :name, :description, :category, :priority, :target_progress, :current_progress, :status, :notes, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                'profile_id' => $data['profile_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? 'general',
                'priority' => $data['priority'] ?? 'medium',
                'target_progress' => $data['target_progress'] ?? 100,
                'current_progress' => $data['current_progress'] ?? 0,
                'status' => $data['status'] ?? 'pending',
                'notes' => $data['notes'] ?? null,
            ]
        );
        $goalId = (int) $this->database->lastInsertId();

        foreach (($data['requirements'] ?? []) as $requirement) {
            $this->database->execute(
                'INSERT INTO goal_requirements (goal_id, requirement_type, requirement_key, requirement_value, is_met, checked_at)
                 VALUES (:goal_id, :requirement_type, :requirement_key, :requirement_value, 0, NULL)',
                [
                    'goal_id' => $goalId,
                    'requirement_type' => $requirement['type'] ?? 'skill',
                    'requirement_key' => $requirement['key'] ?? '',
                    'requirement_value' => (string) ($requirement['value'] ?? '0'),
                ]
            );
        }

        $this->updateProgress($goalId);
        return $goalId;
    }

    public function updateGoal(int $id, array $data): bool
    {
        $updated = $this->database->execute(
            'UPDATE goals
             SET name = :name,
                 description = :description,
                 category = :category,
                 priority = :priority,
                 target_progress = :target_progress,
                 status = :status,
                 notes = :notes,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                'id' => $id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? 'general',
                'priority' => $data['priority'] ?? 'medium',
                'target_progress' => $data['target_progress'] ?? 100,
                'status' => $data['status'] ?? 'pending',
                'notes' => $data['notes'] ?? null,
            ]
        );

        if (array_key_exists('requirements', $data)) {
            $this->database->execute('DELETE FROM goal_requirements WHERE goal_id = :goal_id', ['goal_id' => $id]);
            foreach (($data['requirements'] ?? []) as $requirement) {
                $this->database->execute(
                    'INSERT INTO goal_requirements (goal_id, requirement_type, requirement_key, requirement_value, is_met, checked_at)
                     VALUES (:goal_id, :requirement_type, :requirement_key, :requirement_value, 0, NULL)',
                    [
                        'goal_id' => $id,
                        'requirement_type' => $requirement['type'] ?? 'skill',
                        'requirement_key' => $requirement['key'] ?? '',
                        'requirement_value' => (string) ($requirement['value'] ?? '0'),
                    ]
                );
            }
        }

        $this->updateProgress($id);
        return $updated;
    }

    public function deleteGoal(int $id): bool
    {
        return $this->database->execute('DELETE FROM goals WHERE id = :id', ['id' => $id]);
    }

    public function getGoalsForProfile(int $profileId): array
    {
        return $this->database->fetchAll(
            'SELECT g.*,
                    COALESCE(agg.requirement_count, 0) AS requirement_count,
                    COALESCE(agg.met_count, 0) AS met_count
             FROM goals g
             LEFT JOIN (
                 SELECT goal_id,
                        COUNT(*) AS requirement_count,
                        SUM(CASE WHEN is_met = 1 THEN 1 ELSE 0 END) AS met_count
                 FROM goal_requirements
                 GROUP BY goal_id
             ) agg ON agg.goal_id = g.id
             WHERE g.profile_id = :profile_id
             ORDER BY
               CASE g.status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END,
               CASE g.priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END,
               g.created_at DESC',
            ['profile_id' => $profileId]
        );
    }

    public function updateProgress(int $goalId): void
    {
        $counts = $this->database->fetchOne(
            'SELECT COUNT(*) AS total, SUM(CASE WHEN is_met = 1 THEN 1 ELSE 0 END) AS met FROM goal_requirements WHERE goal_id = :goal_id',
            ['goal_id' => $goalId]
        ) ?? ['total' => 0, 'met' => 0];

        $goal = $this->database->fetchOne('SELECT target_progress FROM goals WHERE id = :id', ['id' => $goalId]) ?? ['target_progress' => 100];
        $total = (int) ($counts['total'] ?? 0);
        $met = (int) ($counts['met'] ?? 0);
        $targetProgress = (float) ($goal['target_progress'] ?? 100);
        $currentProgress = $total > 0 ? round(($met / $total) * $targetProgress, 2) : $targetProgress;
        $status = $total === 0 || $met >= $total ? 'completed' : ($met > 0 ? 'in_progress' : 'pending');

        $this->database->execute(
            'UPDATE goals SET current_progress = :current_progress, status = :status, completed_at = :completed_at, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [
                'id' => $goalId,
                'current_progress' => $currentProgress,
                'status' => $status,
                'completed_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null,
            ]
        );
    }
}
