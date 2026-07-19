<?php

namespace App\Service;

use Classes\Db;

/**
 * Per-menu access control for the admin dashboard.
 *
 * Two tiers, reusing the existing isAdmin / isSuperAdmin options: each admin
 * menu requires either 'admin' or 'superadmin'. The required level per menu is a
 * registry default (below), optionally overridden per-menu in the
 * admin_menu_access table via the superadmin-only config page
 * (admin/access-control.php).
 *
 * A page not in the registry and not overridden defaults to SUPERADMIN — the
 * dashboard is superadmin-only unless a menu is explicitly opened to admins.
 *
 * Sub-pages that hang off a menu (e.g. player-skills.php under players.php) are
 * aliased to their governing menu so they inherit its level, and so the config
 * page stays a short list of real menus.
 */
class AdminMenuAccessService
{
    public const LEVEL_ADMIN = 'admin';
    public const LEVEL_SUPERADMIN = 'superadmin';

    /**
     * Canonical admin menu registry: page => [label, group, default level].
     * Order here is the display order on the config page.
     */
    private const MENUS = [
        'index.php'                => ['Tableau de bord', 'Général', self::LEVEL_ADMIN],

        'tutorial-catalog.php'     => ['Tutoriel · Catalogue', 'Tutoriel', self::LEVEL_ADMIN],
        'tutorial.php'             => ['Tutoriel · Étapes', 'Tutoriel', self::LEVEL_ADMIN],
        'tutorial-npcs.php'        => ['Tutoriel · PNJ', 'Tutoriel', self::LEVEL_ADMIN],
        'tutorial-settings.php'    => ['Tutoriel · Options', 'Tutoriel', self::LEVEL_ADMIN],

        'world_map.php'            => ['Cartes · Carte monde', 'Cartes', self::LEVEL_ADMIN],
        // Plans : superadmin par défaut — création/suppression destructives,
        // même politique que races/factions (ajustable via access-control.php)
        'plans.php'                => ['Cartes · Plans', 'Cartes', self::LEVEL_SUPERADMIN],
        'local_maps.php'           => ['Cartes · Cartes locales', 'Cartes', self::LEVEL_ADMIN],
        'terrain-transitions.php'  => ['Cartes · Transitions de terrain', 'Cartes', self::LEVEL_ADMIN],
        'tile-assets.php'          => ['Cartes · Tuiles & images', 'Cartes', self::LEVEL_ADMIN],
        'screenshots.php'          => ['Cartes · Captures', 'Cartes', self::LEVEL_ADMIN],

        'avatars-portraits.php'    => ['Joueurs · Avatars & portraits', 'Joueurs', self::LEVEL_ADMIN],

        'actions.php'              => ['Actions · Liste', 'Actions', self::LEVEL_SUPERADMIN],
        'action-workbench.php'     => ['Actions · Configuration', 'Actions', self::LEVEL_SUPERADMIN],
        'passive-workbench.php'    => ['Actions · Passifs', 'Actions', self::LEVEL_SUPERADMIN],
        'action-type-defaults.php' => ['Actions · Défauts par type', 'Actions', self::LEVEL_SUPERADMIN],
        'action-import.php'        => ['Actions · Importer', 'Actions', self::LEVEL_SUPERADMIN],

        'players.php'              => ['Joueurs · Compétences', 'Joueurs', self::LEVEL_ADMIN],
        'pnjs.php'                 => ['Joueurs · PNJ', 'Joueurs', self::LEVEL_ADMIN],
        'skill-stats.php'          => ['Joueurs · Statistiques', 'Joueurs', self::LEVEL_ADMIN],
        'admin-access.php'         => ['Joueurs · Accès & options', 'Joueurs', self::LEVEL_SUPERADMIN],

        'recipes.php'              => ['Objets · Recettes', 'Divers', self::LEVEL_SUPERADMIN],
        // Accueil : contenu éditorial de la page d'accueil (présentation,
        // chroniques, galerie) — pas de logique de jeu, niveau admin
        'landing.php'              => ['Page d\'accueil', 'Divers', self::LEVEL_ADMIN],
        // Dialogues : superadmin par défaut — même politique que races
        // (contenu de jeu éditable, suppression possible)
        'dialogs.php'              => ['Dialogues', 'Divers', self::LEVEL_SUPERADMIN],
        'races.php'                => ['Races', 'Divers', self::LEVEL_SUPERADMIN],
        // Effets : catalogue de gameplay (icônes, buffs, corruptions) —
        // même politique que races
        'effects.php'              => ['Effets', 'Divers', self::LEVEL_SUPERADMIN],
        'factions.php'             => ['Factions', 'Divers', self::LEVEL_SUPERADMIN],
        // Bâtiments : pose/retrait sur la carte — travail d'animation, même
        // niveau que les PNJ
        'buildings.php'            => ['Bâtiments · Posés', 'Divers', self::LEVEL_ADMIN],
        // Types de bâtiments : second visage de la table races — même
        // politique que races (contenu de gameplay, suppression possible)
        'structure-types.php'      => ['Bâtiments · Types', 'Divers', self::LEVEL_SUPERADMIN],
        // Objets : flags et réglages d'usure — même politique que races
        'items.php'                => ['Objets', 'Divers', self::LEVEL_SUPERADMIN],
        // Wiki : génération de fiches en lecture seule — niveau admin
        'wiki.php'                 => ['Wiki', 'Divers', self::LEVEL_ADMIN],
    ];

    /**
     * Sub-pages that inherit a menu's level (page => governing menu). Rendered
     * via layout.php but not themselves nav entries.
     */
    private const ALIASES = [
        'upload_image.php'           => 'avatars-portraits.php', // redirection legacy
        'structure-images.php'       => 'avatars-portraits.php', // même stock, visage Bâtiments
        'plans-save.php'             => 'plans.php',
        'dialogs-save.php'           => 'dialogs.php',
        'dialog-seed.php'            => 'dialogs.php',
        'races-save.php'             => 'races.php',
        'race-seed.php'              => 'races.php',
        'effects-save.php'           => 'effects.php',
        'factions-save.php'          => 'factions.php',
        'faction-seed.php'           => 'factions.php',
        'faction-members.php'        => 'factions.php',
        'faction-members-save.php'   => 'factions.php',
        'player-skills.php'          => 'players.php',
        'player-edit.php'            => 'players.php',
        'skill-owners.php'           => 'players.php',
        'tutorial-step-editor.php'   => 'tutorial.php',
        'action-import-preview.php'  => 'action-import.php',
        'landing-save.php'           => 'landing.php',
        'landing-seed.php'           => 'landing.php',
        'buildings-save.php'         => 'buildings.php',
        'items-save.php'             => 'items.php',
        'item-seed.php'              => 'items.php',
        'recipes-save.php'           => 'recipes.php',
        'view_recipes.php'           => 'recipes.php',
    ];

    /** @var array<string,string>|null cached DB overrides (page => level) */
    private ?array $overrideCache = null;

    /**
     * Resolve a page to the menu that governs its access (itself, or its alias).
     */
    private function resolveMenu(string $page): string
    {
        return self::ALIASES[$page] ?? $page;
    }

    /**
     * Required level for a page: DB override, else registry default, else
     * SUPERADMIN (unknown pages are locked down by default).
     */
    public function getRequiredLevel(string $page): string
    {
        $menu = $this->resolveMenu($page);

        $overrides = $this->overrides();
        if (isset($overrides[$menu])) {
            return $overrides[$menu];
        }

        return self::MENUS[$menu][2] ?? self::LEVEL_SUPERADMIN;
    }

    /**
     * Can a viewer (already an admin, since they reached the dashboard) open
     * this page? Superadmins can open anything; plain admins only admin-level
     * pages.
     */
    public function canAccess(string $page, bool $viewerIsSuperAdmin): bool
    {
        if ($viewerIsSuperAdmin) {
            return true;
        }
        return $this->getRequiredLevel($page) === self::LEVEL_ADMIN;
    }

    /**
     * Enforce the current page's required level: always require admin, and
     * escalate to super-admin when the page (or its governing menu) demands it.
     * DoAdminCheck / DoSuperAdminCheck exit() on failure.
     */
    public function enforce(string $page): void
    {
        AdminAuthorizationService::DoAdminCheck();
        if ($this->getRequiredLevel($page) === self::LEVEL_SUPERADMIN) {
            AdminAuthorizationService::DoSuperAdminCheck();
        }
    }

    /**
     * The configurable menus with their current effective level, for the config
     * page. Grouped in registry order.
     *
     * @return array<int, array{page:string, label:string, group:string, level:string, default:string}>
     */
    public function getConfigurableMenus(): array
    {
        $overrides = $this->overrides();

        $out = [];
        foreach (self::MENUS as $page => [$label, $group, $default]) {
            $out[] = [
                'page'    => $page,
                'label'   => $label,
                'group'   => $group,
                'level'   => $overrides[$page] ?? $default,
                'default' => $default,
            ];
        }
        return $out;
    }

    /**
     * Set (or reset) a menu's required level. Only registry menus are settable;
     * an invalid page or level is rejected. Passing the registry default clears
     * the override row so the menu tracks the default again.
     */
    public function setLevel(string $page, string $level): void
    {
        if (!isset(self::MENUS[$page])) {
            return;
        }
        if (!in_array($level, [self::LEVEL_ADMIN, self::LEVEL_SUPERADMIN], true)) {
            return;
        }

        $this->ensureTable();
        $db = new Db();

        if ($level === self::MENUS[$page][2]) {
            // Back to default → drop any override row.
            $db->exe('DELETE FROM admin_menu_access WHERE page = ?', [$page]);
        } else {
            $db->exe(
                'INSERT INTO admin_menu_access (page, required_level) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE required_level = VALUES(required_level)',
                [$page, $level]
            );
        }

        $this->overrideCache = null;
    }

    /**
     * DB overrides (page => level). Cached per instance; degrades to empty (all
     * registry defaults) if the table is absent, so the dashboard keeps working
     * before the migration runs.
     *
     * @return array<string,string>
     */
    private function overrides(): array
    {
        if ($this->overrideCache !== null) {
            return $this->overrideCache;
        }

        $this->overrideCache = [];
        try {
            $res = (new Db())->exe('SELECT page, required_level FROM admin_menu_access');
            while ($row = $res->fetch_assoc()) {
                $this->overrideCache[(string) $row['page']] = (string) $row['required_level'];
            }
        } catch (\Throwable $e) {
            // Table not created yet → registry defaults only.
        }

        return $this->overrideCache;
    }

    /**
     * Create the overrides table if it does not exist. Keeps the feature working
     * even where the Doctrine migration has not been applied; idempotent.
     */
    private function ensureTable(): void
    {
        (new Db())->exe(
            "CREATE TABLE IF NOT EXISTS admin_menu_access (
                page VARCHAR(64) NOT NULL PRIMARY KEY,
                required_level VARCHAR(16) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
