# Handoff — playable buildings, from L2 on

**For**: whoever picks up the playable-buildings track (2026-08-02)
**Read first**: [design-playable-buildings.md](design-playable-buildings.md) — the decisions.
This note is the working context: what is done, what to do next, and what must not be
touched on the way.

---

## 1. Where the track stands

| step | state |
|---|---|
| **L0** — credentials leave `players` | ✅ `accounts` table, `AccountService`, mirrored writes, join in `Player::get_row()` |
| **L1** — capabilities named | ✅ `TakesTurnsInterface`, `ProgressesInterface`, implemented by `Character` only |
| **L2** — turn and progression satellites | ⬜ **next**, described below |
| **L3** — turn rule freed of the session | ✅ `isDue()` / `processDue()`; `processIfDue()` keeps the session gates |
| **L4** — the faction screen drives one building type | ⬜ needs the screen, which does not exist |
| **L5** — branch gates become capability gates | ⬜ after L2/L4 |

---

## 2. L2 — the scope

Move to satellites what `Character` holds and a playable building will need:

| satellite | columns taken from `players` |
|---|---|
| `turns` | `nextTurnTime`, `lastActionTime`, `nextTurnRescheduled`, `antiBerserkTime` |
| `progression` | `xp`, `rank`, `bonus_points`, `pi` |

**`pi` comes along.** `Player::put_xp()` mints it in the same statement as experience, capped
at the season's XP ceiling, and characteristic upgrades spend it. Gold is an item; PI is the
currency progression itself produces. Whether a *building* spends PI on its own stats is a
game-design question for the evolution work, not a reason to file the column elsewhere.

**Take the chance to make the debit atomic.** `scripts/upgrades/carac.php` guards with
`WHERE pi >= ?` but never checks affected rows: two concurrent requests both pass the PHP
check, one `UPDATE` matches nothing, and the caller proceeds as if it had paid. PHP's
session-file lock hides it today, and `session_write_close()` — which the tutorial APIs
already call — unhides it. Moving PI is the moment to debit in one conditional statement and
read the affected-row count.

### Copy the L0 shape — it is what kept that step small

1. **Migration**: create the satellite, backfill from the `players` columns, **keep the
   columns**. Idempotent (`INSERT IGNORE`, `CREATE TABLE IF NOT EXISTS`), FK
   `ON DELETE CASCADE`, characters only.
2. **One service per satellite**, the single gateway for writes. Each write **also updates
   the legacy column** — code still reads it. When the columns drop, the mirror drops with
   them and one statement per method remains.
3. **Reads keep working through the join in `Player::get_row()`.** That is the trick: some
   120 call sites read `$player->data->xp`, `->nextTurnTime` and friends, and none of them
   moved for L0. Add the two joins there.
4. **`NULLIF`, not plain `COALESCE`.** The backfill gives every character a row, so an
   untouched satellite value is `''`/`0`, which would beat a `players` column a path not yet
   routed through the service has just written. Empty means *nothing written here*.
5. Column drop is a **post-deployment** pass, never in the same MR.

### Where the writes are

Use `mcp__serena__replace_in_files` in **dry-run** to inventory — it returns the whole
project unshredded, unlike `grep` through the rtk proxy. `TurnProcessingService::process()`
is the main writer of turn columns (one `UPDATE players SET nextTurnTime, …`);
`Player::put_xp()` writes `xp`, `pi` and `rank` together, and `seasoncmd.php` writes
`bonus_points`.

---

## 3. What must NOT be cleaned up on the way

Three traps, all found the hard way in one session:

- **`players.bonus_points`** — written by the season command (XP above the 3500 cap), read by
  nothing, kept on purpose. It belongs to `progression`, not to the account.
- **`buildings.build_state` = `construction` and `ruin`** — written by nobody today, and
  deliberately kept: they are the display-and-closure half of the building-evolution work
  (§3.5 of the design note).
- **The `unique` id range (30–39 M)** and the `unique_objects` cascade deletes in
  `PlanAdminService` / `TutorialMapInstance` — the table still exists; those lines go with it
  at the post-deployment pass, not before.

The rule that separates these from a real ghost: **not "what writes it" but "what is *meant*
to write it".** A guard on a state nothing can produce is dead (`plan == 'limbes'` was, and
went). Data accumulating for a future use is not.

---

## 4. Working conventions that cost time to rediscover

**Environment.** Everything runs in the `PHP-AOO4-Local` container:
`docker exec PHP-AOO4-Local bash -lc 'cd /var/www/html && make phpstan'` and `… vendor/bin/phpunit`.
The devcontainer has no MariaDB client — use the `aoo-engine-mariadb-aoo4-1` container for SQL.

**Three databases**, and only the first gets migrations:

| db | used by | rebuild |
|---|---|---|
| `aoo4` | dev, and the source of the other two | `vendor/bin/doctrine-migrations migrate --no-interaction --allow-no-migration --no-all-or-nothing` |
| `aoo4_phpunit` | the PHPUnit suite | `docker exec -i -e DB_HOST=127.0.0.1 aoo-engine-mariadb-aoo4-1 bash -s < scripts/testing/reset_phpunit_database.sh` |
| `aoo4_test` | the tutorial cases | clone of `aoo4`; a stale one fails on any new table |

Rebuild `aoo4_phpunit` after every migration, or the suite tests yesterday's schema. CI has no
such trap: it clones both from a migrated `aoo4`.

**Migrations**: number *after* everything already merged (an earlier number that is already
recorded as executed will never run), target rows by **name** not id, keep them idempotent and
backward-compatible.

**Comments**: English, present tense, describing the code — never the defect being fixed or
what the code used to be. That story goes in the commit message, which stays French. Check the
diff before committing; this rule was broken four times in one session.

**Git**: Conventional Commits in French, no AI attribution of any kind. Never commit without
being asked. MRs open with `glab mr create --target-branch integration/hud-redesign`, merge
fast-forward with `glab mr merge <n> --yes --remove-source-branch` **after the pipeline is
green**, then delete the local branch.

---

## 5. Post-deployment queue (unrelated to L2, do not start early)

Waiting on the season deploy: dropping `unique_objects`, `map_resources`, the `map_walls`
view, `players_items_instances` (6 rows to fold into the containment relation first), and the
`players` credential columns now mirrored by `accounts` — each drop taking its mirror or its
cascade delete with it.
