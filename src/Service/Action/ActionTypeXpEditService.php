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
     * The EFFECTIVE XP rule for a type: its own row, or the closest ancestor's
     * (inheritedFrom names that ancestor type), or a "fixed" zero reward when no
     * type in the ancestry has a row. Params are the mode's full key set.
     *
     * @return array{mode: string, params: array<string, int>, inheritedFrom: ?string}
     */
    public function configForType(string $typeKey): array
    {
        $chain = $this->registry->ancestryForTypeKey($typeKey) ?: [$typeKey];

        /** @var array<int, \App\Entity\ActionTypeXp> $rows */
        $rows = $this->entityManager->getRepository(ActionTypeXp::class)->findBy(['typeKey' => $chain]);
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row->getTypeKey()] = $row;
        }

        foreach ($chain as $depth => $key) {
            if (!isset($byKey[$key])) {
                continue;
            }
            $row = $byKey[$key];
            $mode = $this->calculators->has($row->getMode()) ? $row->getMode() : XpCalculatorRegistry::MODE_FIXED;

            return [
                'mode' => $mode,
                'params' => array_merge($this->calculators->defaultsFor($mode), $row->getParams()),
                'inheritedFrom' => $depth === 0 ? null : $key,
            ];
        }

        return ['mode' => XpCalculatorRegistry::MODE_FIXED, 'params' => $this->calculators->defaultsFor(XpCalculatorRegistry::MODE_FIXED), 'inheritedFrom' => null];
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
