<?php
declare(strict_types=1);

namespace SkyBlock\Models;

use SkyBlock\Database\Database;

class Profile
{
    public function __construct(private readonly Database $database = new Database())
    {
    }

    public function findById(int $id): ?array
    {
        return $this->database->fetchOne('SELECT * FROM profiles WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function findByAccountId(int $accountId): array
    {
        return $this->database->fetchAll(
            'SELECT p.*, s.networth, s.magical_power, s.skill_average, s.catacombs_level
             FROM profiles p
             LEFT JOIN profile_snapshots s ON s.id = (
                SELECT ps.id FROM profile_snapshots ps WHERE ps.profile_id = p.id ORDER BY ps.timestamp DESC LIMIT 1
             )
             WHERE p.account_id = :account_id AND p.is_active = 1
             ORDER BY p.is_selected DESC, p.profile_name ASC',
            ['account_id' => $accountId]
        );
    }

    public function findByProfileId(string $profileId): ?array
    {
        return $this->database->fetchOne('SELECT * FROM profiles WHERE profile_id = :profile_id LIMIT 1', ['profile_id' => $profileId]);
    }

    public function create(array $data): int
    {
        $this->database->execute(
            'INSERT INTO profiles (account_id, profile_id, profile_name, game_mode, is_selected, is_active, raw_data, created_at, updated_at)
             VALUES (:account_id, :profile_id, :profile_name, :game_mode, :is_selected, :is_active, :raw_data, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                'account_id' => $data['account_id'],
                'profile_id' => $data['profile_id'],
                'profile_name' => $data['profile_name'],
                'game_mode' => $data['game_mode'] ?? 'normal',
                'is_selected' => $data['is_selected'] ?? 0,
                'is_active' => $data['is_active'] ?? 1,
                'raw_data' => $data['raw_data'] ?? null,
            ]
        );

        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        return $this->database->execute(
            'UPDATE profiles
             SET profile_name = :profile_name,
                 game_mode = :game_mode,
                 is_selected = :is_selected,
                 is_active = :is_active,
                 raw_data = :raw_data,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                'id' => $id,
                'profile_name' => $data['profile_name'],
                'game_mode' => $data['game_mode'] ?? 'normal',
                'is_selected' => $data['is_selected'] ?? 0,
                'is_active' => $data['is_active'] ?? 1,
                'raw_data' => $data['raw_data'] ?? null,
            ]
        );
    }

    public function getLatestSnapshot(int $profileId): ?array
    {
        return $this->database->fetchOne(
            'SELECT * FROM profile_snapshots WHERE profile_id = :profile_id ORDER BY timestamp DESC LIMIT 1',
            ['profile_id' => $profileId]
        );
    }
}
