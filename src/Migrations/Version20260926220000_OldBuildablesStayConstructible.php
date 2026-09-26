<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Re-applies the `constructible` type of the old buildables
 * (Version20260719190000_WallsToStructures) where the JSON seed of
 * admin/item-seed.php wrote the dead `structure` type back over them:
 * `construire` refused them all (chests, statues, walls…).
 */
final class Version20260926220000_OldBuildablesStayConstructible extends AbstractMigration
{
    private const BUILDABLES = [
        'pilier', 'pilier_nain', 'monolithe_flamboyant', 'roue_a_aubes', 'trone',
        'statue_monstrueuse', 'statue_ailee', 'statue_heroique', 'statue_forestiere',
        'statue_noble', 'statue_garde', 'statue_servant', 'statue_colosses', 'statue_gisant',
        'totem_crane', 'totem_sauvage', 'totem_magique', 'piedestal', 'piedestal_pierre',
        'tonneau', 'torche_sol', 'lanternesurpied_geant', 'tombe2',
        'coffre_bois', 'coffre_bois_petrifie', 'coffre_metal',
        'mur_noir', 'mur_bois_petrifie', 'mur_vegetal', 'mur_fer', 'mur_crepusculaire',
        'muret', 'barricade', 'mur_pierre', 'table_bois', 'route',
    ];

    public function getDescription(): string
    {
        return 'items: the old buildables reverted to type structure by the JSON seed are constructible again';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE items SET type = 'constructible', subtype = ''
              WHERE type = 'structure' AND name IN ('" . implode("', '", self::BUILDABLES) . "')"
        );
    }

    public function down(Schema $schema): void
    {
    }
}
