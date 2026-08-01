<?php

namespace App\Service\Map;

use App\Interface\HarvestableInterface;
use App\Factory\EntityManagerFactory;

/**
 * What a structure type IS, read from the one catalogue that holds it.
 *
 * There were two. `resource_types` carried a name and a number whose sign
 * encoded a meaning — negative for a harvestable, positive for a life total,
 * absent for indestructible — while `races` carried the same types with a
 * `structure_nature` saying it in words and a `pv` meaning only life. Two
 * sources for one truth is one too many: a type made harvestable in the admin's
 * type editor stayed an obstacle to the map editor, which read the other table.
 *
 * The sign is gone with the table. A harvestable is a type whose nature says
 * `ressource`, and its `pv` is what it takes to fell it — nothing more encoded.
 *
 * Loaded whole once per request: the catalogue is small and every editor,
 * import and validation touches it.
 */
final class StructureTypeService
{
    public const NATURE_RESOURCE = 'ressource';

    /** @var array<string, array{nature: string, pv: int}>|null */
    private static ?array $catalog = null;

    /**
     * Test seam: unit tests run without a database and inject their catalogue
     * here (null restores normal loading).
     *
     * @param array<string, array{nature: string, pv: int}>|null $catalog
     */
    public static function setCatalogForTests(?array $catalog): void
    {
        self::$catalog = $catalog;
    }

    /** @return array<string, array{nature: string, pv: int}> name => what it is */
    public static function all(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        try {
            $rows = EntityManagerFactory::getEntityManager()->getConnection()->fetchAllAssociative(
                "SELECT name, structure_nature, pv FROM races WHERE kind = 'structure'"
            );
        } catch (\Throwable) {
            /* Database out of reach (unit-test bootstrap): an empty catalogue,
               NOT cached, so a later read with a database is not poisoned. */
            return [];
        }

        $catalog = [];

        foreach ($rows as $row) {
            $catalog[(string) $row['name']] = [
                'nature' => (string) $row['structure_nature'],
                'pv' => (int) $row['pv'],
            ];
        }

        return self::$catalog = $catalog;
    }

    /** A type the catalogue knows how to build, fell or harvest. */
    public static function isKnown(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    /** HarvestableInterface: what fouiller takes from, and what regrows. */
    public static function isHarvestable(string $name): bool
    {
        return (self::all()[$name]['nature'] ?? '') === self::NATURE_RESOURCE;
    }

    /** What it takes to fell one, null when the catalogue does not know the type. */
    public static function pv(string $name): ?int
    {
        return self::all()[$name]['pv'] ?? null;
    }

    /** @return list<string> every structure type, harvestable or not */
    public static function names(): array
    {
        $names = array_keys(self::all());
        sort($names);

        return $names;
    }

    public static function forget(): void
    {
        self::$catalog = null;
    }
}
