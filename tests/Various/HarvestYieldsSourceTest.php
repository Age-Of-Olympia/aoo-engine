<?php

namespace Tests\Various;

use App\Service\Map\HarvestCatalogService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Where the game reads a plan's yields: the TYPE says, the plan may deviate.
 *
 * There is still deliberately NO fallback to the plan JSON — a source read but
 * never shown is a source that rots. A default carried by the type is the
 * opposite of that: it is edited and it is displayed, and it is what makes a
 * newly added type work the moment it is posed instead of yielding nothing
 * until someone declares it plan by plan.
 *
 * So a type with nothing anywhere is still mute, and the admin still names the
 * gap; a `race_harvest` row is now an override, not the only source.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class HarvestYieldsSourceTest extends TestCase
{
    private const PLAN = 'plan_test_yields';
    private const TYPE = 'gm_yields_arbre';

    /** Below the resource range's ceiling, out of reach of any converted id. */
    private const FIXTURE_ID = 59990100;

    private ?Connection $conn = null;
    private string $plansDir;
    private int $raceId = 0;

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
            $this->conn = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $this->plansDir = dirname(__DIR__, 2) . '/datas/private/plans';

        if (!is_dir($this->plansDir) || !is_writable($this->plansDir)) {
            $this->markTestSkipped('datas/private/plans non inscriptible.');
        }

        $this->cleanup();

        $this->conn->executeStatement(
            "INSERT IGNORE INTO races (code, name, label, description, playable, hidden, kind,
                                       structure_nature, bleeds, wound_color, blocks_passage,
                                       blocks_projectiles, bgColor, color, faction, plan, pv)
             VALUES ('GM_YIELDS', ?, 'Gm yields', '', 0, 1, 'structure', 'ressource',
                     '', '#cd7f32', 1, 1, '#8a8a8a', 'black', '', '', 10)",
            [self::TYPE]
        );

        $this->raceId = (int) $this->conn->fetchOne('SELECT id FROM races WHERE name = ?', [self::TYPE]);

        file_put_contents(
            $this->plansDir . '/' . self::PLAN . '.json',
            json_encode([
                'name' => self::PLAN,
                'biomes' => [['wall' => self::TYPE, 'ressource' => 'bois', 'exhaust' => 75, 'regrow' => 20]],
            ], JSON_PRETTY_PRINT)
        );
        json()->forget('plans', self::PLAN);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        if ($this->conn === null) {
            return;
        }

        @unlink($this->plansDir . '/' . self::PLAN . '.json');
        json()->forget('plans', self::PLAN);
        $this->conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [self::FIXTURE_ID]);
        $this->conn->executeStatement('DELETE FROM resources WHERE player_id = ?', [self::FIXTURE_ID]);
        $this->conn->executeStatement('DELETE FROM players WHERE id = ?', [self::FIXTURE_ID]);
        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
        $this->conn->executeStatement('DELETE FROM race_harvest WHERE plan = ?', [self::PLAN]);
        $this->conn->executeStatement('DELETE FROM races WHERE name = ?', [self::TYPE]);
    }

    /**
     * Nothing on the type, nothing on the plan: nothing yielded — and still no
     * silent read of the JSON, which does declare this type.
     */
    public function testATypeWithNeitherDefaultNorRowYieldsNothing(): void
    {
        $yields = (new HarvestCatalogService($this->conn))->yieldsFor(self::PLAN);

        $this->assertArrayNotHasKey(self::TYPE, $yields);
    }

    /**
     * Le cœur de la règle : poser un type suffit à ce qu'il rende quelque
     * chose, sans aller le déclarer plan par plan.
     */
    public function testTheTypeAnswersWhereThePlanSaysNothing(): void
    {
        $this->conn->executeStatement(
            'UPDATE races SET harvest_item = ?, harvest_exhaust = ?, harvest_regrow = ? WHERE id = ?',
            ['bois', 75, 20, $this->raceId]
        );

        $yields = (new HarvestCatalogService($this->conn))->yieldsFor(self::PLAN);

        $this->assertSame('bois', $yields[self::TYPE]['item'], 'le type porte son rendement');
        $this->assertSame(75, $yields[self::TYPE]['exhaust']);
        $this->assertSame(20, $yields[self::TYPE]['regrow']);
    }

    /** Un type qui a un défaut n'est plus un trou à signaler. */
    public function testATypeWithADefaultIsNotReportedMissing(): void
    {
        $this->standAResource();

        $this->conn->executeStatement(
            'UPDATE races SET harvest_item = ? WHERE id = ?',
            ['bois', $this->raceId]
        );

        $missing = (new HarvestCatalogService($this->conn))->plansMissingYields();

        $this->assertNotContains(
            self::PLAN,
            array_column($missing, 'plan'),
            'le catalogue répond : le plan n\'a rien à régler'
        );
    }

    /** And the gap is NAMED, which is what replaces the fallback. */
    public function testAPlanWithResourcesButNoYieldsIsReported(): void
    {
        $this->standAResource();

        $missing = (new HarvestCatalogService($this->conn))->plansMissingYields();
        $reported = array_column($missing, 'plan');

        $this->assertContains(self::PLAN, $reported, 'un plan qui ne rapporte rien doit être signalé');
    }

    /**
     * A type left out is a gap of its own: harvesting IT gives nothing, even
     * where the plan's other types are settled. Counting rows per plan would
     * call this plan done — it is how 58 coconut palms yielded nothing while
     * the board looked configured.
     */
    public function testATypeLeftOutIsReportedThoughThePlanHasOtherYields(): void
    {
        $this->standAResource();

        /* Some OTHER type settled on this plan, so it is not an empty plan. */
        $other = (int) $this->conn->fetchOne(
            "SELECT id FROM races WHERE kind = 'structure' AND name <> ? ORDER BY id LIMIT 1",
            [self::TYPE]
        );
        $this->assertGreaterThan(0, $other, 'il faut un second type pour poser le décor du test');

        $this->conn->executeStatement(
            "INSERT INTO race_harvest (plan, race_id, item, exhaust, regrow) VALUES (?, ?, 'bois', 10, NULL)",
            [self::PLAN, $other]
        );

        $missing = (new HarvestCatalogService($this->conn))->plansMissingYields();
        $row = array_values(array_filter($missing, static fn(array $r): bool => $r['plan'] === self::PLAN));

        $this->assertNotSame([], $row, 'le plan garde un type sans rendement');
        $this->assertContains(self::TYPE, $row[0]['types'], 'et le type fautif est nommé');
    }

    /** One harvestable entity standing on the plan. */
    private function standAResource(): void
    {
        $coordsId = (int) \Classes\View::get_coords_id(
            (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::PLAN]
        );

        $this->conn->executeStatement(
            "INSERT INTO players (id, player_type, display_id, name, race, avatar, portrait,
                                  coords_id, nextTurnTime, registerTime, text)
             VALUES (?, 'resource', 0, 'Gm yields', ?, '', '', ?, 0, ?, '')",
            [self::FIXTURE_ID, self::TYPE, $coordsId, time()]
        );

        $this->conn->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, 0, 0, 0, 0, 'block')",
            [self::FIXTURE_ID, $coordsId, self::PLAN]
        );
    }

    /** Une ligne de plan DÉVIE du type, champ par champ. */
    public function testThePlansRowOverridesTheType(): void
    {
        $this->conn->executeStatement(
            'UPDATE races SET harvest_item = ?, harvest_exhaust = ?, harvest_regrow = ? WHERE id = ?',
            ['bois', 75, 20, $this->raceId]
        );

        $this->conn->executeStatement(
            'INSERT INTO race_harvest (plan, race_id, item, exhaust, regrow) VALUES (?, ?, ?, ?, ?)',
            [self::PLAN, $this->raceId, 'pierre', 10, null]
        );

        $yields = (new HarvestCatalogService($this->conn))->yieldsFor(self::PLAN);

        $this->assertSame('pierre', $yields[self::TYPE]['item'], 'la surcharge gagne');
        $this->assertSame(10, $yields[self::TYPE]['exhaust']);
        $this->assertSame(
            20,
            $yields[self::TYPE]['regrow'],
            'un taux vide hérite du type : le plan ne dit que ce qu\'il change'
        );
    }

    /**
     * « Le même arbre donne moins dans le désert » : un seul chiffre à porter,
     * le reste vient du type.
     */
    public function testAPlanCanChangeOneRateAndInheritTheRest(): void
    {
        $this->conn->executeStatement(
            'UPDATE races SET harvest_item = ?, harvest_exhaust = ?, harvest_regrow = ? WHERE id = ?',
            ['bois', 75, 20, $this->raceId]
        );

        $this->conn->executeStatement(
            "INSERT INTO race_harvest (plan, race_id, item, exhaust, regrow) VALUES (?, ?, '', NULL, ?)",
            [self::PLAN, $this->raceId, 3]
        );

        $yields = (new HarvestCatalogService($this->conn))->yieldsFor(self::PLAN);

        $this->assertSame('bois', $yields[self::TYPE]['item'], 'l\'objet reste celui du type');
        $this->assertSame(75, $yields[self::TYPE]['exhaust'], 'l\'épuisement aussi');
        $this->assertSame(3, $yields[self::TYPE]['regrow'], 'seule la repousse dévie');
    }

    /** « Jamais » a son écriture : 0, et il ne retombe pas sur le type. */
    public function testZeroMeansNeverAndDoesNotInherit(): void
    {
        $this->conn->executeStatement(
            'UPDATE races SET harvest_item = ?, harvest_exhaust = ?, harvest_regrow = ? WHERE id = ?',
            ['bois', 75, 20, $this->raceId]
        );

        $this->conn->executeStatement(
            "INSERT INTO race_harvest (plan, race_id, item, exhaust, regrow) VALUES (?, ?, '', 0, 0)",
            [self::PLAN, $this->raceId]
        );

        $yields = (new HarvestCatalogService($this->conn))->yieldsFor(self::PLAN);

        $this->assertSame(0, $yields[self::TYPE]['exhaust'], 'ici, cet arbre ne s\'épuise jamais');
        $this->assertSame(0, $yields[self::TYPE]['regrow'], 'et ne repousse jamais');
    }

    /** What the screen saves is what the game then reads. */
    public function testWhatTheScreenSavesIsWhatTheGameReads(): void
    {
        $service = new HarvestCatalogService($this->conn);
        $service->seed();

        $service->save([
            self::PLAN . '|' . $this->raceId => ['item' => 'mana', 'exhaust' => '5', 'regrow' => ''],
        ]);

        $yields = $service->yieldsFor(self::PLAN);

        $this->assertSame('mana', $yields[self::TYPE]['item']);
        $this->assertSame(5, $yields[self::TYPE]['exhaust']);
        $this->assertNull($yields[self::TYPE]['regrow']);
    }
}
