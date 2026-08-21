---
paths:
  - "Classes/Player.php"
  - "src/Action/**"
  - "src/Service/**"
  - "src/Entity/**"
  - "go.php"
  - "observe.php"
  - "action.php"
  - "api/map/**"
  - "api/player/**"
  - "admin/**"
  - "src/Migrations/**"
---

# Game mechanics and data model

## Core Gameplay Loop

Age of Olympia is a **turn-based survival RPG** where players:
1. Perform actions (move, search, attack, gather resources)
2. Actions consume **PA (Points d'Action)** or **MVT (Mouvement points)**
3. Wait for **DLA (Délai Avant Action)** - cooldown timer between turns
4. Progress through leveling, combat, and resource gathering

## Turn System

**Key Concepts**:
- **Turn**: A player's turn data is stored in `players.turn` (JSON field)
- **PA (Points d'Action)**: Action points for most activities
- **MVT (Mouvement)**: Movement points specifically for moving on the map
- **DLA (Délai Avant Action)**: Cooldown time until next turn (in seconds)
- **nextTurnTime**: Unix timestamp when next turn is available

**Turn Refresh**:
- Players get new PA/MVT when `nextTurnTime` <= current time
- Turn data is loaded via `$player->get_caracs()` which populates `$player->turn`
- Remaining points checked via `$player->getRemaining('pa')` or `$player->getRemaining('mvt')`

## Map System

**Coordinate System**:
- Grid-based map with (x, y, z) coordinates
- **Plans**: Different map layers (e.g., 'gaia', 'tutorial')
- Coordinates stored in `coords` table with unique entries per location
- Players reference `coords_id` in `players` table

**Map Elements**:
- **Entities** (`entities` + satellites): everything standing on a cell — structures, resources, scenery, buildings, unique objects. One row per object, typed by discriminator, placed through `entity_cells`
- **Items** (`map_items`): Ground items players can pick up
- **Foregrounds** (`map_foregrounds`): Visual decorations (non-interactive)
- `map_resources` (ex-`map_walls`) is empty since `Version20260801100000`; the compat view is kept only until the deployment that follows its removal

**Resource Gathering**:
- Resources are **entities** (`App\Entity\Resource extends Structure`, discriminator `resource`), NOT items and no longer walls
- Trees, stones and the like block the step and can be hit, like the structures they came from
- What a resource *yields* belongs to the (plan, type) pair in `race_harvest`, read through `HarvestCatalogService::yieldsFor()`; the plan's biome list (`plans.biomes`) is only the seed, replayable from admin → Cartes → Rendements
- What a resource *is* right now — standing or exhausted — lives in its own `resources` satellite: an exhausted resource stays on the board and regrows in place
- Players must move **adjacent** to the resource, then use the `fouiller` action
- Gathered materials (wood, stone) go to player inventory as items

## Actions System

**Action Types**:
- **Movement**: `se_deplacer` (costs MVT points)
- **Search**: `fouiller` (gather resources from adjacent tiles)
- **Combat**: `attaquer`, `attaque_double`
- **Rest**: `repos` (restore PA/MVT)
- **Training**: `entrainement` (gain XP)
- **Prayer**: `prier` (faction-specific benefits)

**Action Storage**:
- Available actions: `players_actions` table (player_id, name, type, charges)
- Action definitions: `src/Action/*Action.php` (Doctrine entities)
- Options: `players_options` table (player_id, name)

**Important Options**:
- `showActionDetails`: Shows calculation details and dice rolls (now DEFAULT for new players)
- `isAdmin`: Administrator privileges
- `incognitoMode`: PNJ invisibility mode
- `raceHint`: Show race color borders

## Player System

**Player Creation**:
- Main function: `Player::put_player($name, $race, $pnj=false)` in `Classes/Player.php:2021`
- Creates entry in `players` table
- Initializes at coordinates (0,0) on plan 'gaia'
- Default options applied:
  - First player (ID 1) gets `isAdmin`
  - All new players get `showActionDetails` (as of recent change)
  - PNJs get `incognitoMode`

**Player Data**:
- Core data: `$player->data` (loaded via `$player->get_data()`)
- Turn data: `$player->turn` (loaded via `$player->get_caracs()`)
- Coordinates: `$player->coords` (loaded via `$player->getCoords()`)
- Inventory: `$player->inventory` (loaded via `$player->get_inventory()`)

**Player Types**:
- **Regular Players**: Positive IDs (1, 2, 3, ...)
- **NPCs/PNJs**: Negative IDs (-1, -2, -3, ...)
- **Tutorial Players**: Positive IDs, tracked in `tutorial_players` table

## Important Database Tables

**Players**:
- `players`: Main player data (id, name, race, coords_id, xp, pi, nextTurnTime, turn JSON)
- `players_actions`: Player available actions (player_id, name, type, charges)
- `players_options`: Player preferences (player_id, name)
- `players_items`: Player inventory (player_id, item_id, quantity)
- `players_logs`: Event logs (player_id, target_id, message, timestamp)

**Map**:
- `plans` + `plan_z_levels`: per-plan configuration (name, season — NULL = every season, player_visibility, bounds, bg, z levels…), ex `datas/private/plans/*.json` — single read gateway `plans()->read($slug)` (`App\Service\PlanService`), season-filtered lists via `plans()->forSeason()` defaulting to the game's current season (`SeasonService`), writes via `PlanConfigService`
- `coords`: Coordinate entries (id, x, y, z, plan)
- `entities` + `entity_cells`: every object on the board (structures, resources, scenery…) and the cells it occupies
- `resources`: per-resource state satellite (standing / exhausted, regrow)
- `race_harvest`: yields per (plan, resource type)
- `map_items`: Ground items (coords_id, item_id, quantity)
- `map_foregrounds`: Decorative foregrounds (coords_id, name)


## Key Constants (`config/constants.php`)

**Resources**:
- Defined in the **database**: entity types carry the pv and the nature, `race_harvest` carries the yields, the `resources` satellite carries the state
- Edited via the admin pages (Cartes → Rendements, types d'entités) — the `WALLS_PV` constant is gone

**Races**:
- Defined in the **database** (`races` + `race_starter_actions` + `race_spells` tables), edited via the admin page `admin/races.php`
- Single gateway: `App\Service\RaceService` — `getRaceData($name)` (JSON-shaped read model), `getPlayableRaceNames()` (ex-`RACES` constant), `getAllRaceNames()` (ex-`RACES_EXT`), `getRaceColor()`, `getRaceMaxMvt()`
- `races.name` is the lowercase code stored in `players.race` ('nain', 'hs'…); `races.label` is the display name ('Nain')
- Each race has faction, starting stats (16 CARACS columns), colors, starter actions and learnable spells
- Migrated from `datas/*/races/*.json` by `Version20260710120000_RacesFromJson` (the JSON files are no longer read)

## Frontend Map System

**Map Rendering**:
- Map tiles rendered as `.case` elements with `data-coords` attribute
- Example: `<div class="case" data-coords="0,1" x="0" y="1">`
- Click on tile opens observation panel via `observe.php`
- Player avatar shown as SVG element within tile

**UI Panels**:
- **Actions Panel** (`#ui-card`): Shows available actions for selected tile
- **Characteristics Panel**: Shows player stats
- Panels loaded via AJAX to `observe.php` with coords parameter

**AJAX Flow**:
1. Click map tile → `observe.php?coords=x,y`
2. Server renders observation data (actions, player stats)
3. Response injected into `#ajax-data` div
4. Tutorial system waits for panel visibility before showing tooltips
