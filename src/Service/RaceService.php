<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Race;

class RaceService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Returns a Race entity that matches the given name, or null if not found.
     */
    public function getRaceByName(string $name): ?Race
    {
        $repo = $this->entityManager->getRepository(Race::class);
        return $repo->findOneBy(['name' => $name]);
    }

    /**
     * Returns the ID of the Race that matches the given name, or null if not found.
     */
    public function getRaceIdByName(string $name): ?int
    {
        $race = $this->getRaceByName($name);
        return $race ? $race->getId() : null;
    }
}