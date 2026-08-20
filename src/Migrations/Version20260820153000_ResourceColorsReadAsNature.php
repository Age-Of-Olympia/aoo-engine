<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sur une carte, un arbre se lit vert et une pierre grise.
 *
 * Les couches locales et monde colorient chaque ressource d'après
 * `tile_colors`, mais la palette semée par Version20260723122000 venait
 * des anciennes TUILES du même nom : arbre1 sortait brun, arbre2 bleu
 * acier — l'arène du tutoriel semblait posée d'eau et de rouille — et
 * les pierres, absentes du catalogue, retombaient sur le gris par
 * défaut, indiscernable des murs.
 *
 * Chaque UPDATE est borné sur la valeur exacte du seed d'origine : une
 * couleur retouchée depuis l'admin (admin/tile-colors.php) n'est pas
 * écrasée, et le rejeu est muet.
 */
final class Version20260820153000_ResourceColorsReadAsNature extends AbstractMigration
{
    /** name => [seeded rgb, corrected rgb] */
    private const CORRECTIONS = [
        'arbre1' => [[140, 100, 90], [96, 128, 72]],
        'arbre2' => [[150, 165, 190], [76, 110, 66]],
        'arbre3' => [[195, 190, 145], [122, 142, 79]],
    ];

    /** name => rgb, ajoutés seulement s'ils manquent */
    private const ADDITIONS = [
        'pierre1'       => [158, 152, 142],
        'pierre2'       => [140, 134, 126],
        'pierre3'       => [124, 119, 112],
        'pierre_noire2' => [66, 64, 66],
    ];

    public function getDescription(): string
    {
        return 'tile_colors: les ressources arbre/pierre portent des couleurs de leur nature';
    }

    public function up(Schema $schema): void
    {
        foreach (self::CORRECTIONS as $name => [$old, $new]) {
            $this->addSql(
                'UPDATE tile_colors SET r = ?, g = ?, b = ?
                  WHERE name = ? AND r = ? AND g = ? AND b = ?',
                [...$new, $name, ...$old]
            );
        }

        foreach (self::ADDITIONS as $name => $rgb) {
            $this->addSql(
                'INSERT INTO tile_colors (name, r, g, b)
                 SELECT ?, ?, ?, ?
                  WHERE NOT EXISTS (SELECT 1 FROM tile_colors WHERE name = ?)',
                [$name, ...$rgb, $name]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::CORRECTIONS as $name => [$old, $new]) {
            $this->addSql(
                'UPDATE tile_colors SET r = ?, g = ?, b = ?
                  WHERE name = ? AND r = ? AND g = ? AND b = ?',
                [...$old, $name, ...$new]
            );
        }

        foreach (self::ADDITIONS as $name => $rgb) {
            $this->addSql(
                'DELETE FROM tile_colors WHERE name = ? AND r = ? AND g = ? AND b = ?',
                [$name, ...$rgb]
            );
        }
    }
}
