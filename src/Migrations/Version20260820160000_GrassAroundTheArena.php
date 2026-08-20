<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * De l'herbe tout autour de l'arène du tutoriel.
 *
 * Le damier montre jusqu'à quatre cases autour du joueur ; adossé au mur
 * d'enceinte (position ±3), il voit donc jusqu'à ±7 — au-delà du plan, qui
 * s'arrêtait à ±4. Les cases inexistantes se rendaient en icônes d'images
 * cassées sur le bord du damier.
 *
 * Le plan modèle s'étend à ±7 et chaque case sans tuile reçoit l'herbe
 * d'Eryn Dolen (le tapis intérieur existant est laissé tel quel). Les
 * instances copient coords et tuiles du modèle : chaque nouvelle session
 * naît avec sa prairie. L'enceinte reste infranchissable — l'extérieur
 * n'est qu'un décor.
 */
final class Version20260820160000_GrassAroundTheArena extends AbstractMigration
{
    private const REACH = 7;

    public function getDescription(): string
    {
        return "plan tutorial: cases jusqu'à ±7 et herbe partout où une tuile manque";
    }

    public function up(Schema $schema): void
    {
        $axis = [];
        for ($n = -self::REACH; $n <= self::REACH; $n++) {
            $axis[] = 'SELECT ' . $n . ' AS n';
        }
        $axisSql = implode(' UNION ALL ', $axis);

        $this->addSql("
            INSERT INTO coords (x, y, z, plan)
            SELECT gx.n, gy.n, 0, 'tutorial'
              FROM ({$axisSql}) gx
             CROSS JOIN ({$axisSql}) gy
             WHERE NOT EXISTS (
                SELECT 1 FROM coords c
                 WHERE c.plan = 'tutorial' AND c.z = 0 AND c.x = gx.n AND c.y = gy.n
             )
        ");

        $this->addSql("
            INSERT INTO map_tiles (name, coords_id, foreground)
            SELECT 'eryn_dolen', c.id, 0
              FROM coords c
             WHERE c.plan = 'tutorial' AND c.z = 0
               AND NOT EXISTS (SELECT 1 FROM map_tiles mt WHERE mt.coords_id = c.id)
        ");
    }

    public function down(Schema $schema): void
    {
        // Retire les cases AJOUTÉES (au-delà de ±4) et leurs tuiles ; le
        // tapis intérieur d'origine et l'arène restent intacts.
        $this->addSql("
            DELETE mt FROM map_tiles mt
              JOIN coords c ON c.id = mt.coords_id
             WHERE c.plan = 'tutorial' AND c.z = 0
               AND (ABS(c.x) > 4 OR ABS(c.y) > 4)
        ");

        $this->addSql("
            DELETE FROM coords
             WHERE plan = 'tutorial' AND z = 0
               AND (ABS(x) > 4 OR ABS(y) > 4)
        ");

        // L'herbe posée sur le rang des murs (±4) par cette migration.
        $this->addSql("
            DELETE mt FROM map_tiles mt
              JOIN coords c ON c.id = mt.coords_id
             WHERE c.plan = 'tutorial' AND c.z = 0
               AND (ABS(c.x) = 4 OR ABS(c.y) = 4)
               AND mt.name = 'eryn_dolen'
        ");
    }
}
