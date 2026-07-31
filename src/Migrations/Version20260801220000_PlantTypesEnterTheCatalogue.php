<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les types de plantes entrent au catalogue — avant qu'une seule ligne ne les
 * porte.
 *
 * Cet ordre a déjà été payé une fois : `scenery` avait rejoint la table APRÈS
 * ses lignes, et toute recherche passant par le tronc rendait `null` pour un
 * décor, sans bruit, jusqu'à ce que quelqu'un le remarque. On déclare donc la
 * famille d'abord, on convertira les 59 plantes ensuite.
 *
 * Ce que la migration fait :
 *
 *  - elle apprend `plante` aux déclencheurs, pour que `type_kind` se remplisse
 *    seul comme pour les quatre autres familles ;
 *  - elle crée un type par nom distinct trouvé dans `map_plants`, en rendant
 *    EXPLICITE la convention qui tenait lieu de configuration : une plante
 *    rendait l'objet portant le même nom qu'elle. Ce couplage par la chaîne
 *    disparaît — le type dit ce qu'il rend, et pourra dire autre chose.
 *
 * Les plantes ne bloquent ni le pas ni les tirs : `blocks_passage = 0`,
 * `blocks_projectiles = 0`. C'est la règle du lead — on marche sur une fleur
 * sans la prendre — et elle se traduira en rôle de case `cover` à la pose.
 *
 * `harvest_exhaust` et `harvest_regrow` restent nuls : une plante ne s'épuise
 * pas, elle est cueillie et disparaît. Sa repousse est un autre mécanisme
 * (`items.grow_rate` et les déclencheurs `grow`), qui rejoindra le type dans
 * son propre lot.
 */
final class Version20260801220000_PlantTypesEnterTheCatalogue extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'plant types enter the races catalogue, before any row wears the family';
    }

    /** La règle des familles, déclencheurs compris, avec les plantes. */
    private const DERIVATION = "CASE
        WHEN NEW.kind <> 'structure' THEN 'character'
        WHEN NEW.structure_nature = 'decor' THEN 'scenery'
        WHEN NEW.structure_nature = 'ressource' THEN 'resource'
        WHEN NEW.structure_nature = 'plante' THEN 'plant'
        ELSE 'building'
    END";

    public function up(Schema $schema): void
    {
        foreach (['bi' => 'BEFORE INSERT', 'bu' => 'BEFORE UPDATE'] as $suffix => $moment) {
            $this->addSql("DROP TRIGGER IF EXISTS races_type_kind_{$suffix}");
            $this->addSql(
                "CREATE TRIGGER races_type_kind_{$suffix} {$moment} ON races FOR EACH ROW
                 SET NEW.type_kind = IF(
                     NEW.type_kind IS NULL OR NEW.type_kind = '',
                     " . self::DERIVATION . ",
                     NEW.type_kind
                 )"
            );
        }

        /* Un type par nom planté. `type_kind` n'est pas fourni : le déclencheur
         * le remplit, ce qui prouve au passage qu'il connaît la nouvelle
         * famille. Rejouable — `name` est unique, et le NOT EXISTS évite de
         * buter dessus. */
        $this->addSql(
            "INSERT INTO races
                (code, name, label, description, playable, hidden, kind, structure_nature,
                 bleeds, wound_color, blocks_passage, blocks_projectiles,
                 bgColor, color, faction, plan, pv, harvest_item)
             SELECT UPPER(p.name), p.name, CONCAT(UCASE(LEFT(p.name, 1)), SUBSTRING(p.name, 2)), '',
                    0, 1, 'structure', 'plante',
                    '', '#770001', 0, 0,
                    '#8a8a8a', 'black', '', '', 1, p.name
               FROM (SELECT DISTINCT name FROM map_plants WHERE TRIM(name) <> '') p
              WHERE NOT EXISTS (
                    SELECT 1 FROM races r
                     WHERE r.name COLLATE utf8mb4_general_ci = p.name COLLATE utf8mb4_general_ci
                )"
        );
    }

    public function down(Schema $schema): void
    {
        /* Les types plantés s'en vont ; les déclencheurs reviennent à leur
         * forme d'avant, sans la branche `plante`. */
        $this->addSql("DELETE FROM races WHERE structure_nature = 'plante'");

        foreach (['bi' => 'BEFORE INSERT', 'bu' => 'BEFORE UPDATE'] as $suffix => $moment) {
            $this->addSql("DROP TRIGGER IF EXISTS races_type_kind_{$suffix}");
            $this->addSql(
                "CREATE TRIGGER races_type_kind_{$suffix} {$moment} ON races FOR EACH ROW
                 SET NEW.type_kind = IF(
                     NEW.type_kind IS NULL OR NEW.type_kind = '',
                     CASE
                         WHEN NEW.kind <> 'structure' THEN 'character'
                         WHEN NEW.structure_nature = 'decor' THEN 'scenery'
                         WHEN NEW.structure_nature = 'ressource' THEN 'resource'
                         ELSE 'building'
                     END,
                     NEW.type_kind
                 )"
            );
        }
    }
}
