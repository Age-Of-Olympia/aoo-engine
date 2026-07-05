<?php

namespace App\Service\Action;

use App\Entity\ActionTypeLog;
use App\Entity\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Read/write the log-message templates configured on an action TYPE
 * (the type-defaults editor). Identity is the type key (create-or-update).
 */
final class ActionTypeLogEditService
{
    private EntityManagerInterface $entityManager;
    private ActionTypeRegistry $registry;

    public function __construct(?EntityManagerInterface $entityManager = null, ?ActionTypeRegistry $registry = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ActionTypeRegistry();
    }

    /**
     * The EFFECTIVE templates for a type. `inheritedFrom` names the ancestor the
     * shown templates come from when the type has no own row; `overriddenParent`
     * names the closest ancestor row this type's own row overrides (so the editor
     * can say "hérité de X mais surchargé ici").
     *
     * @return array{actor: ?string, target: ?string, inheritedFrom: ?string, overriddenParent: ?string}
     */
    public function templatesForType(string $typeKey): array
    {
        $chain = $this->registry->ancestryForTypeKey($typeKey) ?: [$typeKey];

        /** @var array<int, ActionTypeLog> $rows */
        $rows = $this->entityManager->getRepository(ActionTypeLog::class)->findBy(['typeKey' => $chain]);
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row->getTypeKey()] = $row;
        }

        $overriddenParent = null;
        foreach (array_slice($chain, 1) as $key) {
            if (isset($byKey[$key])) {
                $overriddenParent = $key;
                break;
            }
        }

        foreach ($chain as $depth => $key) {
            if (isset($byKey[$key])) {
                $log = $byKey[$key];

                return [
                    'actor' => $log->getActorTemplate(),
                    'target' => $log->getTargetTemplate(),
                    'inheritedFrom' => $depth === 0 ? null : $key,
                    'overriddenParent' => $depth === 0 ? $overriddenParent : null,
                ];
            }
        }

        return ['actor' => null, 'target' => null, 'inheritedFrom' => null, 'overriddenParent' => null];
    }

    /**
     * Upsert the templates for a type. Empty strings are stored as null (no line).
     */
    public function save(string $typeKey, ?string $actorTemplate, ?string $targetTemplate): void
    {
        if (!isset($this->registry->assignableTypes()[$typeKey])) {
            throw new InvalidArgumentException("Type d'action inconnu : « {$typeKey} ».");
        }

        $log = $this->find($typeKey);
        if ($log === null) {
            $log = (new ActionTypeLog())->setTypeKey($typeKey);
            $this->entityManager->persist($log);
        }

        $log->setActorTemplate($this->blankToNull($actorTemplate));
        $log->setTargetTemplate($this->blankToNull($targetTemplate));
        $this->entityManager->flush();
    }

    private function find(string $typeKey): ?ActionTypeLog
    {
        return $this->entityManager->getRepository(ActionTypeLog::class)->findOneBy(['typeKey' => $typeKey]);
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
