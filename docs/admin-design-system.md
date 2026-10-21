# Admin — design system and page conventions

One stylesheet, `admin/css/admin.css`, one shell script, `admin/js/admin.js`, one layout,
`admin/layout.php`. Page stylesheets (`action-workbench.css`, `footprints.css`…) only add what
is theirs, never redefine a button, a card or a table.

## Look

The admin belongs to the engine, not to a world: it does not load the game's `main.min.css`,
nor its fonts or colours. A neutral console, one accent.

| Token | Value | Use |
|---|---|---|
| `--page` / `--paper` / `--surface-alt` | `#f1f3f5` / `#fff` / `#f7f8fa` | page, cards and inputs, card and table heads |
| `--ink` / `--mute` / `--rule` | `#1b2026` / `#5d6874` / `#dde1e6` | text, secondary text, hairlines |
| `--accent` (+ `-hover`, `-wash`) | `#0b6e72` | primary buttons, active nav, focus ring |
| `--slate` | `#2b3440` | secondary buttons |
| `--info` `--success` `--warning` `--danger` (+ `-wash`) | | badges, alerts, outline buttons |

One family: the system UI stack (no webfont to load), a monospace stack for codes. Labels and
table headers in sentence case, numbers in tables tabular.

## A page

```php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');

echo admin_layout('Effets', $content, [
    'actions' => '<a class="btn btn-primary" href="?action=new">+ Nouvel effet</a>',
    'intro'   => 'Un effet modifie les caractéristiques de qui le porte…',   // optional
    'back'    => ['/admin/effects.php', 'Effets'],                        // sub-pages
    'styles'  => ['/admin/css/icon-picker.css'],
    'scripts' => ['/admin/js/icon-picker.js'],
]);
```

The layout draws the head — breadcrumb taken from the menu, title, intro, actions — and the
pending flash message under it. A page never prints its own `<h1>` nor calls
`renderFlashMessage()`. `'header' => false` is for a page that draws its own head (monitoring).

The menu is `admin_nav_tree()` in `layout.php`: a new page is one line there plus its right in
`AdminMenuAccessService::PAGES`. A sub-page (edit form, import step) is listed in its link's
`also`, so the link lights up and the breadcrumb follows.

## Posting

```php
// admin/thing-save.php
(new AdminMenuAccessService())->enforce('things.edit');
handleAdminPost('/admin/things.php', function (): string {
    (new ThingService())->save($_POST);
    return 'Objet enregistré.';
});
```

`handleAdminPost` checks the method and the CSRF token, runs the action, flashes its message
(or the exception's: warning for `InvalidArgumentException`, error otherwise) and redirects.
`requireAdminPost($back)` is the same prologue alone, for an endpoint with several outcomes.

## Components

| Need | Markup / helper |
|---|---|
| Form field | `formField`, `formInput`, `formSelect`, `formTextarea`, `formCheckbox`, `formCard` |
| Table | `renderTable()`; `data-admin-list data-page-size="50" data-facets="type:Type"` adds search, facets, paging. Every `.table` scrolls in its own box (admin.js) |
| Ask before acting | `data-confirm="Supprimer … ?"` on the form, the submit button or the link |
| Submit on change | `data-autosubmit` on the field |
| Tick every row | `data-check-all` on the header checkbox (visible rows only) |
| Delete / rename | `dangerZone($html)` at the bottom of the edit form |
| Row menu | `<details class="row-popover"><summary class="btn btn-sm …">…</summary><div class="row-popover-panel">…</div></details>` |
| Tabs | `.nav-tabs > .nav-link.active`, panels `.tab-pane.active` |
| Status | `.badge` + `-success` `-warning` `-danger` `-info` `-secondary`; `.badge-solid` for the loud version |
| Folding panel | `<details class="card"><summary>…</summary>…</details>` |

The utility classes are the Bootstrap 4/5 subset the pages already used (`mb-0`, `text-danger`,
`col-md-4`, `form-row`, `input-group`…), both spellings; admin.css lists them.

## Small screens

Below 900px the sidebar becomes a drawer opened from a top bar, `col-md-*` cells stack, tables
scroll sideways. Mobile is a fallback, not the main use: it must work, not shine.
