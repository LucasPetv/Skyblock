<?php
declare(strict_types=1);

namespace SkyBlock\Tests;

use PHPUnit\Framework\TestCase;
use SkyBlock\Services\ProgressionAnalyzer;

require_once __DIR__ . '/bootstrap.php';

final class ProgressionAnalyzerTest extends TestCase
{
    public function testAnalyzeGoalWithAllRequirementsMet(): void
    {
        $db = createTestDatabase();
        $db->execute('INSERT INTO goals (id, profile_id, name, target_progress, current_progress, status) VALUES (1, 1, "Combat Goal", 100, 0, "pending")');
        $db->execute('INSERT INTO goal_requirements (goal_id, requirement_type, requirement_key, requirement_value) VALUES (1, "skill", "combat", "20")');
        $db->execute('INSERT INTO profile_skills (profile_id, skill_name, level) VALUES (1, "combat", 25)');

        $analysis = (new ProgressionAnalyzer($db))->analyzeGoal(1);

        self::assertCount(1, $analysis['completed_requirements']);
        self::assertSame(100.0, $analysis['progress']);
    }

    public function testAnalyzeGoalWithMissingRequirements(): void
    {
        $db = createTestDatabase();
        $db->execute('INSERT INTO goals (id, profile_id, name, target_progress, current_progress, status) VALUES (1, 1, "Mixed Goal", 100, 0, "pending")');
        $db->execute('INSERT INTO goal_requirements (goal_id, requirement_type, requirement_key, requirement_value) VALUES (1, "skill", "combat", "20")');
        $db->execute('INSERT INTO goal_requirements (goal_id, requirement_type, requirement_key, requirement_value) VALUES (1, "item", "ENCHANTED_DIAMOND", "3")');
        $db->execute('INSERT INTO profile_skills (profile_id, skill_name, level) VALUES (1, "combat", 15)');
        $db->execute('INSERT INTO profile_items (profile_id, item_id, item_name, quantity) VALUES (1, "ENCHANTED_DIAMOND", "ENCHANTED_DIAMOND", 1)');

        $analysis = (new ProgressionAnalyzer($db))->analyzeGoal(1);

        self::assertCount(0, $analysis['completed_requirements']);
        self::assertCount(2, $analysis['missing_requirements']);
        self::assertSame(0.0, $analysis['progress']);
    }

    public function testAnalyzeGoalWithoutRequirements(): void
    {
        $db = createTestDatabase();
        $db->execute('INSERT INTO goals (id, profile_id, name, target_progress, current_progress, status) VALUES (1, 1, "Open Goal", 100, 0, "pending")');

        $analysis = (new ProgressionAnalyzer($db))->analyzeGoal(1);

        self::assertSame(100.0, $analysis['progress']);
        self::assertSame([], $analysis['missing_requirements']);
    }
}
