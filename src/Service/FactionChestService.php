<?php

namespace App\Service;

use App\Entity\BuildingDetails;
use App\Factory\EntityManagerFactory;
use App\Service\Map\EntityLocationService;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * What a faction decides about chests, from its panel.
 *
 * - Floors: on a plan where it has a finished bank, the faction closes
 *   floors to its members' chests (faction_closed_chest_floors). The
 *   admin's plan_z_levels.chests_allowed stays the ceiling.
 * - Ownership: take a public chest back (bank on its plan required),
 *   give a faction chest to a member, abandon one to the public. The
 *   owner of a personal chest may entrust it to their faction.
 *
 * Every ownership change is one guarded UPDATE: the WHERE restates the
 * expected state, so two hands racing on one chest cannot both win.
 */
class FactionChestService
{
    public const FLAG = 'manageChests';
    private const BANK = 'banque';

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Plans where the faction has a finished bank. Race filtered in PHP —
     * races and players collate differently.
     *
     * @return list<string>
     */
    public function bankPlansOf(string $code): array
    {
        if ($code === '') {
            return [];
        }

        $rows = $this->conn->fetchAllAssociative(
            "SELECT DISTINCT c.plan, p.race
               FROM players p
               JOIN buildings b ON b.player_id = p.id
               JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type = 'building' AND b.build_state = ?
                AND CONVERT(p.faction USING utf8mb4) = CONVERT(? USING utf8mb4)",
            [BuildingDetails::STATE_BUILT, $code]
        );

        $plans = [];
        foreach ($rows as $row) {
            if ((string) $row['race'] === self::BANK) {
                $plans[(string) $row['plan']] = true;
            }
        }

        return array_keys($plans);
    }

    /** Has this faction left the floor open to its members' chests? */
    public function floorOpenFor(string $code, string $plan, int $z): bool
    {
        $factionId = $this->factionIdOf($code);
        if ($factionId === null) {
            return true;
        }

        return !$this->conn->fetchOne(
            'SELECT 1 FROM faction_closed_chest_floors WHERE faction_id = ? AND plan = ? AND z = ?',
            [$factionId, $plan, $z]
        );
    }

    /**
     * The floors the faction may open or close: those the admin allows,
     * on its bank plans. A plan without level rows has its ground floor.
     *
     * @return list<array{plan: string, planName: string, z: int, name: string, open: bool}>
     */
    public function floorsOf(string $code): array
    {
        $floors = [];
        foreach ($this->bankPlansOf($code) as $plan) {
            $planJson = plans()->read($plan);
            $levels = $planJson !== false ? ($planJson->z_levels ?? []) : [];
            if ($levels === []) {
                $levels = [(object) ['z' => 0, 'name' => '']];
            }

            foreach ($levels as $level) {
                if (($level->chestsAllowed ?? true) === false) {
                    continue;
                }
                $z = (int) $level->z;
                $floors[] = [
                    'plan'     => $plan,
                    'planName' => (string) ($planJson !== false ? ($planJson->name ?? $plan) : $plan),
                    'z'        => $z,
                    'name'     => (string) ($level->name ?? '') !== '' ? (string) $level->name : 'Niveau ' . $z,
                    'open'     => $this->floorOpenFor($code, $plan, $z),
                ];
            }
        }

        return $floors;
    }

    public function setFloorOpen(int $actorId, string $plan, int $z, bool $open): void
    {
        $code = $this->assertManager($actorId);
        if (!in_array($plan, $this->bankPlansOf($code), true)) {
            throw new RuntimeException('Votre faction n\'a pas de banque sur ce plan.');
        }

        if (!$open && plans()->chestsStandingOn($plan, $z) > 0) {
            throw new RuntimeException('Des coffres sont posés à ce niveau : impossible de le fermer.');
        }

        $factionId = (int) $this->factionIdOf($code);
        if ($open) {
            $this->conn->executeStatement(
                'DELETE FROM faction_closed_chest_floors WHERE faction_id = ? AND plan = ? AND z = ?',
                [$factionId, $plan, $z]
            );
        } else {
            $this->conn->executeStatement(
                'INSERT IGNORE INTO faction_closed_chest_floors (faction_id, plan, z) VALUES (?, ?, ?)',
                [$factionId, $plan, $z]
            );
        }

        $planName = (string) (plans()->read($plan)->name ?? $plan);
        $this->record($code, $actorId, ($open ? 'ouvre' : 'ferme') . " le niveau {$z} de {$planName} aux coffres.");
    }

    /**
     * Public chests standing on the faction's bank plans — what it may
     * take back.
     *
     * @return list<array{id: int, name: string, plan: string, x: int, y: int, z: int}>
     */
    public function claimableOf(string $code): array
    {
        $plans = $this->bankPlansOf($code);
        if ($plans === []) {
            return [];
        }

        $rows = $this->conn->fetchAllAssociative(
            "SELECT p.id, p.name, c.plan, c.x, c.y, c.z
               FROM players p
               JOIN item_instances ii ON ii.entity_id = p.id AND ii.destroyed = 0
               JOIN items i ON i.id = ii.item_id AND i.lockable = 1
               JOIN coords c ON c.id = p.coords_id
              WHERE p.slot = ? AND p.owner_id IS NULL AND p.faction = ''
                AND c.plan IN (?)
              ORDER BY c.plan, p.name",
            [EntityLocationService::SLOT_INSTALLED, $plans],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ArrayParameterType::STRING]
        );

        return array_map(static fn (array $row): array => [
            'id'   => (int) $row['id'],
            'name' => (string) $row['name'],
            'plan' => (string) $row['plan'],
            'x'    => (int) $row['x'],
            'y'    => (int) $row['y'],
            'z'    => (int) $row['z'],
        ], $rows);
    }

    /** @return list<array{id: int, name: string}> */
    public function membersOf(string $code): array
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT id, name FROM players
              WHERE player_type = 'real'
                AND CONVERT(faction USING utf8mb4) = CONVERT(? USING utf8mb4)
              ORDER BY name",
            [$code]
        );

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name']], $rows);
    }

    /** Public → the faction's, where its bank stands. */
    public function claim(int $chestId, int $actorId): void
    {
        $code = $this->assertManager($actorId);
        $chest = $this->chestOrFail($chestId);
        if (!in_array($chest['plan'], $this->bankPlansOf($code), true)) {
            throw new RuntimeException('Votre faction n\'a pas de banque sur ce plan.');
        }

        $this->guardedUpdate(
            "UPDATE players SET faction = ? WHERE id = ? AND owner_id IS NULL AND faction = ''",
            [$code, $chestId],
            'Ce coffre n\'est plus public.'
        );

        $this->record($code, $actorId, "reprend {$chest['name']} pour la faction.");
    }

    /** The faction's → one of its members, as a personal chest. */
    public function offer(int $chestId, int $actorId, int $memberId): void
    {
        $code = $this->assertManager($actorId);
        $chest = $this->chestOrFail($chestId);
        if (!in_array($memberId, array_column($this->membersOf($code), 'id'), true)) {
            throw new RuntimeException('Ce personnage n\'est pas de la faction.');
        }

        $this->guardedUpdate(
            "UPDATE players SET owner_id = ?, faction = ''
              WHERE id = ? AND owner_id IS NULL
                AND CONVERT(faction USING utf8mb4) = CONVERT(? USING utf8mb4)",
            [$memberId, $chestId, $code],
            'Ce coffre n\'est pas à la faction.'
        );

        $this->record($code, $actorId, "offre {$chest['name']} à " . $this->nameOf($memberId) . '.');
    }

    /** The faction's → public. */
    public function abandon(int $chestId, int $actorId): void
    {
        $code = $this->assertManager($actorId);
        $chest = $this->chestOrFail($chestId);

        $this->guardedUpdate(
            "UPDATE players SET faction = ''
              WHERE id = ? AND owner_id IS NULL
                AND CONVERT(faction USING utf8mb4) = CONVERT(? USING utf8mb4)",
            [$chestId, $code],
            'Ce coffre n\'est pas à la faction.'
        );

        $this->record($code, $actorId, "abandonne {$chest['name']} : il est désormais public.");
    }

    /** Personal → the owner's faction. The owner's gesture, no rank needed. */
    public function entrust(int $chestId, int $actorId): void
    {
        $code = (string) $this->conn->fetchOne('SELECT faction FROM players WHERE id = ?', [$actorId]);
        if ($code === '') {
            throw new RuntimeException('Vous n\'avez pas de faction.');
        }
        $chest = $this->chestOrFail($chestId);

        $this->guardedUpdate(
            "UPDATE players SET owner_id = NULL, faction = ? WHERE id = ? AND owner_id = ? AND faction = ''",
            [$code, $chestId, $actorId],
            'Ce coffre n\'est pas le vôtre.'
        );

        $this->record($code, $actorId, "confie {$chest['name']} à la faction.");
    }

    /** May the owner entrust this chest to their faction right now? */
    public function mayEntrust(int $chestId, int $actorId): bool
    {
        $row = $this->conn->fetchAssociative(
            'SELECT c.owner_id, c.faction, a.faction AS actor_faction
               FROM players c, players a
              WHERE c.id = ? AND a.id = ?',
            [$chestId, $actorId]
        );

        return $row !== false
            && (int) $row['owner_id'] === $actorId
            && (string) $row['faction'] === ''
            && (string) $row['actor_faction'] !== '';
    }

    /** @return string the actor's faction code */
    private function assertManager(int $actorId): string
    {
        if (!(new FactionService())->mayManage($actorId, self::FLAG)) {
            throw new RuntimeException('Votre rang ne gère pas les coffres de la faction.');
        }

        return (string) $this->conn->fetchOne('SELECT faction FROM players WHERE id = ?', [$actorId]);
    }

    /** @return array{name: string, plan: string} a standing chest */
    private function chestOrFail(int $chestId): array
    {
        $row = $this->conn->fetchAssociative(
            'SELECT p.name, c.plan
               FROM players p
               JOIN item_instances ii ON ii.entity_id = p.id AND ii.destroyed = 0
               JOIN items i ON i.id = ii.item_id AND i.lockable = 1
               JOIN coords c ON c.id = p.coords_id
              WHERE p.id = ? AND p.slot = ?',
            [$chestId, EntityLocationService::SLOT_INSTALLED]
        );
        if ($row === false) {
            throw new RuntimeException('Ce n\'est pas un coffre posé.');
        }

        return ['name' => (string) $row['name'], 'plan' => (string) $row['plan']];
    }

    /** @param list<mixed> $params */
    private function guardedUpdate(string $sql, array $params, string $refusal): void
    {
        if ($this->conn->executeStatement($sql, $params) !== 1) {
            throw new RuntimeException($refusal);
        }
    }

    private function record(string $code, int $actorId, string $what): void
    {
        $line = $this->nameOf($actorId) . ' ' . $what;
        (new FactionLogService())->add($code, $actorId, $line);
        (new AuditService())->addAuditLog("#{$actorId} [{$code}] {$line}");
    }

    private function nameOf(int $playerId): string
    {
        return (string) $this->conn->fetchOne('SELECT name FROM players WHERE id = ?', [$playerId]);
    }

    private function factionIdOf(string $code): ?int
    {
        if ($code === '') {
            return null;
        }
        $id = $this->conn->fetchOne(
            'SELECT id FROM factions WHERE CONVERT(code USING utf8mb4) = CONVERT(? USING utf8mb4)',
            [$code]
        );

        return $id === false ? null : (int) $id;
    }
}
