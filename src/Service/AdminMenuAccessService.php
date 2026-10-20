<?php

namespace App\Service;

use Classes\Db;
use Classes\Player;

/**
 * Admin rights: one entry per thing an admin may do, named after that thing
 * (`items.edit`, `buildings.place`), each requiring 'admin' or 'superadmin'.
 *
 * The level is a registry default (below), optionally overridden in the
 * admin_rights table from the superadmin-only page admin/access-control.php.
 * Panel pages reach their right through PAGES; console commands name theirs
 * (Command::accessKey()), so one setting governs both sides.
 *
 * An unknown right or page resolves to SUPERADMIN: nothing opens to plain
 * admins unless declared here.
 */
class AdminMenuAccessService
{
    public const LEVEL_ADMIN = 'admin';
    public const LEVEL_SUPERADMIN = 'superadmin';

    /**
     * right => [label, group, default level]. Order here is the display order
     * on the config page.
     */
    private const RIGHTS = [
        'dashboard.view'           => ['Tableau de bord', 'Général', self::LEVEL_ADMIN],
        // Read-only, but shows admin rights, accounts and deployments
        'monitoring.view'          => ['Monitoring', 'Général', self::LEVEL_SUPERADMIN],

        'tutorial.catalog'         => ['Tutoriel · Catalogue', 'Tutoriel', self::LEVEL_ADMIN],
        'tutorial.steps'           => ['Tutoriel · Étapes', 'Tutoriel', self::LEVEL_ADMIN],
        'tutorial.npcs'            => ['Tutoriel · PNJ', 'Tutoriel', self::LEVEL_ADMIN],
        'tutorial.settings'        => ['Tutoriel · Options', 'Tutoriel', self::LEVEL_ADMIN],

        'maps.world'               => ['Cartes · Carte monde', 'Cartes', self::LEVEL_ADMIN],
        // Creating and deleting plans is destructive
        'maps.plans'               => ['Cartes · Plans', 'Cartes', self::LEVEL_SUPERADMIN],
        'maps.local'               => ['Cartes · Cartes locales', 'Cartes', self::LEVEL_ADMIN],
        'maps.terrain-transitions' => ['Cartes · Transitions de terrain', 'Cartes', self::LEVEL_ADMIN],
        'maps.tiles'               => ['Cartes · Tuiles & images', 'Cartes', self::LEVEL_ADMIN],
        'maps.colors'              => ['Cartes · Couleurs de carte', 'Cartes', self::LEVEL_ADMIN],
        'maps.shade'               => ['Cartes · Ombres des cases', 'Cartes', self::LEVEL_SUPERADMIN],
        'maps.screenshots'         => ['Cartes · Captures', 'Cartes', self::LEVEL_ADMIN],
        // Type catalogs are gameplay content: same policy as races
        'resources.types'          => ['Ressources · Types récoltables', 'Ressources', self::LEVEL_SUPERADMIN],
        'plants.types'             => ['Plantes · Types', 'Plantes', self::LEVEL_SUPERADMIN],
        'routes.types'             => ['Routes · Types', 'Routes', self::LEVEL_SUPERADMIN],
        'scenery.footprints'       => ['Décors · Formes', 'Décors', self::LEVEL_SUPERADMIN],
        'scenery.types'            => ['Décors · Types', 'Décors', self::LEVEL_SUPERADMIN],
        'elements.place'           => ['Éléments · Posés', 'Éléments', self::LEVEL_ADMIN],
        'elements.types'           => ['Éléments · Types', 'Éléments', self::LEVEL_SUPERADMIN],
        'effects.edit'             => ['Éléments · Effets', 'Éléments', self::LEVEL_SUPERADMIN],

        'actions.list'             => ['Actions · Liste', 'Actions', self::LEVEL_SUPERADMIN],
        'actions.edit'             => ['Actions · Configuration', 'Actions', self::LEVEL_SUPERADMIN],
        'actions.passives'         => ['Actions · Passifs', 'Actions', self::LEVEL_SUPERADMIN],
        'actions.type-defaults'    => ['Actions · Défauts par type', 'Actions', self::LEVEL_SUPERADMIN],
        'actions.import'           => ['Actions · Importer', 'Actions', self::LEVEL_SUPERADMIN],

        'players.sheet'            => ['Joueurs · Compétences et fiche', 'Joueurs', self::LEVEL_ADMIN],
        'players.rank-removal'     => ['Joueurs · Retrait de rang', 'Joueurs', self::LEVEL_SUPERADMIN],
        'players.npcs'             => ['Joueurs · PNJ', 'Joueurs', self::LEVEL_ADMIN],
        'players.images'           => ['Joueurs · Avatars & portraits', 'Joueurs', self::LEVEL_ADMIN],
        'players.welcome-missive'  => ['Joueurs · Missive d\'accueil', 'Joueurs', self::LEVEL_ADMIN],
        'players.stats'            => ['Joueurs · Statistiques', 'Joueurs', self::LEVEL_ADMIN],
        'players.options'          => ['Joueurs · Accès & options', 'Joueurs', self::LEVEL_SUPERADMIN],

        'items.edit'               => ['Objets', 'Divers', self::LEVEL_SUPERADMIN],
        'items.recipes'            => ['Objets · Recettes', 'Divers', self::LEVEL_SUPERADMIN],
        'landing.edit'             => ['Page d\'accueil', 'Divers', self::LEVEL_SUPERADMIN],
        'dialogs.edit'             => ['Dialogues', 'Divers', self::LEVEL_SUPERADMIN],
        'races.edit'               => ['Races', 'Divers', self::LEVEL_SUPERADMIN],
        'factions.edit'            => ['Factions', 'Divers', self::LEVEL_SUPERADMIN],
        'buildings.place'          => ['Bâtiments · Posés', 'Divers', self::LEVEL_SUPERADMIN],
        'buildings.types'          => ['Bâtiments · Types', 'Divers', self::LEVEL_SUPERADMIN],
        'wiki.generate'            => ['Wiki', 'Divers', self::LEVEL_ADMIN],
        // Replays scheduled scripts, deletions included
        'tools.crons'              => ['Outils · Crons', 'Divers', self::LEVEL_SUPERADMIN],
        'tools.special-events'     => ['Outils · Évènements spéciaux', 'Divers', self::LEVEL_SUPERADMIN],

        'forum.moderate'           => ['Forum · Modération (supprimer, renommer)', 'Forum', self::LEVEL_ADMIN],

        // Console commands with no panel page
        'console.player'           => ['Console · player (PJ, banque, journal, msg, purge)', 'Console', self::LEVEL_ADMIN],
        'console.session'          => ['Console · session', 'Console', self::LEVEL_ADMIN],
        'console.quest'            => ['Console · quest', 'Console', self::LEVEL_ADMIN],
        'console.missive'          => ['Console · missive', 'Console', self::LEVEL_ADMIN],
        'console.entity-cells'     => ['Console · entity-cells', 'Console', self::LEVEL_ADMIN],
        'console.fence'            => ['Console · fence', 'Console', self::LEVEL_ADMIN],
        'console.plants-on-roads'  => ['Console · plants-on-roads', 'Console', self::LEVEL_ADMIN],
        'console.objet'            => ['Console · objet', 'Console', self::LEVEL_SUPERADMIN],
        'console.edit'             => ['Console · edit', 'Console', self::LEVEL_SUPERADMIN],
        'console.forum'            => ['Console · forum (index)', 'Console', self::LEVEL_SUPERADMIN],
        'console.rgpd'             => ['Console · rgpd', 'Console', self::LEVEL_SUPERADMIN],
        'console.season'           => ['Console · season', 'Console', self::LEVEL_SUPERADMIN],
        'console.tool'             => ['Console · tool', 'Console', self::LEVEL_SUPERADMIN],
    ];

    /** Panel page => the right it needs. */
    public const PAGES = [
        'index.php'                => 'dashboard.view',
        'monitoring.php'           => 'monitoring.view',
        'tutorial-catalog.php'     => 'tutorial.catalog',
        'tutorial-launcher.php'    => 'tutorial.catalog',
        'tutorial.php'             => 'tutorial.steps',
        'tutorial-step-editor.php' => 'tutorial.steps',
        'tutorial-step-save.php'   => 'tutorial.steps',
        'tutorial-sessions-api.php' => 'tutorial.steps',
        'tutorial-npcs.php'        => 'tutorial.npcs',
        'tutorial-npcs-save.php'   => 'tutorial.npcs',
        'tutorial-settings.php'    => 'tutorial.settings',
        'world_map.php'            => 'maps.world',
        'plans.php'                => 'maps.plans',
        'plans-save.php'           => 'maps.plans',
        'plan-season-rename.php'   => 'maps.plans',
        'local_maps.php'           => 'maps.local',
        'terrain-transitions.php'  => 'maps.terrain-transitions',
        'tile-assets.php'          => 'maps.tiles',
        'tile-colors.php'          => 'maps.colors',
        'tile-colors-save.php'     => 'maps.colors',
        'tile-shade.php'           => 'maps.shade',
        'screenshots.php'          => 'maps.screenshots',
        'harvest-types.php'        => 'resources.types',
        'harvest-seed.php'         => 'resources.types',
        'plant-types.php'          => 'plants.types',
        'route-types.php'          => 'routes.types',
        'footprints.php'           => 'scenery.footprints',
        'footprints-save.php'      => 'scenery.footprints',
        'scenery-types.php'        => 'scenery.types',
        'map-elements.php'         => 'elements.place',
        'element-types.php'        => 'elements.types',
        'effects.php'              => 'effects.edit',
        'effects-save.php'         => 'effects.edit',
        'actions.php'              => 'actions.list',
        'action-workbench.php'     => 'actions.edit',
        'action-create.php'        => 'actions.edit',
        'action-save.php'          => 'actions.edit',
        'action-delete.php'        => 'actions.edit',
        'action-condition-add.php' => 'actions.edit',
        'action-condition-remove.php' => 'actions.edit',
        'action-instruction-add.php' => 'actions.edit',
        'action-instruction-remove.php' => 'actions.edit',
        'action-outcome-add.php'   => 'actions.edit',
        'action-outcome-remove.php' => 'actions.edit',
        'passive-workbench.php'    => 'actions.passives',
        'passive-create.php'       => 'actions.passives',
        'passive-save.php'         => 'actions.passives',
        'passive-delete.php'       => 'actions.passives',
        'action-type-defaults.php' => 'actions.type-defaults',
        'action-type-save.php'     => 'actions.type-defaults',
        'action-type-log-save.php' => 'actions.type-defaults',
        'action-type-xp-save.php'  => 'actions.type-defaults',
        'action-type-instruction-add.php' => 'actions.type-defaults',
        'action-type-instruction-remove.php' => 'actions.type-defaults',
        'action-type-precondition-add.php' => 'actions.type-defaults',
        'action-type-precondition-remove.php' => 'actions.type-defaults',
        'action-type-precondition-save.php' => 'actions.type-defaults',
        'action-import.php'        => 'actions.import',
        'action-import-preview.php' => 'actions.import',
        'action-import-step.php'   => 'actions.import',
        'action-import-commit.php' => 'actions.import',
        'players.php'              => 'players.sheet',
        'player-skills.php'        => 'players.sheet',
        'player-skills-save.php'   => 'players.sheet',
        'player-edit.php'          => 'players.sheet',
        'skill-owners.php'         => 'players.sheet',
        'pnjs.php'                 => 'players.npcs',
        'pnjs-save.php'            => 'players.npcs',
        'avatars-portraits.php'    => 'players.images',
        'upload_image.php'         => 'players.images',
        'structure-images.php'     => 'players.images',
        'welcome-missive.php'      => 'players.welcome-missive',
        'welcome-missive-save.php' => 'players.welcome-missive',
        'skill-stats.php'          => 'players.stats',
        'admin-access.php'         => 'players.options',
        'admin-access-toggle.php'  => 'players.options',
        'items.php'                => 'items.edit',
        'items-save.php'           => 'items.edit',
        'item-owners.php'          => 'items.edit',
        'recipes.php'              => 'items.recipes',
        'recipes-save.php'         => 'items.recipes',
        'view_recipes.php'         => 'items.recipes',
        'landing.php'              => 'landing.edit',
        'landing-save.php'         => 'landing.edit',
        'landing-seed.php'         => 'landing.edit',
        'dialogs.php'              => 'dialogs.edit',
        'dialogs-save.php'         => 'dialogs.edit',
        'races.php'                => 'races.edit',
        'races-save.php'           => 'races.edit',
        'factions.php'             => 'factions.edit',
        'factions-save.php'        => 'factions.edit',
        'faction-members.php'      => 'factions.edit',
        'faction-members-save.php' => 'factions.edit',
        'buildings.php'            => 'buildings.place',
        'buildings-save.php'       => 'buildings.place',
        'structure-types.php'      => 'buildings.types',
        'entity-kinds.php'         => 'buildings.types',
        'wiki.php'                 => 'wiki.generate',
        'crons.php'                => 'tools.crons',
        'special-events.php'       => 'tools.special-events',
    ];

    /** @var array<string,string>|null cached DB overrides (right => level) */
    private ?array $overrideCache = null;

    /** The right a panel page needs, null when the page is not declared. */
    public function rightOfPage(string $page): ?string
    {
        return self::PAGES[$page] ?? null;
    }

    /**
     * Required level for a right: DB override, else registry default, else
     * SUPERADMIN.
     */
    public function getRequiredLevel(string $right): string
    {
        return $this->overrides()[$right] ?? self::RIGHTS[$right][2] ?? self::LEVEL_SUPERADMIN;
    }

    public function canAccess(string $right, bool $viewerIsSuperAdmin): bool
    {
        return $viewerIsSuperAdmin || $this->getRequiredLevel($right) === self::LEVEL_ADMIN;
    }

    public function canAccessPage(string $page, bool $viewerIsSuperAdmin): bool
    {
        return $viewerIsSuperAdmin
            || (($right = $this->rightOfPage($page)) !== null && $this->canAccess($right, false));
    }

    /**
     * In-game check of a right, for a viewer who may not be an admin.
     */
    public function allows(string $right, Player $player): bool
    {
        if (!$player->have_option('isAdmin')) {
            return false;
        }
        return $this->canAccess($right, (bool) $player->have_option('isSuperAdmin'));
    }

    /**
     * allows() for the character currently played.
     */
    public function viewerAllows(string $right): bool
    {
        return AdminAuthorizationService::isAdmin()
            && $this->canAccess($right, AdminAuthorizationService::isSuperAdmin());
    }

    /** Whether the registry declares this right (unknown ones resolve to super-admin). */
    public function isDeclared(string $right): bool
    {
        return isset(self::RIGHTS[$right]);
    }

    /**
     * Require admin, and super-admin when the right demands it.
     * DoAdminCheck / DoSuperAdminCheck exit() on failure.
     */
    public function enforce(string $right): void
    {
        AdminAuthorizationService::DoAdminCheck();
        if ($this->getRequiredLevel($right) === self::LEVEL_SUPERADMIN) {
            AdminAuthorizationService::DoSuperAdminCheck();
        }
    }

    /** enforce() on a page's right; an undeclared page needs super-admin. */
    public function enforcePage(string $page): void
    {
        $right = $this->rightOfPage($page);
        if ($right === null) {
            AdminAuthorizationService::DoSuperAdminCheck();
            return;
        }
        $this->enforce($right);
    }

    /**
     * Every right with its current effective level, for the config page, in
     * registry order.
     *
     * @return array<int, array{right:string, label:string, group:string, level:string, default:string}>
     */
    public function getConfigurableRights(): array
    {
        $overrides = $this->overrides();

        $out = [];
        foreach (self::RIGHTS as $right => [$label, $group, $default]) {
            $out[] = [
                'right'   => $right,
                'label'   => $label,
                'group'   => $group,
                'level'   => $overrides[$right] ?? $default,
                'default' => $default,
            ];
        }
        return $out;
    }

    /**
     * Set (or reset) a right's required level. Unknown rights and levels are
     * ignored; the registry default clears the override row.
     */
    public function setLevel(string $right, string $level): void
    {
        if (!isset(self::RIGHTS[$right])) {
            return;
        }
        if (!in_array($level, [self::LEVEL_ADMIN, self::LEVEL_SUPERADMIN], true)) {
            return;
        }

        $db = new Db();

        if ($level === self::RIGHTS[$right][2]) {
            $db->exe('DELETE FROM admin_rights WHERE right_key = ?', [$right]);
        } else {
            $db->exe(
                'INSERT INTO admin_rights (right_key, required_level) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE required_level = VALUES(required_level)',
                [$right, $level]
            );
        }

        $this->overrideCache = null;
    }

    /**
     * DB overrides (right => level). Cached per instance.
     *
     * @return array<string,string>
     */
    private function overrides(): array
    {
        if ($this->overrideCache !== null) {
            return $this->overrideCache;
        }

        $this->overrideCache = [];
        $res = (new Db())->exe('SELECT right_key, required_level FROM admin_rights');
        while ($row = $res->fetch_assoc()) {
            $this->overrideCache[(string) $row['right_key']] = (string) $row['required_level'];
        }

        return $this->overrideCache;
    }
}
