<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * Les découpes DÉCLARÉES, et leur place au-dessus des découpes devinées.
 *
 * Trois sources savent dire la forme d'un décor multi-cases, et elles ne se
 * valent pas :
 *
 * 1. **La déclaration** — cette table, éditée depuis l'administration. Elle
 *    l'emporte sur tout : c'est une décision humaine, prise une fois.
 * 2. **La carte**, quand un exemplaire complet y figure. Elle montre la
 *    figure telle qu'elle est posée, mais ignore ce qui ne l'a jamais été —
 *    53 familles sur 130 sont dans ce cas.
 * 3. **Les images d'ensemble**, divisées par 50. Utile, mais faillible :
 *    celle de `geant_petrifie` annonce 1×2 cases quand quatre morceaux
 *    existent et que la carte en montre une figure de 3×3 trouée.
 *
 * L'ordre importe. Une déclaration doit pouvoir CORRIGER ce que la carte
 * montre — sinon on ne pourrait jamais réparer un décor mal posé, puisque
 * c'est lui qui ferait autorité.
 *
 * # La clé est le nom
 *
 * Une découpe décrit une famille de morceaux, et ces familles ne sont pas
 * encore des types du catalogue : elles le deviendront à la conversion des
 * décors en entités. Sur les 24 découpes connues, 23 n'ont aucune ligne dans
 * `races` — s'attacher à `races.id` rendrait la déclaration impossible
 * précisément là où elle sert.
 *
 * Le nom est de toute façon la clé de jointure du monde : `map_foregrounds`,
 * `map_resources` et `players.race` s'y réfèrent déjà.
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

    /**
     * Les découpes déclarées, par nom de type.
     *
     * @return array<string, Footprint>
     */
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
                /* Déclaration illisible ou incohérente : on la laisse aux
                 * sources devinées plutôt que de faire tomber la page qui la
                 * liste — c'est justement là qu'on vient la réparer. */
                continue;
            }
        }

        return $this->declaredCache = $declared;
    }

    /**
     * Le catalogue complet : déclarations d'abord, puis carte, puis images.
     *
     * C'est lui que l'éditeur consulte. Un type déclaré ne consulte plus
     * jamais les autres sources — y compris quand la carte le contredit,
     * ce qui est précisément le cas qu'on veut pouvoir corriger.
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

    /**
     * D'où vient la découpe d'un type — pour que l'administration le dise.
     *
     * @return 'declared'|'map'|'image'|'none'
     */
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
     * Déclare — ou corrige — la découpe d'un type.
     *
     * @param array<int, array{0:int,1:int}> $offsets décalages par morceau,
     *        relatifs au premier ; ce sont eux qui autorisent les figures
     *        trouées, une boîte w×h ne disant pas quelles cases sont occupées
     * @param array<int, string> $roles rôle par morceau ; absent = celui du type
     *
     * @throws RuntimeException découpe vide, ou dimensions hors bornes
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

    /** Retire une déclaration : le type retombe sur ce que la carte ou l'image disent. */
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
