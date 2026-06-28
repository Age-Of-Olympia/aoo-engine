# Admin Import/Export — implementation plan (handoff)

> Prepared 2026-06-22 with the architect (Plan agent) for a **fresh session** to execute.
> Scope decided with the user: **action-first, then generalise across admin objects.**
> NOTE: this file is intentionally **untracked** (not committed). The same plan also lives in memory (`project_import_export_plan.md`).

## How to start the next session
- Integration branch: **`refactor/action-system`** (work lands here MR-by-MR; eventually promoted to `staging`). Branch each slice off it.
- Conventions (established across the action/passive workbenches):
  - Services take an injectable `EntityManagerInterface` (default `EntityManagerFactory::getEntityManager()`); **tests mock the EM — NO DB in the test env.** Entities built in-memory. `tests/bootstrap.php` defines CARACS, ONE_DAY.
  - Admin POST endpoints: `require config.php` + `admin/helpers.php`, `AdminAuthorizationService::DoAdminCheck()`, `CsrfProtectionService` (`validateTokenOrFail`/`regenerateToken`), `setFlash`, redirect. **No inline HTML in controllers** — markup in `src/View/Action/*View.php`.
  - Admin pages `require admin/layout.php` (auth-gates) + `admin_layout($title, renderFlashMessage() . $body, ['styles'=>..,'scripts'=>..])`; nav is the `nav-group` in `admin/layout.php` (Actions group: Workbench / Type defaults / Passives / List). Bump `ADMIN_ASSET_VERSION` on CSS/JS change.
  - Gate each MR: `XDEBUG_MODE=off ./vendor/bin/phpunit` + `make phpstan`. Conventional Commits, **no AI attribution**. One MR per slice; give the user the full MR URL.
- **No migrations needed** — reads/writes existing entities.

## Architecture (decided)

### Export format — JSON envelope
```json
{
  "format": "aoo.config-bundle",
  "formatVersion": 1,
  "exportedAt": "2026-06-22T10:00:00Z",
  "objectType": "action",
  "objects": [ { "...one action..." } ]
}
```
- `objects` always an array (single = array of one). One file = one `objectType` for v1.
- **Action payload: natural keys only — never DB ids.** Identity = `name`. Races by `Race.name` (`races: [...]`). STI `type` explicit (validate vs `Action` `discriminatorMap`). Children embedded with `executionOrder`/`orderIndex` preserved; instruction `type` via `OutcomeInstructionFactory::typeOf()`. Pretty-print `JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES` (diffable).
- **Type-level `ActionTypeInstruction` rows EXCLUDED** from action exports (belong to the type; own `objectType` later). Do **not** export the transient `automaticOutcomeInstructions`.

### Import — identity & conflict
- Match by `name` (add `ActionCatalogService::findByName()`).
- create-or-update; **children replaced wholesale** (owned cascade+orphanRemoval → clear & rebuild). Scalars overwritten.
- STI `type` change on an existing action → **reject that object**. Unknown condition/instruction type → **reject the whole object** (fail-closed, named in report). Unknown race name → **warn + skip the link**, still import.
- **Dry-run preview mandatory; re-validate on commit** (don't trust the session bundle). Reuse `ActionParameterValidator` + `ActionSchemaCatalog` to coerce/validate params. **Transactional all-or-nothing** (mirror `ActionSaveService::saveParameters`).

### Generalisation seam (`src/Service/ImportExport/`)
- `ObjectExporter { objectType(), toArray($entity), exportAll() }` + `ObjectImporter { objectType(), preview($objects): ImportReport, import($objects): ImportReport }`.
- `BundleEnvelope` (build/parse) + `ImportReport` DTO (`created/updated/rejected{reason}/warnings`) + `Exporter/ImporterRegistry`. **Build the registry only at slice 5** (avoid premature abstraction; slices 1–4 use `ActionExporter`/`ActionImporter` directly).

### Security (hand MR 4 + MR 5 to `aoo-security-reviewer`)
Upload endpoint: `DoAdminCheck`, CSRF, `is_uploaded_file`, `application/json`/`.json` only, max ~1 MB, `json_decode` depth limit, envelope `format`/`formatVersion`/`objectType` checks, whitelist field mapping (no `foreach`-assign), param key allow-list (existing `coerceRaw` regex).

## Phased MR plan (test-first, smallest-first)
1. **Export core** — `BundleEnvelope`, `ObjectExporter` iface, `ActionExporter` (entity → payload). Pure, no UI. Tests: in-memory action → exact shape, race-by-name, ordering. Risk: low.
2. **Export UI** — `admin/action-export.php` (attachment headers; must NOT include `layout.php`), export buttons on workbench + `actions.php` via `ExportButtonView`. Risk: low.
3. **Import preview core** — `ObjectImporter` iface, `ImportReport`, `BundleEnvelope::parse()`, `ActionImporter::preview()` (validate + classify create/update/reject/warn, NO writes). Round-trip export→preview test. Risk: medium, no writes.
4. **Import commit (RISKIEST)** — `ActionImporter::import()` transactional; STI subclass via `discriminatorMap[$type]`, `OutcomeInstructionFactory::typeMap()`, child clear+rebuild, race linking, reject STI-type-change; `ActionCatalogService::findByName()`. Tests: mocked EM begin/persist/flush/commit + rollback; round-trip export→import field-by-field. **Security review.** Watch `OutcomeInstructionMetadataListener` subClasses (prior bug).
5. **Import UI** — `admin/action-import.php` (upload) → `action-import-preview.php` → `action-import-commit.php`; `ImportFormView` + `ImportPreviewView`; add `Exporter/ImporterRegistry`; nav link + `ADMIN_ASSET_VERSION` bump; file limits; re-validate before commit. Risk: medium (upload surface). Security review.
6. **Generalise (optional/later)** — `PassiveExporter/Importer` (flat entity, easy) + optional `ActionTypeInstruction` exporter; register both. Proves the seam.

## Open questions for the user (resolve at session start)
1. Type-level instructions excluded from action exports + own type later? (rec yes)
2. Bulk export: all-in-one only, or per-category/per-race subsets?
3. Children replaced wholesale on re-import? Also want a "create-only" safe mode (refuse to touch existing actions)?
4. Missing race on import: warn-and-skip (rec) vs reject vs auto-create?
5. STI type change on re-import: reject (rec)?
6. Legacy scalar `race` column coexists with the `races` ManyToMany — export both, or is the scalar obsolete?
