<?php

namespace Tests\Various;

use App\Service\AdminSettingsService;
use App\Service\PlanAdminService;
use App\Service\PlanService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * The world and death plans are dashboard settings, not hardcoded slugs.
 *
 * plans()->worldPlan() / deathPlan() resolve the admin settings with the
 * historical slugs as fallback. A plan named by either setting cannot be
 * deleted, and renaming one follows through: the setting and the
 * PlanCondition parameters stored in action_conditions are rewritten.
 */
class WorldDeathPlanSettingsTest extends TestCase
{
    private const PLAN = 'plan_test_world_a';
    private const RENAMED = 'plan_test_world_b';
    private const ACTION = 'gm_world_action';

    private ?Connection $conn = null;

    /** @var array<string, string> */
    private array $previousSettings = [];

    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        try {
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $settings = new AdminSettingsService();
        foreach ([PlanService::SETTING_WORLD, PlanService::SETTING_DEATH] as $key) {
            $this->previousSettings[$key] = $settings->get($key, '');
        }

        $this->cleanup();
    }

    protected function tearDown(): void
    {
        if ($this->conn === null) {
            return;
        }

        $settings = new AdminSettingsService();
        foreach ($this->previousSettings as $key => $value) {
            if ($value !== '') {
                $settings->set($key, $value);
            } else {
                $this->conn->executeStatement('DELETE FROM admin_settings WHERE name = ?', [$key]);
            }
        }

        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->executeStatement(
            "DELETE ac FROM action_conditions ac JOIN actions a ON a.id = ac.action_id WHERE a.name = ?",
            [self::ACTION]
        );
        $this->conn->executeStatement('DELETE FROM actions WHERE name = ?', [self::ACTION]);
        $this->conn->executeStatement("DELETE FROM coords WHERE plan LIKE 'plan_test_world_%'");
        $this->conn->executeStatement("DELETE FROM plans WHERE slug LIKE 'plan_test_world_%'");
        PlanService::forget();
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
    }

    private function seedPlan(string $slug): void
    {
        $this->conn->insert('plans', ['slug' => $slug, 'name' => $slug]);
        $this->conn->insert('coords', ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => $slug]);
        PlanService::forget();
    }

    public function testTheResolversFallBackToTheHistoricalSlugs(): void
    {
        $this->conn->executeStatement(
            "DELETE FROM admin_settings WHERE name IN (?, ?)",
            [PlanService::SETTING_WORLD, PlanService::SETTING_DEATH]
        );
        PlanService::forget();

        $this->assertSame('olympia', plans()->worldPlan());
        $this->assertSame('enfers', plans()->deathPlan());
    }

    public function testTheSettingsDriveTheResolvers(): void
    {
        $this->seedPlan(self::PLAN);
        (new AdminSettingsService())->set(PlanService::SETTING_WORLD, self::PLAN);
        PlanService::forget();

        $this->assertSame(self::PLAN, plans()->worldPlan());
    }

    public function testAStructuralPlanCannotBeDeleted(): void
    {
        $this->seedPlan(self::PLAN);
        (new AdminSettingsService())->set(PlanService::SETTING_DEATH, self::PLAN);
        PlanService::forget();

        $preflight = (new PlanAdminService())->deletePreflight(self::PLAN);

        $structural = array_filter(
            $preflight['blockers'],
            static fn(array $b): bool => $b['check'] === 'structural_plan'
        );
        $this->assertNotEmpty($structural, 'the death plan must refuse deletion');
        $this->assertFalse(array_values($structural)[0]['forceable']);
    }

    public function testARenameFollowsIntoSettingsAndConditionParams(): void
    {
        $this->seedPlan(self::PLAN);
        (new AdminSettingsService())->set(PlanService::SETTING_DEATH, self::PLAN);
        PlanService::forget();

        // icon is NOT NULL without default on the CI schema
        $this->conn->insert('actions', ['name' => self::ACTION, 'type' => 'melee', 'icon' => '']);
        $this->conn->insert('action_conditions', [
            'conditionType' => 'PlanCondition',
            'parameters' => json_encode(['plan' => self::PLAN]),
            'action_id' => (int) $this->conn->lastInsertId(),
            'execution_order' => 0,
            'blocking' => 1,
        ]);

        $report = (new PlanAdminService())->renamePlan(self::PLAN, self::RENAMED);

        $this->assertSame(self::RENAMED, plans()->deathPlan(), 'the setting follows the rename');
        $this->assertSame(1, $report['references']['action_conditions'] ?? 0);
        $params = json_decode((string) $this->conn->fetchOne(
            'SELECT ac.parameters FROM action_conditions ac JOIN actions a ON a.id = ac.action_id WHERE a.name = ?',
            [self::ACTION]
        ), true);
        $this->assertSame(self::RENAMED, $params['plan'] ?? null, 'the condition params follow the rename');
    }
}
