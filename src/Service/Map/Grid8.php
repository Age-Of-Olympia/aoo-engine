<?php

namespace App\Service\Map;

/**
 * Le parcours de proche en proche, sur une grille, en voisinage 8.
 *
 * Deux services en avaient chacun leur copie : celui qui dérive les découpes
 * de décor, qui partitionne toute une famille en composantes, et celui qui
 * retrouve l'objet auquel une case appartient. Même double boucle, même file,
 * même dictionnaire de positions — et une seule différence, qui est justement
 * le cœur du sujet : le second REFUSE d'absorber un morceau dont l'indice est
 * déjà pris, parce que c'est là que s'arrête un exemplaire et que commence
 * son voisin.
 *
 * Cette différence est passée en paramètre. Le parcours, lui, n'existe plus
 * qu'ici : un défaut de voisinage ne se corrige plus à deux endroits.
 *
 * # La clé
 *
 * `plan|z|x|y`. Une composante ne traverse ni les plans ni les étages, et la
 * clé le dit d'elle-même — plutôt que de scoper la requête en amont et
 * d'espérer que l'appelant y ait pensé.
 */
final class Grid8
{
    /**
     * La clé d'une case, telle que ce parcours l'attend.
     *
     * @param array{plan?: string, z?: int|string, x: int|string, y: int|string} $cell
     */
    public static function key(array $cell): string
    {
        return ($cell['plan'] ?? '') . '|' . ($cell['z'] ?? 0) . '|' . $cell['x'] . '|' . $cell['y'];
    }

    /**
     * Toutes les composantes d'un ensemble de cases.
     *
     * @param array<string, array{plan?: string, z?: int|string, x: int|string, y: int|string}> $byKey
     * @param null|callable(array<string, mixed>, array<string, mixed>): bool $accept
     *        décide d'absorber une case voisine, connaissant ce que la
     *        composante contient déjà ; null = tout voisin est absorbé
     * @return list<list<array<string, mixed>>>
     */
    public static function components(array $byKey, ?callable $accept = null): array
    {
        $seen = [];
        $components = [];

        foreach (array_keys($byKey) as $start) {
            if (isset($seen[$start])) {
                continue;
            }

            $component = self::componentFrom($byKey, $start, $accept, $seen);

            if ($component !== []) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * La composante qui contient une case donnée.
     *
     * @param array<string, array{plan?: string, z?: int|string, x: int|string, y: int|string}> $byKey
     * @param null|callable(array<string, mixed>, array<string, mixed>): bool $accept
     * @param array<string, true> $seen marqueur partagé quand on partitionne
     *        tout un ensemble ; passé par référence pour qu'une case ne soit
     *        pas visitée deux fois
     * @return list<array<string, mixed>>
     */
    public static function componentFrom(
        array $byKey,
        string $start,
        ?callable $accept = null,
        array &$seen = []
    ): array {
        if (!isset($byKey[$start]) || isset($seen[$start])) {
            return [];
        }

        $seen[$start] = true;
        $queue = [$start];
        $component = [$byKey[$start]];

        while ($queue !== []) {
            $current = $byKey[array_pop($queue)];

            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    if ($dx === 0 && $dy === 0) {
                        continue;
                    }

                    $neighbour = self::key([
                        'plan' => $current['plan'] ?? '',
                        'z'    => $current['z'] ?? 0,
                        'x'    => (int) $current['x'] + $dx,
                        'y'    => (int) $current['y'] + $dy,
                    ]);

                    if (!isset($byKey[$neighbour]) || isset($seen[$neighbour])) {
                        continue;
                    }

                    if ($accept !== null && !$accept($byKey[$neighbour], $component)) {
                        continue;
                    }

                    $seen[$neighbour] = true;
                    $component[] = $byKey[$neighbour];
                    $queue[] = $neighbour;
                }
            }
        }

        return $component;
    }
}
