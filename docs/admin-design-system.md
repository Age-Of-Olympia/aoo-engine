# Admin Design System — spec (proposal)

Status: **approved — phase 1 (foundation) landing.** Reference stylesheet:
`admin/css/admin-design-system.css`, loaded globally after `admin.css` in `admin/layout.php`.

## Why

The admin has no design system. Concretely:

- **No tokens** — every colour (`#3498db`, `#2c3e50`, …) is hardcoded ad-hoc throughout `admin/css/admin.css`. No single source of truth for palette, type, spacing.
- **The game `main.min.css` leaks into admin** — `button{background:rgba(255,255,255,.4);box-shadow:…inset}`, `code{display:block;background:url(.jpeg)}`, serif headings, input-focus parchment. Admin only partially overrides these, so look depends on cascade accidents.
- **Partial components** — `.btn`/`.btn-*` exist and most pages use them, but features add one-offs (`.wb-tab-btn`, `.wb-modal-close`, `.admin-modal-close`) that diverge. This is the "button colours aren't the same" symptom.

Goal: one **tokenized, intentional** look — the bronze direction — applied across every admin page, so admin is *pretty and consistent together*.

## Scope & safety

Everything is scoped under `.admin-layout`, the wrapper present only on admin pages. In-game pages are never touched. The system is **additive**: it overrides `admin.css` and the game base by specificity, so it can roll out page-by-page without a big-bang rewrite.

## Tokens

Defined on `.admin-layout`:

| Group | Tokens |
|---|---|
| Palette | `--slate #2c3e50` · `--ink #23303c` · `--mute #857f76` · `--rule #e6e1d8` · `--paper #fff` · `--parchment #faf8f4` · `--page #f1efe8` |
| Accent | `--bronze #8a5a2b` · `--bronze-bright #b07d3f` · `--bronze-wash #f4ead7` |
| Semantic | `--info #2f6db0` · `--success #2f7a4f` · `--danger #b23a2c` · `--warning #b07d1f` |
| Type | `--font-display goudy` (headings) · `--font-body` system stack · `--font-mono` |
| Form | `--r 4px` · `--r-lg 8px` · `--shadow-sm` · `--shadow` · `--t .15s` |

`goudy` (the game's display face) is reused for headings — authentic, already loaded, no new dependency.

## Insulation layer

Scoped resets that neutralize the game base inside admin:

- `code` → inline bronze monospace (was a block with a JPEG background).
- `button/input/select/textarea` → drop the game's inset box-shadow.
- `.admin-main` → warm `--page` background, left-aligned text, body font; headings → `goudy`.

## Components (in the reference stylesheet)

Buttons (`.btn`, `-primary` bronze, `-secondary` slate, `-danger`, `-outline-*`, `-sm`) ·
Cards (`.card`, `.card-header` parchment+goudy, `.card-body`) ·
Tables (`.table`, `-striped`, `-hover`, `.row-group` section rows) ·
Badges (`.badge` bronze + `-info/-success/-warning/-secondary`) ·
Forms (`.form-control` with bronze focus ring, `.form-label` eyebrow) ·
Alerts (`.alert-*`) ·
Sidebar (slate kept; bronze hover/active with left edge) ·
`.eyebrow` utility.

## Migration plan (phased, low-risk)

1. **Land the foundation** — add `admin-design-system.css`, load it last in `admin/layout.php` after `admin.css`. Tokens + insulation + components take effect everywhere immediately (additive; nothing else changes structurally).
2. **Audit per page** — walk each admin page, screenshot, fix any element still styled by a stale `admin.css` rule or a feature one-off; fold `.wb-*`/modal buttons into the system.
3. **Retire dead rules** — once pages are clean, delete superseded hardcoded blocks from `admin.css` and collapse it into the system.
4. **Document** — component usage cheatsheet so new admin pages start from the system.

Each phase is its own small MR; the loadout pages (#604) inherit the look for free once phase 1 lands.

## Open decisions

- **Accent weight** — bronze as the single accent, or keep steel-blue for links/`info` (current sample keeps blue for `info` badges only).
- **Page background** — warm parchment `--page` vs the cooler current grey; affects every page's feel.
- **Heading face everywhere** — `goudy` for all admin headings (sample) vs only page titles.
