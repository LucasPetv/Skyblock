<?php
declare(strict_types=1);

namespace SkyBlock\Models;

use SkyBlock\Database\Database;

class Goal
{
    public function __construct(private readonly Database $database = new Database())
    {
    }

    public function findById(int $id): ?array
    {
        return $this->database->fetchOne('SELECT * FROM goals WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function findByProfileId(int $profileId): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM goals WHERE profile_id = :profile_id
             ORDER BY
               CASE status WHEN \'in_progress\' THEN 0 WHEN \'pending\' THEN 1 WHEN \'completed\' THEN 2 ELSE 3 END,
               created_at DESC',
            ['profile_id' => $profileId]
        );
    }

    public function create(array $data): int
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

        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        return $this->database->execute(
            'UPDATE goals
             SET name = :name,
                 description = :description,
                 category = :category,
                 priority = :priority,
                 target_progress = :target_progress,
                 current_progress = :current_progress,
                 status = :status,
                 notes = :notes,
                 completed_at = :completed_at,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                'id' => $id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? 'general',
                'priority' => $data['priority'] ?? 'medium',
                'target_progress' => $data['target_progress'] ?? 100,
                'current_progress' => $data['current_progress'] ?? 0,
                'status' => $data['status'] ?? 'pending',
                'notes' => $data['notes'] ?? null,
                'completed_at' => $data['completed_at'] ?? null,
            ]
        );
    }

    public function delete(int $id): bool
    {
        return $this->database->execute('DELETE FROM goals WHERE id = :id', ['id' => $id]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->database->execute(
            'UPDATE goals SET status = :status, completed_at = :completed_at, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [
                'id' => $id,
                'status' => $status,
                'completed_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null,
            ]
        );
    }
}
