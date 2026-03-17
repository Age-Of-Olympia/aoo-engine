<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\ActionPassive;

class ActionPassiveService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getActionPassiveCount(int $playerId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
        ->select('COUNT(pp.passive)')
        ->from(\App\Entity\PlayerPassive::class, 'pp')
        ->where('pp.playerId = :playerId')
        ->setParameter('playerId', $playerId)
        ->getQuery()
        ->getSingleScalarResult();
    }

    public function getActionPassiveByName(string $name): ?ActionPassive
    {
        $query = $this->entityManager->createQuery(
        'SELECT p FROM App\Entity\ActionPassive p WHERE p.name = :name'
        );
        $query->setParameter("name", $name);
        
        try {
            return $query->getSingleResult();
        } catch (\Doctrine\ORM\NoResultException $e) {
            return null;
        }
    }

    public function getIdByName($name): int
    {
        $query = $this->entityManager->createQuery(
        'SELECT p.id FROM App\Entity\ActionPassive p WHERE p.name = :name'
        );
        $query->setParameter("name", $name);

        try {
            return (int) $query->getSingleScalarResult();
        } catch (\Doctrine\ORM\NoResultException | \Doctrine\ORM\NonUniqueResultException $e) {
            return 0;
        }
    }

    public function getActionPassivesByCategory(string $category): array
    {
        $query = $this->entityManager->createQuery(
        'SELECT a FROM App\Entity\ActionPassive a 
         WHERE a.category = :cat 
         ORDER BY a.level ASC, a.name ASC'
        )
        ->setParameter('cat', $category);

        return $query->getResult();
    }

    public function getPrice($level) : int
        {

        switch ($level) {
            case 1:
                return 50;
            case 2:
                return 100;
            case 3:
                return 200;
            case 4:
                return 300;
            case 5:
                return 300;
            default:
                return 50;
        }
    }

}
