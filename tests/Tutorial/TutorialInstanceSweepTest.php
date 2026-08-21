<?php

namespace Tests\Tutorial;

use App\Factory\EntityManagerFactory;
use App\Tutorial\TutorialResourceManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Le balayage global des instances de tutoriel (cleanupStale).
 *
 * Les chemins par joueur (complete, cancel, prochain start) ne rattrapent
 * jamais un joueur qui abandonne sans revenir, ni un démontage échoué à
 * mi-chemin. Le balayage ramasse tout ce qui n'est pas une session en
 * cours — et rien d'autre : une session jouée reste debout, un joueur
 * réel bloque la suppression.
 *
 * Tourne dans une transaction sur la connexion de la suite, comme
 * TutorialMapInstanceCloneTest : le rollback emporte les fixtures.
 */
#[Group('tutorial')]
class TutorialInstanceSweepTest extends TestCase
{
    private Connection $conn;

    private mixed $previousLink = null;

    private string $sessionId;

    private string $plan;

    protected function setUp(): void
    {
        try {
            $this->conn = EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('suite DB unreachable: ' . $e->getMessage());
        }

        // db() (utilisé par les chemins de démontage) doit voir NOTRE transaction.
        $this->previousLink = $GLOBALS['link'] ?? null;
        $GLOBALS['link'] = $this->conn;

        $suffix = bin2hex(random_bytes(5));
        $this->sessionId = $suffix . '-sweep-test';
        $this->plan = 'tut_' . substr($this->sessionId, 0, 10);

        $this->conn->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->conn) && $this->conn->isTransactionActive()) {
            $this->conn->rollBack();
        }
        $GLOBALS['link'] = $this->previousLink;

        \App\Service\PlanService::forget();
        EntityManagerFactory::getEntityManager()->clear();
    }

    public function testAnInProgressSessionIsLeftAlone(): void
    {
        $playerId = $this->seedInstance();
        $this->seedSession($playerId, completed: false, ageHours: 1);

        $report = (new TutorialResourceManager())->cleanupStale();

        $this->assertNotContains($this->plan, $report['swept']);
        $this->assertSame(1, $this->planRowCount(), 'la config du plan reste');
        $this->assertNotFalse(
            $this->conn->fetchOne('SELECT 1 FROM players WHERE id = ?', [$playerId]),
            'le personnage de la session en cours reste'
        );
    }

    public function testACompletedSessionIsSwept(): void
    {
        $playerId = $this->seedInstance();
        $this->seedSession($playerId, completed: true, ageHours: 1);

        $report = (new TutorialResourceManager())->cleanupStale();

        $this->assertContains($this->plan, $report['swept']);
        $this->assertInstanceGone($playerId);
    }

    public function testAnOldAbandonedSessionIsSwept(): void
    {
        $playerId = $this->seedInstance();
        $this->seedSession($playerId, completed: false, ageHours: 72);

        $report = (new TutorialResourceManager())->cleanupStale();

        $this->assertContains($this->plan, $report['swept']);
        $this->assertInstanceGone($playerId);
    }

    /** Une ligne de config sans coords ni session : le vrai orphelin. */
    public function testAnOrphanConfigRowIsSwept(): void
    {
        $this->conn->insert('plans', ['slug' => $this->plan, 'name' => 'Orphelin']);
        \App\Service\PlanService::forget($this->plan);

        $report = (new TutorialResourceManager())->cleanupStale();

        $this->assertContains($this->plan, $report['swept']);
        $this->assertSame(0, $this->planRowCount(), 'la ligne orpheline part sans coords');
    }

    public function testARealPlayerBlocksTheSweep(): void
    {
        $this->seedInstance();

        $this->conn->insert('players', [
            'player_type' => 'real',
            'race'        => 'nain',
            'name'        => 'Coincé du sweep',
            'coords_id'   => $this->coordsId(0, 1),
        ]);

        $report = (new TutorialResourceManager())->cleanupStale();

        $this->assertArrayHasKey($this->plan, $report['skipped']);
        $this->assertNotContains($this->plan, $report['swept']);
        $this->assertSame(1, $this->planRowCount(), 'le plan reste tant qu\'un joueur réel s\'y trouve');
    }

    /**
     * Config + deux coords + un personnage de tutoriel dessus.
     *
     * @return int id du personnage de tutoriel
     */
    private function seedInstance(): int
    {
        $this->conn->insert('plans', ['slug' => $this->plan, 'name' => 'Instance de test sweep']);
        \App\Service\PlanService::forget($this->plan);

        $this->conn->insert('coords', ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => $this->plan]);
        $spawnId = (int) $this->conn->lastInsertId();
        $this->conn->insert('coords', ['x' => 0, 'y' => 1, 'z' => 0, 'plan' => $this->plan]);

        $this->conn->insert('players', [
            'player_type' => 'tutorial',
            'race'        => 'nain',
            'name'        => 'Apprenti du sweep',
            'coords_id'   => $spawnId,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function seedSession(int $playerId, bool $completed, int $ageHours): void
    {
        $this->conn->insert('tutorial_players', [
            'tutorial_session_id' => $this->sessionId,
            'player_id'           => $playerId,
            'name'                => 'Apprenti du sweep',
            'is_active'           => 1,
        ]);
        $this->conn->executeStatement(
            'UPDATE tutorial_players SET created_at = (NOW() - INTERVAL ? HOUR) WHERE tutorial_session_id = ?',
            [$ageHours, $this->sessionId]
        );

        $this->conn->insert('tutorial_progress', [
            'player_id'           => $playerId,
            'tutorial_session_id' => $this->sessionId,
            'current_step'        => 'welcome',
            'completed'           => $completed ? 1 : 0,
        ]);
    }

    private function assertInstanceGone(int $playerId): void
    {
        $this->assertSame(0, $this->planRowCount(), 'la config du plan part');
        $this->assertSame(
            0,
            (int) $this->conn->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [$this->plan]),
            'les coords partent'
        );
        $this->assertFalse(
            $this->conn->fetchOne('SELECT 1 FROM players WHERE id = ?', [$playerId]),
            'le personnage de tutoriel part'
        );
        $this->assertNotFalse(
            $this->conn->fetchOne(
                'SELECT 1 FROM tutorial_players WHERE tutorial_session_id = ? AND deleted_at IS NOT NULL',
                [$this->sessionId]
            ),
            'la ligne de session est soft-deleted'
        );
    }

    private function planRowCount(): int
    {
        return (int) $this->conn->fetchOne('SELECT COUNT(*) FROM plans WHERE slug = ?', [$this->plan]);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) $this->conn->fetchOne(
            'SELECT id FROM coords WHERE plan = ? AND x = ? AND y = ? AND z = 0',
            [$this->plan, $x, $y]
        );
    }
}
