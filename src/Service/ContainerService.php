<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Service\Map\EntityLocationService;
use Classes\View;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * The flows in and out of a CONTAINER — a chest, and any entity that
 * holds things. Its contents are its children (players.holder_id) for
 * exemplars and its own stack rows (players_items, bag slot) for the
 * fungible; there is no chest-specific storage, and no reader here
 * asks "is it a chest".
 *
 * Using one asks, in order: it stands on a cell, it is not shut
 * (closureReason — a ruin, a wreck, a turned lock all deny their
 * contents), the actor is one of its people (the household rule), and
 * the actor stands next to it. Every refusal speaks the words the
 * player reads.
 *
 * Stack moves are guarded UPDATEs read by their affected-row count:
 * the giver's row is debited only while it still holds enough, so two
 * hands taking the last unit cannot both walk away with it.
 */
final class ContainerService extends BaseService
{
    /** Within reach: on the container's cell or the one next to it. */
    private const REACH = 1;

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        parent::__construct();
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * May $actorId use this container here and now? Throws the refusal
     * otherwise.
     */
    public function assertUsable(int $containerId, int $actorId): void
    {
        $row = $this->conn->fetchAssociative(
            'SELECT p.coords_id FROM players p WHERE p.id = ?',
            [$containerId]
        );
        if ($row === false || $row['coords_id'] === null) {
            throw new RuntimeException('Ce contenant n\'est posé nulle part.');
        }

        $reason = $this->closureReasonOf($containerId);
        if ($reason !== null) {
            throw new RuntimeException('Ce contenant est ' . $reason . '.');
        }

        $refusal = $this->householdRefusal($containerId, $actorId);
        if ($refusal !== null) {
            throw new RuntimeException($refusal);
        }

        $actorCoords = $this->conn->fetchAssociative(
            'SELECT c.x, c.y, c.z, c.plan FROM coords c JOIN players p ON p.coords_id = c.id WHERE p.id = ?',
            [$actorId]
        );
        if (
            $actorCoords === false
            || View::get_distance_to_entity($actorCoords, $containerId) > self::REACH
        ) {
            throw new RuntimeException('Approchez-vous pour l\'ouvrir.');
        }
    }

    /**
     * Why this container denies its contents, or null when it serves.
     * The one closure rule (BuildingService::closureReason), fed for an
     * entity that has no building satellite.
     */
    public function closureReasonOf(int $containerId): ?string
    {
        $entity = \App\Factory\PlayerFactory::legacy($containerId);
        $entity->get_caracs();

        $pvMax = (int) ($entity->caracs->pv ?? 0);
        $pvPct = $pvMax > 0 ? (int) floor($entity->getRemaining('pv') / $pvMax * 100) : 100;

        $details = (new BuildingService())->getDetails($containerId);

        return (new BuildingService())->closureReason($containerId, $details, $pvPct);
    }

    /**
     * What it holds: the fungible stacks first, then each exemplar with
     * its own identity — same dual read as a bag.
     *
     * @return array{stacks: array<int, array<string, mixed>>, exemplars: array<int, array<string, mixed>>}
     */
    public function contentsOf(int $containerId): array
    {
        $stacks = $this->conn->fetchAllAssociative(
            "SELECT pi.item_id, it.name, pi.n
               FROM players_items pi
               JOIN items it ON it.id = pi.item_id
              WHERE pi.player_id = ? AND pi.slot = '' AND pi.equiped = '' AND pi.n > 0
              ORDER BY it.name",
            [$containerId]
        );

        $exemplars = $this->conn->fetchAllAssociative(
            "SELECT it.name, i.item_id, i.id AS instance_id, i.custom_name, e.id AS entity_id
               FROM players e
               JOIN item_instances i ON i.entity_id = e.id
               JOIN items it ON it.id = i.item_id
              WHERE e.holder_id = ? AND e.slot = '' AND i.destroyed = 0
              ORDER BY it.name, i.id",
            [$containerId]
        );

        return ['stacks' => $stacks, 'exemplars' => $exemplars];
    }

    /** Moves $n units of a stack from the actor's bag into the container. */
    public function depositStack(int $containerId, int $actorId, int $itemId, int $n): void
    {
        $this->assertUsable($containerId, $actorId);

        /* A stack joining an EXISTING line takes no room, the coin
         * never does; a new line asks the capacity. */
        if ($this->stackNeedsRoom($containerId, $itemId)) {
            $this->assertRoomForALine($containerId);
        }

        $this->moveStack($actorId, $containerId, $itemId, $n, 'Vous n\'avez pas cela.');
        $this->journal($containerId, $actorId, 'a déposé ' . $n . ' × ' . $this->itemLabel($itemId) . ' dans');
        $this->addAuditLog("container #{$containerId}: #{$actorId} y dépose {$n} × item #{$itemId}");
    }

    /** Takes $n units of a stack out of the container, into the bag. */
    public function withdrawStack(int $containerId, int $actorId, int $itemId, int $n): void
    {
        $this->assertUsable($containerId, $actorId);
        if ($this->stackNeedsRoom($actorId, $itemId)) {
            $this->assertRoomForALine($actorId, 'Votre sac est plein.');
        }
        $this->moveStack($containerId, $actorId, $itemId, $n, 'Le contenant n\'a pas cela.');
        $this->journal($containerId, $actorId, 'a pris ' . $n . ' × ' . $this->itemLabel($itemId) . ' dans');
        $this->addAuditLog("container #{$containerId}: #{$actorId} en retire {$n} × item #{$itemId}");
    }

    /** Puts an exemplar from the actor's bag inside the container. */
    public function depositExemplar(int $containerId, int $actorId, int $instanceId): void
    {
        $this->assertUsable($containerId, $actorId);
        $this->assertRoomForALine($containerId);
        $entityId = $this->entityOfCarriedInstance($instanceId, $actorId, 'Vous ne portez pas cet objet.');

        /* Self-holding and cycles are putInside()'s own refusals. */
        $this->conn->transactional(function (Connection $conn) use ($entityId, $containerId): void {
            (new EntityLocationService($conn))->putInside($entityId, $containerId);
        });
        $this->journal($containerId, $actorId, 'a déposé ' . $this->exemplarLabel($instanceId) . ' dans');
        $this->addAuditLog("container #{$containerId}: #{$actorId} y dépose l'exemplaire #{$instanceId}");
    }

    /** Takes an exemplar out of the container, into the actor's bag. */
    public function withdrawExemplar(int $containerId, int $actorId, int $instanceId): void
    {
        $this->assertUsable($containerId, $actorId);
        $this->assertRoomForALine($actorId, 'Votre sac est plein.');

        $entityId = $this->conn->fetchOne(
            "SELECT e.id FROM players e
               JOIN item_instances i ON i.entity_id = e.id
              WHERE i.id = ? AND e.holder_id = ? AND e.slot = '' AND i.destroyed = 0",
            [$instanceId, $containerId]
        );
        if ($entityId === false) {
            throw new RuntimeException('Le contenant n\'a pas cet objet.');
        }

        $this->conn->transactional(function (Connection $conn) use ($entityId, $actorId): void {
            (new EntityLocationService($conn))->putInside((int) $entityId, $actorId);
        });
        $this->journal($containerId, $actorId, 'a pris ' . $this->exemplarLabel($instanceId) . ' dans');
        $this->addAuditLog("container #{$containerId}: #{$actorId} en retire l'exemplaire #{$instanceId}");
    }

    /**
     * Empties the container into the actor's bag — what fits. Composes
     * the unit withdrawals so every guard, journal line and audit entry
     * stays theirs; a full bag skips the line instead of aborting, like
     * the ground sweep: what does not fit stays in the chest.
     *
     * @return array{taken: string[], full: bool}
     */
    public function withdrawAll(int $containerId, int $actorId): array
    {
        $this->assertUsable($containerId, $actorId);

        $taken = [];
        $full = false;
        $contents = $this->contentsOf($containerId);

        foreach ($contents['stacks'] as $row) {
            if ($this->stackNeedsRoom($actorId, (int) $row['item_id']) && !$this->hasRoomForALine($actorId)) {
                $full = true;
                continue;
            }
            $this->withdrawStack($containerId, $actorId, (int) $row['item_id'], (int) $row['n']);
            $taken[] = self::stackLabel($row);
        }

        foreach ($contents['exemplars'] as $row) {
            /* Every exemplar is a new line: once the bag is at its
             * ceiling, none of the remaining ones can enter. */
            if (!$this->hasRoomForALine($actorId)) {
                $full = true;
                break;
            }
            $this->withdrawExemplar($containerId, $actorId, (int) $row['instance_id']);
            $taken[] = self::exemplarEntryLabel($row);
        }

        return ['taken' => $taken, 'full' => $full];
    }

    /**
     * Turns the lock: the container's people (mayLock — its owner, a
     * member of its faction) shut or open it themselves. What is shut
     * denies its contents to everyone, holder included.
     */
    public function toggleOpen(int $containerId, int $actorId, bool $open): void
    {
        if (!(new LockService())->mayLock($containerId, $actorId)) {
            throw new RuntimeException('Cette serrure ne vous connaît pas.');
        }

        /* Within a faction, the lock follows the same rank flag as the
         * contents — one flag governs the chest whole. */
        $refusal = $this->factionRankRefusal($containerId, $actorId);
        if ($refusal !== null) {
            throw new RuntimeException($refusal);
        }

        /* A closure the latch does not explain — ruin, construction,
         * damage — jams the lock for every path, the remote faction
         * gesture included. */
        $closure = $this->closureReasonOf($containerId);
        if ($closure !== null && $closure !== BuildingService::CLOSED_BY_HAND) {
            throw new RuntimeException('La serrure ne répond plus : c\'est ' . $closure . '.');
        }

        (new BuildingService())->setOpen($containerId, $open);
        $this->journal($containerId, $actorId, $open ? 'a ouvert' : 'a fermé');
        $this->addAuditLog("container #{$containerId}: #{$actorId} " . ($open ? 'ouvre' : 'ferme'));
    }

    /**
     * May $actorId see inside and use this container, standing aside
     * the where-and-reach questions? The peek on the observation panel
     * asks this — seeing follows the same rule as using.
     */
    public function mayUse(int $containerId, int $actorId): bool
    {
        return $this->householdRefusal($containerId, $actorId) === null;
    }

    /**
     * May $actorId turn this lock at all — its owner, or a member whose
     * rank carries the flag? Where the gesture happens (beside it, or
     * from the faction panel) is the caller's affair.
     */
    public function mayTurnLock(int $containerId, int $actorId): bool
    {
        return (new LockService())->mayLock($containerId, $actorId)
            && $this->factionRankRefusal($containerId, $actorId) === null;
    }

    /**
     * The household rule, refined by RANK: the owner is at home; within
     * a faction, the useChest flag says who uses its containers; a
     * thing with neither owner nor faction serves everyone.
     */
    private function householdRefusal(int $containerId, int $actorId): ?string
    {
        if (!(new LockService())->mayActOn($containerId, $actorId)) {
            return 'Vous n\'êtes pas des siens.';
        }

        return $this->factionRankRefusal($containerId, $actorId);
    }

    /** The rank half of the household rule, alone — mayLock has its own first half. */
    private function factionRankRefusal(int $containerId, int $actorId): ?string
    {
        $thing = $this->conn->fetchAssociative(
            'SELECT owner_id, faction FROM players WHERE id = ?',
            [$containerId]
        );
        if ($thing === false) {
            return 'Ce contenant n\'existe pas.';
        }

        $ownerId = $thing['owner_id'] === null ? null : (int) $thing['owner_id'];
        if (
            (string) $thing['faction'] !== ''
            && $ownerId !== $actorId
            && !(new FactionService())->mayManage($actorId, 'useChest')
        ) {
            return 'Votre rang n\'use pas des coffres de la faction.';
        }

        return null;
    }

    /**
     * One guarded stack move. The giver's row is debited only while it
     * still holds enough — never read then written — and an emptied row
     * leaves the table, as the bag's own writers keep it.
     */
    private function moveStack(int $fromId, int $toId, int $itemId, int $n, string $refusal): void
    {
        if ($n <= 0) {
            throw new RuntimeException('Rien à déplacer.');
        }

        $this->conn->transactional(function (Connection $conn) use ($fromId, $toId, $itemId, $n, $refusal): void {
            $debited = $conn->executeStatement(
                "UPDATE players_items SET n = n - ?
                  WHERE player_id = ? AND item_id = ? AND slot = '' AND equiped = '' AND n >= ?",
                [$n, $fromId, $itemId, $n]
            );
            if ($debited !== 1) {
                throw new RuntimeException($refusal);
            }

            $conn->executeStatement(
                "INSERT INTO players_items (player_id, item_id, n, equiped, slot)
                 VALUES (?, ?, ?, '', '')
                 ON DUPLICATE KEY UPDATE n = n + VALUES(n)",
                [$toId, $itemId, $n]
            );

            $conn->executeStatement(
                "DELETE FROM players_items WHERE player_id = ? AND item_id = ? AND slot = '' AND n <= 0",
                [$fromId, $itemId]
            );
        });
    }

    /** The coin: it counts for no line, in a bag or in a chest. */
    private const COIN = 'or';

    /**
     * The capacity in content LINES — one rule for every holder: an
     * OBJECT container reads its item type (`items.capacity`, NULL =
     * unlimited); everything else reads its race (`races.capacity`,
     * 0 = unlimited) — which is how a CHARACTER's bag gets its lines.
     */
    public function capacityOf(int $containerId): ?int
    {
        $row = $this->conn->fetchAssociative(
            'SELECT it.capacity AS item_capacity, i.id AS instance_id, r.capacity AS race_capacity
               FROM players p
               LEFT JOIN item_instances i ON i.entity_id = p.id
               LEFT JOIN items it ON it.id = i.item_id
               LEFT JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(p.race USING utf8mb4)
              WHERE p.id = ?',
            [$containerId]
        );
        if ($row === false) {
            return null;
        }

        if ($row['instance_id'] !== null) {
            return $row['item_capacity'] === null ? null : (int) $row['item_capacity'];
        }

        return (int) ($row['race_capacity'] ?? 0) > 0 ? (int) $row['race_capacity'] : null;
    }

    /**
     * How many lines a holder carries — the coin and what is equipped
     * count for nothing, exactly what capacityOf() limits.
     */
    public function lineCountOf(int $holderId): int
    {
        return (int) $this->conn->fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM players_items pi
                   JOIN items it ON it.id = pi.item_id
                  WHERE pi.player_id = ? AND pi.slot = '' AND pi.equiped = '' AND pi.n > 0
                    AND it.name != '" . self::COIN . "')
              + (SELECT COUNT(*) FROM players e
                   JOIN item_instances ii ON ii.entity_id = e.id
                  WHERE e.holder_id = ? AND e.slot = '' AND ii.destroyed = 0)",
            [$holderId, $holderId]
        );
    }

    /** May one more line enter — or is the holder at its ceiling? */
    public function hasRoomForALine(int $holderId): bool
    {
        $capacity = $this->capacityOf($holderId);

        return $capacity === null || $this->lineCountOf($holderId) < $capacity;
    }

    /**
     * Would this stack need a NEW line in that holder's bag? The coin
     * never does, nor does a stack joining its existing line.
     */
    public function stackNeedsRoom(int $holderId, int $itemId): bool
    {
        $isCoin = (bool) $this->conn->fetchOne(
            "SELECT 1 FROM items WHERE id = ? AND name = '" . self::COIN . "'",
            [$itemId]
        );
        if ($isCoin) {
            return false;
        }

        return $this->conn->fetchOne(
            "SELECT 1 FROM players_items WHERE player_id = ? AND item_id = ? AND slot = '' AND equiped = ''",
            [$holderId, $itemId]
        ) === false;
    }

    /** One more line must fit — a stack line or an exemplar, one each. */
    private function assertRoomForALine(int $holderId, string $refusal = 'Le contenant est plein.'): void
    {
        if (!$this->hasRoomForALine($holderId)) {
            throw new RuntimeException($refusal);
        }
    }

    /** One content line's label — a stack: « Bois ×3 ». */
    public static function stackLabel(array $row): string
    {
        return ucfirst((string) $row['name']) . ' ×' . (int) $row['n'];
    }

    /** One content line's label — an exemplar: its own name if christened. */
    public static function exemplarEntryLabel(array $row): string
    {
        return (string) ($row['custom_name'] ?? '') !== ''
            ? '« ' . $row['custom_name'] . ' »'
            : ucfirst((string) $row['name']);
    }

    /**
     * One line in the faction's journal, when the container is the
     * faction's: "{Actor} {verb phrase} {Container}." — the house sees
     * what happened to its things, internal takings included.
     */
    private function journal(int $containerId, int $actorId, string $verbPhrase): void
    {
        $thing = $this->conn->fetchAssociative(
            'SELECT name, faction FROM players WHERE id = ?',
            [$containerId]
        );
        if ($thing === false || (string) $thing['faction'] === '') {
            return;
        }

        $actorName = (string) $this->conn->fetchOne('SELECT name FROM players WHERE id = ?', [$actorId]);

        (new FactionLogService())->add(
            (string) $thing['faction'],
            $actorId,
            $actorName . ' ' . $verbPhrase . ' ' . $thing['name'] . '.'
        );
    }

    /** The item's display name, for a journal line. */
    private function itemLabel(int $itemId): string
    {
        return ucfirst((string) $this->conn->fetchOne('SELECT name FROM items WHERE id = ?', [$itemId]));
    }

    /** The exemplar's name — its own if christened, its type's otherwise. */
    private function exemplarLabel(int $instanceId): string
    {
        $row = $this->conn->fetchAssociative(
            'SELECT i.custom_name, it.name FROM item_instances i JOIN items it ON it.id = i.item_id WHERE i.id = ?',
            [$instanceId]
        );
        if ($row === false) {
            return 'un objet';
        }

        return (string) $row['custom_name'] !== ''
            ? '« ' . $row['custom_name'] . ' »'
            : ucfirst((string) $row['name']);
    }

    /**
     * The entity id of an instance the actor carries in the BAG — not
     * equipped, not banked, not destroyed. Throws $refusal otherwise.
     */
    private function entityOfCarriedInstance(int $instanceId, int $actorId, string $refusal): int
    {
        $entityId = $this->conn->fetchOne(
            "SELECT e.id FROM players e
               JOIN item_instances i ON i.entity_id = e.id
              WHERE i.id = ? AND e.holder_id = ? AND e.slot = '' AND i.destroyed = 0",
            [$instanceId, $actorId]
        );
        if ($entityId === false) {
            throw new RuntimeException($refusal);
        }

        return (int) $entityId;
    }
}
