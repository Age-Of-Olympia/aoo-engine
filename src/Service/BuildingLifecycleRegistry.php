<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Interface\BuildingLifecycleInterface;

/**
 * One lifecycle behavior per building type (races.name). Adding a type
 * is one line here — a future town center or tower will bring its
 * class the way the bank brought its own.
 */
class BuildingLifecycleRegistry
{
    /** @var array<string, BuildingLifecycleInterface> */
    private array $lifecycles;

    public function __construct()
    {
        $this->lifecycles = [
            'banque' => new BankLifecycle(),
        ];
    }

    public function of(string $raceName): ?BuildingLifecycleInterface
    {
        return $this->lifecycles[$raceName] ?? null;
    }

    /**
     * Resolves the building's type/plan/faction and fires rose() when
     * its type has a behavior — called at the two places a building
     * becomes built: end of chantier, and workless placement.
     */
    public static function dispatchRose(int $entityId): void
    {
        $row = EntityManagerFactory::getEntityManager()->getConnection()->fetchAssociative(
            'SELECT p.race, p.faction, c.plan
               FROM players p
               JOIN coords c ON c.id = p.coords_id
              WHERE p.id = ?',
            [$entityId]
        );
        if ($row === false) {
            return;
        }

        (new self())->of((string) $row['race'])
            ?->rose($entityId, (string) $row['plan'], (string) $row['faction']);
    }
}
