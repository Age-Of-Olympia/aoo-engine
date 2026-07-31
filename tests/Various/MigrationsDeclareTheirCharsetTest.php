<?php

namespace Tests\Various;

use PHPUnit\Framework\TestCase;

/**
 * Une table créée par une migration déclare son jeu de caractères.
 *
 * Sans déclaration, une table aux colonnes écrites à la main prend le défaut de
 * la BASE. Il vaut utf8mb4 en développement et latin1 sur un hébergement plus
 * ancien : la migration part donc en production avec des colonnes d'un jeu que
 * personne n'a choisi, et la première comparaison un peu stricte échoue.
 *
 * C'est arrivé sur une table TEMPORAIRE, la pire pour enquêter : elle naît et
 * meurt dans la migration, si bien qu'après l'échec plus aucune colonne latin1
 * n'existe dans le schéma. On cherche longtemps une trace qui a déjà disparu.
 *
 * `CREATE TABLE ... AS SELECT` est hors de cause : ses colonnes héritent du jeu
 * de leurs sources, mesuré dans une base dont le défaut était latin1.
 */
class MigrationsDeclareTheirCharsetTest extends TestCase
{
    public function testEveryTableCreatedByAMigrationDeclaresItsCharset(): void
    {
        $offenders = [];

        foreach (glob(dirname(__DIR__, 2) . '/src/Migrations/*.php') ?: [] as $file) {
            foreach (file($file) ?: [] as $no => $line) {
                /* Le moteur et le jeu se déclarent ensemble, sur la ligne qui
                 * ferme la table. Une ligne qui pose l'un sans l'autre est
                 * précisément l'oubli qu'on traque. */
                if (!preg_match('/ENGINE\s*=/i', $line)) {
                    continue;
                }

                if (preg_match('/CHARSET\s*=|CHARACTER SET/i', $line)) {
                    continue;
                }

                $offenders[] = basename($file) . ':' . ($no + 1) . ' → ' . trim($line);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Ces tables prendront le jeu de caractères de la base — latin1 sur un vieux serveur.\n"
            . "Ajouter DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci.\n"
            . implode("\n", $offenders)
        );
    }
}
