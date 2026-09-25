<?php
declare(strict_types=1);

namespace SkyBlock\Tests;

use PHPUnit\Framework\TestCase;
use SkyBlock\Services\CacheService;

require_once __DIR__ . '/bootstrap.php';

final class CacheServiceTest extends TestCase
{
    public function testSetAndGetValue(): void
    {
        $service = new CacheService(createTestDatabase());
        $service->set('player_1', ['ok' => true], 60);
        self::assertSame(['ok' => true], $service->get('player_1'));
    }

    public function testExpiredValueReturnsNull(): void
    {
        $service = new CacheService(createTestDatabase());
        $service->set('old', ['value' => 1], 1);
        $db = createTestDatabase();
        $service = new CacheService($db);
        $db->execute('INSERT INTO api_cache (cache_key, response_json, expires_at, created_at) VALUES (:cache_key, :response_json, :expires_at, :created_at)', [
            'cache_key' => 'expired',
            'response_json' => json_encode(['value' => 1]),
            'expires_at' => '2000-01-01 00:00:00',
            'created_at' => '2000-01-01 00:00:00',
        ]);
        self::assertNull($service->get('expired'));
    }

    public function testDeleteRemovesValue(): void
    {
        $service = new CacheService(createTestDatabase());
        $service->set('remove_me', ['value' => 1], 60);
        $service->delete('remove_me');
        self::assertNull($service->get('remove_me'));
    }

    public function testFlushDeletesExpiredOnly(): void
    {
        $db = createTestDatabase();
        $service = new CacheService($db);
        $db->execute('INSERT INTO api_cache (cache_key, response_json, expires_at, created_at) VALUES (:cache_key, :response_json, :expires_at, :created_at)', [
            'cache_key' => 'expired',
            'response_json' => json_encode(['value' => 1]),
            'expires_at' => '2000-01-01 00:00:00',
            'created_at' => '2000-01-01 00:00:00',
        ]);
        $db->execute('INSERT INTO api_cache (cache_key, response_json, expires_at, created_at) VALUES (:cache_key, :response_json, :expires_at, :created_at)', [
            'cache_key' => 'active',
            'response_json' => json_encode(['value' => 2]),
            'expires_at' => '2999-01-01 00:00:00',
            'created_at' => '2000-01-01 00:00:00',
        ]);
        $service->flush();
        self::assertNull($service->get('expired'));
        self::assertSame(['value' => 2], $service->get('active'));
    }
}
