<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011120000_FactionAnimateur extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'factions.animateurId : l\'animateur passe de la race à la faction (races.animateurId gardée pour l\'ancien code)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE factions ADD COLUMN IF NOT EXISTS animateurId INT DEFAULT NULL');

        // The playable race's animateur wins; otherwise only when the faction's races agree on one.
        // Only a PNJ answers for a faction: a player account (Orcrist, Glenefal…) gives way to Gaïa (-91).
        $this->addSql(
            'UPDATE factions f
               JOIN (SELECT faction,
                            COALESCE(MIN(CASE WHEN playable = 1 THEN animateur END),
                                     IF(COUNT(DISTINCT animateur) = 1, MIN(animateur), NULL)) AS animateurId
                       FROM (SELECT r.faction, r.playable,
                                    IF(p.player_type = \'npc\', r.animateurId, -91) AS animateur
                               FROM races r LEFT JOIN players p ON p.id = r.animateurId
                              WHERE r.animateurId IS NOT NULL) a
                      GROUP BY faction) r ON CONVERT(r.faction USING utf8mb4) = CONVERT(f.code USING utf8mb4)
                SET f.animateurId = r.animateurId
              WHERE f.animateurId IS NULL AND r.animateurId IS NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE factions DROP COLUMN IF EXISTS animateurId');
    }
}
