<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move the world-map tile palette from code into the DB (tile_colors).
 *
 * ColorService carried two hardcoded palettes keyed by biome/tile name:
 * initializePastelColors() (the one ViewService actually renders the
 * generated map with) and initializeColors() (dead — no caller), plus a
 * third dead palette in config/map_elements.php (no consumer at all).
 * The live pastel palette is seeded here; the two dead ones are deleted
 * with their sources. ColorService::palette() is the new gateway, the
 * transition-blend logic (trans_A_B_code names) is untouched and keeps
 * working from the DB-loaded table.
 *
 * Idempotent (IF NOT EXISTS + no-op ON DUPLICATE KEY): re-running never
 * clobbers admin-edited rows.
 */
final class Version20260723122000_TileColorsFromCode extends AbstractMigration
{
    /**
     * Snapshot of ColorService::initializePastelColors() (the source is
     * deleted with this migration).
     *
     * @var array<string, array{int, int, int}>
     */
    private const TILE_COLORS = [
        // Terrain naturel
        'desert_de_l_egeon' => [247, 234, 215],
        'jungle_sauvage' => [188, 193, 203],
        'eryn_dolen' => [190, 204, 158],
        'foret_petrifiee' => [212, 212, 212],
        'monts_de_l_oubli' => [197, 196, 196],
        'cimes_geantes' => [135, 135, 135],
        'falaise' => [192, 192, 192],
        'lac_cenedril' => [167, 174, 168],
        'lac_thetis' => [231, 238, 243],
        'lac_pegasus' => [219, 197, 133],
        'archipel' => [127, 231, 232],
        'havres' => [190, 204, 158],
        'redora' => [85, 85, 85],
        'cimetiere_des_betes_sacrees' => [165, 165, 165],
        'fort_turok' => [190, 185, 180],
        'sol_gris' => [226, 180, 131],

        // Éléments
        'boue' => [197, 162, 137],
        'eau' => [127, 180, 198],
        'lave' => [245, 60, 80],
        'ronce' => [145, 162, 145],

        // Routes et passages
        'route' => [152, 152, 152],
        'escalier_vers_le_bas' => [207, 161, 137],
        'escalier_vers_le_haut' => [207, 161, 137],
        'echelle' => [197, 162, 137],
        'carreaux' => [223, 223, 223],

        // Murs
        'arbre1' => [140, 100, 90],
        'arbre2' => [150, 165, 190],
        'arbre3' => [195, 190, 145],
        'arbre4' => [200, 230, 235],
        'arbre5' => [240, 245, 240],

        // Donjons et grottes
        'caverne' => [197, 196, 196],
        'mines' => [151, 164, 164],
        'faille_naine' => [140, 140, 183],
        'pit' => [127, 127, 127],
        'enfers' => [197, 127, 127],

        // Établissements et structures
        'campement_redoraan' => [234, 225, 213],
        'praetorium' => [219, 197, 133],
        'manoir_tiroloin' => [197, 162, 137],
        'taverne_d_olympia' => [234, 225, 213],
        'barge_stellaire' => [70, 130, 180],
        'fefnir' => [178, 34, 34],

        // Lieux spéciaux
        'arbre_sacre-02' => [85, 107, 47],
        'banniere_velue' => [192, 192, 192],

        // Runes
        'rune1' => [148, 0, 211],
        'rune3' => [148, 0, 211],
        'rune4' => [148, 0, 211],
        'rune10' => [148, 0, 211],
        'rune11' => [148, 0, 211],
        'rune16' => [148, 0, 211],

        // Zones de butin
        'loot' => [255, 215, 0],

        // Couleur par défaut pour les types inconnus
        'default' => [100, 100, 100],
    ];

    public function getDescription(): string
    {
        return 'Move the world-map tile palette (ColorService, map_elements.php) into tile_colors';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE IF NOT EXISTS tile_colors (
                name VARCHAR(100) NOT NULL PRIMARY KEY,
                r SMALLINT UNSIGNED NOT NULL,
                g SMALLINT UNSIGNED NOT NULL,
                b SMALLINT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        foreach (self::TILE_COLORS as $name => [$r, $g, $b]) {
            $this->addSql(
                'INSERT INTO tile_colors (name, r, g, b) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = name',
                [$name, $r, $g, $b]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tile_colors');
    }
}
