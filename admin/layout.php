<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
use App\Service\AdminMenuAccessService;
use Classes\Player;

/* Per-menu access control: require admin to reach the dashboard at all, and
 * escalate to super-admin for menus configured (or defaulting) to that level.
 * Runs at include time — before any page logic — so an access failure exits
 * before output. */
(new AdminMenuAccessService())->enforce(basename($_SERVER['PHP_SELF']));

/** Bump to bust the cache when admin CSS/JS changes. */
const ADMIN_ASSET_VERSION = '20260718h';

/** Game-wide main stylesheet — its own deploy-driven cache-bust, separate from admin assets. */
const MAIN_CSS_VERSION = '20260614';

/**
 * Render the admin chrome (sidebar + main column) around a page's $content.
 *
 * Page-specific assets are injected via $assets so each page keeps its styles
 * and scripts in their own files rather than inline:
 *   $assets['styles']  list<string> of CSS paths (versioned automatically)
 *   $assets['scripts'] list<string> of JS paths (versioned automatically)
 *
 * @param array{styles?: list<string>, scripts?: list<string>} $assets
 */
function admin_layout($title, $content, array $assets = []) {
    // Get current page for active menu highlighting
    $currentPage = basename($_SERVER['PHP_SELF']);

    // Per-menu access: hide links the viewer cannot open. The viewer is already
    // an admin (enforced at include time); the only question is super-admin.
    $access = new AdminMenuAccessService();
    $viewerIsSuperAdmin = !empty($_SESSION['isSuperAdmin'])
        || (bool) (new Player($_SESSION['playerId']))->have_option('isSuperAdmin');

    // A nav link, or '' when the viewer cannot access that page.
    $navLink = function($page, $label, $href) use ($currentPage, $access, $viewerIsSuperAdmin) {
        if (!$access->canAccess($page, $viewerIsSuperAdmin)) {
            return '';
        }
        $isActive = ($currentPage === $page) ||
                    ($page === 'tutorial.php' && $currentPage === 'tutorial-step-editor.php') ||
                    ($page === 'players.php' && $currentPage === 'player-skills.php');
        $activeClass = $isActive ? ' active' : '';
        return "<a href=\"$href\" class=\"nav-link$activeClass\">$label</a>";
    };

    // A collapsible group; returns '' when the viewer can access none of its
    // items, so empty groups never render.
    $navGroup = function(string $title, array $items, array $activePages) use ($navLink, $currentPage) {
        $links = [];
        foreach ($items as [$page, $label, $href]) {
            $link = $navLink($page, $label, $href);
            if ($link !== '') {
                $links[] = $link;
            }
        }
        if (!$links) {
            return '';
        }
        $openClass = in_array($currentPage, $activePages, true) ? ' nav-group-open' : '';
        return "<div class=\"nav-group{$openClass}\">\n                "
            . "    <span class=\"nav-group-title\">{$title}</span>\n                "
            . "    <div class=\"nav-group-children\">\n                    "
            . implode("\n                    ", $links) . "\n                "
            . "    </div>\n                "
            . "</div>";
    };

    $tutorialPages = ['tutorial-catalog.php', 'tutorial.php', 'tutorial-step-editor.php',
                      'tutorial-npcs.php', 'tutorial-settings.php'];
    $mapPages = ['world_map.php', 'plans.php', 'local_maps.php', 'terrain-transitions.php', 'tile-assets.php',
                 'map-elements.php', 'screenshots.php'];
    $actionPages = ['action-workbench.php', 'action-type-defaults.php', 'actions.php', 'passive-workbench.php',
                    'action-import.php', 'action-import-preview.php'];
    $playerPages = ['players.php', 'player-skills.php', 'skill-stats.php', 'skill-owners.php', 'admin-access.php',
                    'pnjs.php', 'avatars-portraits.php'];
    $racePages = ['races.php', 'race-seed.php'];
    $itemPages = ['items.php', 'item-seed.php', 'recipes.php'];
    $factionPages = ['factions.php', 'faction-members.php', 'faction-seed.php'];
    $dialogPages = ['dialogs.php', 'dialog-seed.php'];

    // array_filter drops links/groups the viewer cannot access.
    $navParts = array_filter([
        $navLink('index.php', 'Tableau de bord', '/admin/index.php'),
        $navLink('landing.php', 'Page d\'accueil', '/admin/landing.php'),
        $navGroup('Tutoriel', [
            ['tutorial-catalog.php', 'Catalogue', '/admin/tutorial-catalog.php'],
            ['tutorial.php', 'Étapes', '/admin/tutorial.php'],
            ['tutorial-npcs.php', 'PNJ', '/admin/tutorial-npcs.php'],
            ['tutorial-settings.php', 'Options', '/admin/tutorial-settings.php'],
        ], $tutorialPages),
        $navGroup('Cartes', [
            ['world_map.php', 'Carte monde', '/admin/world_map.php'],
            ['plans.php', 'Plans', '/admin/plans.php'],
            ['local_maps.php', 'Cartes locales', '/admin/local_maps.php'],
            ['terrain-transitions.php', 'Transitions de terrain', '/admin/terrain-transitions.php'],
            ['tile-assets.php', 'Tuiles &amp; images', '/admin/tile-assets.php'],
            ['map-elements.php', 'Éléments posés', '/admin/map-elements.php'],
            ['screenshots.php', 'Captures', '/admin/screenshots.php'],
        ], $mapPages),
        $navGroup('Actions', [
            ['actions.php', 'Liste', '/admin/actions.php'],
            ['action-workbench.php', 'Configuration actions', '/admin/action-workbench.php'],
            ['passive-workbench.php', 'Configuration passifs', '/admin/passive-workbench.php'],
            ['action-type-defaults.php', 'Défauts par type', '/admin/action-type-defaults.php'],
            ['action-import.php', 'Importer', '/admin/action-import.php'],
        ], $actionPages),
        $navGroup('Personnages', [
            ['players.php', 'Compétences', '/admin/players.php'],
            ['pnjs.php', 'PNJ', '/admin/pnjs.php'],
            ['avatars-portraits.php', 'Avatars &amp; portraits', '/admin/avatars-portraits.php'],
            ['skill-stats.php', 'Statistiques', '/admin/skill-stats.php'],
            ['admin-access.php', 'Accès &amp; options', '/admin/admin-access.php'],
        ], $playerPages),
        $navGroup('Dialogues', [
            ['dialogs.php', 'Liste', '/admin/dialogs.php'],
            ['dialog-seed.php', 'Seed JSON legacy', '/admin/dialog-seed.php'],
        ], $dialogPages),
        $navGroup('Races', [
            ['races.php', 'Liste', '/admin/races.php'],
            ['race-seed.php', 'Seed JSON legacy', '/admin/race-seed.php'],
        ], $racePages),
        $navGroup('Effets', [
            ['effects.php', 'Liste', '/admin/effects.php'],
        ], ['effects.php']),
        $navGroup('Factions', [
            ['factions.php', 'Liste', '/admin/factions.php'],
            ['faction-members.php', 'Membres', '/admin/faction-members.php'],
            ['faction-seed.php', 'Seed JSON legacy', '/admin/faction-seed.php'],
        ], $factionPages),
        $navGroup('Bâtiments', [
            // Pas de « Liste » ici : partout ailleurs elle désigne le
            // catalogue — le catalogue des bâtiments, ce sont les Types ;
            // cette page-ci gère les instances posées dans le monde.
            ['buildings.php', 'Posés', '/admin/buildings.php'],
            ['structure-types.php', 'Types', '/admin/structure-types.php'],
            ['structure-images.php', 'Images', '/admin/structure-images.php'],
        ], ['buildings.php', 'structure-types.php', 'structure-images.php']),
        $navGroup('Objets', [
            ['items.php', 'Liste', '/admin/items.php'],
            ['recipes.php', 'Recettes', '/admin/recipes.php'],
            ['item-seed.php', 'Seed JSON legacy', '/admin/item-seed.php'],
        ], $itemPages),
        $navLink('wiki.php', 'Wiki', '/admin/wiki.php'),
        // Superadmin-only: self-hides for plain admins (defaults to superadmin).
        $navLink('access-control.php', 'Contrôle d\'accès', '/admin/access-control.php'),
    ]);

    $navigation = implode("\n                ", $navParts);

    $version = ADMIN_ASSET_VERSION;
    $mainCssVersion = MAIN_CSS_VERSION;

    $styleLinks = '';
    foreach ($assets['styles'] ?? [] as $href) {
        $styleLinks .= "\n    <link rel=\"stylesheet\" href=\"{$href}?v={$version}\">";
    }
    $scriptTags = '';
    foreach ($assets['scripts'] ?? [] as $src) {
        $scriptTags .= "\n    <script src=\"{$src}?v={$version}\"></script>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title - Admin of Olympia</title>
    <link href="/css/main.min.css?v=$mainCssVersion" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/admin/css/admin.css?v=$version">
    <link rel="stylesheet" href="/admin/css/admin-design-system.css?v=$version">$styleLinks
    <script src="/admin/js/admin-list.js?v=$version" defer></script>
</head>
<body>
    <div class="admin-layout">
        <div class="admin-sidebar">
            <h1 class="main-title">Admin of Olympia</h1>
            <nav class="vertical-nav">
                $navigation
            </nav>
            <div class="sidebar-footer">
                <a href="/index.php" class="nav-link nav-back-to-game">&larr; Retour au jeu</a>
            </div>
        </div>

        <div class="admin-main">
            $content
        </div>
    </div>$scriptTags
    <script>(function(){
        var nav = document.querySelector('.vertical-nav');
        if (!nav) return;
        var KEY = 'adminNavOpen';
        var open = {};
        try { open = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) {}
        nav.querySelectorAll('.nav-group').forEach(function (g) {
            var title = g.querySelector('.nav-group-title');
            if (!title) return;
            var name = title.textContent.trim();
            if (open[name]) g.classList.add('nav-group-open');
            title.addEventListener('click', function () {
                open[name] = g.classList.toggle('nav-group-open');
                try { localStorage.setItem(KEY, JSON.stringify(open)); } catch (e) {}
            });
        });
    })();</script>
</body>
</html>
HTML;
}
?>
