<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Interface\LockableInterface;
use Doctrine\DBAL\Connection;
use RuntimeException;

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
     * May $actorId shut or open $entityId? Its people may; a thing with
     * neither owner nor faction (chest or door) belongs to everyone.
     */
    public function mayLock(int $entityId, int $actorId): bool
    {
        return $this->isLockable($entityId) && $this->mayActOn($entityId, $actorId);
    }

    /**
     * Does $actorId count among $entityId's people? The owner, a member of
     * its faction — and a thing with neither owner nor faction belongs to
     * everyone: yes for all.
     *
     * mayLock() adds the latch to this rule: working on a palissade's
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

    /**
     * May $actorId turn this lock at all — its people (mayLock), and
     * within a faction the rank flag? Where the gesture happens (beside
     * it, or from the faction panel) is the caller's affair.
     */
    public function mayTurnLock(int $entityId, int $actorId): bool
    {
        return $this->mayLock($entityId, $actorId) && $this->lockRankRefusal($entityId, $actorId) === null;
    }

    /**
     * mayTurnLock(), and the lock answers: a closure the latch does not
     * explain (ruin, site, wreck) jams it. Decides whether a lock button
     * is shown at all.
     */
    public function mayTurnLockNow(int $entityId, int $actorId): bool
    {
        return $this->mayTurnLock($entityId, $actorId) && !$this->isJammed($entityId);
    }

    /**
     * Turns the lock. What is shut denies its contents or its passage to
     * everyone, its people included.
     */
    public function toggleOpen(int $entityId, int $actorId, bool $open): void
    {
        if (!$this->mayLock($entityId, $actorId)) {
            throw new RuntimeException('Cette serrure ne vous connaît pas.');
        }

        $refusal = $this->lockRankRefusal($entityId, $actorId);
        if ($refusal !== null) {
            throw new RuntimeException($refusal);
        }

        if ($this->isJammed($entityId)) {
            throw new RuntimeException(
                'La serrure ne répond plus : c\'est ' . (new BuildingService())->closureReasonOf($entityId) . '.'
            );
        }

        (new BuildingService())->setOpen($entityId, $open);
        (new FactionLogService($this->conn))->addAboutThing($entityId, $actorId, $open ? 'a ouvert' : 'a fermé');
        (new AuditService())->addAuditLog("lock #{$entityId}: #{$actorId} " . ($open ? 'ouvre' : 'ferme'));
    }

    /**
     * The rank half of the household rule: within a faction, whoever is
     * not the owner needs $flag. Null when allowed.
     */
    public function rankRefusal(
        int $entityId,
        int $actorId,
        string $flag = 'useChest',
        string $refusal = 'Votre rang ne permet pas de verrouiller les coffres de la faction.'
    ): ?string {
        $thing = $this->conn->fetchAssociative('SELECT owner_id, faction FROM players WHERE id = ?', [$entityId]);
        if ($thing === false) {
            return 'Cette entité n\'existe pas.';
        }

        $ownerId = $thing['owner_id'] === null ? null : (int) $thing['owner_id'];
        if ((string) $thing['faction'] !== '' && $ownerId !== $actorId && !(new FactionService())->mayManage($actorId, $flag)) {
            return $refusal;
        }

        return null;
    }

    /** A door opens under useDoor; anything else (chest, building) under useChest. */
    private function lockRankRefusal(int $entityId, int $actorId): ?string
    {
        $type = (string) $this->conn->fetchOne('SELECT race FROM players WHERE id = ?', [$entityId]);

        return (new RaceService())->getRaceByName($type)?->isDoor()
            ? $this->rankRefusal($entityId, $actorId, 'useDoor', 'Votre rang ne permet pas d\'ouvrir les portes de la faction.')
            : $this->rankRefusal($entityId, $actorId);
    }

    /** A closure the latch does not explain — ruin, site, damage — jams the lock. */
    private function isJammed(int $entityId): bool
    {
        $closure = (new BuildingService())->closureReasonOf($entityId);

        return $closure !== null && $closure !== BuildingService::CLOSED_BY_HAND;
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
