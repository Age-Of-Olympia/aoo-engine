<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Contexte d'affichage des boutons d'action (décision 2026-07-18) :
 * une condition marquée display_context gate AUSSI l'AFFICHAGE du
 * bouton dans le panneau d'observation — évaluée au rendu (miroir
 * ActionTargeting::matchesDisplayContext), en plus du filtre existant
 * par catégorie de cible (TargetType). Exemple : RequiresDistance
 * contextuelle = le bouton n'apparaît qu'à portée.
 *
 * Opt-in par condition, configurable au workbench — aucune action
 * n'est flaggée par cette migration.
 */
final class Version20260718160000_ConditionDisplayContext extends AbstractMigration
{
    public function getDescription(): string
    {
        return "action_conditions.display_context : la condition gate aussi l'affichage du bouton";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_conditions ADD display_context TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_conditions DROP COLUMN display_context');
    }
}
