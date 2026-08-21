<?php

namespace Tests\Various;

use App\Service\AdminSettingsService;
use App\Service\PlanAdminService;
use App\Service\PlanConfigService;
use App\Service\PlanSeasonRenameService;
use App\Service\PlanService;
use App\Service\SeasonService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * The season-suffix rename ceremony, and the by-name references a rename
 * must carry along.
 *
 * The current season's plans take the clean base slug; a displaced archive
 * keeps its own season as suffix. Conflicts are reported, never forced.
 * renamePlan itself must follow the name into race_harvest and into the
 * perception keys of the history (coords_computed = "x_y_z_plan").
 */
class SeasonSlugRenameTest extends TestCase
{
    private const PREFIX = 'plan_test_ssr';
    private const LOG_PLAYER = 990601;

    private ?Connection $conn = null;

    private ?string $previousSeason = null;

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

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
        }

        $this->previousSeason = (new AdminSettingsService())->get(SeasonService::SETTING_CURRENT, '');
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        if ($this->conn === null) {
            return;
        }

        if ($this->previousSeason !== '') {
            (new AdminSettingsService())->set(SeasonService::SETTING_CURRENT, $this->previousSeason);
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
        $this->conn->executeStatement("DELETE FROM race_harvest WHERE plan LIKE ?", [self::PREFIX . '%']);
        $this->conn->executeStatement('DELETE FROM players_logs WHERE player_id = ?', [self::LOG_PLAYER]);
        $this->conn->executeStatement('DELETE FROM players WHERE id = ?', [self::LOG_PLAYER]);
        $this->conn->executeStatement("DELETE FROM coords WHERE plan LIKE ?", [self::PREFIX . '%']);
        $this->conn->executeStatement("DELETE FROM plans WHERE slug LIKE ?", [self::PREFIX . '%']);
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/img/tiles/' . self::PREFIX . '_a_s2.webp');
        PlanService::forget();
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
    }

    private function seedPlan(string $slug, ?int $season, ?string $bg = null): void
    {
        $this->conn->insert('plans', ['slug' => $slug, 'name' => $slug, 'season' => $season, 'bg' => $bg]);
        PlanService::forget();
    }

    public function testARenameFollowsIntoHarvestAndPerceptionKeys(): void
    {
        $from = self::PREFIX . '_ref';
        $to = self::PREFIX . '_ref2';
        $this->seedPlan($from, 1);

        $raceId = (int) $this->conn->fetchOne('SELECT id FROM races LIMIT 1');
        // players_logs holds its author by foreign key
        $this->conn->insert('coords', ['x' => 0, 'y' => -1, 'z' => 0, 'plan' => $from]);
        $coordsId = (int) $this->conn->lastInsertId();
        $this->conn->insert('players', [
            'id' => self::LOG_PLAYER, 'player_type' => 'npc', 'name' => 'Témoin ssr',
            'race' => 'nain', 'coords_id' => $coordsId,
        ]);
        $this->conn->insert('race_harvest', [
            'plan' => $from, 'race_id' => $raceId, 'item' => 'bois', 'exhaust' => 10, 'regrow' => 10,
        ]);
        $this->conn->insert('players_logs', [
            'player_id' => self::LOG_PLAYER, 'target_id' => self::LOG_PLAYER, 'text' => 'ssr test', 'hiddenText' => '',
            'type' => 'move', 'plan' => $from, 'time' => 1, 'coords_id' => $coordsId, 'coords_computed' => '0_-1_0_' . $from,
        ]);

        (new PlanAdminService())->renamePlan($from, $to);

        $this->assertSame(
            $to,
            $this->conn->fetchOne('SELECT plan FROM race_harvest WHERE race_id = ? AND plan LIKE ?', [$raceId, self::PREFIX . '%']),
            'the yield overrides follow the rename'
        );
        $this->assertSame(
            '0_-1_0_' . $to,
            $this->conn->fetchOne('SELECT coords_computed FROM players_logs WHERE player_id = ?', [self::LOG_PLAYER]),
            'the perception key of the history follows the rename'
        );
    }

    public function testTheCurrentSeasonTakesTheBaseName(): void
    {
        (new SeasonService())->setCurrent(2);

        $base = self::PREFIX . '_a';
        $this->seedPlan($base, 1);
        // No bg, and a slug-named tile on disk: the fallback must be pinned.
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/img/tiles/' . $base . '_s2.webp', 'x');
        $this->seedPlan($base . '_s2', 2);
        $this->seedPlan(self::PREFIX . '_libre_s2', 2);

        $report = (new PlanSeasonRenameService())->apply();

        $this->assertNull($report['failed']);
        $this->assertSame(
            [[$base, $base . '_s1', 'archive'], [$base . '_s2', $base, 'strip'], [self::PREFIX . '_libre_s2', self::PREFIX . '_libre', 'strip']],
            array_map(static fn(array $r): array => [$r['from'], $r['to'], $r['kind']], $report['renamed']),
            'archives move first, then the suffixes fall'
        );

        $seasons = $this->conn->fetchAllKeyValue(
            'SELECT slug, season FROM plans WHERE slug LIKE ? ORDER BY slug',
            [self::PREFIX . '%']
        );
        $this->assertSame(1, (int) $seasons[$base . '_s1']);
        $this->assertSame(2, (int) $seasons[$base]);
        $this->assertArrayHasKey(self::PREFIX . '_libre', $seasons);

        $this->assertSame(
            'img/tiles/' . $base . '_s2.webp',
            (new PlanConfigService())->read($base)['bg'],
            'the slug-named background fallback is pinned before the rename'
        );
    }

    public function testConflictsAreReportedNotForced(): void
    {
        (new SeasonService())->setCurrent(2);

        $this->seedPlan(self::PREFIX . '_b', null); // all-season holder
        $this->seedPlan(self::PREFIX . '_b_s2', 2);

        $preview = (new PlanSeasonRenameService())->preview();

        $this->assertSame([], $preview['operations']);
        $this->assertArrayHasKey(self::PREFIX . '_b_s2', $preview['skipped']);
    }

    public function testASuffixSeasonMismatchIsSkipped(): void
    {
        (new SeasonService())->setCurrent(2);

        $this->seedPlan(self::PREFIX . '_c_s2', 2);
        $this->conn->executeStatement(
            'UPDATE plans SET season = 1 WHERE slug = ?',
            [self::PREFIX . '_c_s2']
        );
        PlanService::forget();

        $preview = (new PlanSeasonRenameService())->preview();

        $this->assertSame([], $preview['operations']);
        // Season 1 with an _s2 suffix: not current, keeps its (wrong) name —
        // nothing to do, nothing broken.
        $this->assertSame([], $preview['skipped']);
    }
}
