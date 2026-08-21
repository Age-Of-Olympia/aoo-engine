<?php
namespace Tests\Logs;

use PHPUnit\Framework\TestCase;
use Tests\Logs\Mock\PlayerMock;
use Tests\Logs\Mock\TestDatabase;
use Tests\Logs\Mock\ViewMock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Classes\Log;

class LogTest extends TestCase
{
    private PlayerMock $player;
    private TestDatabase $testDb;

    protected function setUp(): void
    {
        $this->player = new PlayerMock(1, 'TestPlayer');
        $this->testDb = new TestDatabase();
        
        // Injection des mocks dans Log
        Log::setDbInstance($this->testDb);
        Log::setViewClass('Tests\Logs\Mock\ViewMock');
        // La config de plan vit en base : on stub le lecteur, pas le Json
        Log::setPlanReader(fn (string $plan): object => (object) ['player_visibility' => true]);

        // Reset des mocks
        ViewMock::reset();

        // Mock des constantes si nécessaire
        if (!defined('THREE_DAYS')) {
            define('THREE_DAYS', 259200); // 3 jours en secondes
        }
    }

    protected function tearDown(): void
    {
        Log::resetTestInstances();
        ViewMock::reset();
    }

    #[Group('log-get')]
    public function testGetLogsFiltersByType(): void
    {
        // Arrange
        $this->testDb->insertLog([
            'type' => 'mdj',
            'text' => 'Message MDJ',
            'time' => time()
        ]);
        $this->testDb->insertLog([
            'type' => 'action',
            'text' => 'Action normale',
            'time' => time()
        ]);

        // Act - Test type MDJ
        $result = Log::get($this->player, THREE_DAYS, 'mdj');
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('mdj', $result[0]->type);
        $this->assertEquals('Message MDJ', $result[0]->text);
    }

    #[Group('log-get')]
    public function testGetLogsFiltersByAge(): void
    {
        // Arrange
        $currentTime = time();
        $this->testDb->insertLog([
            'text' => 'Recent',
            'time' => $currentTime - 3600 // 1h ago
        ]);
        $this->testDb->insertLog([
            'text' => 'Old',
            'time' => $currentTime - 400000 // 4+ days ago
        ]);

        // Act
        $result = Log::get($this->player, THREE_DAYS);
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Recent', $result[0]->text);
    }

    #[Group('log-get')]
    public function testPlayerSeesOwnActions(): void
    {
        // Arrange
        $this->testDb->insertLog([
            'text' => 'Player action',
            'player_id' => $this->player->id,
            'target_id' => 2
        ]);
        $this->testDb->insertLog([
            'text' => 'Other action',
            'player_id' => 3,
            'target_id' => 4
        ]);

        // Act
        $result = Log::get($this->player);
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Player action', $result[0]->text);
        $this->assertEquals($this->player->id, $result[0]->player_id);
    }

    #[Group('log-get')]
    public function testPlayerSeesActionsTargetingThem(): void
    {
        // Arrange
        $this->testDb->insertLog([
            'text' => 'Action on player',
            'player_id' => 2,
            'target_id' => $this->player->id
        ]);
        $this->testDb->insertLog([
            'text' => 'Action on other',
            'player_id' => 2,
            'target_id' => 3
        ]);

        // Act
        $result = Log::get($this->player);
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Action on player', $result[0]->text);
        $this->assertEquals($this->player->id, $result[0]->target_id);
    }

    #[Group('log-get')]
    public function testDestroyActionVisibleToWitnesses(): void
    {
        // Arrange
        $this->testDb->insertLog([
            'type' => 'destroy',
            'text' => 'Destruction',
            'player_id' => 2,
            'target_id' => 3,
            'coords_computed' => '5_5_0_test_plan'
        ]);
        
        // Le joueur n'est ni acteur ni cible, mais dans le champ de perception
        ViewMock::setCoordsAroundResult(['5_5_0_test_plan']);

        // Act
        $result = Log::get($this->player);
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('destroy', $result[0]->type);
        $this->assertEquals('Destruction', $result[0]->text);
    }

    #[Group('log-get')]
    public function testDestroyActionNotVisibleIfTooFar(): void
    {
        // Arrange
        $this->testDb->insertLog([
            'type' => 'destroy',
            'text' => 'Far destruction',
            'player_id' => 2,
            'target_id' => 3,
            'coords_computed' => '100_100_0_test_plan'
        ]);
        
        // Le joueur n'est pas dans le champ de perception
        ViewMock::setCoordsAroundResult(['5_5_0_test_plan']);

        // Act
        $result = Log::get($this->player);
        
        // Assert
        $this->assertEmpty($result);
    }

    #[Group('log-get')]
    public function testHiddenActionNotVisibleToTarget(): void
    {
        // Arrange
        $this->testDb->insertLog([
            'type' => 'hidden_action',
            'text' => 'Hidden action',
            'player_id' => 2,
            'target_id' => $this->player->id
        ]);

        // Act
        $result = Log::get($this->player);
        
        // Assert
        $this->assertEmpty($result);
    }

    #[Group('log-get')]
    public function testBirdlandLogsAreFiltered(): void
    {
        // Arrange
        $this->testDb->insertLog([
            'text' => 'Normal action',
            'player_id' => $this->player->id,
            'plan' => 'normal_plan'
        ]);
        /* Événement discret d'un AUTRE : c'est à celui-là que « birdland »
           sert. Il portait l'id du lecteur avant, ce qui testait en réalité
           que l'on se cachait ses propres actions — voir
           testIncognitoAuthorStillSeesHisOwnEvent. */
        $this->testDb->insertLog([
            'text' => 'Birdland action',
            'player_id' => 99,
            'target_id' => 98,
            'plan' => 'birdland'
        ]);

        // Act
        $result = Log::get($this->player);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Normal action', $result[0]->text);
        $this->assertEquals('normal_plan', $result[0]->plan);
    }

    #[Group('log-get')]
    public function testPerceptionBasedVisibility(): void
    {
        // Arrange
        $this->testDb->insertLog([
            'text' => 'Close action',
            'player_id' => 2,
            'target_id' => 3,
            'coords_computed' => '5_5_0_test_plan'
        ]);
        $this->testDb->insertLog([
            'text' => 'Far action',
            'player_id' => 2,
            'target_id' => 3,
            'coords_computed' => '100_100_0_test_plan'
        ]);
        
        // Mock perception : seulement '5_5_0_test_plan' est visible
        ViewMock::setCoordsAroundResult(['5_5_0_test_plan']);

        // Act
        $result = Log::get($this->player);
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Close action', $result[0]->text);
    }

    #[Group('log-put')]
    public function testPutCreatesLogEntry(): void
    {
        // Arrange
        $target = 2;
        $text = 'Test log entry';
        $type = 'action';
        
        // Act
        Log::put($this->player, $target, $text, $type);
        
        // Assert
        $this->assertEquals(1, $this->testDb->getLogCount());
        
        // Vérifier le contenu
        $result = Log::get($this->player);
        $this->assertCount(1, $result);
        $this->assertEquals($text, $result[0]->text);
        $this->assertEquals($type, $result[0]->type);
    }

    /**
     * Le mode discret range l'événement sur le plan fictif « birdland » —
     * invisible aux autres, mais TOUJOURS lisible par son auteur.
     *
     * Ce test vérifiait auparavant que l'auteur ne voyait rien du tout. Ce
     * n'était pas l'intention du mode discret, seulement l'effet d'un filtre
     * placé avant le contrôle de propriété de la ligne.
     */
    #[Group('log-put')]
    public function testPutHandlesIncognitoMode(): void
    {
        // Arrange
        $this->player->setOption('incognitoMode', true);
        $target = 2;
        $text = 'Secret action';

        // Act
        Log::put($this->player, $target, $text, 'action');

        // Assert
        $result = Log::get($this->player);
        $this->assertCount(1, $result, 'l\'auteur lit son propre événement discret');
        $this->assertEquals('birdland', $result[0]->plan, 'rangé hors des regards');
        $this->assertStringContainsString($text, $result[0]->text);
    }

    #[Group('log-put')]
    public function testPutHiddenActionsHaveNullCoords(): void
    {
        // Act
        Log::put($this->player, 2, 'Hidden action', 'hidden_action');
        
        // Assert
        $result = Log::get($this->player);
        $this->assertCount(1, $result);
        $this->assertNull($result[0]->coords_id);
        $this->assertNull($result[0]->coords_computed);
    }

    #[Group('log-put')]
    public function testPutUsesCustomTime(): void
    {
        // Arrange
        $customTime = time() - (3600 * 24);
        
        // Act
        Log::put($this->player, 2, 'Timed action', 'action', '', $customTime);
        
        // Assert
        $result = Log::get($this->player);
        $this->assertCount(1, $result);
        $this->assertEquals($customTime, $result[0]->time);
    }

    public static function selfTargetedEventTypeProvider(): array
    {
        return [
            'destroy' => ['destroy'],
            'build' => ['build'],
        ];
    }

    #[Group('log-get')]
    #[DataProvider('selfTargetedEventTypeProvider')]
    public function testActorSeesOwnEventOnce(string $type): void
    {
        $this->testDb->insertLog([
            'type' => $type,
            'text' => 'Self event',
            'player_id' => $this->player->id,
            'target_id' => $this->player->id,
            'coords_computed' => '5_5_0_test_plan'
        ]);
        ViewMock::setCoordsAroundResult(['5_5_0_test_plan']);

        $result = Log::get($this->player);

        $this->assertCount(1, $result);
        $this->assertEquals($type, $result[0]->type);
    }

    #[Group('log-get')]
    #[DataProvider('selfTargetedEventTypeProvider')]
    public function testActorSeesOwnEventOnceOutsidePerception(string $type): void
    {
        $this->testDb->insertLog([
            'type' => $type,
            'text' => 'Self event',
            'player_id' => $this->player->id,
            'target_id' => $this->player->id,
            'coords_computed' => '100_100_0_test_plan'
        ]);
        ViewMock::setCoordsAroundResult(['5_5_0_test_plan']);

        $result = Log::get($this->player);

        $this->assertCount(1, $result);
    }

    #[Group('log-get')]
    #[DataProvider('selfTargetedEventTypeProvider')]
    public function testWitnessSeesEventWithinPerception(string $type): void
    {
        $this->testDb->insertLog([
            'type' => $type,
            'text' => 'Witnessed event',
            'player_id' => 2,
            'target_id' => 3,
            'coords_computed' => '5_5_0_test_plan'
        ]);
        ViewMock::setCoordsAroundResult(['5_5_0_test_plan']);

        $result = Log::get($this->player);

        $this->assertCount(1, $result);
        $this->assertEquals($type, $result[0]->type);
    }

    #[Group('log-get')]
    #[DataProvider('selfTargetedEventTypeProvider')]
    public function testWitnessDoesNotSeeEventOutOfPerception(string $type): void
    {
        $this->testDb->insertLog([
            'type' => $type,
            'text' => 'Far event',
            'player_id' => 2,
            'target_id' => 3,
            'coords_computed' => '100_100_0_test_plan'
        ]);
        ViewMock::setCoordsAroundResult(['5_5_0_test_plan']);

        $result = Log::get($this->player);

        $this->assertEmpty($result);
    }

    /**
     * L'incognito cache aux AUTRES, pas à soi-même.
     *
     * Log::put() range les événements d'un acteur discret sur le plan fictif
     * « birdland », d'où ils ne sont lus par personne. L'auteur y compris,
     * jusqu'ici : un PNJ discret ne voyait pas ses propres actions dans son
     * fil, seulement la version écrite du point de vue de sa cible — qui,
     * elle, n'est pas discrète.
     */
    #[Group('log-get')]
    public function testIncognitoAuthorStillSeesHisOwnEvent(): void
    {
        $this->testDb->insertLog([
            'type' => 'action',
            'text' => "Plan d'origine : gaia - Vous avez agi discrètement",
            'plan' => 'birdland',
            'player_id' => $this->player->id,
            'target_id' => 2,
        ]);

        $result = Log::get($this->player);

        $this->assertCount(1, $result, 'l\'auteur voit son propre événement discret');
        $this->assertSame('birdland', $result[0]->plan);
    }

    /** Et personne d'autre ne le voit — c'est tout l'objet du mode discret. */
    #[Group('log-get')]
    public function testIncognitoEventStaysHiddenFromEveryoneElse(): void
    {
        $this->testDb->insertLog([
            'type' => 'action',
            'text' => "Plan d'origine : gaia - Quelqu'un a agi discrètement",
            'plan' => 'birdland',
            'player_id' => 99,
            'target_id' => 98,
            'coords_computed' => '5_5_0_test_plan',
        ]);
        ViewMock::setCoordsAroundResult(['5_5_0_test_plan']);

        $result = Log::get($this->player);

        $this->assertEmpty($result, 'un tiers ne voit pas l\'événement d\'un acteur discret');
    }

    /**
     * Le cas exact du signalement.
     *
     * Une action écrit deux lignes — celle de l'acteur et celle de la cible —
     * et filterRows() n'en garde qu'UNE, celle du point de vue du lecteur.
     * Quand l'acteur est discret, sa propre ligne partait à « birdland » et
     * disparaissait AVANT ce tri : il ne restait que celle de la cible, d'où
     * l'impression de lire l'action du mauvais côté.
     *
     * Maintenant qu'elle survit, le tri fait son travail et rend à l'acteur
     * sa propre version.
     */
    #[Group('log-get')]
    public function testIncognitoActorSeesHisOwnSideOfTheAction(): void
    {
        $this->testDb->insertLog([
            'type' => 'action',
            'text' => 'Vous avez attaqué',
            'plan' => 'birdland',
            'player_id' => $this->player->id,
            'target_id' => 2,
        ]);
        $this->testDb->insertLog([
            'type' => 'action_other_player',
            'text' => 'Vous avez été attaqué',
            'plan' => 'test_plan',
            'player_id' => 2,
            'target_id' => $this->player->id,
        ]);

        $textes = array_map(static fn ($row): string => $row->text, Log::get($this->player));

        $this->assertContains('Vous avez attaqué', $textes, 'sa propre ligne, discrète, est rendue');
        $this->assertNotContains(
            'Vous avez été attaqué',
            $textes,
            'et remplace celle de la cible : une action ne se lit qu\'une fois'
        );
    }
}