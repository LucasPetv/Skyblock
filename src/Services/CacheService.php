<?php
declare(strict_types=1);

namespace SkyBlock\Services;

use DateInterval;
use DateTimeImmutable;
use SkyBlock\Database\Database;

class CacheService
{
    private readonly bool $isSqlite;

    public function __construct(private readonly Database $database)
    {
        $driver = $this->database->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->isSqlite = ($driver === 'sqlite');
    }

    public function get(string $key): ?array
    {
        $row = $this->database->fetchOne(
            'SELECT response_json, expires_at FROM api_cache WHERE cache_key = :cache_key LIMIT 1',
            ['cache_key' => $key]
        );

        if (!$row) {
            return null;
        }

        $expiresAt = new DateTimeImmutable((string) $row['expires_at']);
        if ($expiresAt < new DateTimeImmutable()) {
            $this->delete($key);
            return null;
        }

        $data = json_decode((string) $row['response_json'], true);
        return is_array($data) ? $data : null;
    }

    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT' . max(1, $ttlSeconds) . 'S'));
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $params = [
            'cache_key' => $key,
            'response_json' => $json,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ];

        if ($this->isSqlite) {
            $sql = 'INSERT OR REPLACE INTO api_cache (id, cache_key, response_json, expires_at, created_at)
                    VALUES ((SELECT id FROM api_cache WHERE cache_key = :cache_key), :cache_key, :response_json, :expires_at, CURRENT_TIMESTAMP)';
        } else {
            $sql = 'INSERT INTO api_cache (cache_key, response_json, expires_at, created_at)
                    VALUES (:cache_key, :response_json, :expires_at, CURRENT_TIMESTAMP) AS new
                    ON DUPLICATE KEY UPDATE response_json = new.response_json, expires_at = new.expires_at';
        }

        $this->database->execute($sql, $params);
    }

    public function delete(string $key): void
    {
        $this->database->execute('DELETE FROM api_cache WHERE cache_key = :cache_key', ['cache_key' => $key]);
    }

    public function flush(): void
    {
        $this->database->execute('DELETE FROM api_cache WHERE expires_at < :now', ['now' => date('Y-m-d H:i:s')]);
    }
}
