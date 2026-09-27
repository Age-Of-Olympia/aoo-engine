<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Interface\LockableInterface;
use Doctrine\DBAL\Connection;

/**
 * Who may shut what, and whether it shuts at all.
 *
 * The TYPE says what has a door, from either catalogue; the ENTITY says who
 * owns it, by named owner or by shared faction.
 */
final class LockService
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Ce que porte une entité comme type, quel que soit le catalogue.
     *
     * Même aiguillage que {@see EntityTypeCaracsService} : le discriminant dit
     * quelle table lire, le type répond le reste.
     */
    private function typeOf(int $entityId): ?LockableInterface
    {
        $row = $this->conn->fetchAssociative(
            'SELECT player_type, race FROM players WHERE id = ?',
            [$entityId]
        );
        if ($row === false || (string) $row['race'] === '') {
            return null;
        }

        $em = EntityManagerFactory::getEntityManager();
        $class = (string) $row['player_type'] === ItemInstanceService::ENTITY_TYPE
            ? \App\Entity\Item::class
            : \App\Entity\Race::class;

        $type = $em->getRepository($class)->findOneBy(['name' => (string) $row['race']]);

        return $type instanceof LockableInterface ? $type : null;
    }

    /** Cette entité peut-elle se fermer du tout ? */
    public function isLockable(int $entityId): bool
    {
        return $this->typeOf($entityId)?->isLockable() ?? false;
    }

    /**
     * May $actorId shut or open $entityId?
     *
     * A public chest (no owner, no faction) belongs to everyone, lid
     * included. A public door keeps the state the map gave it: an ownerless
     * town gate is not for any passer-by to bar.
     */
    public function mayLock(int $entityId, int $actorId): bool
    {
        $type = $this->typeOf($entityId);
        if (!($type?->isLockable() ?? false)) {
            return false;
        }

        $thing = $this->conn->fetchAssociative(
            'SELECT owner_id, faction FROM players WHERE id = ?',
            [$entityId]
        );
        if ($thing === false) {
            return false;
        }

        $ownerId = $thing['owner_id'] === null ? null : (int) $thing['owner_id'];
        $faction = (string) $thing['faction'];

        if ($ownerId === null && $faction === '') {
            return $type instanceof \App\Entity\Item;
        }

        return $this->isOneOfTheirs($ownerId, $faction, $actorId);
    }

    /**
     * Does $actorId count among $entityId's people? The owner, a member of
     * its faction — and a thing with neither owner nor faction belongs to
     * everyone: yes for all.
     *
     * This is mayLock()'s rule without the latch: working on a palissade's
     * construction site asks this question, not the lock's.
     */
    public function mayActOn(int $entityId, int $actorId): bool
    {
        $thing = $this->conn->fetchAssociative(
            'SELECT owner_id, faction FROM players WHERE id = ?',
            [$entityId]
        );
        if ($thing === false) {
            return false;
        }

        $ownerId = $thing['owner_id'] === null ? null : (int) $thing['owner_id'];
        $faction = (string) $thing['faction'];

        if ($ownerId === null && $faction === '') {
            return true;
        }

        return $this->isOneOfTheirs($ownerId, $faction, $actorId);
    }

    /** The household rule shared by the lock and the construction site. */
    private function isOneOfTheirs(?int $ownerId, string $faction, int $actorId): bool
    {
        if ($ownerId !== null && $ownerId === $actorId) {
            return true;
        }

        if ($faction === '') {
            return false;
        }

        $actorFaction = (string) $this->conn->fetchOne(
            'SELECT faction FROM players WHERE id = ?',
            [$actorId]
        );

        return $actorFaction !== '' && $actorFaction === $faction;
    }
}
