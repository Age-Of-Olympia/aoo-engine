<?php

namespace Tests\Tutorial;

use App\Factory\PlayerFactory;
use App\Tutorial\TutorialContext;
use App\Tutorial\TutorialProgressManager;
use App\Tutorial\TutorialSessionManager;
use App\Tutorial\TutorialStepRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * La reprise re-synchronise le drapeau de consommation des pas.
 *
 * `tutorial_consume_movements` vit dans la session PHP et n'était posé
 * qu'en ENTRANT dans une étape. Reprendre le tutoriel dans une session
 * neuve (re-login, autre onglet) en plein « épuisez vos mouvements »
 * perdait le drapeau : chaque pas redevenait gratuit, et l'étape — qui
 * attend Mvt = 0 — ne se validait plus jamais.
 *
 * La lecture du step courant SANS rejouer les prérequis (le chemin de la
 * reprise) doit re-poser ce drapeau, et lui seul : re-restaurer les
 * ressources à chaque reprise ne serait pas idempotent.
 */
#[Group('tutorial')]
class TutorialResumeKeepsConsumptionTest extends TutorialIntegrationTestCase
{
    private ?string $previousErrorLog = null;

    /** @var mixed */
    private mixed $previousLink = null;

    private int $playerId = 0;

    protected function setUp(): void
    {
        $this->previousErrorLog = ini_get('error_log') ?: '';
        ini_set('error_log', '/tmp/phpunit-resume-consumption.log');
        ob_start();

        parent::setUp();

        require_once __DIR__ . '/../../config/db_constants.php';
        require_once __DIR__ . '/../../config/functions.php';
        $this->previousLink = $GLOBALS['link'] ?? null;
        $GLOBALS['link'] = $this->conn;
        require_once __DIR__ . '/../../config/constants.php';

        if ((int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM tutorial_steps WHERE version = '1.0.0' AND step_id = 'deplete_movements'"
        ) === 0) {
            $this->markTestSkipped('tutorial content not seeded in this database');
        }

        $this->conn->insert('players', [
            'name'        => 'ResumeConsume_' . bin2hex(random_bytes(4)),
            'race'        => 'nain',
            'player_type' => 'tutorial',
            'coords_id'   => $this->seedTile(),
        ]);
        $this->playerId = (int) $this->conn->lastInsertId();

        $_SESSION = ['playerId' => $this->playerId];
    }

    protected function tearDown(): void
    {
        ob_end_clean();
        $_SESSION = [];
        ini_set('error_log', $this->previousErrorLog ?? '');
        $GLOBALS['link'] = $this->previousLink;
        $this->previousLink = null;
        parent::tearDown();
    }

    public function testReadingTheCurrentStepRestoresTheConsumptionFlag(): void
    {
        $manager = $this->makeProgressManager();

        // La session neuve ne porte rien.
        unset($_SESSION['tutorial_consume_movements']);

        $manager->getCurrentStepForClient('deplete_movements', '1.0.0', false);

        $this->assertTrue(
            $this->consumptionFlag(),
            'repris sur « épuisez vos mouvements », chaque pas doit consommer'
        );

        $manager->getCurrentStepForClient('first_move', '1.0.0', false);

        $this->assertFalse(
            $this->consumptionFlag(),
            'repris sur le premier pas (gratuit), le drapeau retombe'
        );
    }

    /* Indirection volontaire : la lecture directe de $_SESSION juste après
     * son unset() laisse l'analyse statique croire le drapeau absent — elle
     * ne voit pas l'écriture faite à l'intérieur du manager. */
    private function consumptionFlag(): ?bool
    {
        return $_SESSION['tutorial_consume_movements'] ?? null;
    }

    private function makeProgressManager(): TutorialProgressManager
    {
        $player = PlayerFactory::legacy($this->playerId);
        $player->get_data();

        return new TutorialProgressManager(
            new TutorialContext($player),
            new TutorialStepRepository(),
            new TutorialSessionManager()
        );
    }
}
