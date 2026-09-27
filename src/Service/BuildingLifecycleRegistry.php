<?php

namespace App\Service;

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
}
