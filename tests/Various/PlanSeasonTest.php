<?php

namespace Tests\Various;

use App\Service\AdminSettingsService;
use App\Service\PlanConfigService;
use App\Service\PlanService;
use App\Service\SeasonService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * A plan's season is a column, the current season a setting.
 *
 * NULL = the plan exists in every season (olympia, enfers, the tutorial
 * family). Season-scoped lists (PlanService::forSeason) default to the
 * game's current season — the current_season setting, no longer the _s2
 * name suffix.
 */
class PlanSeasonTest extends TestCase
{
    private const PREFIX = 'plan_test_season_';

    private ?Connection $conn = null;

    private ?string $previousSetting = null;

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

        // The setting is GLOBAL: restore it no matter what.
        $this->previousSetting = (new AdminSettingsService())->get(SeasonService::SETTING_CURRENT, '');

        $this->cleanup();
    }

    protected function tearDown(): void
    {
        if ($this->conn === null) {
            return;
        }

        if ($this->previousSetting !== '') {
            (new AdminSettingsService())->set(SeasonService::SETTING_CURRENT, $this->previousSetting);
        } else {
            $this->conn->executeStatement(
                'DELETE FROM admin_settings WHERE name = ?',
                [SeasonService::SETTING_CURRENT]
            );
        }
        SeasonService::forget();

        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn->executeStatement(
            "DELETE FROM plans WHERE slug LIKE '" . self::PREFIX . "%'"
        );
        PlanService::forget();
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
    }

    private function seedPlan(string $suffix, ?int $season): string
    {
        $slug = self::PREFIX . $suffix;
        $this->conn->insert('plans', ['slug' => $slug, 'name' => $slug, 'season' => $season]);
        PlanService::forget($slug);

        return $slug;
    }

    public function testForSeasonKeepsTheSeasonAndTheSeasonless(): void
    {
        $s1 = $this->seedPlan('un', 1);
        $s2 = $this->seedPlan('deux', 2);
        $all = $this->seedPlan('toutes', null);

        $slugs = array_keys((new PlanService())->forSeason(2));

        $this->assertContains($s2, $slugs);
        $this->assertContains($all, $slugs, 'a season-less plan belongs to every season');
        $this->assertNotContains($s1, $slugs);
    }

    public function testForSeasonDefaultsToTheGameSeason(): void
    {
        $s1 = $this->seedPlan('un', 1);
        $s2 = $this->seedPlan('deux', 2);

        (new SeasonService())->setCurrent(1);
        $slugs = array_keys((new PlanService())->forSeason());

        $this->assertContains($s1, $slugs);
        $this->assertNotContains($s2, $slugs);
    }

    public function testTheSettingRoundTripsAndFallsBackToTheDefault(): void
    {
        $service = new SeasonService();

        $service->setCurrent(3);
        SeasonService::forget();
        $this->assertSame(3, $service->current());

        $this->conn->executeStatement(
            'DELETE FROM admin_settings WHERE name = ?',
            [SeasonService::SETTING_CURRENT]
        );
        SeasonService::forget();
        $this->assertSame(SeasonService::DEFAULT_SEASON, $service->current());
    }

    public function testTheSeasonIsEditablePlanConfig(): void
    {
        $slug = $this->seedPlan('editable', 1);
        $config = new PlanConfigService();

        $config->write($slug, $config->parse(['season' => '3']));
        $this->assertSame('3', $config->read($slug)['season']);

        // '' = key removed = every season
        $config->write($slug, $config->parse(['season' => '']));
        $this->assertSame('', $config->read($slug)['season']);
        PlanService::forget($slug);
        $this->assertObjectNotHasProperty('season', plans()->read($slug));
    }
}
