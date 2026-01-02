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

    /**
     * Returns the background color of the Race that matches the given name.
     */
    public function getRaceBackgroundColor(string $raceName): string {
        $raceName = strtolower($raceName);
        $raceData = json()->decode('races', $raceName);

        if ($raceData && isset($raceData->bgColor)) {
            return $raceData->bgColor;
        }

        return '#FFFFFF';
    }

    /**
     * Returns the max movement points for a race from JSON data.
     *
     * @param string $raceName Race name (e.g., 'nain', 'elfe')
     * @return int Max movement points (default 4 if not found)
     */
    public function getRaceMaxMvt(string $raceName): int {
        $raceName = strtolower($raceName);
        $raceData = json()->decode('races', $raceName);

        if ($raceData && isset($raceData->mvt)) {
            return (int)$raceData->mvt;
        }

        return 4; // Default fallback
    }

    /**
     * Returns full race data from JSON.
     *
     * @param string $raceName Race name (e.g., 'nain', 'elfe')
     * @return object|null Race data object or null if not found
     */
    public function getRaceData(string $raceName): ?object {
        $raceName = strtolower($raceName);
        return json()->decode('races', $raceName) ?: null;
    }
}
