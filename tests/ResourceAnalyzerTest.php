<?php
declare(strict_types=1);

namespace SkyBlock\Tests;

use PHPUnit\Framework\TestCase;
use SkyBlock\Services\ResourceAnalyzer;

require_once __DIR__ . '/bootstrap.php';

final class ResourceAnalyzerTest extends TestCase
{
    public function testBottleneckDetectionWithZeroOwned(): void
    {
        $db = createTestDatabase();
        $db->execute('INSERT INTO goals (id, profile_id, name, status) VALUES (1, 1, "Drill", "pending")');
        $db->execute('INSERT INTO goal_requirements (goal_id, requirement_type, requirement_key, requirement_value) VALUES (1, "item", "ENCHANTED_GOLD", "64")');

        $bottlenecks = (new ResourceAnalyzer($db))->getBottlenecks(1, [1]);

        self::assertSame('ENCHANTED_GOLD', $bottlenecks[0]['item']);
        self::assertSame(64.0, $bottlenecks[0]['missing']);
    }

    public function testBottleneckDetectionWithSomeOwned(): void
    {
        $db = createTestDatabase();
        $db->execute('INSERT INTO goals (id, profile_id, name, status) VALUES (1, 1, "Armor", "pending")');
        $db->execute('INSERT INTO goal_requirements (goal_id, requirement_type, requirement_key, requirement_value) VALUES (1, "item", "ENCHANTED_IRON", "10")');
        $db->execute('INSERT INTO profile_items (profile_id, item_id, item_name, quantity) VALUES (1, "ENCHANTED_IRON", "ENCHANTED_IRON", 4)');

        $requirements = (new ResourceAnalyzer($db))->getResourceRequirements(1);

        self::assertSame(6.0, $requirements[0]['missing']);
    }

    public function testBottleneckDetectionWithAllOwned(): void
    {
        $db = createTestDatabase();
        $db->execute('INSERT INTO goals (id, profile_id, name, status) VALUES (1, 1, "Collection", "pending")');
        $db->execute('INSERT INTO goal_requirements (goal_id, requirement_type, requirement_key, requirement_value) VALUES (1, "collection", "MITHRIL_ORE", "100")');
        $db->execute('INSERT INTO profile_collections (profile_id, collection_key, collection_name, amount) VALUES (1, "MITHRIL_ORE", "Mithril Ore", 150)');

        $bottlenecks = (new ResourceAnalyzer($db))->getBottlenecks(1, [1]);

        self::assertSame([], $bottlenecks);
    }
}
