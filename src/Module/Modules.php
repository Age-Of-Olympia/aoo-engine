<?php

namespace App\Module;

use App\Factory\EntityManagerFactory;

/**
 * Which modules are on, read from the `modules` table once per request.
 *
 * A module with no row is off. A row naming no module of the code is
 * ignored. Testers (`players`) get the module even while it is off.
 */
final class Modules
{
    /** @var array<string, array{enabled: bool, players: list<int>}>|null */
    private static ?array $state = null;

    /** @var array<string, array{enabled: bool, players: list<int>}> test overrides */
    private static array $forced = [];

    /** Is the module on for everyone, or for this player as a tester? */
    public static function on(Module $module, ?int $playerId = null): bool
    {
        $row = self::row($module);

        return $row['enabled'] || ($playerId !== null && in_array($playerId, $row['players'], true));
    }

    /** @return list<int> the module's testers */
    public static function players(Module $module): array
    {
        return self::row($module)['players'];
    }

    /** @param list<int> $players */
    public static function save(Module $module, bool $enabled, array $players): void
    {
        EntityManagerFactory::getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO modules (name, enabled, players) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), players = VALUES(players)',
            [$module->value, (int) $enabled, json_encode($players)]
        );
        self::$state = null;
    }

    /**
     * Matricules typed by an admin ("12, 40 7") → positive ids, deduplicated.
     *
     * @return list<int>
     */
    public static function parsePlayers(string $input): array
    {
        $ids = array_map('intval', preg_split('/[\s,;]+/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Test helper: pin a module's state until reset().
     *
     * @param list<int> $players
     */
    public static function force(Module $module, bool $enabled, array $players = []): void
    {
        self::$forced[$module->value] = ['enabled' => $enabled, 'players' => $players];
    }

    public static function reset(): void
    {
        self::$forced = [];
        self::$state = null;
    }

    /** @return array{enabled: bool, players: list<int>} */
    private static function row(Module $module): array
    {
        return self::$forced[$module->value]
            ?? self::state()[$module->value]
            ?? ['enabled' => false, 'players' => []];
    }

    /** @return array<string, array{enabled: bool, players: list<int>}> */
    private static function state(): array
    {
        if (self::$state !== null) {
            return self::$state;
        }

        self::$state = [];
        try {
            $rows = EntityManagerFactory::getEntityManager()->getConnection()
                ->fetchAllAssociative('SELECT name, enabled, players FROM modules');
        } catch (\Throwable $e) {
            // A broken read turns every module off instead of failing the page
            error_log('[Modules] ' . $e->getMessage());
            return self::$state;
        }

        foreach ($rows as $row) {
            $players = json_decode((string) $row['players'], true);
            self::$state[(string) $row['name']] = [
                'enabled' => (bool) $row['enabled'],
                'players' => is_array($players) ? array_map('intval', $players) : [],
            ];
        }

        return self::$state;
    }
}
