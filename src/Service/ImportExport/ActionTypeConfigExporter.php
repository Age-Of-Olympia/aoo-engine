<?php

namespace App\Service\ImportExport;

use App\Interface\ObjectExporterInterface;
use App\Entity\ActionTypeLog;
use App\Entity\ActionTypeXp;
use App\Factory\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Exports the per-type action config — the XP rule and the log templates — as
 * one payload per type key. These are type-level settings (not per-action), so
 * the natural key is the typeKey and each object carries an optional `xp` and
 * `logs` block (null when that type has no row of that kind).
 *
 * Round-trips with {@see ActionTypeConfigImporter}.
 */
final class ActionTypeConfigExporter implements ObjectExporterInterface
{
    private ?EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        // Lazy: the default factory opens a DB connection, so toArray() stays pure.
        $this->entityManager = $entityManager;
    }

    public function objectType(): string
    {
        return 'action-type';
    }

    public function exportAll(): array
    {
        $em = $this->em();

        $xpByType = [];
        foreach ($em->getRepository(ActionTypeXp::class)->findAll() as $xp) {
            $xpByType[$xp->getTypeKey()] = $xp;
        }
        $logByType = [];
        foreach ($em->getRepository(ActionTypeLog::class)->findAll() as $log) {
            $logByType[$log->getTypeKey()] = $log;
        }

        $typeKeys = array_keys($xpByType + $logByType);
        sort($typeKeys);

        $objects = [];
        foreach ($typeKeys as $typeKey) {
            $objects[] = $this->payload($typeKey, $xpByType[$typeKey] ?? null, $logByType[$typeKey] ?? null);
        }

        return $objects;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $entity): array
    {
        $typeKey = match (true) {
            $entity instanceof ActionTypeXp, $entity instanceof ActionTypeLog => $entity->getTypeKey(),
            default => throw new InvalidArgumentException('ActionTypeConfigExporter exports ActionTypeXp / ActionTypeLog.'),
        };

        $em = $this->em();

        return $this->payload(
            $typeKey,
            $em->getRepository(ActionTypeXp::class)->findOneBy(['typeKey' => $typeKey]),
            $em->getRepository(ActionTypeLog::class)->findOneBy(['typeKey' => $typeKey]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $typeKey, ?ActionTypeXp $xp, ?ActionTypeLog $log): array
    {
        return [
            'typeKey' => $typeKey,
            'xp' => $xp === null ? null : ['mode' => $xp->getMode(), 'params' => $xp->getParams()],
            'logs' => $log === null ? null : ['actorTemplate' => $log->getActorTemplate(), 'targetTemplate' => $log->getTargetTemplate()],
        ];
    }

    private function em(): EntityManagerInterface
    {
        return $this->entityManager ??= EntityManagerFactory::getEntityManager();
    }
}
