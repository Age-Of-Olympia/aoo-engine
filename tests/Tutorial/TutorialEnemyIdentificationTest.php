<?php

namespace Tests\Tutorial;

use App\Tutorial\TutorialHelper;
use PHPUnit\Framework\Attributes\Group;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * Comment le damier reconnaît un PNJ du tutoriel.
 *
 * Il le reconnaissait à son NOM — « Âme d'entraînement » — pour lui poser la
 * marque `.tutorial-enemy`, celle à laquelle les étapes accrochent leur
 * surlignage. Or ce nom est un libellé d'affichage, éditable depuis
 * l'administration du tutoriel : le renommer éteignait le surlignage sans
 * lever la moindre erreur, et rien dans le formulaire d'édition ne laissait
 * deviner qu'un champ de présentation commandait une mécanique.
 *
 * Ces cas fixent le critère de remplacement — l'inscription en base, posée
 * par l'apparition elle-même — et, surtout, le fait qu'il SURVIT au
 * renommage. C'est tout l'objet du changement.
 */
#[Group('tutorial-enemy-identification')]
class TutorialEnemyIdentificationTest extends TutorialIntegrationTestCase
{
    /** @var mixed $GLOBALS['link'] d'origine */
    private $previousLink = null;

    /** @var array<string, mixed> $_SESSION d'origine */
    private array $previousSession = [];

    protected function setUp(): void
    {
        parent::setUp();

        /* TutorialHelper passe par Classes\Db, qui lit $GLOBALS['link'] :
         * on le pointe sur la connexion transactionnelle du test pour que
         * les lignes semées disparaissent au rollback. */
        $this->previousLink = $GLOBALS['link'] ?? null;
        $GLOBALS['link'] = $this->conn;

        $this->previousSession = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->previousSession;
        $GLOBALS['link'] = $this->previousLink;

        parent::tearDown();
    }

    /** Hors tutoriel, la question ne se pose pas — et n'interroge pas la base. */
    public function testNoTutorialSessionYieldsNoEnemy(): void
    {
        $_SESSION = [];

        $this->assertSame([], TutorialHelper::getSessionEnemyIds());
    }

    /** Le PNJ inscrit pour la session en cours est reconnu. */
    public function testTheSpawnedNpcOfTheSessionIsRecognised(): void
    {
        $sessionId = $this->openSession();
        $enemyId = $this->spawnEnemy($sessionId, "Âme d'entraînement");

        $this->assertSame([$enemyId => true], TutorialHelper::getSessionEnemyIds());
    }

    /**
     * LE point du changement : le renommage ne casse plus rien.
     *
     * Un animateur qui rebaptise le PNJ depuis l'administration du tutoriel
     * éteignait jusqu'ici le surlignage de l'étape de combat.
     */
    public function testRenamingTheNpcDoesNotLoseIt(): void
    {
        $sessionId = $this->openSession();
        $enemyId = $this->spawnEnemy($sessionId, 'Spectre du Val');

        $this->assertSame(
            [$enemyId => true],
            TutorialHelper::getSessionEnemyIds(),
            'le nom ne commande plus la reconnaissance'
        );
    }

    /** Le PNJ d'une AUTRE session ne déborde pas sur la nôtre. */
    public function testAnotherSessionsNpcIsNotOurs(): void
    {
        $mySession = $this->openSession();
        $mine = $this->spawnEnemy($mySession, 'Le mien');

        $otherSession = $this->newSessionId();
        $this->spawnEnemy($otherSession, "Celui d'à côté");

        $this->assertSame([$mine => true], TutorialHelper::getSessionEnemyIds());
    }

    private function newSessionId(): string
    {
        return sprintf(
            '%08x-%04x-4%03x-%04x-%012x',
            random_int(0, 0xffffffff),
            random_int(0, 0xffff),
            random_int(0, 0xfff),
            random_int(0x8000, 0xbfff),
            random_int(0, 0xffffffffffff),
        );
    }

    /** Ouvre une session de tutoriel côté PHP et rend son identifiant. */
    private function openSession(): string
    {
        $sessionId = $this->newSessionId();

        $_SESSION['in_tutorial'] = true;
        $_SESSION['tutorial_session_id'] = $sessionId;

        return $sessionId;
    }

    /**
     * Sème un PNJ et son inscription, comme le fait
     * TutorialResourceManager::spawnDynamicNpcs().
     */
    private function spawnEnemy(string $sessionId, string $name): int
    {
        $this->conn->insert('coords', [
            'x'    => random_int(1000, 99999),
            'y'    => 0,
            'z'    => 0,
            'plan' => 'test-enemy-identification',
        ]);
        $coordsId = (int) $this->conn->lastInsertId();

        $this->conn->insert('players', [
            'name'        => $name,
            'race'        => 'nain',
            'player_type' => 'npc',
            'coords_id'   => $coordsId,
        ]);
        $enemyId = (int) $this->conn->lastInsertId();

        $this->conn->insert('tutorial_enemies', [
            'tutorial_session_id' => $sessionId,
            'enemy_player_id'     => $enemyId,
            'enemy_coords_id'     => $coordsId,
        ]);

        return $enemyId;
    }
}
