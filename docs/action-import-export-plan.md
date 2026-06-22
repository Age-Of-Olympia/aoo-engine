# Admin Import/Export — implementation plan (handoff)

> Prepared 2026-06-22 with the architect (Plan agent) for a **fresh session** to execute.
> Scope decided with the user: **action-first, then generalise across admin objects.**

## How to start the next session
- Integration branch: **`refactor/action-system`** (all action-system work lands here MR-by-MR; eventually promoted to `staging`). Branch each slice off it.
- Conventions (already established across the action/passive workbenches):
  - Services take an injectable `EntityManagerInterface` (default `EntityManagerFactory::getEntityManager()`); **tests mock the EM — there is NO DB in the test env.** Entities built in-memory. `tests/bootstrap.php` defines canonical constants (CARACS, ONE_DAY).
  - Admin POST endpoints: `require config.php` + `admin/helpers.php`, `AdminAuthorizationService::DoAdminCheck()`, `CsrfProtectionService` (`validateTokenOrFail` / `regenerateToken`), `setFlash`, redirect. **No inline HTML in controllers** — markup lives in `src/View/Action/*View.php`.
  - Admin pages `require admin/layout.php` (auth-gates) + `admin_layout($title, renderFlashMessage() . $body, ['styles'=>..,'scripts'=>..])`; nav is the `nav-group` in `admin/layout.php` (Actions group: Workbench / Type defaults / Passives / List). Bump `ADMIN_ASSET_VERSION` on CSS/JS change.
  - Gate every MR: `XDEBUG_MODE=off ./vendor/bin/phpunit` + `make phpstan`. Commit style: Conventional Commits, **no AI attribution**. One MR per slice; give the user the full MR URL.
- **No migrations needed** — import/export reads/writes existing entities via existing services. (Removes the riskiest class of work.)

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
- **Action payload uses natural keys only — never DB ids.** Identity = `name`. Races linked by `Race.name` (`races: [...]`). STI `type` carried explicitly (validated against `Action` `discriminatorMap`). Children (`conditions`, `outcomes` → `instructions`) embedded with `executionOrder`/`orderIndex` preserved; instruction `type` via `OutcomeInstructionFactory::typeOf()`. Pretty-print with `JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES` (diffable).
- **Type-level `ActionTypeInstruction` rows are EXCLUDED** from an action export (they belong to the type, not the action) — handled later as their own `objectType: "action-type-instruction"`. (Open Q1.)

### Import — identity & conflict
- Match by `name` (add `ActionCatalogService::findByName()`).
- **create-or-update; children replaced wholesale** (owned collections, cascade+orphanRemoval — clear and rebuild from the file). Top-level scalars overwritten.
- STI `type` change on an existing action → **reject that object** (can't change a Doctrine discriminator in place).
- Unknown condition/instruction type → **reject the whole object** (fail-closed), named in the report. Unknown race name → **warn + skip that link**, still import.
- **Dry-run preview is mandatory before commit.** Re-validate on commit (never trust the session-held bundle blindly).
- Reuse `ActionParameterValidator` + `ActionSchemaCatalog` to coerce/validate every param map (as `ParameterMerger` does). **Transactional all-or-nothing** import (mirror `ActionSaveService::saveParameters`).

### Generalisation seam (`src/Service/ImportExport/`)
- `ObjectExporter` { `objectType()`, `toArray($entity)`, `exportAll()` } and `ObjectImporter` { `objectType()`, `preview($objects): ImportReport`, `import($objects): ImportReport` }.
- `BundleEnvelope` (build/parse the wrapper) + `ImportReport` DTO (`created[] / updated[] / rejected[]{reason} / warnings[]`) + `Exporter/ImporterRegistry` (objectType → impl). **Don't build the registry until slice 5** (a 2nd consumer appears); slices 1–4 use `ActionExporter`/`ActionImporter` directly (avoid premature abstraction).

### Security
Import = upload + deserialize + bulk DB-write. Endpoint must: `DoAdminCheck`, CSRF, `is_uploaded_file`, `application/json`/`.json` only, max size (~1 MB), `json_decode` depth limit, envelope `format`/`formatVersion`/`objectType` checks, whitelist field mapping (no `foreach`-assign), param key allow-list (existing `coerceRaw` regex). **Hand MR 4 + MR 5 to `aoo-security-reviewer`.**

## Phased MR plan (test-first, smallest-first)
1. **Export core** — `BundleEnvelope`, `ObjectExporter` iface, `ActionExporter` (entity → payload). Pure, no UI. Tests: in-memory action → exact shape, race-by-name, ordering. *(Don't export the transient `automaticOutcomeInstructions`.)* Risk: low.
2. **Export UI** — `admin/action-export.php` (attachment headers; must NOT include `layout.php`), export buttons on workbench + `actions.php` via `ExportButtonView`. Risk: low.
3. **Import preview core** — `ObjectImporter` iface, `ImportReport`, `BundleEnvelope::parse()`, `ActionImporter::preview()` (validate envelope+objects, classify create/update/reject/warn, NO writes). Tests incl. **round-trip export→preview**. Risk: medium (identity/conflict logic), but no writes.
4. **Import commit (RISKIEST)** — `ActionImporter::import()` transactional; STI subclass instantiation (`discriminatorMap[$type]`), `OutcomeInstructionFactory::typeMap()`, child-collection clear+rebuild, race linking, reject STI-type-change; `ActionCatalogService::findByName()`. Tests: mocked EM begin/persist/flush/commit + rollback; round-trip export→import field-by-field. **Security review.** Watch `OutcomeInstructionMetadataListener` subClasses (prior bug).
5. **Import UI** — `admin/action-import.php` (upload) → `action-import-preview.php` → `action-import-commit.php`; `ImportFormView` + `ImportPreviewView`; add `Exporter/ImporterRegistry`; nav link + `ADMIN_ASSET_VERSION` bump; file limits. Re-validate before commit. Risk: medium (upload surface). Security review.
6. **Generalise (optional/later)** — `PassiveExporter/Importer` (flat entity, easy) + optionally `ActionTypeInstruction` exporter; register both. Proves the seam.

## Open questions for the user (resolve at session start)
1. Type-level instructions excluded from action exports + handled as their own type later? (recommended yes)
2. Bulk export: all-in-one only, or also per-category/per-race subsets?
3. Children replaced wholesale on re-import? Also want a "create-only" safe mode that refuses to touch existing actions?
4. Missing race on import: warn-and-skip (recommended) vs reject vs auto-create?
5. STI type change on re-import: reject (recommended) — confirm import never needs to convert a type.
6. The legacy scalar `race` column coexists with the `races` ManyToMany — export both, or is the scalar obsolete?
