# Player ID System Tests

Unit tests for the Player ID Range System that ensures:
- Real players have sequential IDs (1, 2, 3...)
- Tutorial players use separate ID range (10,000,000+)
- NPCs use negative IDs
- Display IDs are sequential within each type

## Running the Tests

```bash
# Run all player tests
./vendor/bin/phpunit tests/Player --testdox

# Run specific test file
./vendor/bin/phpunit tests/Player/PlayerIdSystemTest.php --testdox

# Run tests with coverage
make coverage
```

## Test Coverage

The test suite covers:

### ID Generation Functions (`config/functions.php`)
- `getNextEntityId(string $type)` - Generate next ID in range
- `getNextDisplayId(string $type)` - Generate next display ID

### Test Cases

1. **testGetNextEntityIdForRealPlayer** - Real players get sequential IDs (1, 2, 3...)
2. **testGetNextEntityIdForTutorialPlayer** - Tutorial players get IDs in tutorial range (10M+)
3. **testGetNextEntityIdForNpc** - NPCs get negative IDs
4. **testGetNextEntityIdWhenTableEmpty** - First IDs are correct for empty database
5. **testGetNextDisplayIdForRealPlayer** - Display IDs are sequential for real players
6. **testGetNextDisplayIdForTutorialPlayer** - Tutorial players have separate display ID sequence
7. **testDisplayIdIsIndependentPerType** - Each type maintains separate display ID counter
8. **testIdRangesDoNotOverlap** - Verify ranges don't overlap
9. **testRealPlayersHaveSequentialIds** - Multiple real players created correctly
10. **testTutorialPlayersDoNotAffectRealPlayerSequence** - Tutorial players don't create gaps
11. **testInvalidEntityTypeThrowsException** - Invalid type is rejected
12. **testConcurrentPlayerCreation** - Mixed player type creation works correctly

## Mock Database

Uses SQLite in-memory database (`tests/Player/Mock/TestDatabase.php`) to:
- Simulate Doctrine DBAL Connection interface
- Provide fast, isolated test environment
- Reset state between tests

## Test Results

```
Player Id System (Tests\Player\PlayerIdSystem)
 ✔ Get next entity id for real player
 ✔ Get next entity id for tutorial player
 ✔ Get next entity id for npc
 ✔ Get next entity id when table empty
 ✔ Get next display id for real player
 ✔ Get next display id for tutorial player
 ✔ Display id is independent per type
 ✔ Id ranges do not overlap
 ✔ Real players have sequential ids
 ✔ Tutorial players do not affect real player sequence
 ✔ Invalid entity type throws exception
 ✔ Concurrent player creation

Tests: 12, Assertions: 44
```

## Related Files

- `config/constants.php` - ID range definitions
- `config/functions.php` - ID generation functions
- `Classes/Player.php` - Player creation using ID system
- `src/Tutorial/TutorialPlayer.php` - Tutorial player creation
- `src/Tutorial/TutorialResourceManager.php` - NPC enemy creation
