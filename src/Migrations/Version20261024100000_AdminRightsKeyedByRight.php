<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Admin level overrides keyed by right (`items.edit`) instead of by page file
 * (`items.php`). The overrides of admin_menu_access are copied under their
 * right; admin_menu_access stays for the code still deployed and is dropped
 * by a later migration.
 */
final class Version20261024100000_AdminRightsKeyedByRight extends AbstractMigration
{
    /** Page key of admin_menu_access => right key of admin_rights. */
    private const RIGHT_OF_PAGE = [
        'index.php'                => 'dashboard.view',
        'monitoring.php'           => 'monitoring.view',
        'tutorial-catalog.php'     => 'tutorial.catalog',
        'tutorial.php'             => 'tutorial.steps',
        'tutorial-npcs.php'        => 'tutorial.npcs',
        'tutorial-settings.php'    => 'tutorial.settings',
        'world_map.php'            => 'maps.world',
        'plans.php'                => 'maps.plans',
        'local_maps.php'           => 'maps.local',
        'terrain-transitions.php'  => 'maps.terrain-transitions',
        'tile-assets.php'          => 'maps.tiles',
        'harvest-types.php'        => 'resources.types',
        'plant-types.php'          => 'plants.types',
        'route-types.php'          => 'routes.types',
        'footprints.php'           => 'scenery.footprints',
        'scenery-types.php'        => 'scenery.types',
        'tile-colors.php'          => 'maps.colors',
        'tile-shade.php'           => 'maps.shade',
        'map-elements.php'         => 'elements.place',
        'element-types.php'        => 'elements.types',
        'screenshots.php'          => 'maps.screenshots',
        'avatars-portraits.php'    => 'players.images',
        'actions.php'              => 'actions.list',
        'action-workbench.php'     => 'actions.edit',
        'passive-workbench.php'    => 'actions.passives',
        'action-type-defaults.php' => 'actions.type-defaults',
        'action-import.php'        => 'actions.import',
        'players.php'              => 'players.sheet',
        'pnjs.php'                 => 'players.npcs',
        'welcome-missive.php'      => 'players.welcome-missive',
        'skill-stats.php'          => 'players.stats',
        'admin-access.php'         => 'players.options',
        'recipes.php'              => 'items.recipes',
        'landing.php'              => 'landing.edit',
        'dialogs.php'              => 'dialogs.edit',
        'races.php'                => 'races.edit',
        'effects.php'              => 'effects.edit',
        'factions.php'             => 'factions.edit',
        'buildings.php'            => 'buildings.place',
        'structure-types.php'      => 'buildings.types',
        'items.php'                => 'items.edit',
        'wiki.php'                 => 'wiki.generate',
        'crons.php'                => 'tools.crons',
        'special-events.php'       => 'tools.special-events',
        'forum-moderation'         => 'forum.moderate',
    ];

    public function getDescription(): string
    {
        return 'table admin_rights (niveaux d\'accès admin par droit), reprise des surcharges de admin_menu_access';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS admin_rights (
                right_key VARCHAR(64) NOT NULL PRIMARY KEY,
                required_level VARCHAR(16) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        foreach (self::RIGHT_OF_PAGE as $page => $right) {
            $this->addSql(
                'INSERT IGNORE INTO admin_rights (right_key, required_level)
                 SELECT ?, required_level FROM admin_menu_access WHERE page = ?',
                [$right, $page]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS admin_rights');
    }
}
