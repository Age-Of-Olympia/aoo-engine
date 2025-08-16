<?php
namespace Tests\Logs;

use PHPUnit\Framework\TestCase;
use Tests\Logs\Mock\PlayerMock;
use Tests\Logs\Mock\TestDatabase;
use Tests\Logs\Mock\ViewMock;
use Tests\Logs\Mock\JsonMock;
use PHPUnit\Framework\Attributes\Group;
use Classes\Log;

/**
 * Tests spécifiques pour la méthode filterRows
 */
class FilterRowsTest extends TestCase
{
    private PlayerMock $player;
    private TestDatabase $testDb;

    protected function setUp(): void
    {
        $this->player = new PlayerMock(1, 'TestPlayer');
        $this->testDb = new TestDatabase();

        // Injection des mocks
        Log::setDbInstance($this->testDb);
        Log::setViewClass('Tests\Logs\Mock\ViewMock');
        Log::setJsonInstance(new JsonMock());

        // Reset et nettoyage
        ViewMock::reset();
        $this->testDb->clearLogs();

        if (!defined('THREE_DAYS')) {
            define('THREE_DAYS', 259200);
        }
    }

    protected function tearDown(): void
    {
        Log::resetTestInstances();
    }

    #[Group('filter-rows')]
    public function testFilterRowsRemovesActionPair(): void
    {
        // Arrange - Créer une paire action/action_other_player
        $timestamp = time();
        $this->testDb->insertLog([
            'type' => 'action',
            'text' => 'Player action',
            'time' => $timestamp,
            'player_id' => $this->player->id,
            'target_id' => 2
        ]);
        $this->testDb->insertLog([
            'type' => 'action_other_player',
            'text' => 'Other player sees action',
            'time' => $timestamp,
            'player_id' => 2,
            'target_id' => $this->player->id
        ]);

        // Act
        $result = Log::get($this->player);
        
        // Assert - Ne devrait garder que l'action du joueur
        $this->assertCount(1, $result);
        $this->assertEquals('action', $result[0]->type);
        $this->assertEquals($this->player->id, $result[0]->player_id);
        $this->assertEquals('Player action', $result[0]->text);
    }

    #[Group('filter-rows')]
    public function testFilterRowsRemovesHiddenActionPair(): void
    {
        // Arrange
        $timestamp = time();
        $this->testDb->insertLog([
            'type' => 'hidden_action',
            'text' => 'Hidden action by player',
            'time' => $timestamp,
            'player_id' => $this->player->id,
            'target_id' => 2
        ]);
        $this->testDb->insertLog([
            'type' => 'hidden_action_other_player',
            'text' => 'Hidden action other side',
            'time' => $timestamp,
            'player_id' => 2,
            'target_id' => $this->player->id
        ]);

        // Act
        $result = Log::get($this->player);
        
        // Assert - Ne devrait garder que l'action du joueur
        $this->assertCount(1, $result);
        $this->assertEquals('hidden_action', $result[0]->type);
        $this->assertEquals($this->player->id, $result[0]->player_id);
    }

    #[Group('filter-rows')]
    public function testFilterRowsHandlesKillPairs(): void
    {
        // Arrange
        $timestamp = time();
        $this->testDb->insertLog([
            'type' => 'kill',
            'text' => 'Player kills target',
            'time' => $timestamp,
            'player_id' => $this->player->id,
            'target_id' => 2
        ]);
        $this->testDb->insertLog([
            'type' => 'kill',
            'text' => 'Target is killed by player',
            'time' => $timestamp,
            'player_id' => 2,
            'target_id' => $this->player->id
        ]);

        // Act
        $result = Log::get($this->player);
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('kill', $result[0]->type);
        $this->assertEquals($this->player->id, $result[0]->player_id);
    }

    #[Group('filter-rows')]
    public function testFilterRowsKeepsNonPairs(): void
    {
        // Arrange - Events with different timestamps
        $timestamp1 = time();
        $timestamp2 = time() + 1;
        $this->testDb->insertLog([
            'type' => 'action',
            'text' => 'First action',
            'time' => $timestamp1,
            'player_id' => $this->player->id,
            'target_id' => 2
        ]);
        $this->testDb->insertLog([
            'type' => 'action_other_player',
            'text' => 'Second action (different time)',
            'time' => $timestamp2,
            'player_id' => 2,
            'target_id' => $this->player->id
        ]);

        // Act
        $result = Log::get($this->player);
        
        // Assert - Both should be kept since timestamps differ
        $this->assertCount(2, $result);
    }

    #[Group('filter-rows')]
    public function testFilterRowsKeepsSingleEvents(): void
    {
        // Arrange
        $this->testDb->insertLog([
            'type' => 'action',
            'text' => 'Solo action',
            'time' => time(),
            'player_id' => $this->player->id,
            'target_id' => 2
        ]);
        $this->testDb->insertLog([
            'type' => 'destroy',
            'text' => 'Destroy event',
            'time' => time() + 1,
            'player_id' => 2,
            'target_id' => 3,
            'coords_computed' => '0_0_0_test_plan'
        ]);

        // Le destroy doit être visible (dans le champ de perception)
        ViewMock::setCoordsAroundResult(['0_0_0_test_plan']);

        // Act
        $result = Log::get($this->player);
        
        // Assert
        $this->assertCount(2, $result);
    }

    #[Group('filter-rows')]
    public function testFilterRowsWhenPlayerNotInvolved(): void
    {
        // Arrange - Paire entre deux autres joueurs
        $timestamp = time();
        $this->testDb->insertLog([
            'type' => 'action',
            'text' => 'Action between others 1',
            'time' => $timestamp,
            'player_id' => 2,
            'target_id' => 3,
            'coords_computed' => '0_0_0_test_plan'
        ]);
        $this->testDb->insertLog([
            'type' => 'action_other_player',
            'text' => 'Action between others 2',
            'time' => $timestamp,
            'player_id' => 3,
            'target_id' => 2,
            'coords_computed' => '0_0_0_test_plan'
        ]);

        // Les événements doivent être visibles (dans le champ de perception)
        ViewMock::setCoordsAroundResult(['0_0_0_test_plan']);

        // Act
        $result = Log::get($this->player);
        
        // Assert - Devrait garder le premier événement quand le joueur n'est pas impliqué
        $this->assertCount(1, $result);
        $this->assertEquals('action', $result[0]->type);
    }

    #[Group('filter-rows')]
    public function testFilterRowsWithWrongTargetRelation(): void
    {
        // Arrange - Même timestamp mais mauvaise relation player/target
        $timestamp = time();
        $this->testDb->insertLog([
            'type' => 'action',
            'text' => 'Action 1',
            'time' => $timestamp,
            'player_id' => 2,
            'target_id' => 3,
            'coords_computed' => '0_0_0_test_plan'
        ]);
        $this->testDb->insertLog([
            'type' => 'action_other_player',
            'text' => 'Action 2 (no relation)',
            'time' => $timestamp,
            'player_id' => 4,
            'target_id' => 5, // Pas de relation avec la première action
            'coords_computed' => '0_0_0_test_plan'
        ]);

        // Les événements doivent être visibles
        ViewMock::setCoordsAroundResult(['0_0_0_test_plan']);

        // Act
        $result = Log::get($this->player);
        
        // Assert - Les deux devraient être gardés car pas de relation valide
        $this->assertCount(2, $result);
    }

    #[Group('filter-rows')]
    public function testFilterRowsWithMultiplePairs(): void
    {
        // Arrange - Plusieurs paires mélangées
        $timestamp1 = time();
        $timestamp2 = time() + 1;
        
        // Paire 1 : action du joueur
        $this->testDb->insertLog([
            'type' => 'action',
            'time' => $timestamp1,
            'player_id' => $this->player->id,
            'target_id' => 2
        ]);
        $this->testDb->insertLog([
            'type' => 'action_other_player',
            'time' => $timestamp1,
            'player_id' => 2,
            'target_id' => $this->player->id
        ]);
        
        // Paire 2 : kill impliquant le joueur
        $this->testDb->insertLog([
            'type' => 'kill',
            'time' => $timestamp2,
            'player_id' => 3,
            'target_id' => $this->player->id
        ]);
        $this->testDb->insertLog([
            'type' => 'kill',
            'time' => $timestamp2,
            'player_id' => $this->player->id,
            'target_id' => 3
        ]);

        // Act
        $result = Log::get($this->player);
        
        // Assert - Devrait garder un élément de chaque paire (ceux du joueur)
        $this->assertCount(2, $result);
        
        // Les deux événements gardés devraient être ceux du joueur
        foreach ($result as $log) {
            $this->assertEquals($this->player->id, $log->player_id);
        }
    }
}