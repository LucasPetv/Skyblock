<?php
declare(strict_types=1);

namespace SkyBlock\Models;

use SkyBlock\Database\Database;

class Account
{
    public function __construct(private readonly Database $database = new Database())
    {
    }

    public function findById(int $id): ?array
    {
        return $this->database->fetchOne('SELECT * FROM accounts WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function findAll(): array
    {
        return $this->database->fetchAll(
            'SELECT a.*,
                    (
                        SELECT COUNT(*)
                        FROM profiles p
                        WHERE p.account_id = a.id AND p.is_active = 1
                    ) AS profile_count
             FROM accounts a
             ORDER BY COALESCE(a.display_name, a.minecraft_name) ASC'
        );
    }

    public function create(array $data): int
    {
        $this->database->execute(
            'INSERT INTO accounts (minecraft_uuid, minecraft_name, display_name, notes, created_at, updated_at)
             VALUES (:minecraft_uuid, :minecraft_name, :display_name, :notes, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                'minecraft_uuid' => $data['minecraft_uuid'],
                'minecraft_name' => $data['minecraft_name'],
                'display_name' => $data['display_name'] ?? $data['minecraft_name'],
                'notes' => $data['notes'] ?? null,
            ]
        );

        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        return $this->database->execute(
            "UPDATE accounts
             SET minecraft_name = :minecraft_name,
                 minecraft_uuid = COALESCE(NULLIF(:minecraft_uuid, ''), minecraft_uuid),
                 display_name = :display_name,
                 notes = :notes,
                 last_synced_at = :last_synced_at,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id",
            [
                'id' => $id,
                'minecraft_name' => $data['minecraft_name'] ?? '',
                'minecraft_uuid' => $data['minecraft_uuid'] ?? '',
                'display_name' => $data['display_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'last_synced_at' => $data['last_synced_at'] ?? null,
            ]
        );
    }

    public function delete(int $id): bool
    {
        return $this->database->execute('DELETE FROM accounts WHERE id = :id', ['id' => $id]);
    }
}
