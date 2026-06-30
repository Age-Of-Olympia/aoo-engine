<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
use App\Service\AdminAuthorizationService;
AdminAuthorizationService::DoAdminCheck();

/** Bump to bust the cache when admin CSS/JS changes. */
const ADMIN_ASSET_VERSION = '20260701o';

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

    // Helper function to add active class and star
    $navLink = function($page, $label, $href) use ($currentPage) {
        $isActive = ($currentPage === $page) ||
                    ($page === 'tutorial.php' && $currentPage === 'tutorial-step-editor.php') ||
                    ($page === 'players.php' && $currentPage === 'player-skills.php');
        $activeClass = $isActive ? ' active' : '';
        $star = $isActive ? '⭐ ' : '';
        return "<a href=\"$href\" class=\"nav-link$activeClass\">$star$label</a>";
    };

    /* "Tutorial" pages share a section heading + indented children so
     * the sidebar stays tidy as the tutorial admin surface grows. */
    $tutorialPages = ['tutorial-catalog.php', 'tutorial.php', 'tutorial-step-editor.php',
                      'tutorial-npcs.php', 'tutorial-settings.php'];
    $tutorialActive = in_array($currentPage, $tutorialPages, true);
    $tutorialGroupClass = $tutorialActive ? ' nav-group-open' : '';

    $tutorialSubLinks =
        $navLink('tutorial-catalog.php', 'Catalogue', '/admin/tutorial-catalog.php') . "\n                    " .
        $navLink('tutorial.php', 'Étapes', '/admin/tutorial.php') . "\n                    " .
        $navLink('tutorial-npcs.php', 'PNJ', '/admin/tutorial-npcs.php') . "\n                    " .
        $navLink('tutorial-settings.php', 'Options', '/admin/tutorial-settings.php');

    /* Map admin pages get their own group too. */
    $mapsSubLinks =
        $navLink('world_map.php', 'Carte monde', '/admin/world_map.php') . "\n                    " .
        $navLink('local_maps.php', 'Cartes locales', '/admin/local_maps.php') . "\n                    " .
        $navLink('screenshots.php', 'Captures', '/admin/screenshots.php');

    /* Action admin pages: the workbench, the per-type defaults editor, the list
     * and the passive editor. */
    $actionPages = ['action-workbench.php', 'action-type-defaults.php', 'actions.php', 'passive-workbench.php',
                    'action-import.php', 'action-import-preview.php'];
    $actionsActive = in_array($currentPage, $actionPages, true);
    $actionsGroupClass = $actionsActive ? ' nav-group-open' : '';
    $actionsSubLinks =
        $navLink('actions.php', 'Liste', '/admin/actions.php') . "\n                    " .
        $navLink('action-workbench.php', 'Configuration actions', '/admin/action-workbench.php') . "\n                    " .
        $navLink('passive-workbench.php', 'Configuration passifs', '/admin/passive-workbench.php') . "\n                    " .
        $navLink('action-type-defaults.php', 'Défauts par type', '/admin/action-type-defaults.php') . "\n                    " .
        $navLink('action-import.php', 'Importer', '/admin/action-import.php');

    /* Player admin pages: the Compétences editor (per-player actions/passives),
     * its stats overview, and the owner roster. The editor's detail page shares
     * the "Compétences" highlight with the search landing. */
    $playerPages = ['players.php', 'player-skills.php', 'skill-stats.php', 'skill-owners.php'];
    $playersActive = in_array($currentPage, $playerPages, true);
    $playersGroupClass = $playersActive ? ' nav-group-open' : '';
    $playersSubLinks =
        $navLink('players.php', 'Compétences', '/admin/players.php') . "\n                    " .
        $navLink('skill-stats.php', 'Statistiques', '/admin/skill-stats.php');

    $navigation =
        $navLink('index.php', 'Tableau de bord', '/admin/index.php') . "\n                " .
        "<div class=\"nav-group{$tutorialGroupClass}\">\n                " .
        "    <span class=\"nav-group-title\">Tutoriel</span>\n                " .
        "    <div class=\"nav-group-children\">\n                    " .
        $tutorialSubLinks . "\n                " .
        "    </div>\n                " .
        "</div>\n                " .
        "<div class=\"nav-group\">\n                " .
        "    <span class=\"nav-group-title\">Cartes</span>\n                " .
        "    <div class=\"nav-group-children\">\n                    " .
        $mapsSubLinks . "\n                " .
        "    </div>\n                " .
        "</div>\n                " .
        $navLink('upload_image.php', 'Importer images', '/admin/upload_image.php') . "\n                " .
        "<div class=\"nav-group{$actionsGroupClass}\">\n                " .
        "    <span class=\"nav-group-title\">Actions</span>\n                " .
        "    <div class=\"nav-group-children\">\n                    " .
        $actionsSubLinks . "\n                " .
        "    </div>\n                " .
        "</div>\n                " .
        "<div class=\"nav-group{$playersGroupClass}\">\n                " .
        "    <span class=\"nav-group-title\">Joueurs</span>\n                " .
        "    <div class=\"nav-group-children\">\n                    " .
        $playersSubLinks . "\n                " .
        "    </div>\n                " .
        "</div>\n                " .
        $navLink('view_recipes.php', 'Recettes', '/admin/view_recipes.php');

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
</head>
<body>
    <div class="admin-layout">
        <div class="admin-sidebar">
            <h1 class="main-title">Admin of Olympia</h1>
            <nav class="vertical-nav">
                $navigation
            </nav>
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
