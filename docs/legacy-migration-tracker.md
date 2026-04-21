# Legacy-to-Modern Migration Tracker

> Last updated: 2026-04-13
>
> This document tracks the ongoing migration from legacy procedural PHP (`Classes/`, root files, `scripts/`)
> to modern OOP architecture (`src/Entity/`, `src/Service/`, `src/View/`).

## Migration Progress Overview

| Layer | Total | Modernized | Legacy | Progress |
|-------|-------|-----------|--------|----------|
| Root PHP files | 37 | 6 | 12 | 16% |
| Classes/ | 57 | 7 | 50 | 12% |
| scripts/ | 90 | 19 | 71 | 21% |
| **Overall** | **184** | **32** | **133** | **17%** |

---

## Root PHP Entry Points (37 files)

### Modern (using Services + TutorialHelper) — 6 files
- [x] `action.php` — ActionService, ActionExecutorService, PlayerService
- [x] `console.php` — ConsoleService
- [x] `logs.php` — LogService
- [x] `login.php` — FirewallService
- [x] `map.php` — TutorialHelper, Services
- [x] `observe.php` — ActionService, PlayerService, TutorialHelper

### Legacy (direct `new Player($_SESSION['playerId'])`) — 12 files
- [ ] `account.php` — direct session, raw SQL
- [ ] `build.php` — direct session
- [ ] `check_mail.php` — direct session
- [ ] `fish.php` — direct session
- [ ] `index.php` — direct session (high traffic)
- [ ] `inventory.php` — direct session
- [ ] `load_caracs.php` — direct session
- [ ] `merchant.php` — direct session
- [ ] `minigame_morpion.php` — direct session
- [ ] `pnjs.php` — direct session
- [ ] `upgrades.php` — direct session
- [ ] `warschool.php` — direct session

### Mixed/Hybrid — 19 files
- [ ] `classements.php`, `deck.php`, `destroy.php`, `exit_tutorial.php`
- [ ] `faction.php`, `force_tutorial_exit.php`, `go.php`, `infos.php`
- [ ] `register.php`, `tiled.php`, `tools.php`, `worship.php`
- [ ] And 7 others

---

## Legacy Classes/ (57 files)

### Have Modern Equivalents (can be gradually replaced)

| Legacy Class | Modern Replacement | Status |
|-------------|-------------------|--------|
| `Classes/Dialog.php` | `src/Service/DialogService.php` | Partial |
| `Classes/Exchange.php` | `src/View/Merchant/ExchangesView.php` | Partial |
| `Classes/Forum.php` | `src/View/Forum/ForumView.php` | Partial |
| `Classes/Item.php` | `src/Entity/Item.php` (Doctrine) | Partial |
| `Classes/Quest.php` | `src/View/QuestsView.php` | Partial |
| `Classes/View.php` | `src/Service/ViewService.php` | Partial |
| `Classes/WarSchool.php` | `src/View/WarSchool/` | Partial |

### Core Legacy (No Replacement Yet) — High Priority

| Class | LOC | Why It Matters |
|-------|-----|---------------|
| `Classes/Player.php` | 51k+ | God object — central to everything |
| `Classes/Db.php` | ~500 | Raw SQL wrapper — used everywhere |
| `Classes/Log.php` | ~2k | Event logging — tightly coupled |
| `Classes/Ui.php` | ~1k | HTML generation — used by all views |

### Core Legacy (No Replacement Yet) — Lower Priority
- `Classes/Str.php` — String utilities, minification
- `Classes/File.php` — File operations
- `Classes/Json.php` — JSON data loading
- `Classes/Tag.php` — Tagging system
- `Classes/Dice.php` — Random number generation
- `Classes/bbcode.php` — Forum text formatting
- `Classes/PerfTimer.php` — Performance monitoring
- `Classes/Element.php` — Game elements
- `Classes/Market.php` — Market system
- `Classes/Console.php` — Admin console
- `Classes/Command.php` — Console commands
- And ~15 more utility classes

---

## Scripts Directory (90 files across 14 subdirectories)

### By Directory

| Directory | Files | Services Used | Raw SQL | Modernization |
|-----------|-------|--------------|---------|---------------|
| `scripts/tutorial/` | 7 | 0 | 7 | **0%** — Critical (active dev) |
| `scripts/tiled/` | 14 | 0 | 2 | **0%** |
| `scripts/tools/` | 17 | 2 | 3 | **11%** |
| `scripts/crons/` | 14 | 3 | 6 | **21%** |
| `scripts/map/` | 11 | 0 | 2 | **0%** |
| `scripts/infos/` | 3 | 0 | 3 | **0%** |
| `scripts/account/` | 7 | 2 | 6 | **29%** |
| `scripts/merchant/` | 3 | 0 | 1 | **0%** |
| `scripts/actions/` | 6 | 0 | 1 | **0%** |
| `scripts/upgrades/` | 2 | 1 | 1 | **50%** |
| `scripts/forum/` | 2 | 0 | 0 | N/A |

---

## Modern Layer (src/) — What's Done

### Doctrine Entities (20+)
Action, ActionCondition, ActionOutcome, ActionPassive, Audit, ForumCookie,
Item, OutcomeInstruction, Player, PlayerBonus, PlayerEffect, PlayerPassive,
PlayerPnj, Race, Recipe, RecipeIngredient, RecipeResult, Route

### Services (28+)
ActionExecutorService, ActionPassiveService, ActionService, AdminAuthorizationService,
BaseService, CronService, DialogService, FirewallService, ForumService,
InventoryService, MapService, MissiveService, OutcomeInstructionService,
PlayerEffectService, PlayerPassiveService, PlayerService, RaceService,
RecipeService, TutorialStepValidationService, ViewService, and more

### Views (15+)
MainView, MenuView, NewTurnView, InfosView, ActionResultsView,
InventoryView, BankView, CraftView, ForumView, FoiView,
AsksView, BidsView, ExchangesView, SpellsView,
WarSchool views (Melee, Distance, Magic, Spell, Stealth, Survival)

---

## Recommended Migration Order

### Phase 1 — Tutorial Compatibility (immediate)
Ensure all root files that handle player actions use `TutorialHelper::getActivePlayerId()`.
Files remaining: `inventory.php`, `merchant.php`, `build.php`, `warschool.php`

### Phase 2 — Extract Player.php Services (gradual)
Break down the 51k LOC god object into focused services:
1. Player stats/characteristics → PlayerStatsService
2. Player inventory operations → InventoryService (exists, expand)
3. Player combat calculations → CombatService
4. Player turn management → TurnService

### Phase 3 — Replace Db.php Usage (systematic)
Migrate raw SQL queries to Doctrine repositories:
1. Start with `scripts/tutorial/` (active development)
2. Then `scripts/crons/` (automated, testable)
3. Then `scripts/map/` and `scripts/tiled/`

### Phase 4 — Root File Modernization
Convert remaining root PHP files to use Services instead of raw queries.
Priority by traffic: `index.php` > `inventory.php` > `account.php`

---

## How to Use This Tracker

When working on a file:
1. Check if it's listed as "Legacy" above
2. If adding new functionality, use modern patterns (Services, Doctrine)
3. If fixing a bug, consider migrating the affected code path
4. Update this tracker when you modernize a file (change `[ ]` to `[x]`)
