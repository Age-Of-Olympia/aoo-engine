<?php

namespace Tests\Various;

use App\Service\BuildingService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Consecrating an altar, and worshipping at one.
 *
 * The two gestures the altars work is for. They are catalogue rows, so what
 * is worth pinning is the shape of those rows: what they aim at, what they
 * demand, and which way the god travels. A wrong parameter here is silent —
 * the button simply never appears, or appears everywhere.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class ConsacrerEtVenererTest extends TestCase
{
    private const PLAN = 'plan_test_consacrer';

    private ?Connection $conn = null;

    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
        }

        try {
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $this->cleanup();
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

        foreach ($this->conn->fetchFirstColumn(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        ) as $id) {
            $this->conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [(int) $id]);
            $this->conn->executeStatement('DELETE FROM buildings WHERE player_id = ?', [(int) $id]);
            BuildingService::deleteEntityRows($this->conn, (int) $id);
        }

        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    /** @return array<string, mixed> the conditions of an action, by type */
    private function conditionsOf(string $action): array
    {
        $rows = $this->conn->fetchAllAssociative(
            'SELECT c.conditionType, c.parameters, c.display_context
               FROM action_conditions c JOIN actions a ON a.id = c.action_id
              WHERE a.name = ? ORDER BY c.execution_order',
            [$action]
        );

        $byType = [];

        foreach ($rows as $row) {
            $byType[$row['conditionType']][] = [
                'params' => json_decode((string) $row['parameters'], true),
                'context' => (int) $row['display_context'],
            ];
        }

        return $byType;
    }

    public function testBothActionsExistAndAimAtAnAltar(): void
    {
        foreach (['consacrer', 'venerer'] as $action) {
            $type = $this->conn->fetchOne('SELECT type FROM actions WHERE name = ?', [$action]);

            $this->assertSame('buff', $type, $action . ' n\'est pas un soin');

            $conditions = $this->conditionsOf($action);

            $this->assertSame(['altar'], $conditions['TargetRace'][0]['params']['allowed']);
            $this->assertSame(1, $conditions['TargetRace'][0]['context'], 'sinon le bouton s\'affiche partout');
            $this->assertSame(1, $conditions['RequiresDistance'][0]['params']['max'], 'au contact');
        }
    }

    /** Consecrating asks a naked altar, a god of one's own, and 50 pf. */
    public function testConsecratingAsksForANakedAltarAndFiftyFaith(): void
    {
        $conditions = $this->conditionsOf('consacrer');

        $states = array_map(
            static fn(array $c): string => $c['params']['side'] . ':' . $c['params']['state'],
            $conditions['RequiresGodAffiliation']
        );

        $this->assertContains('target:none', $states, 'un autel déjà consacré ne se reconsacre pas');
        $this->assertContains('actor:any', $states, 'et il faut un Dieu à lui donner');
        $this->assertSame(50, $conditions['RequiresFaith'][0]['params']['pf']);
    }

    /**
     * Worshipping asks the altar for a god that is not already yours —
     * `other`, which also refuses a naked altar.
     */
    public function testWorshippingAsksTheAltarForAnotherGod(): void
    {
        $conditions = $this->conditionsOf('venerer');

        $this->assertSame('target', $conditions['RequiresGodAffiliation'][0]['params']['side']);
        $this->assertSame('other', $conditions['RequiresGodAffiliation'][0]['params']['state']);
        $this->assertArrayNotHasKey('RequiresFaith', $conditions, 'vénérer est gratuit, comme avant');
    }

    /** The god travels one way for each gesture, and never the same way. */
    public function testTheGodTravelsTheRightWay(): void
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT a.name AS action, o.apply_to, i.type, i.parameters
               FROM actions a
               JOIN action_outcomes o ON o.action_id = a.id
               JOIN outcome_instructions i ON i.outcome_id = o.id
              WHERE a.name IN ('consacrer', 'venerer')"
        );

        $byAction = [];

        foreach ($rows as $row) {
            $byAction[$row['action']] = ['apply_to' => $row['apply_to']] + json_decode((string) $row['parameters'], true)
                + ['type' => $row['type']];
        }

        $this->assertSame('setgod', $byAction['consacrer']['type']);
        $this->assertSame('actor', $byAction['consacrer']['from']);
        $this->assertSame('target', $byAction['consacrer']['to']);
        $this->assertSame('Autel de {dieu}', $byAction['consacrer']['rename'], 'l\'autel dit de qui il est');

        $this->assertSame('target', $byAction['venerer']['from']);
        $this->assertSame('actor', $byAction['venerer']['to']);
    }

    /**
     * Both outcomes apply to the TARGET, because that is how the engine
     * decides what an action may be aimed at (ActionTargeting::scopeOf).
     *
     * `venerer` said `self` — worshipping does change the worshipper — and the
     * button then never appeared on an altar, the one place it belongs. Who is
     * aimed at and who changes are two questions; the instruction carries the
     * second through from/to.
     */
    public function testBothActionsAreAimedAtTheAltar(): void
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT a.name, o.apply_to
               FROM actions a JOIN action_outcomes o ON o.action_id = a.id
              WHERE a.name IN ('consacrer', 'venerer')"
        );

        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertSame('target', $row['apply_to'], $row['name'] . ' doit viser la cible');
        }
    }

    /** An action nobody holds has no button: they go where `prier` goes. */
    public function testTheyAreGrantedWhereverPrayerIs(): void
    {
        foreach (['race_starter_actions', 'players_actions'] as $table) {
            $reference = (int) $this->conn->fetchOne("SELECT COUNT(*) FROM {$table} WHERE name = 'prier'");

            /* Personne ne tient `prier` : la distribution est vraie pour rien,
             * et l'exiger reviendrait à demander une population. Le catalogue,
             * lui, est toujours là — c'est sur lui que la règle porte. */
            if ($reference === 0 && $table === 'players_actions') {
                continue;
            }

            $this->assertGreaterThan(0, $reference, 'prier doit servir de référence');

            foreach (['consacrer', 'venerer'] as $action) {
                $this->assertSame(
                    $reference,
                    (int) $this->conn->fetchOne("SELECT COUNT(*) FROM {$table} WHERE name = ?", [$action]),
                    $action . ' doit être distribuée comme prier dans ' . $table
                );
            }
        }
    }
}
