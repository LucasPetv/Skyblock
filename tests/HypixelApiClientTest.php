<?php
declare(strict_types=1);

namespace SkyBlock\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SkyBlock\Api\HypixelApiClient;

final class HypixelApiClientTest extends TestCase
{
    public function testReturnsPlayerPayload(): void
    {
        $client = new HypixelApiClient('secret', 'https://example.test', 5, static fn(): array => [200, json_encode(['success' => true, 'player' => ['uuid' => 'abc']])]);
        self::assertSame(['uuid' => 'abc'], $client->getPlayerByUuid('abc'));
    }

    public function testThrowsOnApiError(): void
    {
        $client = new HypixelApiClient('secret', 'https://example.test', 5, static fn(): array => [200, json_encode(['success' => false, 'cause' => 'Invalid API key'])]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hypixel API error');
        $client->getProfilesByUuid('abc');
    }

    public function testThrowsOnInvalidJson(): void
    {
        $client = new HypixelApiClient('secret', 'https://example.test', 5, static fn(): array => [200, 'not-json']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');
        $client->getBazaar();
    }

    public function testThrowsOnRateLimit(): void
    {
        $client = new HypixelApiClient('secret', 'https://example.test', 5, static fn(): array => [429, json_encode(['success' => false])]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rate limit');
        $client->getAuctions();
    }

    public function testThrowsOnTimeoutLikeException(): void
    {
        $client = new HypixelApiClient('secret', 'https://example.test', 5, static function (): array {
            throw new RuntimeException('HTTP request failed: timeout');
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('timeout');
        $client->getPlayerByUuid('abc');
    }

    public function testResolvesUsernameToUuid(): void
    {
        $client = new HypixelApiClient('secret', 'https://example.test', 5, static fn(): array => [200, json_encode(['id' => 'uuid123', 'name' => 'Player'])], mojangUrl: 'https://mojang.test');
        self::assertSame(['id' => 'uuid123', 'name' => 'Player'], $client->resolveUsernameToUuid('Player'));
    }
}
