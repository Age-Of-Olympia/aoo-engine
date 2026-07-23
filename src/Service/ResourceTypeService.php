<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;

/**
 * Single gateway to the resource/wall type catalog, now stored in the DB
 * (resource_types) instead of the RESOURCES_PV constant of
 * config/constants.php.
 *
 * Semantics (unchanged from the constant): pv < 0 marks a resource type
 * (-1 récoltable / -2 épuisé at the map_resources.damages instance
 * level), pv > 0 is the hit-point total of the destructible survivors
 * (autels, types unique_*, destroy.php), and an unknown name is an
 * indestructible obstacle.
 *
 * The catalog is tiny (~120 rows) and consulted by the editors, the
 * import paths and the harvest checks, so it is loaded whole once per
 * request.
 */
class ResourceTypeService
{
    /** @var array<string, int>|null Per-request catalog, name => pv. */
    private static ?array $catalog = null;

    /**
     * Test seam: unit tests run without DB and inject their catalog here
     * (null restores normal DB loading).
     *
     * @param array<string, int>|null $catalog
     */
    public static function setCatalogForTests(?array $catalog): void
    {
        self::$catalog = $catalog;
    }

    /** @return array<string, int> The whole catalog, name => pv. */
    public static function all(): array
    {
        if (self::$catalog === null) {
            try {
                $rows = EntityManagerFactory::getEntityManager()->getConnection()
                    ->fetchAllKeyValue('SELECT name, pv FROM resource_types');
            } catch (\Throwable) {
                // Base indisponible (bootstrap de tests unitaires) :
                // catalogue vide, NON mis en cache pour ne pas empoisonner
                // une lecture ultérieure avec base.
                return [];
            }

            self::$catalog = array_map('intval', $rows);
        }

        return self::$catalog;
    }

    /** PV du type, null si inconnu (= indestructible). */
    public static function pv(string $name): ?int
    {
        return self::all()[$name] ?? null;
    }

    /** Un type ressource : pv négatif (-1 récoltable / -2 épuisé). */
    public static function isResource(string $name): bool
    {
        return (self::all()[$name] ?? 0) < 0;
    }

    /**
     * Récoltable en l'état (pv === -1) : le défaut authoré des damages à
     * la pose/l'import d'une ressource.
     */
    public static function isHarvestable(string $name): bool
    {
        return (self::all()[$name] ?? 0) === -1;
    }

    /** Crée ou met à jour un type (admin/resource-types.php) et purge le cache. */
    public static function save(string $name, int $pv): void
    {
        EntityManagerFactory::getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO resource_types (name, pv) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE pv = VALUES(pv)',
            [$name, $pv]
        );
        self::$catalog = null;
    }

    /**
     * Supprime un type. Garde-fou : refusé tant que des instances posées
     * (map_resources) portent encore ce nom — elles redeviendraient des
     * obstacles indestructibles muets.
     */
    public static function delete(string $name): void
    {
        $placed = self::countPlacedByName($name);
        if ($placed > 0) {
            throw new \RuntimeException(
                "Suppression impossible : {$placed} instance(s) de « {$name} » encore posée(s) sur les cartes."
            );
        }

        EntityManagerFactory::getEntityManager()->getConnection()
            ->executeStatement('DELETE FROM resource_types WHERE name = ?', [$name]);
        self::$catalog = null;
    }

    /** Instances posées (map_resources) portant ce nom. */
    public static function countPlacedByName(string $name): int
    {
        return (int) EntityManagerFactory::getEntityManager()->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM map_resources WHERE name = ?', [$name]);
    }
}
