<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Une ressource porte deux informations de nature différente, et elles
 * s'étaient contredites sur quelques cases.
 *
 * Le TYPE dit ce que la chose est : `resource_types.pv` positif = elle
 * se casse (un piédestal à 10 PV), négatif = elle se récolte (un arbre,
 * une pierre). La LIGNE, elle, ne porte qu'un état : `damages` négatif
 * signifie récoltable (-1) ou épuisée (-2).
 *
 * Un type qui se casse portant un état de récolte n'a pas de sens : la
 * case s'annonce « Destructible » ET « Récoltable ». Le bouton « mode
 * récolte » de l'éditeur en est la cause — son cycle n'était que
 * 0 → -1 → -2 → -1, sans retour à zéro, si bien qu'un clic de trop sur
 * un mur ordinaire ne se défaisait plus depuis ce bouton (corrigé dans
 * le même lot).
 *
 * Conséquence plus lourde que l'affichage : le passage des murs en
 * entités ne prenait que `damages >= 0`. Ces lignes-là sont donc
 * restées des ressources alors que leurs semblables devenaient des
 * entités — un piédestal sur 85, sur l'expérimental.
 *
 * Une règle étroite, et c'est délibéré : on ne corrige que les types
 * SOLIDES dont il ne reste qu'UNE ligne de ressource. Un type solide
 * qui en compte des dizaines n'est pas un oubli, c'est un type mal
 * déclaré — et trancher pour lui écraserait du jeu. L'expérimental en
 * offre l'exemple : cocotier2 et cocotier3 sont déclarés à 1 PV quand
 * cocotier1 l'est à -1, et remettre leurs 58 lignes à zéro rendrait
 * autant de palmiers non récoltables. Ceux-là demandent une décision,
 * pas une migration ; la console les signale.
 */
final class Version20260726180000_HarvestFlagOnSolidTypes extends AbstractMigration
{
    public function getDescription(): string
    {
        return "map_resources : l'état de récolte égaré sur un type solide isolé";
    }

    public function up(Schema $schema): void
    {
        /* CONVERT des deux côtés : les deux tables n'ont pas forcément le
         * même jeu de caractères d'une base à l'autre (map_resources est
         * plus ancienne), et une jointure directe échoue alors sur un
         * mélange de collations. */
        $this->addSql(
            "UPDATE map_resources AS r
             JOIN resource_types AS t
               ON CONVERT(t.name USING utf8mb4) = CONVERT(r.name USING utf8mb4)
             SET r.damages = 0
             WHERE t.pv > 0
               AND r.damages < 0
               AND (SELECT COUNT(*) FROM map_resources AS o
                    WHERE CONVERT(o.name USING utf8mb4) = CONVERT(r.name USING utf8mb4)) = 1"
        );
    }

    public function down(Schema $schema): void
    {
        /* Sans retour : on ne sait pas lesquelles étaient récoltables
         * avant le clic de trop, et les remettre toutes à -1 inventerait
         * un état. L'aller est de toute façon une remise en cohérence,
         * pas un changement de règle. */
    }
}
