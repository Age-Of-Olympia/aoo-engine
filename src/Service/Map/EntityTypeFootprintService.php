<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * Cut-outs DECLARED from the admin, stacked on top of the guessed ones.
 *
 * Precedence: declaration, then map, then whole-object image. A declaration
 * has to be able to correct what the map shows, otherwise a badly placed
 * scenery object would be its own authority.
 *
 * Keyed by family NAME, not `races.id`: most scenery families have no row in
 * `races` yet, and the name is already the world's join key.
 */
final class EntityTypeFootprintService
{
    private ?Connection $conn;
    private SceneryFootprintDeriver $deriver;

    /** @var array<string, Footprint>|null */
    private ?array $declaredCache = null;

    public function __construct(?Connection $conn = null, ?SceneryFootprintDeriver $deriver = null)
    {
        $this->conn = $conn;
        $this->deriver = $deriver ?? new SceneryFootprintDeriver($conn);
    }

    private function conn(): Connection
    {
        return $this->conn ??= EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return array<string, Footprint> declared cut-outs, by family name */
    public function declared(): array
    {
        if ($this->declaredCache !== null) {
            return $this->declaredCache;
        }

        $declared = [];

        foreach ($this->conn()->fetchAllAssociative(
            'SELECT type_name, w, h, offsets, roles FROM entity_type_footprints'
        ) as $row) {
            try {
                $declared[(string) $row['type_name']] = Footprint::boxed(
                    (int) $row['w'],
                    (int) $row['h'],
                    self::decodeOffsets((string) $row['offsets']),
                    self::decodeRoles($row['roles'])
                );
            } catch (\InvalidArgumentException) {
                /* Unreadable declaration: fall back to the guessed sources
                 * rather than break the page where it gets repaired. */
                continue;
            }
        }

        return $this->declaredCache = $declared;
    }

    /**
     * Every cut-out the editors and placement read: declarations override
     * whatever the map and the images say.
     *
     * @return array<string, Footprint>
     */
    public function catalogue(): array
    {
        $catalogue = $this->deriver->guessed();

        foreach ($this->declared() as $name => $footprint) {
            $catalogue[$name] = $footprint;
        }

        ksort($catalogue);

        return $catalogue;
    }

    /** @return 'declared'|'map'|'image'|'none' which source the cut-out comes from */
    public function sourceOf(string $name): string
    {
        if (isset($this->declared()[$name])) {
            return 'declared';
        }

        if (isset($this->deriver->derive()[$name])) {
            return 'map';
        }


        if (isset($this->deriver->imageFootprints()[$name])) {
            return 'image';
        }

        return 'none';
    }

    /**
     * Declare — or correct — a type's cut-out.
     *
     * @param array<int, array{0:int,1:int}> $offsets per piece; a w×h box alone
     *        cannot say which cells are occupied, so holed figures need these
     * @param array<int, string> $roles per piece; absent means the type decides
     *
     * @throws RuntimeException on an empty cut-out or out-of-range dimensions
     */
    public function declare(string $typeName, int $w, int $h, array $offsets, array $roles = []): void
    {
        if ($offsets === []) {
            throw new RuntimeException('Une découpe sans morceau ne décrit rien.');
        }

        if ($w < 1 || $h < 1 || $w > 32 || $h > 32) {
            throw new RuntimeException('Les dimensions doivent tenir entre 1 et 32 cases.');
        }

        if (trim($typeName) === '') {
            throw new RuntimeException('Une découpe sans type ne se range nulle part.');
        }

        $this->conn()->executeStatement(
            'INSERT INTO entity_type_footprints (type_name, w, h, offsets, roles)
             VALUES (:name, :w, :h, :offsets, :roles)
             ON DUPLICATE KEY UPDATE w = VALUES(w), h = VALUES(h),
                                     offsets = VALUES(offsets), roles = VALUES(roles)',
            [
                'name'    => $typeName,
                'w'       => $w,
                'h'       => $h,
                'offsets' => (string) json_encode($offsets),
                'roles'   => $roles === [] ? null : (string) json_encode($roles),
            ]
        );

        $this->declaredCache = null;
    }

    /** Drop a declaration: the type falls back to map or image. */
    public function forget(string $typeName): void
    {
        $this->conn()->executeStatement(
            'DELETE FROM entity_type_footprints WHERE type_name = ?',
            [$typeName]
        );

        $this->declaredCache = null;
    }

    /**
     * @return array<int, array{0:int,1:int}>
     */
    private static function decodeOffsets(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $offsets = [];

        foreach ($decoded as $piece => $offset) {
            if (is_array($offset) && count($offset) === 2) {
                $offsets[(int) $piece] = [(int) $offset[0], (int) $offset[1]];
            }
        }

        ksort($offsets);

        return $offsets;
    }

    /**
     * @return array<int, string>
     */
    private static function decodeRoles(?string $json): array
    {
        $decoded = $json === null ? [] : json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $roles = [];

        foreach ($decoded as $piece => $role) {
            if (is_string($role) && $role !== '') {
                $roles[(int) $piece] = $role;
            }
        }

        ksort($roles);

        return $roles;
    }
}
