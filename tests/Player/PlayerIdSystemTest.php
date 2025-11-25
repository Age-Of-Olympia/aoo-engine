<?php
namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Tests\Player\Mock\TestDatabase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the Player ID Range System
 *
 * Verifies that:
 * - Real players get sequential IDs (1, 2, 3...)
 * - Tutorial players get IDs in tutorial range (10000000+)
 * - NPCs get negative IDs
 * - Display IDs are sequential within each type
 */
class PlayerIdSystemTest extends TestCase
{
    private TestDatabase $testDb;
    private $originalDbFunction;

    protected function setUp(): void
    {
        // Load the functions file that contains getNextEntityId() and getNextDisplayId()
        require_once __DIR__ . '/../../config/functions.php';
        require_once __DIR__ . '/../../config/constants.php';

        // Create test database
        $this->testDb = new TestDatabase();

        // Mock the global db() function to return our test database
        $GLOBALS['link'] = $this->testDb;
    }

    protected function tearDown(): void
    {
        $this->testDb->clearPlayers();
        TestDatabase::reset();
    }

    #[Group('player-id')]
    public function testGetNextEntityIdForRealPlayer(): void
    {
        // Arrange: Create 3 real players
        $this->testDb->insertPlayer(['id' => 1, 'player_type' => 'real', 'display_id' => 1]);
        $this->testDb->insertPlayer(['id' => 2, 'player_type' => 'real', 'display_id' => 2]);
        $this->testDb->insertPlayer(['id' => 3, 'player_type' => 'real', 'display_id' => 3]);

        // Act
        $nextId = \getNextEntityId('real');

        // Assert
        $this->assertEquals(4, $nextId, 'Next real player should have ID 4');
    }

    #[Group('player-id')]
    public function testGetNextEntityIdForTutorialPlayer(): void
    {
        // Arrange: Create 2 tutorial players
        $this->testDb->insertPlayer(['id' => 10000000, 'player_type' => 'tutorial', 'display_id' => 1]);
        $this->testDb->insertPlayer(['id' => 10000001, 'player_type' => 'tutorial', 'display_id' => 2]);

        // Act
        $nextId = \getNextEntityId('tutorial');

        // Assert
        $this->assertEquals(10000002, $nextId, 'Next tutorial player should have ID 10000002');
        $this->assertGreaterThanOrEqual(ENTITY_ID_RANGES['tutorial']['start'], $nextId);
        $this->assertLessThanOrEqual(ENTITY_ID_RANGES['tutorial']['end'], $nextId);
    }

    #[Group('player-id')]
    public function testGetNextEntityIdForNpc(): void
    {
        // Arrange: Create 2 NPCs
        $this->testDb->insertPlayer(['id' => -1, 'player_type' => 'npc', 'display_id' => 1]);
        $this->testDb->insertPlayer(['id' => -2, 'player_type' => 'npc', 'display_id' => 2]);

        // Act
        $nextId = \getNextEntityId('npc');

        // Assert
        $this->assertEquals(-3, $nextId, 'Next NPC should have ID -3');
        $this->assertLessThan(0, $nextId, 'NPC IDs must be negative');
    }

    #[Group('player-id')]
    public function testGetNextEntityIdWhenTableEmpty(): void
    {
        // Act
        $nextRealId = \getNextEntityId('real');
        $nextTutorialId = \getNextEntityId('tutorial');
        $nextNpcId = \getNextEntityId('npc');

        // Assert
        $this->assertEquals(1, $nextRealId, 'First real player should have ID 1');
        $this->assertEquals(10000000, $nextTutorialId, 'First tutorial player should have ID 10000000');
        $this->assertEquals(-2, $nextNpcId, 'First NPC should have ID -2 (since -1 is default minimum)');
    }

    #[Group('player-id')]
    public function testGetNextDisplayIdForRealPlayer(): void
    {
        // Arrange: Create 3 real players
        $this->testDb->insertPlayer(['id' => 1, 'player_type' => 'real', 'display_id' => 1]);
        $this->testDb->insertPlayer(['id' => 2, 'player_type' => 'real', 'display_id' => 2]);
        $this->testDb->insertPlayer(['id' => 3, 'player_type' => 'real', 'display_id' => 3]);

        // Act
        $nextDisplayId = \getNextDisplayId('real');

        // Assert
        $this->assertEquals(4, $nextDisplayId, 'Next real player display ID should be 4');
    }

    #[Group('player-id')]
    public function testGetNextDisplayIdForTutorialPlayer(): void
    {
        // Arrange: Create 2 tutorial players with high IDs but low display IDs
        $this->testDb->insertPlayer(['id' => 10000000, 'player_type' => 'tutorial', 'display_id' => 1]);
        $this->testDb->insertPlayer(['id' => 10000001, 'player_type' => 'tutorial', 'display_id' => 2]);

        // Act
        $nextDisplayId = \getNextDisplayId('tutorial');

        // Assert
        $this->assertEquals(3, $nextDisplayId, 'Tutorial display IDs should be sequential (1, 2, 3...)');
    }

    #[Group('player-id')]
    public function testDisplayIdIsIndependentPerType(): void
    {
        // Arrange: Create players of different types all with display_id 1
        $this->testDb->insertPlayer(['id' => 1, 'player_type' => 'real', 'display_id' => 1]);
        $this->testDb->insertPlayer(['id' => 10000000, 'player_type' => 'tutorial', 'display_id' => 1]);
        $this->testDb->insertPlayer(['id' => -1, 'player_type' => 'npc', 'display_id' => 1]);

        // Act
        $nextRealDisplay = \getNextDisplayId('real');
        $nextTutorialDisplay = \getNextDisplayId('tutorial');
        $nextNpcDisplay = \getNextDisplayId('npc');

        // Assert
        $this->assertEquals(2, $nextRealDisplay, 'Each type maintains separate display ID sequence');
        $this->assertEquals(2, $nextTutorialDisplay, 'Each type maintains separate display ID sequence');
        $this->assertEquals(2, $nextNpcDisplay, 'Each type maintains separate display ID sequence');
    }

    #[Group('player-id')]
    public function testIdRangesDoNotOverlap(): void
    {
        // Arrange
        $ranges = ENTITY_ID_RANGES;

        // Assert: Real and tutorial ranges don't overlap
        $this->assertLessThan(
            $ranges['tutorial']['start'],
            $ranges['real']['end'],
            'Real player range should not overlap with tutorial range'
        );

        // Assert: Tutorial and building ranges don't overlap
        $this->assertLessThan(
            $ranges['building']['start'],
            $ranges['tutorial']['end'],
            'Tutorial range should not overlap with building range'
        );

        // Assert: NPCs are negative, others are positive
        $this->assertLessThan(0, $ranges['npc']['end']);
        $this->assertGreaterThan(0, $ranges['real']['start']);
    }

    #[Group('player-id')]
    public function testRealPlayersHaveSequentialIds(): void
    {
        // Simulate creating multiple real players
        for ($i = 1; $i <= 5; $i++) {
            $id = \getNextEntityId('real');
            $displayId = \getNextDisplayId('real');

            $this->testDb->insertPlayer([
                'id' => $id,
                'player_type' => 'real',
                'display_id' => $displayId,
                'name' => "Player $i"
            ]);

            // For real players, ID and display_id should match
            $this->assertEquals($i, $id, "Real player $i should have ID $i");
            $this->assertEquals($i, $displayId, "Real player $i should have display_id $i");
        }

        // Verify all players were created correctly
        $this->assertEquals(5, $this->testDb->getPlayerCount('real'));
    }

    #[Group('player-id')]
    public function testTutorialPlayersDoNotAffectRealPlayerSequence(): void
    {
        // Create real player 1
        $this->testDb->insertPlayer(['id' => 1, 'player_type' => 'real', 'display_id' => 1]);

        // Create tutorial players (should use different ID range)
        $tutorialId1 = \getNextEntityId('tutorial');
        $this->testDb->insertPlayer(['id' => $tutorialId1, 'player_type' => 'tutorial', 'display_id' => 1]);

        // Create real player 2 (should get ID 2, not be affected by tutorial player)
        $nextRealId = \getNextEntityId('real');
        $this->assertEquals(2, $nextRealId, 'Tutorial players should not create gaps in real player IDs');

        // Verify tutorial player has high ID
        $this->assertGreaterThanOrEqual(10000000, $tutorialId1, 'Tutorial player should have ID in tutorial range');
    }

    #[Group('player-id')]
    public function testInvalidEntityTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid entity type: invalid_type');

        \getNextEntityId('invalid_type');
    }

    #[Group('player-id')]
    public function testConcurrentPlayerCreation(): void
    {
        // Simulate concurrent creation of different player types
        // IMPORTANT: Insert each player immediately after getting its ID,
        // otherwise getNextEntityId() will return the same ID twice

        $ids = [];

        // Create real player 1
        $ids['real1'] = \getNextEntityId('real');
        $this->testDb->insertPlayer(['id' => $ids['real1'], 'player_type' => 'real', 'display_id' => 1]);

        // Create tutorial player 1
        $ids['tutorial1'] = \getNextEntityId('tutorial');
        $this->testDb->insertPlayer(['id' => $ids['tutorial1'], 'player_type' => 'tutorial', 'display_id' => 1]);

        // Create NPC 1
        $ids['npc1'] = \getNextEntityId('npc');
        $this->testDb->insertPlayer(['id' => $ids['npc1'], 'player_type' => 'npc', 'display_id' => 1]);

        // Create real player 2
        $ids['real2'] = \getNextEntityId('real');
        $this->testDb->insertPlayer(['id' => $ids['real2'], 'player_type' => 'real', 'display_id' => 2]);

        // Create tutorial player 2
        $ids['tutorial2'] = \getNextEntityId('tutorial');
        $this->testDb->insertPlayer(['id' => $ids['tutorial2'], 'player_type' => 'tutorial', 'display_id' => 2]);

        // Assert correct ID ranges
        $this->assertEquals(1, $ids['real1']);
        $this->assertEquals(2, $ids['real2']);
        $this->assertEquals(10000000, $ids['tutorial1']);
        $this->assertEquals(10000001, $ids['tutorial2']);
        $this->assertLessThan(0, $ids['npc1']);

        // Verify counts
        $this->assertEquals(2, $this->testDb->getPlayerCount('real'));
        $this->assertEquals(2, $this->testDb->getPlayerCount('tutorial'));
        $this->assertEquals(1, $this->testDb->getPlayerCount('npc'));
    }
}
