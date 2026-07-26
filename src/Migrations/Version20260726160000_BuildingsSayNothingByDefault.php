<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `players.text` a pour défaut « Je suis nouveau, frappez-moi! ». La
 * phrase a du sens pour un personnage qui vient de naître ; elle n'en a
 * aucun pour un mur, un coffre ou une statue.
 *
 * Comme un bâtiment EST une ligne de `players`, il en héritait — et
 * comme ce texte occupe l'emplacement de l'inscription, chaque bâtiment
 * du monde annonçait qu'il était nouveau et qu'il fallait le frapper.
 * Au recensement de l'expérimental, les 13 549 bâtiments le portaient
 * tous, sans exception.
 *
 * Un bâtiment neuf n'a rien d'inscrit dessus : il se tait. Les nouveaux
 * naissent muets (BuildingService::place pose text = ''), celle-ci
 * s'occupe de ceux qui existent déjà.
 *
 * Prudence délibérée : seul le texte de création est effacé. Un
 * bâtiment dont un animateur a écrit la description la garde.
 */
final class Version20260726160000_BuildingsSayNothingByDefault extends AbstractMigration
{
    /** Défaut de players.text, cf. BuildingService::DEFAULT_TEXT. */
    private const CREATION_TEXT = 'Je suis nouveau, frappez-moi!';

    public function getDescription(): string
    {
        return "un bâtiment neuf n'a rien d'inscrit dessus : players.text vidé pour les bâtiments";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE players SET text = ''
             WHERE player_type = 'building' AND text = ?",
            [self::CREATION_TEXT]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE players SET text = ?
             WHERE player_type = 'building' AND text = ''",
            [self::CREATION_TEXT]
        );
    }
}
