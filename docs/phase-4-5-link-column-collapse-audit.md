# Phase 4.5 — Collapse the dual real↔tutorial link columns

**Status**: audit (no code change).
**Authored**: 2026-04-19, on `tutorial-refactoring` after Phase 4.4 landed.
**Supersedes (partially)**: the "Link between real and tutorial player" review
finding surfaced on !390's description. This doc expands that one-paragraph note
into a collapse plan.

## Context

Two DB columns carry the same information — the real player account that owns
a tutorial player row:

1. `tutorial_players.real_player_id` — the original link, NOT NULL, indexed,
   with an `ON DELETE CASCADE` FK to `players.id`. Predates the entity layer.
2. `players.real_player_id_ref` — added in Phase 3.1 (!383) so Doctrine's
   `TutorialPlayer` entity could map it. Nullable, unindexed, no FK.

Since Phase 4.4's factory hotfix (commit `2851395`), both are written together
on every tutorial-player creation. Neither is read for comparison. One should go.

The entity-mapped column (`players.real_player_id_ref`) is the canonical
candidate to win: it lives next to the entity's own row, is already what
`TutorialPlayer::getRealPlayerIdRef()` reads, and is what the Phase 4
entity-facing methods depend on.

## 1. Schema reality

### `tutorial_players.real_player_id`

From `db/init_noupdates.sql` (`CREATE TABLE tutorial_players`):

```sql
`real_player_id` int(11) NOT NULL COMMENT 'Link to actual player account',
...
KEY `idx_real_player` (`real_player_id`),
...
CONSTRAINT `tutorial_players_ibfk_1`
    FOREIGN KEY (`real_player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
```

- `INT(11) NOT NULL`
- Index: `idx_real_player` (non-unique)
- FK: `tutorial_players_ibfk_1` → `players(id)` `ON DELETE CASCADE`

### `players.real_player_id_ref`

From `db/init_noupdates.sql` (`CREATE TABLE players`):

```sql
`real_player_id_ref` int(11) DEFAULT NULL
    COMMENT 'Real player ID reference (for tutorial players)',
```

- `INT(11)` nullable, no default other than NULL
- No index, no FK
- Mapped on `App\Entity\TutorialPlayer::$realPlayerIdRef` via
  `#[ORM\Column(name: "real_player_id_ref")]`
- Idempotent migration
  `src/Migrations/Version20260419180000_AddTutorialColumnsToPlayers.php`
  provisions it on older dev DBs

## 2. Writers

**Sole writer: `src/Tutorial/TutorialPlayerFactory::create()`.**

Two INSERTs in the same transaction, both receive the same `$realPlayerId`:

```php
// INSERT into players (src/Tutorial/TutorialPlayerFactory.php ~L97)
$conn->insert('players', [
    'id' => $actualPlayerId,
    'player_type' => 'tutorial',
    // ...
    'tutorial_session_id' => $tutorialSessionId,
    'real_player_id_ref' => $realPlayerId,      // column 2
]);

// INSERT into tutorial_players (~L140)
$conn->insert('tutorial_players', [
    'real_player_id' => $realPlayerId,           // column 1
    'tutorial_session_id' => $tutorialSessionId,
    'player_id' => $actualPlayerId,
    'name' => $name,
    'is_active' => true,
]);
```

Neither column is ever UPDATE'd afterwards. No other code path (grep
confirmed: no `insert.*real_player_id`, no `UPDATE tutorial_players`
writing either column, no scripts, no fixtures) writes to these columns.

## 3. Readers

Five raw-SQL readers hit `tutorial_players.real_player_id`; one entity
reader hits `players.real_player_id_ref`.

### Readers of `tutorial_players.real_player_id`

| File | Purpose | Rewrite shape |
|------|---------|---------------|
| `api/tutorial/exit_tutorial_mode.php` ~L38–45 | Find real player to restore `$_SESSION['playerId']` on exit | JOIN `players p ON p.id = tp.player_id`, read `p.real_player_id_ref` |
| `api/tutorial/check_tutorial_character.php` | Return link in JSON response | Same JOIN |
| `src/Tutorial/TutorialResourceManager.php` ~L228–234, ~L265 | Find previous-session tutorial players for cleanup | Same JOIN |
| `src/Tutorial/TutorialPlayerCleanup.php` ~L231 | Orphan cleanup scan | Same JOIN |
| `api/tutorial/complete.php` (indirect, via resource manager) | Batch deactivate | Inherited from resource manager fix |

All five exist purely to follow the link. None do anything else with the
column. All can survive a JOIN through `players`.

### Reader of `players.real_player_id_ref`

- `src/Entity/TutorialPlayer::transferRewardsToRealPlayer()` (L108–130),
  called from `src/Tutorial/TutorialManager::completeTutorial()` at L478.
  Reads via `$this->realPlayerIdRef`. **No change needed post-collapse** —
  this is the path that wins.

## 4. Tests touching either column

- `tests/Tutorial/TutorialPlayerRewardTransferTest.php` — `realPlayerIdRef`
  path. Unaffected.
- `tests/Tutorial/TutorialPlayerCleanupIntegrationTest.php` — seeds
  `tutorial_players.real_player_id`. **Must update seed helper** to stop
  writing the dropped column.
- `tests/Tutorial/TutorialManagerCompletionFlowTest.php` — completion path
  via entity getter. Unaffected.
- `tests/Tutorial/TutorialResourceManagerEntityAdaptersTest.php` — exercises
  factory + cleanup. **Seed helper update** (factory change covers it if
  test uses factory; direct INSERT seeds need editing).

No test asserts a value match between the two columns. No test exposes a
mismatch today.

## 5. Collapse plan

### Winner
`players.real_player_id_ref` — already Doctrine-mapped, already the path the
entity method uses, already NULL-safe-guarded in
`transferRewardsToRealPlayer`.

### MR shape (single MR, no feature flag)

1. **Pre-migration audit query** (run on staging before deploying):

   ```sql
   SELECT COUNT(*) AS mismatches
   FROM tutorial_players tp
   LEFT JOIN players p ON p.id = tp.player_id
   WHERE p.player_type = 'tutorial'
     AND (p.real_player_id_ref IS NULL
          OR p.real_player_id_ref <> tp.real_player_id);
   ```
   Expected: `0`. Non-zero means pre-4.4 rows exist whose `real_player_id_ref`
   was never populated — backfill before dropping the column.

2. **Backfill (if audit returns rows)**:

   ```sql
   UPDATE players p
   JOIN tutorial_players tp ON tp.player_id = p.id
   SET p.real_player_id_ref = tp.real_player_id
   WHERE p.player_type = 'tutorial' AND p.real_player_id_ref IS NULL;
   ```

3. **Readers** — rewrite the five sites above. Representative diff:

   ```php
   // before
   $sql = 'SELECT id, player_id FROM tutorial_players
           WHERE real_player_id = ? AND is_active = 1 AND deleted_at IS NULL';

   // after
   $sql = 'SELECT tp.id, tp.player_id
           FROM tutorial_players tp
           JOIN players p ON p.id = tp.player_id
           WHERE p.real_player_id_ref = ?
             AND tp.is_active = 1 AND tp.deleted_at IS NULL';
   ```

4. **Writer** — drop the `'real_player_id' => $realPlayerId` key from the
   second INSERT in `TutorialPlayerFactory::create()`.

5. **Migration** — new
   `src/Migrations/Version20260419200000_DropTutorialPlayersRealPlayerIdColumn.php`:

   ```php
   public function up(Schema $schema): void
   {
       $this->addSql('ALTER TABLE tutorial_players DROP FOREIGN KEY tutorial_players_ibfk_1');
       $this->addSql('ALTER TABLE tutorial_players DROP INDEX idx_real_player');
       $this->addSql('ALTER TABLE tutorial_players DROP COLUMN real_player_id');
   }

   public function down(Schema $schema): void
   {
       $this->addSql("
           ALTER TABLE tutorial_players
           ADD COLUMN real_player_id INT(11) NOT NULL
               COMMENT 'Link to actual player account' AFTER id
       ");
       $this->addSql('
           UPDATE tutorial_players tp
           JOIN players p ON p.id = tp.player_id
           SET tp.real_player_id = p.real_player_id_ref
           WHERE p.player_type = "tutorial"
       ');
       $this->addSql('ALTER TABLE tutorial_players ADD INDEX idx_real_player (real_player_id)');
       $this->addSql('
           ALTER TABLE tutorial_players
           ADD CONSTRAINT tutorial_players_ibfk_1
               FOREIGN KEY (real_player_id) REFERENCES players (id) ON DELETE CASCADE
       ');
   }
   ```

6. **Schema init file** — update `db/init_noupdates.sql` (the snapshot used
   by `reset_test_database.sh`) so fresh test databases match. Drop the
   column/index/FK from the `tutorial_players` CREATE TABLE.

7. **Tests** — update seed helpers in
   `TutorialPlayerCleanupIntegrationTest` and any sibling that directly
   inserts into `tutorial_players` to stop providing the dropped column.
   Re-run the Cypress `tutorial-production-ready` spec end-to-end (the
   cleanup path is the one most likely to catch a regression).

## 6. Risks

- **Legacy NULL `real_player_id_ref` on pre-4.4 rows.** The
  pre-migration audit query above is the gate. If the target DB has any
  tutorial `players` rows created before commit `2851395`, backfill is
  mandatory. On `tutorial-refactoring`'s test DB this won't trigger; on
  staging/prod it might.

- **FK cascade on real-player delete is lost.** Today, deleting a row from
  `players` (real account) cascades into `tutorial_players` via the
  `ibfk_1` FK. Post-collapse there is no FK from `tutorial_players` back
  to the real player — the link goes through the tutorial `players` row,
  which has no FK of its own on `real_player_id_ref`. Real-player deletion
  would leave orphaned `tutorial_players` rows AND orphaned tutorial
  `players` rows. Today we handle this via `TutorialPlayerCleanup`
  (periodic orphan scan); collapse does not make it worse in practice but
  does remove one guardrail. **Mitigation**: add an explicit FK on
  `players.real_player_id_ref → players.id ON DELETE SET NULL`
  as part of the same migration. `SET NULL` (not `CASCADE`) because
  cascading would delete the tutorial player's own row — orphan scan can
  take it from there.

- **JOIN cost on cleanup scans.** The readers all move from a direct
  indexed column lookup on `tutorial_players` to a JOIN via
  `tutorial_players.player_id → players.id` (PK). `idx_tutorial_player` on
  `tutorial_players.player_id` already exists per the init SQL, so the
  JOIN is two index lookups. Not a concern at current scale; noted for
  completeness.

- **Migration ordering on shared dev DB.** The umbrella MR !390 is already
  pending a migration run (!394). This adds a second one. Batch both on
  the same deploy window.

## 7. Sizing

- ~25 LOC migration
- ~30 LOC across 5 reader rewrites
- ~5 LOC factory writer cleanup
- ~15 LOC test seed-helper edits
- ~30 LOC tests pinning the new JOIN shape (cleanup integration test is a
  natural fit)

**Total**: ~100 LOC net, 1 MR, 1 migration, no feature flag. Sits inside
the "Phase 4.5 mini-phase" scope described on !390.

## 8. Recommended next step

Do not ship until the pre-migration audit query returns `0` on staging.
Once confirmed, the MR can be a single commit because the migration +
reader rewrites + factory cleanup + test seed edits are all coupled: any
one shipped without the others breaks prod.
