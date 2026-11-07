<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Images hidden from the editor palettes (tiled.php and the Tiled extension)
 * while staying in the tile inventory: they may still be placed on maps or
 * used elsewhere (plan grounds, ladders, loot…). Hiding only trims the
 * palette — a hidden image already on the board is drawn as before.
 *
 * The table holds the exceptions only, so the editor reads it whole in one
 * query per request.
 */
class TilePaletteVisibilityService
{
    /** @var array<string, array<string, true>>|null per-request cache of the whole table */
    private static ?array $cache = null;

    private ?EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        $this->entityManager = $entityManager;
    }

    /** @return array<string, array<string, true>> layer => name => true */
    public function hiddenByLayer(): array
    {
        if (self::$cache === null) {
            $hidden = [];
            foreach ($this->connection()->fetchAllAssociative('SELECT layer, name FROM tile_palette_hidden') as $row) {
                $hidden[(string) $row['layer']][(string) $row['name']] = true;
            }
            self::$cache = $hidden;
        }

        return self::$cache;
    }

    /** @return array<string, true> */
    public function hiddenNames(string $layer): array
    {
        return $this->hiddenByLayer()[$layer] ?? [];
    }

    public function isHidden(string $layer, string $name): bool
    {
        return isset($this->hiddenByLayer()[$layer][$name]);
    }

    /**
     * Palette names of a layer without the hidden ones.
     *
     * @param list<string> $names
     * @return list<string>
     */
    public function filter(string $layer, array $names): array
    {
        $hidden = $this->hiddenNames($layer);
        if ($hidden === []) {
            return $names;
        }

        return array_values(array_filter($names, static fn (string $name): bool => !isset($hidden[$name])));
    }

    public function setHidden(string $layer, string $name, bool $hidden): void
    {
        if ($hidden) {
            $this->connection()->executeStatement(
                'INSERT IGNORE INTO tile_palette_hidden (layer, name) VALUES (?, ?)',
                [$layer, $name]
            );
        } else {
            $this->forget($layer, $name);
        }
        self::$cache = null;
    }

    /** The image was renamed: its mark follows it. */
    public function rename(string $layer, string $old, string $new): void
    {
        $this->connection()->executeStatement(
            'UPDATE IGNORE tile_palette_hidden SET name = ? WHERE layer = ? AND name = ?',
            [$new, $layer, $old]
        );
        $this->forget($layer, $old);
    }

    /** The image changed layer: its mark follows it. */
    public function move(string $fromLayer, string $name, string $toLayer): void
    {
        $this->connection()->executeStatement(
            'UPDATE IGNORE tile_palette_hidden SET layer = ? WHERE layer = ? AND name = ?',
            [$toLayer, $fromLayer, $name]
        );
        $this->forget($fromLayer, $name);
    }

    /** The image is gone: drop its mark. */
    public function forget(string $layer, string $name): void
    {
        $this->connection()->executeStatement(
            'DELETE FROM tile_palette_hidden WHERE layer = ? AND name = ?',
            [$layer, $name]
        );
        self::$cache = null;
    }

    private function connection(): Connection
    {
        $this->entityManager ??= EntityManagerFactory::getEntityManager();

        return $this->entityManager->getConnection();
    }
}
