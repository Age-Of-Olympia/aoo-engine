<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les plantes quittent `map_plants` et deviennent des entités.
 *
 * Même geste que pour les ressources, avec une différence qui change tout :
 * une plante ne bloque pas. Ses cases prennent donc `part` — le rôle où le
 * TYPE tranche — et le type dit `blocks_passage = 0`. Surtout pas `cover`, qui
 * est un ordre de dessin : on marche SUR une fleur, on ne se cache pas dedans.
 *
 * Les types ont été déclarés au lot précédent, avant qu'une seule ligne ne les
 * porte : c'est la leçon de `scenery`, arrivé après ses lignes, dont toutes les
 * recherches par le tronc rendaient `null` en silence.
 *
 * Une plante SANS type au catalogue n'est pas convertie — on ne devine pas une
 * famille. Le semis précédent en a créé un par nom planté, donc le cas ne
 * devrait pas se présenter ; s'il se présente, la ligne reste et se voit.
 *
 * Réversible : le `down()` reconstruit les lignes depuis les entités, puis les
 * retire. Rien n'est archivé parce que rien n'est perdu — une plante n'a ni
 * état ni histoire, seulement un nom et une case.
 */
final class Version20260802000000_PlantsBecomeEntities extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'map_plants rows become plant entities with part cells';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TEMPORARY TABLE IF EXISTS tmp_plant_conversion');
        $this->addSql(
            "CREATE TEMPORARY TABLE tmp_plant_conversion AS
             SELECT p.id AS plant_id, p.name, p.coords_id,
                    c.plan, c.z, c.x, c.y,
                    COALESCE(NULLIF(r.label, ''), p.name) AS label
               FROM map_plants p
               JOIN coords c ON c.id = p.coords_id
               JOIN races r ON r.name COLLATE utf8mb4_general_ci = p.name COLLATE utf8mb4_general_ci
                           AND r.type_kind = 'plant'"
        );

        /* Les identifiants, dans la plage des plantes, attribués d'un coup :
         * un MAX par ligne serait faux autant que lent. */
        $this->addSql(
            'ALTER TABLE tmp_plant_conversion ADD COLUMN entity_id INT NULL,
                                              ADD COLUMN display_id INT NULL'
        );
        $this->addSql(
            "UPDATE tmp_plant_conversion t
               JOIN (SELECT COALESCE(MAX(id), 59999999) AS base FROM players
                      WHERE id BETWEEN 60000000 AND 69999999) m
                SET t.entity_id = m.base + (
                        SELECT COUNT(*) + 1 FROM (SELECT plant_id FROM tmp_plant_conversion) s
                         WHERE s.plant_id < t.plant_id
                    )"
        );
        $this->addSql(
            "UPDATE tmp_plant_conversion t
               JOIN (SELECT COALESCE(MAX(display_id), 0) AS base FROM players
                      WHERE player_type = 'plant') m
                SET t.display_id = m.base + (
                        SELECT COUNT(*) + 1 FROM (SELECT plant_id FROM tmp_plant_conversion) s
                         WHERE s.plant_id < t.plant_id
                    )"
        );

        $this->addSql(
            "INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait,
                 coords_id, nextTurnTime, registerTime, text)
             SELECT t.entity_id, 'plant', t.display_id, t.label, t.name,
                    CONCAT('img/plants/', t.name, '.png'),
                    CONCAT('img/plants/', t.name, '.png'),
                    t.coords_id, 0, UNIX_TIMESTAMP(), ''
               FROM tmp_plant_conversion t"
        );

        /* `part`, et une seule case : une fleur n'a jamais eu d'emprise. */
        $this->addSql(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             SELECT t.entity_id, t.coords_id, t.plan, t.z, t.x, t.y, 0, 'part'
               FROM tmp_plant_conversion t"
        );

        $this->addSql(
            'DELETE p FROM map_plants p JOIN tmp_plant_conversion t ON t.plant_id = p.id'
        );

        $this->addSql('DROP TEMPORARY TABLE IF EXISTS tmp_plant_conversion');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO map_plants (name, coords_id)
             SELECT p.race, p.coords_id FROM players p WHERE p.player_type = 'plant'"
        );
        $this->addSql(
            "DELETE ec FROM entity_cells ec
               JOIN players p ON p.id = ec.player_id
              WHERE p.player_type = 'plant'"
        );
        $this->addSql("DELETE FROM players WHERE player_type = 'plant'");
    }
}
