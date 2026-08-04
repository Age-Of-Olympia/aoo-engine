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

        if (!(new LockService())->mayActOn($containerId, $actorId)) {
            throw new RuntimeException('Vous n\'êtes pas des siens.');
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
        $this->moveStack($actorId, $containerId, $itemId, $n, 'Vous n\'avez pas cela.');
        $this->addAuditLog("container #{$containerId}: #{$actorId} y dépose {$n} × item #{$itemId}");
    }

    /** Takes $n units of a stack out of the container, into the bag. */
    public function withdrawStack(int $containerId, int $actorId, int $itemId, int $n): void
    {
        $this->assertUsable($containerId, $actorId);
        $this->moveStack($containerId, $actorId, $itemId, $n, 'Le contenant n\'a pas cela.');
        $this->addAuditLog("container #{$containerId}: #{$actorId} en retire {$n} × item #{$itemId}");
    }

    /** Puts an exemplar from the actor's bag inside the container. */
    public function depositExemplar(int $containerId, int $actorId, int $instanceId): void
    {
        $this->assertUsable($containerId, $actorId);
        $entityId = $this->entityOfCarriedInstance($instanceId, $actorId, 'Vous ne portez pas cet objet.');

        /* Self-holding and cycles are putInside()'s own refusals. */
        $this->conn->transactional(function (Connection $conn) use ($entityId, $containerId): void {
            (new EntityLocationService($conn))->putInside($entityId, $containerId);
        });
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

        (new BuildingService())->setOpen($containerId, $open);
        $this->addAuditLog("container #{$containerId}: #{$actorId} " . ($open ? 'ouvre' : 'ferme'));
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
