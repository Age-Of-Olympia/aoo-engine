<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
use App\Service\AdminAuthorizationService;
AdminAuthorizationService::DoAdminCheck();

/** Bump to bust the cache when admin CSS/JS changes. */
const ADMIN_ASSET_VERSION = '20260622f';

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
                    ($page === 'tutorial.php' && $currentPage === 'tutorial-step-editor.php');
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
        $navLink('tutorial-catalog.php', 'Catalog', '/admin/tutorial-catalog.php') . "\n                    " .
        $navLink('tutorial.php', 'Steps', '/admin/tutorial.php') . "\n                    " .
        $navLink('tutorial-npcs.php', 'NPCs', '/admin/tutorial-npcs.php') . "\n                    " .
        $navLink('tutorial-settings.php', 'Flags', '/admin/tutorial-settings.php');

    /* Map admin pages get their own group too. */
    $mapsSubLinks =
        $navLink('world_map.php', 'World Map', '/admin/world_map.php') . "\n                    " .
        $navLink('local_maps.php', 'Local Maps', '/admin/local_maps.php') . "\n                    " .
        $navLink('screenshots.php', 'Screenshots', '/admin/screenshots.php');

    /* Action admin pages: the workbench, the per-type defaults editor, the list
     * and the passive editor. */
    $actionPages = ['action-workbench.php', 'action-type-defaults.php', 'actions.php', 'passive-workbench.php',
                    'action-import.php', 'action-import-preview.php'];
    $actionsActive = in_array($currentPage, $actionPages, true);
    $actionsGroupClass = $actionsActive ? ' nav-group-open' : '';
    $actionsSubLinks =
        $navLink('action-workbench.php', 'Workbench', '/admin/action-workbench.php') . "\n                    " .
        $navLink('action-type-defaults.php', 'Type defaults', '/admin/action-type-defaults.php') . "\n                    " .
        $navLink('passive-workbench.php', 'Passives', '/admin/passive-workbench.php') . "\n                    " .
        $navLink('actions.php', 'List', '/admin/actions.php') . "\n                    " .
        $navLink('action-import.php', 'Import', '/admin/action-import.php');

    $navigation =
        $navLink('index.php', 'Dashboard', '/admin/index.php') . "\n                " .
        "<div class=\"nav-group{$tutorialGroupClass}\">\n                " .
        "    <span class=\"nav-group-title\">Tutorial</span>\n                " .
        "    <div class=\"nav-group-children\">\n                    " .
        $tutorialSubLinks . "\n                " .
        "    </div>\n                " .
        "</div>\n                " .
        "<div class=\"nav-group\">\n                " .
        "    <span class=\"nav-group-title\">Maps</span>\n                " .
        "    <div class=\"nav-group-children\">\n                    " .
        $mapsSubLinks . "\n                " .
        "    </div>\n                " .
        "</div>\n                " .
        $navLink('upload_image.php', 'Upload Images', '/admin/upload_image.php') . "\n                " .
        "<div class=\"nav-group{$actionsGroupClass}\">\n                " .
        "    <span class=\"nav-group-title\">Actions</span>\n                " .
        "    <div class=\"nav-group-children\">\n                    " .
        $actionsSubLinks . "\n                " .
        "    </div>\n                " .
        "</div>\n                " .
        "<!-- <a href=\"/admin/players.php\" class=\"nav-link\">Manage Players</a> -->\n                " .
        $navLink('view_recipes.php', 'View Recipes', '/admin/view_recipes.php');

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
    <link rel="stylesheet" href="/admin/css/admin.css?v=$version">$styleLinks
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
</body>
</html>
HTML;
}
?>
