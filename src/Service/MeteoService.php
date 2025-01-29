<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Meteo;

class MeteoService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Returns the Meteo that matches the given coord, or null if not found.
     */
    public function getMeteoByCoord_id(string $coord): ?string
    {
        $repo = $this->entityManager->getRepository(Meteo::class);
        return $repo->findOneBy(['coords_computed' => $coord]);

    }
}