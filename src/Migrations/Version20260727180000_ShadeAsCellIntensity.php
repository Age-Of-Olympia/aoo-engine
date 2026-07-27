<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'ombre devient une intensité de case, au lieu de lignes empilées.
 *
 * `ombre` est un unique PNG de 50×50, noir uni à ~5,5 % d'opacité. Pour
 * assombrir davantage, les animateurs le posent PLUSIEURS FOIS sur la même
 * case : c'est un dégradé peint à la main, sur cinq niveaux.
 *
 * Mesuré sur la carte de production du 26 juillet :
 *
 *   1 ombre : 7 104 cases      4 ombres :  31
 *   2 ombres :  319            5 ombres :   5
 *   3 ombres :  154
 *
 * soit 8 353 lignes pour 7 613 cases — **82 % de `map_foregrounds`**, qui
 * tombe de 10 215 à 1 862 lignes et redevient lisible.
 *
 * # Pourquoi une colonne et pas un dédoublonnage
 *
 * Le premier jet du plan disait « dédoublonner les 509 cases doublement
 * assombries ». C'était une erreur, et elle aurait détruit du travail : ces
 * cases ne sont pas des doublons, ce sont les plus sombres. Les aplatir
 * effaçait le dégradé sans que rien ne le signale.
 *
 * L'empilement EST l'intensité — mais exprimée en répétant une ligne de
 * table, ce qu'aucun code ne peut lire comme telle : ni l'éditeur pour
 * offrir un curseur, ni le rendu pour n'en dessiner qu'un. La colonne rend
 * l'intention lisible.
 *
 * # Fidélité du rendu
 *
 * N calques d'opacité `a` donnent une opacité résultante `1-(1-a)^N`. Le
 * rendu dessine donc UN rectangle de cette opacité au lieu de N images :
 * mêmes pixels exactement, cinq éléments de moins sur les cases les plus
 * sombres.
 *
 * # La couleur, plus tard
 *
 * Le seul outil d'aujourd'hui est du noir — `tile_colors` associe une
 * couleur à un NOM de décor (la carte du monde), pas à une case. Si une
 * teinte est voulue un jour, elle s'ajoutera en colonne avec le noir par
 * défaut, sans reprendre une seule des 7 613 cases.
 *
 * Idempotente : une seconde exécution ne trouve plus d'ombre à convertir.
 */
final class Version20260727180000_ShadeAsCellIntensity extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'coords.shade — stacked "ombre" foreground rows become a per-cell intensity (0-5)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE coords
            ADD COLUMN IF NOT EXISTS shade TINYINT UNSIGNED NOT NULL DEFAULT 0
            COMMENT 'Assombrissement de la case, 0 = aucun. Chaque niveau vaut un calque noir à 5,5 %'
        ");

        /* Le niveau est le NOMBRE de calques posés : c'est très exactement ce
         * que l'empilement voulait dire. */
        $this->addSql("
            UPDATE coords c
            JOIN (
                SELECT coords_id, COUNT(*) AS n
                FROM map_foregrounds
                WHERE name = 'ombre'
                GROUP BY coords_id
            ) o ON o.coords_id = c.id
            SET c.shade = LEAST(o.n, 255)
        ");

        $this->addSql("DELETE FROM map_foregrounds WHERE name = 'ombre'");
    }

    public function down(Schema $schema): void
    {
        /* Reposer autant de lignes que de niveaux : la table de nombres est
         * construite à la volée, MariaDB n'ayant pas de générateur de séries. */
        $this->addSql("
            INSERT INTO map_foregrounds (name, coords_id)
            SELECT 'ombre', c.id
            FROM coords c
            JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
                  UNION SELECT 6 UNION SELECT 7 UNION SELECT 8) k ON k.n <= c.shade
            WHERE c.shade > 0
        ");

        $this->addSql('ALTER TABLE coords DROP COLUMN IF EXISTS shade');
    }
}
