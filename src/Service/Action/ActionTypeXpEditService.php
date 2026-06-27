<?php

namespace App\Service\Action;

use App\Entity\ActionTypeXp;
use App\Entity\EntityManagerFactory;
use App\Service\Action\Xp\XpCalculatorRegistry;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Read/write the XP rule configured on an action TYPE (the type-defaults editor).
 * The mode picks the algorithm; only the params that mode actually reads are kept
 * (coerced to int), so switching modes can't smuggle stray keys.
 */
final class ActionTypeXpEditService
{
    private EntityManagerInterface $entityManager;
    private ActionTypeRegistry $registry;
    private XpCalculatorRegistry $calculators;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionTypeRegistry $registry = null,
        ?XpCalculatorRegistry $calculators = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ActionTypeRegistry();
        $this->calculators = $calculators ?? new XpCalculatorRegistry();
    }

    /**
     * The mode + params for a type, defaulting to a "fixed" zero reward when the
     * type has no row yet. Params are the mode's full key set (stored over default).
     *
     * @return array{mode: string, params: array<string, int>}
     */
    public function configForType(string $typeKey): array
    {
        $row = $this->find($typeKey);
        $mode = $row?->getMode() ?? XpCalculatorRegistry::MODE_FIXED;
        if (!$this->calculators->has($mode)) {
            $mode = XpCalculatorRegistry::MODE_FIXED;
        }

        return ['mode' => $mode, 'params' => array_merge($this->calculators->defaultsFor($mode), $row?->getParams() ?? [])];
    }

    /**
     * @param array<string, mixed> $rawParams posted values, keyed by param name
     */
    public function save(string $typeKey, string $mode, array $rawParams): void
    {
        if (!isset($this->registry->assignableTypes()[$typeKey])) {
            throw new InvalidArgumentException("Type d'action inconnu : « {$typeKey} ».");
        }
        if (!$this->calculators->has($mode)) {
            throw new InvalidArgumentException("Mode XP inconnu : « {$mode} ».");
        }

        $params = [];
        foreach ($this->calculators->defaultsFor($mode) as $key => $default) {
            $params[$key] = (int) ($rawParams[$key] ?? $default);
        }

        $row = $this->find($typeKey);
        if ($row === null) {
            $row = (new ActionTypeXp())->setTypeKey($typeKey);
            $this->entityManager->persist($row);
        }
        $row->setMode($mode)->setParams($params);
        $this->entityManager->flush();
    }

    private function find(string $typeKey): ?ActionTypeXp
    {
        return $this->entityManager->getRepository(ActionTypeXp::class)->findOneBy(['typeKey' => $typeKey]);
    }
}
