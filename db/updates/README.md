# `db/updates/` — DEPRECATED, awaiting archive

**Status: deprecated as of 2026-04-19 (MR consolidating init_noupdates.sql).**

Every SQL file in this directory has been folded into
`db/init_noupdates.sql`. Loading that single dump produces the full
schema + reference data + applied-Doctrine-migration markers — there
is no longer any reason to apply files from this directory by hand or
in CI.

## Going forward

**All schema and data changes go through Doctrine migrations**
(`src/Migrations/Version*.php`), full stop. The dual-mechanism era
(raw SQL + Doctrine) is over. Doctrine migrations are auto-applied by
`scripts/deploy_sql.sh` in production and by `cypress_tutorial_job`
in CI; nothing in this directory is.

If you need to add a one-shot data fix that is not naturally a schema
migration, do it in a regular Doctrine migration with a clear
`getDescription()` and an explicit `down()` that documents that the
fix is not reversible. Do **not** add a new file here.

## Why this directory still exists

Git history. The files document how the schema evolved before the
Doctrine-only era, and several were referenced by ad-hoc scripts and
older docs. Removing them now would break those references and force
a rebase storm across in-flight branches.

The directory will be moved to `db/updates_archive/` (or deleted
outright) once the in-flight work has merged. Tracked as:

> **Issue: archive `db/updates/` once dust settles** — see GitLab issue
> linked from this MR's description.

When that move happens, this README will move with it; nothing in
this directory is meant to be applied to a database again.

## What the consolidation actually did

Starting from the previous `init_noupdates.sql` (a phpMyAdmin dump from
2025-05-11) the consolidation script applied, in order:

1. `db/add_player_type_and_display_id.sql` (idempotent column-adder
   that production needed and that some raw SQL files transitively
   depended on).
2. The post-snapshot raw SQL files in this directory, in chronological
   order:
   - `20251024_add_last_post_missive_views.sql` — missives `last_post`
   - `20251025_players_passives_tables.sql` — passive tables
   - `20251117_add_new_comp.sql` — new actions / outcomes (partial,
     known FK off-by-one issues — see git history for context)
   - `20250617.craftdb.sql` — craft tables
   - `20251005_forums_cookie_table.sql` — forums cookie table
   - `20260114_alter_items.sql` — items.is_bankable
   - `20260402_alter_items_struct.sql` — items.exotique + drop blessed_by_id
   - `20260205_add_new_comp2.sql` — competence v2 (partial)
   - `20251207.add_tooltip_offset_columns.sql` — tutorial tooltip offsets
3. The Doctrine migrations (`Version20250427223731`,
   `Version20251127000000_CreateCompleteTutorialSystem`,
   `Version20260102000000_AddCraftingTutorial`).
4. `doctrine_migration_versions` was seeded so `doctrine-migrations
   migrate` is a no-op against a freshly-loaded `init_noupdates.sql`.

Files that were already represented in `init_noupdates.sql` (most of
the pre-2025-05-11 set: `20240907.*`, `20241026_*`, etc.) were not
re-applied — they only produced "duplicate column" errors and would
have been no-ops.
