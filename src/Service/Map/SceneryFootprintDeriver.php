<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use App\Service\TiledMapService;
use Doctrine\DBAL\Connection;

/**
 * Derives multi-cell scenery cut-outs from the map itself.
 *
 * Not from the image files: `TileCatalogService::buildComposites()` only
 * knows the `-NN` naming (442 pieces on disk use `_NN`) and rejects HOLED
 * figures, which do exist — `geant_petrifie` fills 4 cells of a 3×3 box.
 *
 * Grouping is NOT plain connectivity: two copies of a decor placed side by
 * side touch and would merge. The rule is "connected AND all piece indexes
 * distinct" — a group holding `-00` twice holds two objects.
 *
 * The anchor is the FIRST PIECE, not the bounding box corner, since a holed
 * figure may have no cell at its bottom-left.
 */
final class SceneryFootprintDeriver
{
    /** Opened only when the map is queried: `imageFootprints()` needs no database. */
    private ?Connection $conn;

    /**
     * Memoised per INSTANCE, not statically: the map changes between tests.
     *
     * @var array<string, array{footprint: Footprint, instances: int, truncated: int}>|null
     */
    private ?array $mapCache = null;

    /** @var array<string, Footprint>|null */
    private ?array $imageCache = null;

    /** @var array<string, array<int, string>>|null */
    private ?array $diskCache = null;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn;
    }

    /** La connexion, ouverte au premier besoin. */
    private function conn(): Connection
    {
        return $this->conn ??= EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Split a scenery name into (family, piece index). Three conventions
     * coexist: `-NN`, `_NN`, and a bare digit; no suffix means piece 0.
     *
     * @return array{0: string, 1: int}
     */
    public static function splitPiece(string $name): array
    {
        if (preg_match('/^(.*?)[-_](\d{1,2})$/', $name, $m)) {
            return [$m[1], (int) $m[2]];
        }

        if (preg_match('/^(.*?[a-z])(\d{1,2})$/', $name, $m)) {
            return [$m[1], (int) $m[2]];
        }

        return [$name, 0];
    }

    /** @return array<string, array{footprint: Footprint, instances: int, truncated: int}> */
    public function derive(): array
    {
        if ($this->mapCache !== null) {
            return $this->mapCache;
        }

        $catalogue = [];

        foreach ($this->families() as $family => $cells) {
            $pieces = array_values(array_unique(array_column($cells, 'piece')));

            if (count($pieces) < 2) {
                continue; /* single piece: nothing to cut out */
            }

            $groups = $this->groupsIn($cells);
            $model = $this->completeModel($groups, count($pieces));

            if ($model === null) {
                continue; /* no complete copy: the cut-out cannot be derived */
            }

            [$instances, $truncated] = $this->countInstances($groups, count($pieces));

            $catalogue[$family] = [
                'footprint' => Footprint::fromOffsets($this->offsetsFrom($model)),
                'instances' => $instances,
                'truncated' => $truncated,
            ];
        }

        ksort($catalogue);

        return $this->mapCache = $catalogue;
    }

    /**
     * GUESSED cut-outs: the map, completed by whole-object images. Declared
     * ones are stacked on top by `EntityTypeFootprintService::catalogue()`.
     *
     * The map wins over the images, which contradict it — `geant_petrifie`'s
     * image claims 1×2 where the map shows a holed 3×3.
     *
     * @return array<string, Footprint>
     */
    public function guessed(): array
    {
        $catalogue = [];

        foreach ($this->derive() as $family => $derived) {
            $catalogue[$family] = $derived['footprint'];
        }

        foreach ($this->imageFootprints() as $family => $footprint) {
            $catalogue[$family] ??= $footprint;
        }

        ksort($catalogue);

        return $catalogue;
    }

    /**
     * Cut-outs readable on the whole-object images, `base/base.png`: size
     * divided by 50, pieces laid in rows from the top-left — Tiled's own
     * convention. An image too small for its pieces is DISCARDED rather than
     * believed.
     *
     * @return array<string, Footprint>
     */
    public function imageFootprints(): array
    {
        if ($this->imageCache !== null) {
            return $this->imageCache;
        }

        $root = self::foregroundsDir();

        if ($root === null) {
            return [];
        }

        $footprints = [];

        foreach ($this->piecesOnDisk() as $family => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $size = @getimagesize($root . $family . '/' . $family . '.png');

            if (!$size || $size[0] % TiledMapService::TILE_SIZE !== 0 || $size[1] % TiledMapService::TILE_SIZE !== 0) {
                continue;
            }

            $w = (int) ($size[0] / TiledMapService::TILE_SIZE);
            $h = (int) ($size[1] / TiledMapService::TILE_SIZE);

            if ($w * $h < count($indexes)) {
                continue; /* incomplete image: it does not describe this figure */
            }

            $offsets = [];

            foreach (array_keys($indexes) as $piece) {
                $offsets[$piece] = [$piece % $w, $h - 1 - intdiv($piece, $w)];
            }

            /* Box from the IMAGE, which may exceed the figure: that gap is
             * what makes it holed. Deducing it would erase the holes. */
            $footprints[$family] = Footprint::boxed($w, $h, $offsets);
        }

        return $this->imageCache = $footprints;
    }

    /**
     * Pieces present on disk — what EXISTS, as opposed to what is placed.
     *
     * @return array<string, array<int, string>> famille → morceau → chemin web
     */
    public function piecesOnDisk(): array
    {
        if ($this->diskCache !== null) {
            return $this->diskCache;
        }

        $root = self::foregroundsDir();

        if ($root === null) {
            return $this->diskCache = [];
        }

        $pieces = [];

        foreach (glob($root . '*.png') ?: [] as $file) {
            $base = basename($file, '.png');
            [$family, $index] = self::splitPiece($base);
            $pieces[$family][$index] = '/img/foregrounds/' . $base . '.png';
        }

        foreach ($pieces as &$indexes) {
            ksort($indexes);
        }

        unset($indexes);
        ksort($pieces);

        return $this->diskCache = $pieces;
    }

    /**
     * Scenery directory, or null when absent. `DOCUMENT_ROOT` is unset outside
     * the web (console, migration, test), hence the repository-relative fallback.
     */
    private static function foregroundsDir(): ?string
    {
        $root = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/img/foregrounds/';

        if (!is_dir($root)) {
            $root = dirname(__DIR__, 3) . '/img/foregrounds/';
        }

        return is_dir($root) ? $root : null;
    }

    /**
     * Families whose copies are all incomplete, so no cut-out can be derived:
     * they need a human decision rather than a guess.
     *
     * @return array<string, array{pieces: int, groups: int}>
     */
    public function undecidable(): array
    {
        $result = [];

        foreach ($this->families() as $family => $cells) {
            $pieces = array_values(array_unique(array_column($cells, 'piece')));

            if (count($pieces) < 2) {
                continue;
            }

            $groups = $this->groupsIn($cells);

            if ($this->completeModel($groups, count($pieces)) === null) {
                $result[$family] = ['pieces' => count($pieces), 'groups' => count($groups)];
            }
        }

        ksort($result);

        return $result;
    }

    /**
     * Les cases de la carte, groupées par famille.
     *
     * @return array<string, list<array{name: string, x: int, y: int, z: int, plan: string, piece: int}>>
     */
    private function families(): array
    {
        $families = [];

        foreach ($this->conn()->fetchAllAssociative(
            'SELECT f.name, f.coords_id, c.x, c.y, c.z, c.plan
               FROM map_foregrounds f JOIN coords c ON c.id = f.coords_id'
        ) as $row) {
            [$family, $piece] = self::splitPiece((string) $row['name']);

            $families[$family][] = [
                'name'      => (string) $row['name'],
                'coords_id' => (int) $row['coords_id'],
                'x'         => (int) $row['x'],
                'y'         => (int) $row['y'],
                'z'         => (int) $row['z'],
                'plan'      => (string) $row['plan'],
                'piece'     => $piece,
            ];
        }

        return $families;
    }

    /**
     * Every scenery OBJECT placed on the map, cells included.
     *
     * A copy stops where a piece index repeats, so two objects standing side
     * by side stay two.
     *
     * @return list<array{family: string, cells: list<array{name: string, coords_id: int, x: int, y: int, z: int, plan: string, piece: int}>}>
     */
    public function objects(): array
    {
        $objects = [];

        foreach ($this->families() as $family => $cells) {
            $byKey = [];

            foreach ($cells as $cell) {
                $byKey[TouchingCells::key($cell)] = $cell;
            }

            $seen = [];

            foreach (array_keys($byKey) as $key) {
                if (isset($seen[$key])) {
                    continue;
                }

                $group = TouchingCells::groupAround($byKey, $key, self::distinctPieces(), $seen);

                if ($group !== []) {
                    /** @var list<array{name: string, coords_id: int, x: int, y: int, z: int, plan: string, piece: int}> $group */
                    $objects[] = ['family' => (string) $family, 'cells' => $group];
                }
            }
        }

        return $objects;
    }

    /**
     * Stop rule shared by every walk over scenery: a piece index already in
     * the group belongs to the neighbouring copy, not to this one.
     *
     * @return callable(array<string, mixed>, array<string, mixed>): bool
     */
    public static function distinctPieces(): callable
    {
        return static function (array $candidate, array $group): bool {
            foreach ($group as $cell) {
                if ($cell['piece'] === $candidate['piece']) {
                    return false;
                }
            }

            return true;
        };
    }

    /**
     * @param list<array{x: int, y: int, z: int, plan: string, piece: int}> $cells
     * @return list<list<array{x: int, y: int, z: int, plan: string, piece: int}>>
     */
    private function groupsIn(array $cells): array
    {
        $byKey = [];

        foreach ($cells as $cell) {
            $byKey[TouchingCells::key($cell)] = $cell;
        }

        /** @var list<list<array{x: int, y: int, z: int, plan: string, piece: int}>> */
        return TouchingCells::groups($byKey);
    }

    /**
     * A group carrying EVERY piece exactly once — the complete figure that
     * models the catalogue. Truncated copies and clusters cannot give it.
     *
     * @param list<list<array{piece: int, x: int, y: int}>> $groups
     * @return list<array{piece: int, x: int, y: int}>|null
     */
    private function completeModel(array $groups, int $pieceCount): ?array
    {
        foreach ($groups as $group) {
            $counts = array_count_values(array_column($group, 'piece'));

            if (max($counts) === 1 && count($counts) === $pieceCount) {
                return $group;
            }
        }

        return null;
    }

    /**
     * How many copies, and how many are truncated. A cluster of touching
     * copies counts as many copies as its most repeated piece.
     *
     * @param list<list<array{piece: int}>> $groups
     * @return array{0: int, 1: int}
     */
    private function countInstances(array $groups, int $pieceCount): array
    {
        $instances = 0;
        $truncated = 0;

        foreach ($groups as $group) {
            $counts = array_count_values(array_column($group, 'piece'));
            $copies = max($counts);
            $instances += $copies;

            if ($copies === 1 && count($counts) < $pieceCount) {
                $truncated++;
            }
        }

        return [$instances, $truncated];
    }

    /**
     * Décalages relatifs au premier morceau.
     *
     * @param list<array{piece: int, x: int, y: int}> $model
     * @return array<int, array{0:int,1:int}>
     */
    private function offsetsFrom(array $model): array
    {
        usort($model, static fn (array $a, array $b): int => $a['piece'] <=> $b['piece']);

        $ax = $model[0]['x'];
        $ay = $model[0]['y'];
        $offsets = [];

        foreach ($model as $cell) {
            $offsets[$cell['piece']] = [$cell['x'] - $ax, $cell['y'] - $ay];
        }

        ksort($offsets);

        return $offsets;
    }
}
