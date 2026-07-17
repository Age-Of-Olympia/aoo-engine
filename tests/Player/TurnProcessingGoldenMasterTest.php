<?php

namespace Tests\Player;

use App\Service\TurnProcessingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Moteur de tour extrait de NewTurnView (TurnProcessingService) : un
 * tour dû applique les mutations (XP, nextTurnTime avancé, énergie) et
 * journalise le récap en ÉVÉNEMENT type 'turn', relisible dans les
 * Évènements. Pas dû : rien ne bouge, pas d'événement.
 */
#[Group('entities-golden-master')]
class TurnProcessingGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionBackup = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        parent::tearDown();
    }

    /**
     * Player::refresh_data dépend de DOCUMENT_ROOT (vide en CLI) : purge
     * par le chemin du dépôt, comme le teardown du harnais — ET le cache
     * mémoire du décodeur JSON, partagé sur tout le process de test.
     */
    private function purgeDataCache(int $playerId): void
    {
        @unlink(__DIR__ . '/../../datas/private/players/' . $playerId . '.json');
        json()->forget('players', (string) $playerId);
    }

    public function testADueTurnMutatesAndLogsATurnEvent(): void
    {
        $player = $this->createRealPlayer('GmTurn');
        $_SESSION = ['playerId' => (int) $player->id];

        $this->link->executeStatement(
            'UPDATE players SET nextTurnTime = ? WHERE id = ?',
            [time() - 60, (int) $player->id]
        );
        $this->purgeDataCache((int) $player->id);

        $xpBefore = (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [(int) $player->id]);

        $recap = (new TurnProcessingService())->processIfDue($player);

        $this->assertNotNull($recap, 'un tour dû doit être traité');
        $this->assertNotEmpty($recap->rows, 'le récap porte les lignes de récupération');
        $this->assertGreaterThan(time(), $recap->nextTurnTime, 'le prochain tour est dans le futur');

        $this->assertSame(
            $recap->nextTurnTime,
            (int) $this->link->fetchOne('SELECT nextTurnTime FROM players WHERE id = ?', [(int) $player->id])
        );
        $this->assertGreaterThan(
            $xpBefore,
            (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [(int) $player->id]),
            "l'XP du tour est créditée"
        );

        $event = $this->link->fetchAssociative(
            "SELECT type, text FROM players_logs WHERE player_id = ? AND type = 'turn' ORDER BY id DESC LIMIT 1",
            [(int) $player->id]
        );
        $this->assertNotFalse($event, "le récap est journalisé en événement type 'turn'");
        $this->assertStringContainsString('Nouveau tour', (string) $event['text']);
        $this->assertStringContainsString('Prochain tour le', (string) $event['text']);
    }

    public function testATurnNotDueDoesNothing(): void
    {
        $player = $this->createRealPlayer('GmTurn');
        $_SESSION = ['playerId' => (int) $player->id];

        $future = time() + 3600;
        $this->link->executeStatement(
            'UPDATE players SET nextTurnTime = ? WHERE id = ?',
            [$future, (int) $player->id]
        );
        $this->purgeDataCache((int) $player->id);

        $this->assertNull((new TurnProcessingService())->processIfDue($player));

        $this->assertSame(
            $future,
            (int) $this->link->fetchOne('SELECT nextTurnTime FROM players WHERE id = ?', [(int) $player->id]),
            'rien ne bouge quand le tour n\'est pas dû'
        );
        $this->assertFalse(
            $this->link->fetchOne(
                "SELECT id FROM players_logs WHERE player_id = ? AND type = 'turn'",
                [(int) $player->id]
            ),
            'pas d\'événement sans tour'
        );
    }
}
