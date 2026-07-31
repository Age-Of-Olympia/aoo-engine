<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * « Cassé » est un ÉTAT, pas un type d'objet.
 *
 * `resource_types` contient dix-huit entrées en `_broken` — `mur_pierre_broken`,
 * `coffre_bois_broken`, `table_bois_broken`… — qui décrivent l'apparence
 * abîmée d'un objet, pas un objet.
 *
 * Le mécanisme, lui, a déménagé : `BuildingService` bascule le sprite d'une
 * structure sur sa variante `_broken` sous la moitié de ses points de vie, et
 * `ResourceCardView` retire le suffixe pour retrouver le type de base. L'état
 * abîmé se DÉRIVE donc du type et de ses PV ; le décrire une seconde fois
 * comme un type distinct laisse deux vérités pour une seule chose.
 *
 * # Ce qui autorise à les retirer
 *
 * Aucune n'est posée. Vérifié sur la carte de production, dans les trois
 * endroits où un nom peut apparaître : `map_resources`, `map_foregrounds`,
 * et `players.race`. Zéro pose pour les dix-huit.
 *
 * Six d'entre elles n'ont même plus d'image (`mur_crepusculaire_broken`,
 * `mur_fer_broken`, `mur_vegetal_broken`, `tonneau_broken`,
 * `torche_sol_broken`, `trone_broken`) : leur bascule visuelle ne pouvait pas
 * fonctionner.
 *
 * # Ce qu'on ne touche pas
 *
 * **Les images restent.** C'est le sprite abîmé que `BuildingService` va
 * chercher : les supprimer casserait la bascule pour les douze types qui en
 * ont une.
 *
 * **`altar_broken` reste aussi.** Il est POSÉ, cinq fois, et relève du système
 * d'autels — un autre chantier. Le retirer d'ici serait décider à sa place.
 *
 * Idempotente, et sans perte : `down()` repose les lignes depuis les PV du
 * type de base, qui sont ce qu'elles portaient.
 */
final class Version20260728110000_BrokenIsAStateNotAType extends AbstractMigration
{
    /**
     * Les dix-huit types à retirer, nommés un par un.
     *
     * Un `LIKE '%_broken'` aurait emporté `altar_broken`, qui est posé. La
     * liste explicite dit exactement ce qui part, et une relecture peut la
     * vérifier ligne à ligne.
     */
    private const BROKEN_TYPES = [
        'coffre_bois_broken',
        'coffre_bois_petrifie_broken',
        'coffre_metal_broken',
        'mur_blanc_broken',
        'mur_bois_broken',
        'mur_bois_petrifie_broken',
        'mur_crepusculaire_broken',
        'mur_fer_broken',
        'mur_noir_broken',
        'mur_pierre_bleue_broken',
        'mur_pierre_broken',
        'mur_vegetal_broken',
        'piedestal_broken',
        'piedestal_pierre_broken',
        'table_bois_broken',
        'tonneau_broken',
        'torche_sol_broken',
        'trone_broken',
    ];

    public function getDescription(): string
    {
        return 'resource_types loses the 18 unplaced *_broken entries — broken is a state, images kept';
    }

    public function up(Schema $schema): void
    {
        $names = implode(', ', array_map(
            fn (string $name): string => $this->connection->quote($name),
            self::BROKEN_TYPES
        ));

        /* Garde-fou : on ne retire que ce qui n'est posé NULLE PART. Si une de
         * ces entrées a été posée entre la mesure et le déploiement, elle
         * reste — mieux vaut une ligne de trop qu'un décor sans type. */
        $this->addSql("
            DELETE t FROM resource_types t
            WHERE t.name IN ({$names})
              AND NOT EXISTS (SELECT 1 FROM map_resources m WHERE CONVERT(m.name USING utf8mb4) = CONVERT(t.name USING utf8mb4))
              AND NOT EXISTS (SELECT 1 FROM map_foregrounds f WHERE CONVERT(f.name USING utf8mb4) = CONVERT(t.name USING utf8mb4))
              AND NOT EXISTS (SELECT 1 FROM players p WHERE CONVERT(p.race USING utf8mb4) = CONVERT(t.name USING utf8mb4))
        ");
    }

    /**
     * Repose les types depuis les PV de leur type de base.
     *
     * C'est ce qu'ils portaient : un `mur_pierre_broken` à 150 PV pour un
     * `mur_pierre`. Le lien se lit en retirant le suffixe.
     */
    public function down(Schema $schema): void
    {
        foreach (self::BROKEN_TYPES as $broken) {
            $base = substr($broken, 0, -strlen('_broken'));

            $this->addSql(
                'INSERT IGNORE INTO resource_types (name, pv)
                 SELECT ' . $this->connection->quote($broken) . ', pv
                   FROM resource_types WHERE name = ' . $this->connection->quote($base)
            );
        }
    }
}
