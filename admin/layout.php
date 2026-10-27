<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
require_once __DIR__ . '/helpers.php';
use App\Service\AdminMenuAccessService;
use App\Service\AdminAuthorizationService;

/* Per-menu access control: require admin to reach the dashboard at all, and
 * escalate to super-admin for menus configured (or defaulting) to that level.
 * Runs at include time — before any page logic — so an access failure exits
 * before output. */
(new AdminMenuAccessService())->enforcePage(basename($_SERVER['PHP_SELF']));

/** Bump to bust the cache when admin CSS/JS changes. */
const ADMIN_ASSET_VERSION = '20261022';


/**
 * The admin menu, the one place pages are named and placed. Sections follow
 * who uses them: animation first, then the map maker, the designer, the
 * newcomers' welcome, the operator.
 *
 * A link is [label, href] or [label, href, also]: `also` lists the sub-pages
 * reached from it (an edit form, an import step), which light the link up
 * and borrow its breadcrumb. A group is [title, links]; a section is
 * ['section' => title, 'items' => groups or links]. A link with a query
 * (Formes per kind) is active only when the request carries that query.
 *
 * @return list<mixed>
 */
function admin_nav_tree(): array
{
    return [
        ['Tableau de bord', '/admin/index.php'],

        ['section' => 'Animation', 'items' => [
            ['Personnages', '/admin/players.php', ['player-skills.php', 'player-edit.php']],
            ['PNJ', '/admin/pnjs.php'],
            ['Évènements spéciaux', '/admin/special-events.php'],
            ['Membres des factions', '/admin/faction-members.php'],
            ['Bâtiments posés', '/admin/buildings.php'],
            ['Éléments posés', '/admin/map-elements.php'],
            ['Captures', '/admin/screenshots.php'],
        ]],

        ['section' => 'Le monde', 'items' => [
            ['Plans', '/admin/plans.php'],
            ['Foi', [
                ['Cultes', '/admin/cults.php'],
                ['Récompenses', '/admin/faith-rewards.php'],
            ]],
            ['Génération des cartes', [
                ['Carte monde', '/admin/world_map.php'],
                ['Cartes locales', '/admin/local_maps.php'],
                ['Couleurs', '/admin/tile-colors.php'],
                ['Ombres', '/admin/tile-shade.php'],
            ]],
            ['Tuiles & images', '/admin/tile-assets.php'],
            ['Transitions de terrain', '/admin/terrain-transitions.php'],
            ['Formes', '/admin/footprints.php'],
            ['Images des bâtiments', '/admin/structure-images.php'],
            ['Composeur d\'éléments', '/admin/element-composer.php'],
        ]],

        ['section' => 'Catalogues', 'items' => [
            ['Peuples', [
                ['Races', '/admin/races.php'],
                ['Avatars & portraits', '/admin/avatars-portraits.php'],
                ['Factions', '/admin/factions.php'],
            ]],
            ['Plateau', [
                ['Bâtiments', '/admin/structure-types.php'],
                ['Décors', '/admin/scenery-types.php'],
                ['Ressources', '/admin/harvest-types.php'],
                ['Rendements par plan', '/admin/harvest-seed.php'],
                ['Plantes', '/admin/plant-types.php'],
                ['Routes', '/admin/route-types.php'],
                ['Éléments', '/admin/element-types.php'],
            ]],
            ['Objets', [
                ['Objets', '/admin/items.php', ['item-owners.php']],
                ['Recettes', '/admin/recipes.php'],
            ]],
            ['Compétences', [
                ['Actions', '/admin/action-workbench.php', ['actions.php']],
                ['Passifs', '/admin/passive-workbench.php'],
                ['Défauts par type', '/admin/action-type-defaults.php'],
                ['Statistiques', '/admin/skill-stats.php', ['skill-owners.php']],
            ]],
            ['Effets', '/admin/effects.php'],
            ['Dialogues', '/admin/dialogs.php'],
        ]],

        ['section' => 'Accueil des joueurs', 'items' => [
            ['Page d\'accueil', '/admin/landing.php', ['landing-seed.php']],
            ['Missive d\'accueil', '/admin/welcome-missive.php'],
            ['Tutoriel', [
                ['Catalogue', '/admin/tutorial-catalog.php'],
                ['Étapes', '/admin/tutorial.php', ['tutorial-step-editor.php']],
                ['PNJ', '/admin/tutorial-npcs.php'],
                ['Options', '/admin/tutorial-settings.php'],
            ]],
        ]],

        ['section' => 'Administration', 'items' => [
            ['Contrôle d\'accès', '/admin/access-control.php'],
            ['Admins & options', '/admin/admin-access.php'],
            ['Import / export', '/admin/action-import.php', ['action-import-preview.php', 'action-import-run.php']],
            ['Crons', '/admin/crons.php'],
            ['Monitoring', '/admin/monitoring.php'],
            ['Wiki', '/admin/wiki.php'],
            ['Maintenance', [
                ['Renommage de saison', '/admin/plan-season-rename.php'],
                ['Catégories à corriger', '/admin/entity-kinds.php'],
                ['Dialogues de case', '/admin/tile-dialogs-migration.php'],
            ]],
        ]],
    ];
}

/**
 * Is this nav link the page being served (or one of its sub-pages)?
 *
 * @param list<string> $also
 */
function admin_nav_is_active(string $href, array $also, string $currentPage): bool
{
    if (in_array($currentPage, $also, true)) {
        return true;
    }
    if (basename((string) parse_url($href, PHP_URL_PATH)) !== $currentPage) {
        return false;
    }
    parse_str((string) parse_url($href, PHP_URL_QUERY), $wanted);

    foreach ($wanted as $key => $value) {
        if (($_GET[$key] ?? null) !== $value) {
            return false;
        }
    }

    return true;
}

/**
 * Render the nav and find the breadcrumb of the current page, in one walk.
 *
 * @param list<mixed> $tree admin_nav_tree()
 * @return array{0: string, 1: list<string>} [nav HTML, crumbs]
 */
function admin_nav_render(array $tree, string $currentPage, callable $canOpen): array
{
    $crumbs = [];

    $link = function (array $item, array $trail) use ($currentPage, $canOpen, &$crumbs): string {
        [$label, $href] = $item;
        $page = basename((string) parse_url($href, PHP_URL_PATH));
        if (!$canOpen($page)) {
            return '';
        }
        $active = admin_nav_is_active($href, $item[2] ?? [], $currentPage);
        if ($active && !$crumbs) {
            $crumbs = $trail;
        }

        return '<a href="' . e($href) . '" class="nav-link' . ($active ? ' active" aria-current="page' : '') . '">'
            . e($label) . '</a>';
    };

    // A folding box; '' when nothing inside survived the access filter.
    $fold = function (string $kind, string $title, array $children): string {
        $children = array_filter($children);
        if (!$children) {
            return '';
        }
        $inner = implode('', $children);
        $open = str_contains($inner, ' active"') ? " nav-{$kind}-open" : '';

        return "<div class=\"nav-{$kind}{$open}\">"
            . "<button type=\"button\" class=\"nav-{$kind}-title\">" . e($title) . '</button>'
            . "<div class=\"nav-{$kind}-children\">{$inner}</div></div>";
    };

    $items = function (array $list, array $trail) use (&$items, $link, $fold): array {
        $html = [];
        foreach ($list as $item) {
            if (isset($item['section'])) {
                $html[] = $fold('section', $item['section'], $items($item['items'], [$item['section']]));
            } elseif (is_array($item[1])) {
                $html[] = $fold('group', $item[0], $items($item[1], [...$trail, $item[0]]));
            } else {
                $html[] = $link($item, $trail);
            }
        }

        return $html;
    };

    return [implode("\n", array_filter($items($tree, []))), $crumbs];
}

/**
 * Render the admin chrome around a page's $content.
 *
 * The layout draws the page head — breadcrumb from the menu, the title, an
 * optional intro and actions — then the pending flash message, then the
 * content. Options:
 *   styles / scripts  list<string> page assets (versioned automatically)
 *   actions           HTML of the buttons on the right of the title
 *   intro             HTML of one explanatory paragraph under the title
 *   back              [href, label] link above the title, for a sub-page
 *   header            false for a page drawing its own head (monitoring)
 *
 * @param array{styles?: list<string>, scripts?: list<string>, actions?: string, intro?: string,
 *              back?: array{0: string, 1: string}, header?: bool} $options
 */
function admin_layout(string $title, string $content, array $options = []): string
{
    $currentPage = basename($_SERVER['PHP_SELF']);

    // Per-menu access: hide links the viewer cannot open.
    $access = new AdminMenuAccessService();
    $viewerIsSuperAdmin = AdminAuthorizationService::isSuperAdmin();
    [$navigation, $crumbs] = admin_nav_render(
        admin_nav_tree(),
        $currentPage,
        fn(string $page): bool => $access->canAccessPage($page, $viewerIsSuperAdmin)
    );

    $head = '';
    if (($options['header'] ?? true) !== false) {
        $trail = array_map(fn(string $crumb): string => '<span>' . e($crumb) . '</span>', $crumbs);
        if (isset($options['back'])) {
            // The list a sub-page returns to is often the menu's last crumb: link it instead of repeating it.
            $backLink = '<span><a href="' . e($options['back'][0]) . '">' . e($options['back'][1]) . '</a></span>';
            if (end($crumbs) === $options['back'][1]) {
                array_pop($trail);
            }
            $trail[] = $backLink;
        }
        $head = '<header class="page-head"><div class="page-head-text">'
            . ($trail ? '<nav class="page-crumbs" aria-label="Fil d\'Ariane">' . implode('', $trail) . '</nav>' : '')
            . '<h1>' . $title . '</h1>'
            . (isset($options['intro']) ? '<div class="page-intro">' . $options['intro'] . '</div>' : '')
            . '</div>'
            . (isset($options['actions']) ? '<div class="page-actions">' . $options['actions'] . '</div>' : '')
            . '</header>';
    }

    // A page that did not print its flash gets it under the head.
    $flash = renderFlashMessage();

    $version = ADMIN_ASSET_VERSION;
    $tabTitle = strip_tags($title);

    $styleLinks = '';
    foreach ($options['styles'] ?? [] as $href) {
        $styleLinks .= "\n    <link rel=\"stylesheet\" href=\"{$href}?v={$version}\">";
    }
    $scriptTags = '';
    foreach ($options['scripts'] ?? [] as $src) {
        $scriptTags .= "\n    <script src=\"{$src}?v={$version}\"></script>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$tabTitle · Administration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/admin/css/admin.css?v=$version">
    <link rel="stylesheet" href="/css/modal.css?v=$version">$styleLinks
    <script src="/js/modal.js?v=$version"></script>
    <script src="/admin/js/admin.js?v=$version" defer></script>
    <script src="/admin/js/admin-list.js?v=$version" defer></script>
</head>
<body>
    <script>try { if (localStorage.getItem('adminTechNames') === '1') document.body.classList.add('show-tech'); } catch (e) {}</script>
    <div class="admin-layout">
        <header class="admin-topbar">
            <button type="button" class="admin-nav-toggle" aria-controls="admin-sidebar" aria-expanded="false"
                    aria-label="Menu">&#9776;</button>
            <span class="admin-topbar-title">$tabTitle</span>
        </header>
        <aside class="admin-sidebar" id="admin-sidebar">
            <a class="admin-brand" href="/admin/index.php">Administration</a>
            <nav class="vertical-nav" aria-label="Administration">
                $navigation
            </nav>
            <div class="sidebar-footer">
                <label class="tech-switch"><input type="checkbox" data-tech-names> Noms techniques</label>
                <a href="/index.php">&larr; Retour au jeu</a>
            </div>
        </aside>
        <div class="admin-scrim"></div>

        <main class="admin-main">
            $head
            $flash
            $content
        </main>
    </div>$scriptTags
</body>
</html>
HTML;
}
