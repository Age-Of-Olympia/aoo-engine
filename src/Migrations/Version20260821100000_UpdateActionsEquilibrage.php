<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mise à jour de certaines actions.
 */
final class Version20260821100000_UpdateActionsEquilibrage extends AbstractMigration
{
    private const ACTION_NAME = 'saut_attaque';
    
    // Nouvelles valeurs
    private const NEW_CATEGORY = 'melee-off';
    private const NEW_COST = '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">15 PM</span>,<span style="color: #27ae60;">1 Mvt</span>';

    // Anciennes valeurs
    private const OLD_CATEGORY = 'todo';
    private const OLD_COST = '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">10 PM</span>,<span style="color: #27ae60;">1 Mvt</span>';

    public function getDescription(): string
    {
        return 'Mise à jour de la catégorie et du coût de l\'action saut_attaque';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE actions SET category = ?, cost = ? WHERE name = ?',
            [self::NEW_CATEGORY, self::NEW_COST, self::ACTION_NAME]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE actions SET category = ?, cost = ? WHERE name = ?',
            [self::OLD_CATEGORY, self::OLD_COST, self::ACTION_NAME]
        );
    }
}