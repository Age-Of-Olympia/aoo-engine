<?php

namespace App\Service\Map;

use App\Entity\RouteType;
use App\Factory\EntityManagerFactory;
use App\Service\RaceService;
use Doctrine\DBAL\Connection;

/**
 * The road types are the road images: img/routes/<name>.png is what both
 * editors offer on their routes palette, so each image gets its RouteType
 * row as soon as something needs it — a road laid, a push, the admin list.
 */
final class RouteTypeService
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return list<string> every road the images offer, sorted */
    public static function imageNames(): array
    {
        $names = array_map(
            static fn(string $path): string => pathinfo($path, PATHINFO_FILENAME),
            glob(self::imageDir() . '/*.png') ?: []
        );
        sort($names);

        return $names;
    }

    /**
     * Give each name that has a road image its RouteType row; names without
     * an image, or already typed (whatever their family), are left alone.
     *
     * @param list<string> $names
     * @return int how many types were created
     */
    public function ensure(array $names): int
    {
        $created = 0;

        foreach (array_unique($names) as $name) {
            if (!is_file(self::imageDir() . '/' . $name . '.png')) {
                continue;
            }

            // Same row the first road type was seeded with, `spd` included
            $created += (int) $this->conn->executeStatement(
                "INSERT INTO races (code, name, label, description, playable, hidden, kind, type_kind,
                                    structure_nature, bleeds, wound_color, blocks_passage, blocks_projectiles,
                                    pv, spd, bgColor, color, repairable, faction, plan)
                 SELECT ?, ?, ?, '', 0, 1, 'structure', 'route', 'route', '', '#8b4513', 0, 0,
                        60, 16, '#8b4513', 'black', 1, '', ''
                  WHERE NOT EXISTS (SELECT 1 FROM races WHERE CONVERT(name USING utf8mb4) = CONVERT(? USING utf8mb4))",
                [strtoupper($name), $name, ucfirst(str_replace('_', ' ', $name)), $name]
            );
        }

        if ($created > 0) {
            RaceService::clearCache();
            StructureTypeService::forget();
        }

        return $created;
    }

    private static function imageDir(): string
    {
        return dirname(__DIR__, 3) . '/img/' . RouteType::IMAGE_DIR;
    }
}
