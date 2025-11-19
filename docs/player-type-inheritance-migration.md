# Player Type Inheritance System - Migration Documentation

**Date:** 2025-11-19
**Purpose:** Implement Single Table Inheritance for players to isolate tutorial players from real players

## Problem Statement

The tutorial system was creating temporary tutorial players as regular entries in the `players` table with positive IDs. This caused several critical issues:

1. **Tutorial players appeared in rankings and leaderboards** alongside real players
2. **Tutorial players appeared in faction member lists**
3. **Tutorial players could be selected as missive recipients**
4. **Tutorial players appeared on world map** player markers
5. **Tutorial players inflated faction power calculations**
6. **No clear way to distinguish** tutorial players from real players programmatically

## Solution: Single Table Inheritance (Discriminator Pattern)

Implemented the same pattern used by the Action system - Single Table Inheritance with a discriminator column.

### Architecture Overview

```
players table
├── player_type (discriminator: 'real', 'tutorial', 'npc')
├── tutorial_session_id (for tutorial players only)
├── real_player_id_ref (for tutorial players - link to real account)
└── [all existing player fields]

Doctrine Entities:
├── PlayerEntity (abstract base class)
│   ├── RealPlayer (player_type='real')
│   ├── TutorialPlayerEntity (player_type='tutorial')
│   └── NPCEntity (player_type='npc')
```

### Database Schema Changes

```sql
-- Add discriminator columns
ALTER TABLE players
ADD COLUMN player_type VARCHAR(20) NOT NULL DEFAULT 'real' AFTER id,
ADD COLUMN tutorial_session_id VARCHAR(36) NULL AFTER player_type,
ADD COLUMN real_player_id_ref INT(11) NULL AFTER tutorial_session_id,
ADD INDEX idx_player_type (player_type),
ADD INDEX idx_tutorial_session (tutorial_session_id);

-- Mark existing players
UPDATE players SET player_type = 'npc' WHERE id < 0;
UPDATE players p
INNER JOIN tutorial_players tp ON p.id = tp.player_id
SET p.player_type = 'tutorial',
    p.tutorial_session_id = tp.tutorial_session_id,
    p.real_player_id_ref = tp.real_player_id
WHERE tp.player_id IS NOT NULL;
-- All others remain 'real' (default)
```

## Doctrine Entities Created

### 1. PlayerEntity (Base Class)
**File:** `src/Entity/PlayerEntity.php`

Abstract base class containing all common player fields (name, race, xp, coords, etc.).

```php
#[ORM\Entity]
#[ORM\Table(name: "players")]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'player_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'real' => RealPlayer::class,
    'tutorial' => TutorialPlayerEntity::class,
    'npc' => NPCEntity::class
])]
abstract class PlayerEntity { ... }
```

### 2. RealPlayer
**File:** `src/Entity/RealPlayer.php`

Represents actual game players (player_type='real'). These are the permanent player accounts that:
- Appear in rankings and leaderboards
- Participate in the real game world
- Have persistent progress and items

### 3. TutorialPlayerEntity
**File:** `src/Entity/TutorialPlayerEntity.php`

Represents temporary tutorial characters (player_type='tutorial'). These:
- Exist only during tutorial sessions
- Are isolated from real players
- Have their own isolated map instances
- Transfer rewards (XP, PI) to real player on completion
- Get deleted when tutorial completes

**Additional fields:**
- `tutorialSessionId` - UUID linking to tutorial session
- `realPlayerIdRef` - ID of the real player account

### 4. NPCEntity
**File:** `src/Entity/NPCEntity.php`

Represents non-player characters (player_type='npc'). NPCs have:
- Negative IDs (traditionally)
- Special behaviors and loot tables
- Tutorial enemies use ID range -100,000+

## Legacy Player Class Updates

Updated `Classes/Player.php` to support player type detection:

### New Methods Added

```php
// Check if this is a real player (not tutorial, not NPC)
public function isRealPlayer(): bool

// Check if this is a tutorial player (temporary character)
public function isTutorialPlayer(): bool

// Check if this is an NPC (non-player character)
public function isNPC(): bool

// Check if player should appear in public lists
public function isPubliclyVisible(): bool

// Get player type ('real', 'tutorial', 'npc')
public function getPlayerType(): string
```

## Query Modifications

All queries that retrieve player lists have been updated to filter by `player_type='real'`.

### 1. Master Query - Player::refresh_list()
**File:** `Classes/Player.php:2264`

**Before:**
```php
$sql = 'SELECT id,name,race,xp,rank,pr,faction,secretFaction,lastLoginTime
        FROM players ORDER BY name';
```

**After:**
```php
$sql = 'SELECT id,name,race,xp,rank,pr,faction,secretFaction,lastLoginTime
        FROM players WHERE player_type = "real" ORDER BY name';
```

**Impact:** Fixes ALL ranking views (general, bourrins, fortunes, reputation), password reset, new turn view, deck generation

### 2. Faction Member Lists
**File:** `faction.php:45, 61`

**Secret factions (line 45):**
```php
WHERE nextTurnTime > ? AND secretFaction = ? AND player_type = "real"
```

**Public factions (line 61):**
```php
WHERE nextTurnTime > ? AND faction = ? AND player_type = "real"
```

### 3. Missive Faction-Wide
**File:** `src/View/Forum/MissiveView.php:28`

```php
WHERE (faction = ? OR secretFaction = ?) AND player_type = "real"
```

### 4. Player Search Autocomplete
**File:** `src/Service/PlayerService.php:80`

```php
WHERE players.name LIKE ?
AND players_options.player_id IS NULL
AND players.player_type = "real"
```

### 5. Player Lookup by Name
**File:** `Classes/Player.php:2197`

```php
SELECT id FROM players WHERE name = ? AND player_type = "real"
```

**Used by:**
- Exchange creation (`api/exchanges/exchanges-create.php`)
- Missive creation (`src/View/Forum/MissiveView.php`)
- Console commands

### 6. World Map Player Markers
**File:** `src/Service/ViewService.php:707`

```php
WHERE c.plan = ?
AND p.id != ?
AND po.player_id IS NULL
AND p.player_type = 'real'
```

### 7. Faction Power Map
**File:** `scripts/map/local.php:125`

```php
WHERE c.plan = ?
AND p.player_type = "real"
AND p.id NOT IN (SELECT player_id FROM players_options WHERE name="incognitoMode")
```

## Tutorial System Updates

### TutorialPlayer Class
**File:** `src/Tutorial/TutorialPlayer.php:90-93`

Now sets discriminator fields when creating tutorial characters:

```php
$conn->insert('players', [
    'player_type' => 'tutorial',           // ← Discriminator
    'tutorial_session_id' => $tutorialSessionId,  // ← Session link
    'real_player_id_ref' => $realPlayerId,        // ← Real player link
    'name' => $name,
    // ... rest of fields
]);
```

### TutorialManager
**File:** `src/Tutorial/TutorialManager.php:672`

Tutorial enemies also marked with discriminator:

```php
INSERT INTO players (id, player_type, name, ...)
VALUES (?, 'npc', ?, ...)  // ← Negative ID + 'npc' discriminator
```

## Benefits of This Approach

### ✅ Advantages

1. **Complete isolation** - Tutorial players never appear in real player lists
2. **Type safety** - Can query by entity type: `$em->getRepository(RealPlayer::class)->findAll()`
3. **No schema duplication** - Single table, Doctrine handles mapping
4. **Follows existing patterns** - Same architecture as Action system
5. **Backward compatible** - Legacy `Classes\Player` still works
6. **Clear semantics** - `player_type` column makes intent obvious
7. **Easy filtering** - Simple WHERE clause: `player_type = 'real'`
8. **Future extensibility** - Easy to add new player types (e.g., 'bot', 'guest')

### 🔧 Migration Path

**Phase 1 (COMPLETED):**
- ✅ Add discriminator columns to players table
- ✅ Mark existing players with correct types
- ✅ Create Doctrine entities (PlayerEntity, RealPlayer, TutorialPlayerEntity, NPCEntity)
- ✅ Update TutorialPlayer creation to set discriminator
- ✅ Add player type detection methods to Classes\Player
- ✅ Update all player list queries

**Phase 2 (FUTURE):**
- Gradually migrate `Classes\Player` to use Doctrine entities
- Refactor player-related services to use entity repositories
- Add type hints: `function processPlayer(RealPlayer $player)`
- Create migration script to clean up old tutorial players

## Testing Checklist

Before deploying, verify:

- [ ] Tutorial players don't appear in rankings (classements.php)
- [ ] Tutorial players don't appear in faction member lists (faction.php)
- [ ] Tutorial players can't be selected as missive recipients
- [ ] Tutorial players don't appear on world map markers
- [ ] Tutorial players don't inflate faction power calculations
- [ ] Real players can still do everything as before
- [ ] Tutorial flow still works (start, complete, rewards transfer)
- [ ] Player search autocomplete only suggests real players
- [ ] Exchange creation can't target tutorial players

## Database Statistics (After Migration)

```sql
SELECT player_type, COUNT(*) as count FROM players GROUP BY player_type;
```

Results:
- `real`: 239 players
- `npc`: 239 NPCs
- `tutorial`: 180 tutorial players (orphaned from previous tests)

## Maintenance Notes

### When Adding New Player Lists

Always filter by `player_type`:

```php
// ✅ CORRECT
SELECT * FROM players WHERE player_type = 'real' AND ...

// ✅ CORRECT (Doctrine)
$realPlayers = $em->getRepository(RealPlayer::class)->findAll();

// ❌ WRONG - will include tutorial players
SELECT * FROM players WHERE id > 0 AND ...
```

### When Checking Player Type

Use the new methods in `Classes\Player`:

```php
// ✅ CORRECT
if ($player->isRealPlayer()) {
    // Add to leaderboard
}

// ❌ WRONG - doesn't account for tutorial players
if ($player->id > 0) {
    // This will include tutorial players!
}
```

## Related Files

### Entities
- `src/Entity/PlayerEntity.php` - Base class
- `src/Entity/RealPlayer.php` - Real players
- `src/Entity/TutorialPlayerEntity.php` - Tutorial players
- `src/Entity/NPCEntity.php` - NPCs

### Modified Core Files
- `Classes/Player.php` - Added type detection methods
- `src/Tutorial/TutorialPlayer.php` - Sets discriminator on creation
- `src/Tutorial/TutorialManager.php` - Sets discriminator for enemies

### Modified Query Files
- `Classes/Player.php` - refresh_list(), get_player_by_name()
- `faction.php` - Faction member lists
- `src/View/Forum/MissiveView.php` - Missive recipients
- `src/Service/PlayerService.php` - Player search
- `src/Service/ViewService.php` - World map markers
- `scripts/map/local.php` - Faction power calculation

## Migration Script (For Future Use)

If you need to clean up old tutorial players:

```php
// Delete orphaned tutorial players (older than 24 hours)
DELETE p, tp
FROM players p
INNER JOIN tutorial_players tp ON p.id = tp.player_id
WHERE p.player_type = 'tutorial'
AND tp.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
AND tp.is_active = 0;
```

## Rollback Plan (Emergency)

If something goes wrong:

```sql
-- Remove discriminator constraint (restore old behavior)
UPDATE players SET player_type = 'real' WHERE id > 0;
UPDATE players SET player_type = 'npc' WHERE id < 0;

-- Revert query changes (remove AND player_type = 'real' clauses)
-- Use git to revert modified files
```

---

**End of Migration Documentation**
