<?php

namespace App\Service\Map;

/**
 * 8-connectivity traversal over map cells, keyed `plan|z|x|y` so a group
 * never crosses a plan or a level.
 *
 * Callers differ only in whether they absorb a given neighbour, which is why
 * that decision is a parameter and the walk lives here alone.
 */
final class TouchingCells
{
    /** @param array{plan?: string, z?: int|string, x: int|string, y: int|string} $cell */
    public static function key(array $cell): string
    {
        return ($cell['plan'] ?? '') . '|' . ($cell['z'] ?? 0) . '|' . $cell['x'] . '|' . $cell['y'];
    }

    /**
     * @param array<string, array{plan?: string, z?: int|string, x: int|string, y: int|string}> $byKey
     * @param null|callable(array<string, mixed>, array<string, mixed>): bool $accept
     *        absorb this neighbour, knowing what the group already holds;
     *        null absorbs every neighbour
     * @return list<list<array<string, mixed>>>
     */
    public static function groups(array $byKey, ?callable $accept = null): array
    {
        $seen = [];
        $groups = [];

        foreach (array_keys($byKey) as $start) {
            if (isset($seen[$start])) {
                continue;
            }

            $group = self::groupAround($byKey, $start, $accept, $seen);

            if ($group !== []) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @param array<string, array{plan?: string, z?: int|string, x: int|string, y: int|string}> $byKey
     * @param null|callable(array<string, mixed>, array<string, mixed>): bool $accept
     * @param array<string, true> $seen shared marker when walking a whole set,
     *        by reference so a cell is not visited twice
     * @return list<array<string, mixed>>
     */
    public static function groupAround(
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
        $group = [$byKey[$start]];

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

                    if ($accept !== null && !$accept($byKey[$neighbour], $group)) {
                        continue;
                    }

                    $seen[$neighbour] = true;
                    $group[] = $byKey[$neighbour];
                    $queue[] = $neighbour;
                }
            }
        }

        return $group;
    }
}
