<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;

class ActionPassiveService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
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
