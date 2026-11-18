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
 * The step sent to the client (start, resume and advance alike) has its
 * placeholders replaced, title included.
 */
#[Group('tutorial')]
class TutorialStepForClientPlaceholdersTest extends TutorialIntegrationTestCase
{
    /** @var mixed */
    private mixed $previousLink = null;

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../config/db_constants.php';
        require_once __DIR__ . '/../../config/functions.php';
        $this->previousLink = $GLOBALS['link'] ?? null;
        $GLOBALS['link'] = $this->conn;
        require_once __DIR__ . '/../../config/constants.php';

        if ((int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM tutorial_steps WHERE version = '1.0.0' AND step_id = 'first_move'"
        ) === 0) {
            $this->markTestSkipped('tutorial content not seeded in this database');
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $GLOBALS['link'] = $this->previousLink;
        $this->previousLink = null;
        parent::tearDown();
    }

    public function testTitleAndTextReachTheClientWithPlaceholdersReplaced(): void
    {
        $name = 'Placeholders_' . bin2hex(random_bytes(4));
        $this->conn->insert('players', [
            'name' => $name, 'race' => 'nain', 'player_type' => 'tutorial', 'coords_id' => $this->seedTile(),
        ]);
        $player = PlayerFactory::legacy((int) $this->conn->lastInsertId());
        $player->get_data();
        $_SESSION = ['playerId' => $player->id];

        $this->conn->executeStatement(
            "UPDATE tutorial_steps SET title = 'Salut {player_name}', text = 'Un {race} de {max_mvt} pas'
              WHERE version = '1.0.0' AND step_id = 'first_move'"
        );

        $manager = new TutorialProgressManager(new TutorialContext($player), new TutorialStepRepository(), new TutorialSessionManager());
        $step = $manager->getCurrentStepForClient('first_move', '1.0.0', false);

        $this->assertNotNull($step);
        $this->assertSame('Salut ' . $name, $step['title']);
        $this->assertStringNotContainsString('{', $step['text']);
    }
}
