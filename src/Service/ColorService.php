<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;

class ColorService {

    /** Couleur des types inconnus quand la base est indisponible. */
    private const FALLBACK_RGB = [100, 100, 100];

    /** @var array<string, array{int, int, int}>|null Palette par requête, name => RGB. */
    private static ?array $palette = null;

    /**
     * Test seam : les tests unitaires sans base injectent leur palette ici
     * (null restaure la lecture en base).
     *
     * @param array<string, array{int, int, int}>|null $palette
     */
    public static function setPaletteForTests(?array $palette): void
    {
        self::$palette = $palette;
    }

    /**
     * Palette carte des tuiles/biomes, en base (tile_colors) depuis
     * Version20260723122000 — ex-initializePastelColors(). Chargée entière
     * une fois par requête ; garantit toujours une entrée « default ».
     *
     * @return array<string, array{int, int, int}> name => RGB
     */
    public static function palette(): array
    {
        if (self::$palette === null) {
            try {
                $rows = EntityManagerFactory::getEntityManager()->getConnection()
                    ->fetchAllAssociative('SELECT name, r, g, b FROM tile_colors');
            } catch (\Throwable) {
                // Base indisponible (bootstrap de tests unitaires) : palette
                // minimale, NON mise en cache.
                return ['default' => self::FALLBACK_RGB];
            }

            $palette = [];
            foreach ($rows as $row) {
                $palette[$row['name']] = [(int) $row['r'], (int) $row['g'], (int) $row['b']];
            }
            $palette['default'] ??= self::FALLBACK_RGB;

            self::$palette = $palette;
        }

        return self::$palette;
    }

    /** Crée ou met à jour une couleur (admin/tile-colors.php) et purge le cache. */
    public static function saveColor(string $name, int $r, int $g, int $b): void
    {
        EntityManagerFactory::getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO tile_colors (name, r, g, b) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE r = VALUES(r), g = VALUES(g), b = VALUES(b)',
            [$name, $r, $g, $b]
        );
        self::$palette = null;
    }

    /**
     * Supprime une couleur. « default » est protégée : c'est le repli de
     * colorFor() pour tout nom inconnu de la palette.
     */
    public static function deleteColor(string $name): void
    {
        if ($name === 'default') {
            throw new \RuntimeException('La couleur « default » est le repli des tuiles inconnues — non supprimable.');
        }

        EntityManagerFactory::getEntityManager()->getConnection()
            ->executeStatement('DELETE FROM tile_colors WHERE name = ?', [$name]);
        self::$palette = null;
    }

    /**
     * Couleur carte d'une tuile, y compris les tuiles de transition
     * (« trans_<A>_<B>[_<C>[_<D>]]_<code> ») : moyenne à parts égales des
     * couleurs des 2 à 4 biomes du nom — la même pour toutes les tuiles d'un
     * ensemble, pour que la frontière forme une bande d'une seule couleur
     * sur la carte générée. Inconnue → couleur « default ».
     *
     * @param array<string, array{int, int, int}> $colors table name => RGB
     * @return array{int, int, int}
     */
    public static function colorFor(string $name, array $colors): array
    {
        return $colors[$name]
            ?? self::transitionBlend($name, $colors)
            ?? $colors['default'];
    }

    /**
     * Nom d'une tuile de transition — le pendant constructeur du parseur
     * transitionBlend ci-dessous, pour que la convention vive en un seul
     * endroit (le générateur tools/tiled/generate_transitions.php l'appelle).
     *
     * @param list<string> $tiles biomes dans l'ordre des lettres du code
     */
    public static function transitionTileName(array $tiles, string $cornerCode): string
    {
        return 'trans_' . implode('_', $tiles) . '_' . $cornerCode;
    }

    /** @return array{int, int, int}|null */
    private static function transitionBlend(string $name, array $colors): ?array
    {
        if (!preg_match('/^trans_(.+)_([a-d]{4})$/', $name, $matches)) {
            return null;
        }

        // Le code référence ses biomes par lettre : « a » = premier nom,
        // « b » = deuxième… Le générateur garantit que chaque lettre utilisée
        // est présente et que les lettres partent de « a » sans trou.
        $letters = array_unique(str_split($matches[2]));
        sort($letters);
        $count = count($letters);
        if ($letters !== array_slice(['a', 'b', 'c', 'd'], 0, $count)) {
            return null;
        }

        // Les noms de biomes peuvent contenir des underscores
        // (desert_de_l_egeon) : on cherche la coupure où chaque morceau
        // est une tuile connue de la table
        $names = self::splitBiomes(explode('_', $matches[1]), $count, $colors);
        if ($names === null) {
            return null;
        }

        // Couleur CONSTANTE par ensemble de biomes (parts égales), quel que
        // soit le code de coins : sur la carte générée, la frontière entre
        // deux biomes est une bande d'une seule couleur — un dégradé par
        // nombre de coins produisait un patchwork illisible.
        $blended = [0.0, 0.0, 0.0];
        foreach ($names as $biome) {
            $rgb = $colors[$biome];
            for ($channel = 0; $channel < 3; $channel++) {
                $blended[$channel] += $rgb[$channel] / $count;
            }
        }

        return [(int) round($blended[0]), (int) round($blended[1]), (int) round($blended[2])];
    }

    /**
     * Coupe les morceaux d'un nom composé en $count noms de biomes connus de
     * la table (première coupure valide trouvée, par backtracking).
     *
     * @param list<string> $parts
     * @return list<string>|null
     */
    private static function splitBiomes(array $parts, int $count, array $colors): ?array
    {
        if ($count === 1) {
            $name = implode('_', $parts);
            return isset($colors[$name]) ? [$name] : null;
        }

        for ($i = 1; $i <= count($parts) - ($count - 1); $i++) {
            $head = implode('_', array_slice($parts, 0, $i));
            if (!isset($colors[$head])) {
                continue;
            }
            $tail = self::splitBiomes(array_slice($parts, $i), $count - 1, $colors);
            if ($tail !== null) {
                return [$head, ...$tail];
            }
        }

        return null;
    }
}
