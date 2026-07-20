<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * map_walls → map_resources : depuis la conversion des obstacles en
 * entités bâtiment (Version20260719280000_WallsToEntities), la table ne
 * porte plus que les RESSOURCES récoltables (plus autels, unique_* et
 * murs de tutoriel) — son nom suit son contenu.
 *
 * Backward-compat du déploiement (migrations avant code) : une VUE
 * `map_walls` (SELECT * sur la table renommée, donc modifiable —
 * INSERT/UPDATE/DELETE passent) garde l'ancien code fonctionnel pendant
 * la fenêtre de bascule. Elle tombera dans une migration ultérieure,
 * une fois le code renommé partout déployé.
 *
 * Idempotente : ne renomme que si map_resources n'existe pas encore.
 * map_walls_archive (archive de la conversion) garde son nom : c'est un
 * enregistrement historique, référencé par le down() de la conversion.
 */
final class Version20260720130000_RenameMapWallsToResources extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme map_walls en map_resources (vue map_walls conservée pour la bascule)';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        $hasResources = (bool) $conn->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'map_resources'"
        );

        if (!$hasResources) {
            $conn->executeStatement('RENAME TABLE map_walls TO map_resources');
        }

        // Vue de transition : l'ancien nom reste requêtable ET modifiable
        // (vue simple mono-table) tant que du code pré-renommage tourne
        $conn->executeStatement(
            'CREATE OR REPLACE VIEW map_walls AS SELECT * FROM map_resources'
        );
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;

        $conn->executeStatement('DROP VIEW IF EXISTS map_walls');

        $hasResources = (bool) $conn->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'map_resources'"
        );
        if ($hasResources) {
            $conn->executeStatement('RENAME TABLE map_resources TO map_walls');
        }
    }
}
