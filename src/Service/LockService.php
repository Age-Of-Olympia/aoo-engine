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
final class LockService extends BaseService
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        parent::__construct();
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
     * $actorId a-t-il le droit de fermer ou d'ouvrir $entityId ?
     *
     * Sans propriétaire NI faction, personne n'est chez soi : la chose reste
     * ouverte à tous, ce qui vaut mieux qu'une serrure que nul ne peut tourner.
     */
    public function mayLock(int $entityId, int $actorId): bool
    {
        if (!$this->isLockable($entityId)) {
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
            return false;
        }

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
