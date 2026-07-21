<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove deprecated actions from players_actions table.
 */
final class Version20260721400000_CleanOldPlayersActions extends AbstractMigration
{
    private const DEPRECATED_ACTIONS = [
        'dm1/pic_de_pierre',
        'dps/poings_pierre',
        'soins/barbier',
        'special/attaque_sautee',
        'esquive/cle_de_bras',
        'dm1/lame_volante',
        'soins/imposition_des_mains',
        'dmg1/fleche_aquatique',
        'dmg2/frappe_vicieuse',
        'dps/glaciation',
        'dmg1/boule_de_magma',
        'dmg2/uppercut',
        'dmg2/assomoir',
        'dmg1/aiguillon',
        'dmg1/dard',
        'special/arme_vivante',
        'corrupt/corruption_du_bois',
        'soins/regeneration',
        'soins/flux_vital',
        'dmg2/desarmement',
        'dps/soumission',
        'special/lame_benie',
        'dps/souffle_cime',
        'soins/lien_de_vie',
        'esquive/parade',
        'special/meteore',
        'special/trait_beni',
        'esquive/leurre',
        'enchant/enchantement_de_boucliers',
        'enchant/enchantement_d_armures',
        'esquive/pas_de_cote',
        'dps/taillade',
        'dmg2/griffes',
        'dmg2/miasmes',
    ];

    public function getDescription(): string
    {
        return 'Delete rows from players_actions matching deprecated action names';
    }

    public function up(Schema $schema): void
    {
        $placeholders = implode(', ', array_fill(0, count(self::DEPRECATED_ACTIONS), '?'));
        
        $this->addSql(
            "DELETE FROM players_actions WHERE name IN ({$placeholders})",
            self::DEPRECATED_ACTIONS
        );
    }

    public function down(Schema $schema): void
    {
        // Note: Deleted player action rows cannot be automatically restored.
    }
}