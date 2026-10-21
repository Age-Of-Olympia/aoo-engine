---
paths:
  - "admin/**"
---

# Admin pages

Conventions in `docs/admin-design-system.md`. The short version:

- `admin_layout($title, $content, ['actions' =>, 'intro' =>, 'back' =>])` draws the head
  (breadcrumb from the menu, title, actions) and the flash. No `<h1>`, no `renderFlashMessage()`
  in a page.
- New page = one line in `admin_nav_tree()` (`admin/layout.php`) + its right in
  `AdminMenuAccessService::PAGES`; a sub-page goes in its link's `also`.
- POST endpoints: `handleAdminPost($redirect, fn(): string => …)` or `requireAdminPost($back)`.
- `data-confirm="…"` instead of `onsubmit="return confirm(…)"`; `data-autosubmit` instead of
  `onchange="this.form.submit()"`; `dangerZone()` for delete/rename.
- Styles: `admin/css/admin.css` only (tokens, components, Bootstrap-subset utilities). A page
  stylesheet adds its own components, never redefines `.btn`, `.card`, `.table`, `.badge`.
  Bump `ADMIN_ASSET_VERSION` in `admin/layout.php` after any admin CSS/JS change.
- Must work at 390px wide: tables scroll by themselves, avoid fixed widths elsewhere.
