<?php
namespace Tests\Logs;

use PHPUnit\Framework\TestCase;
use Tests\Logs\Mock\PlayerMock;
use Tests\Logs\Mock\TestDatabase;
use Tests\Logs\Mock\ViewMock;
use Tests\Logs\Mock\JsonMock;
use PHPUnit\Framework\Attributes\Group;
use Classes\Log;
use Tests\Logs\Mock\TestDatabaseLogs;

class LogTest extends TestCase
{
    private PlayerMock $player;
    private TestDatabaseLogs $testDb;
    private JsonMock $jsonMock;

    protected function setUp(): void
    {
        $this->player = new PlayerMock(1, 'TestPlayer');
        $this->testDb = new TestDatabaseLogs();
        $this->jsonMock = new JsonMock();
        
        // Injection des mocks dans Log
        Log::setDbInstance($this->testDb);
        Log::setViewClass('Tests\Logs\Mock\ViewMock');
        Log::setJsonInstance($this->jsonMock);

        // Reset des mocks
        ViewMock::reset();
        JsonMock::reset();

        // Mock des constantes si nécessaire
        if (!defined('THREE_DAYS')) {
            define('THREE_DAYS', 259200); // 3 jours en secondes
        }
    }

    protected function tearDown(): void
    {
        Log::resetTestInstances();
        ViewMock::reset();
        JsonMock::reset();
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
        $this->testDb->insertLog([
            'text' => 'Birdland action',
            'player_id' => $this->player->id,
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
        $this->assertCount(0, $result);
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
}