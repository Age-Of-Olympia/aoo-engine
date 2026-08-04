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

        /* A stack joining an EXISTING line takes no room; a new line
         * asks the capacity. */
        $existingLine = $this->conn->fetchOne(
            "SELECT 1 FROM players_items WHERE player_id = ? AND item_id = ? AND slot = '' AND equiped = ''",
            [$containerId, $itemId]
        );
        if ($existingLine === false) {
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

    /**
     * The container's capacity in content LINES — its item type's
     * `capacity` column. Null (unlimited) for buildings and for types
     * the admin left unset.
     */
    public function capacityOf(int $containerId): ?int
    {
        $capacity = $this->conn->fetchOne(
            'SELECT it.capacity FROM item_instances i
               JOIN items it ON it.id = i.item_id
              WHERE i.entity_id = ?',
            [$containerId]
        );

        return ($capacity === false || $capacity === null) ? null : (int) $capacity;
    }

    /** One more line must fit — a stack line or an exemplar, one each. */
    private function assertRoomForALine(int $containerId): void
    {
        $capacity = $this->capacityOf($containerId);
        if ($capacity === null) {
            return;
        }

        $contents = $this->contentsOf($containerId);
        if (count($contents['stacks']) + count($contents['exemplars']) >= $capacity) {
            throw new RuntimeException('Le contenant est plein.');
        }
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
