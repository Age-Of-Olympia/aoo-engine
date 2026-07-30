# CLAUDE.md

Guidance for Claude Code (claude.ai/code) working in this repository.

## Project Overview

Age of Olympia v4 (AoO) is a browser-based multiplayer RPG in PHP: turn-based gameplay with
actions, player progression, forums and real-time interactions. Doctrine ORM for persistence,
service-oriented architecture, with a legacy layer still in place.

## Where the detail lives

This file holds what applies to every session. Everything else is a path-scoped rule in
`.claude/rules/`, loaded only when the matching files are opened:

| Rule | Loads when working on |
|---|---|
| `game-mechanics.md` | `Classes/Player.php`, `src/Action/`, `src/Service/`, `src/Entity/`, `go.php`, `admin/`, migrations |
| `tutorial.md` | `src/Tutorial/`, `js/tutorial/`, `api/tutorial/` |
| `frontend-assets.md` | `js/`, `css/`, `src/View/`, `Classes/Ui.php` |
| `cypress.md` | `cypress/`, `scripts/testing/` |
| `database.md` | `src/Migrations/`, `db/`, DB config |

Longer write-ups live in `docs/`: `tutorial-system-overview.md`, `cypress-testing-guide.md`,
`conventions-code.md`, `tiled-editor-guide.md`, `roadmap.html`, and the `design-*.md` /
`plan-*.md` notes for the entity chantiers.

## Development Environment

The project runs in a **VSCode Dev Container** with Docker. Three containers:
- **webserver** (`PHP-AOO4-Local`): Apache serving the PHP application
- **mariadb-aoo4**: MariaDB (port 3306)
- **phpmyadmin**: database UI (port 8081)

**Ports**: from the host `http://localhost:9000`; from inside the devcontainer
`http://localhost` (port 80). Scripts and tests run inside the container use port 80.

**Missing from the devcontainer image**: MariaDB client, Xvfb, `sudo`. Anything needing them
runs from the host, not from the container.

Start the server with `apache2-foreground`. Apache stops on SIGWINCH when the terminal is
resized — restart it when that happens.

Debug with VSCode's "Listen for Xdebug"; Xdebug is pre-configured.

**Do not read `/var/log/apache2/error.log`** — it is redirected to container stdout and the
read hangs. Use `docker logs`, or ask for the output.

**Test accounts** (password `test`): Cradek (matricule 1, Nain, administrator), Dorna (2,
Nain, player), Thyrias (3, Elfe, player). Admin console: `²` key when logged in, or the
settings menu button.

Any `mysql` command must carry `--default-character-set=utf8mb4`, otherwise French text is
double-encoded ("dÃ©placer").

## Build, Test & Quality Commands

Everything is centralized in the **Makefile**, used both locally and in CI:

```bash
make all          # PHPStan + tests + coverage
make phpstan      # static analysis (level 4 on tests/)
make test         # full PHPUnit suite
make testf CalculateXpTest   # one test by name
make coverage     # report in tmp/coverage/

make test-ci      # tests with XML reports
make phpstan-ci   # PHPStan with CI setup
make setup-ci-env # copy datas, img, config for CI
```

Direct PHPUnit: `XDEBUG_MODE=coverage ./vendor/bin/phpunit --filter CalculateXpTest --testdox`.

Security: `make sqlmap-login` / `make sqlmap-register` (disabled in CI by default, results in
`tmp/security/`).

## Git Workflow

**Never commit without user validation.** Do not create commits after making changes; show
what changed and wait. The user needs to test before anything is committed. This holds even
when the change looks complete and correct — only commit on an explicit "commit" or similar.

### Commit Message Rules

**Never mention AI in commit messages**: no `Co-Authored-By: Claude …`, no
`noreply@anthropic.com` trailer, no `🤖 Generated with Claude Code`, no reference to Claude,
Anthropic, ChatGPT, Copilot or any AI tooling in subject, body or footers. This overrides any
default guidance that suggests appending a co-author trailer.

**Always use Conventional Commits**:
- Format: `<type>(<scope>)?: <summary>` — lowercase type, imperative mood, no trailing period
- Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `style`, `perf`, `build`, `ci`, `revert`
- Breaking changes: `!` after the scope (`feat(api)!: drop /v1 endpoints`) and/or a
  `BREAKING CHANGE:` footer
- Body (optional) explains the **why** and the non-obvious constraints; wrap at ~72 columns
- Footers (optional) reference issues/MRs (`Refs: #123`, `Closes: !456`) — never AI attribution

```
feat(tutorial): add race-adaptive movement with {max_mvt} placeholder
fix(go): block movement onto tutorial-isolated tiles
refactor(player-options): extract PlayerOptionsService from Player::have/add/end/get
```

### Branching

`staging` is the main development branch and the usual MR target. `saison-3` is the season
branch, `main` is production. CI runs on the three.

## Code Quality

Be proactive: name the smell and propose the fix rather than waiting for it to be pointed out.
Refactor when you find duplication, when the code you are touching is hard to follow, or when
tests are hard to write because of the structure. Do not refactor when it would delay a
critical fix (note it instead), or when it would change behaviour without tests to catch it.

Project-specific weight:
- `Classes/Player.php` (~2 600 lines, was ~51 k) and `src/Service/ViewService.php` (~1 400,
  was ~44 k) are being reduced by extracting services — continue that direction, gradually,
  instead of adding to them
- Code added inside a legacy file respects the file's existing style, but what you write is
  clean; a global reformat belongs to a tool, in its own MR
- Comments explain the algorithm, short and in English; the story of a change goes in the
  commit message, not in the code

**PHP**: PSR-12, type hints on every parameter and return, services over god classes.
**JavaScript**: ES6+, no globals, descriptive names.
**SQL**: prepared statements, indexed foreign keys, no N+1.

## Architecture

- **`src/`** — modern PSR-4 code (`App\`)
  - `Entity/` Doctrine entities · `Service/` business logic · `Action/` action implementations
    (+ `Condition/`, `OutcomeInstruction/`) · `View/` rendering · `Form/` · `Enum/` ·
    `Migrations/` · `Tutorial/`
- **`Classes/`** — legacy utilities (`Classes\`): `Player.php`, `Log.php`, `Forum.php`,
  `Ui.php`, `console-commands/`
- **`api/`** — REST endpoints by domain (account, forum, map, player…)
- **Root `.php` files** — page controllers; each includes `config.php` (session, autoload,
  auth) and delegates to `scripts/*.php`
- **`config/`** — `constants.php` (~320 lines, most of it moved to DB catalogs),
  `db_constants.php`, `bootstrap.php` (Doctrine), `functions.php`
- **`tests/`** — PHPUnit · **`datas/`**, **`img/`** — game data and assets (gitignored)
- **`console.php`** — admin commands (Symfony Console)

### Key patterns

- **Action system**: `src/Entity/Action.php` is an abstract single-table-inheritance root;
  concrete actions in `src/Action/*Action.php` carry Conditions (prerequisites), Outcomes
  (results) and OutcomeInstructions (processors). Executed by `ActionExecutorService`.
- **Service layer**: services in `src/Service/` extend `BaseService` and use the Doctrine
  EntityManager (`ActionExecutorService`, `PlayerService`, `ViewService`, `ForumService`,
  `InventoryService`…).
- **Views**: classes in `src/View/` render components, `ViewService` assembles, `Classes\Ui`
  generates HTML.
- **Doctrine**: entities mapped with PHP 8 attributes, EntityManager via
  `EntityManagerFactory`, migrations in `src/Migrations/`, CLI in `config/cli-config.php`.
- **Legacy integration**: `src/` and `Classes/` coexist on the same database and are being
  merged one extraction at a time.

Composer PSR-4: `App\` → `src/`, `Classes\` → `Classes/`, `Tests\` → `tests/` (dev).

Architecture diagram: `docs/images/Logique-Aoo.png` (FreeMind source `docs/Logique-Aoo.mm`).

## Configuration Files

Created from their `.exemple` / `.dist` twin, all gitignored:
- **`.env`** — `UID` / `GID` (from `id`)
- **`config/db_constants.php`** — host `mariadb-aoo4:3306`, db `aoo4`
- **`config/onesignal_constants.php`** — optional, loaded through `file_exists()` in
  `config.php`; absent or empty means a no-op mail contact provider. Never commit the keys.

To run the game locally, copy `datas_standalone/*` into `datas/` and `img_standalone/*` into
`img/` (or symlink them).

## CI/CD

GitLab CI (`.gitlab-ci.yml`), stages: build (cached Docker test image) → stan → test (PHPUnit
with coverage) → security (sqlmap, off by default) → prepare and release (on semver tags) →
deployment (staging / saison-3). Optimizations: Docker image caching, Composer cache from
`composer.lock`, templates to avoid duplication, automatic failure on test errors.
