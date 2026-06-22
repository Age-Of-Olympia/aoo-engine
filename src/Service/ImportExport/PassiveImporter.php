<?php

namespace App\Service\ImportExport;

use App\Entity\ActionPassive;
use App\Entity\EntityManagerFactory;
use App\Service\Action\ActionPassiveCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Imports passive payloads produced by {@see PassiveExporter}. A passive is flat
 * (no STI, no children), so create-or-update by name just overwrites scalars and
 * the two JSON columns — no type whitelisting or child rebuild. import() is
 * transactional and all-or-nothing, matching {@see ActionImporter}.
 *
 * Proves the {@see ObjectImporter} seam generalises beyond actions.
 */
final class PassiveImporter implements ObjectImporter
{
    private EntityManagerInterface $entityManager;
    private ActionPassiveCatalogService $catalog;

    public function __construct(
        ?EntityManagerInterface $entityManager = null,
        ?ActionPassiveCatalogService $catalog = null,
    ) {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->catalog = $catalog ?? new ActionPassiveCatalogService($this->entityManager);
    }

    public function objectType(): string
    {
        return 'passive';
    }

    public function preview(array $objects): ImportReport
    {
        $report = new ImportReport();
        $seen = [];
        foreach ($objects as $index => $object) {
            $name = $this->classify($report, $object, (int) $index);
            if ($name === null || $this->isDuplicate($report, $seen, $name)) {
                continue;
            }
            $this->record($report, $name);
        }

        return $report;
    }

    public function import(array $objects): ImportReport
    {
        $report = new ImportReport();
        $this->entityManager->beginTransaction();
        try {
            $seen = [];
            $plans = [];
            foreach ($objects as $index => $object) {
                $name = $this->classify($report, $object, (int) $index);
                if ($name === null || $this->isDuplicate($report, $seen, $name)) {
                    continue;
                }
                /** @var array<string, mixed> $object */
                $plans[] = $object;
                $this->record($report, $name);
            }

            if ($report->hasRejections()) {
                $this->entityManager->rollback();
                $this->entityManager->clear();
                return $report;
            }

            foreach ($plans as $object) {
                $this->apply($object);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $exception) {
            $this->entityManager->rollback();
            $this->entityManager->clear();
            throw $exception;
        }

        return $report;
    }

    /**
     * Validate the object and return its name, or null (recording a rejection)
     * when it can't import. A passive has no type/children to whitelist, so the
     * only structural requirement is a non-empty name.
     */
    private function classify(ImportReport $report, mixed $object, int $index): ?string
    {
        if (!is_array($object)) {
            $report->reject('#' . $index, 'Objet invalide (pas un objet JSON).');
            return null;
        }

        $name = is_string($object['name'] ?? null) ? trim($object['name']) : '';
        if ($name === '') {
            $report->reject('#' . $index, 'Nom manquant.');
            return null;
        }

        return $name;
    }

    private function record(ImportReport $report, string $name): void
    {
        if ($this->catalog->findByName($name) !== null) {
            $report->addUpdated($name);
        } else {
            $report->addCreated($name);
        }
    }

    /**
     * @param array<string, mixed> $object
     */
    private function apply(array $object): void
    {
        $name = trim((string) ($object['name'] ?? ''));
        $passive = $this->catalog->findByName($name);
        if ($passive === null) {
            $passive = new ActionPassive();
            $passive->setName($name);
            $this->entityManager->persist($passive);
        }

        // Mirror ActionPassiveSaveService: scalars coerced, empty nullable columns
        // stored as '' rather than null, traits a list, conditions a JSON map.
        $displayName = trim((string) ($object['displayName'] ?? ''));
        $passive->setDisplayName($displayName !== '' ? $displayName : $name);
        $passive->setType((string) ($object['type'] ?? ''));
        $passive->setCarac((string) ($object['carac'] ?? ''));
        $passive->setValue((float) ($object['value'] ?? 0));
        $passive->setLevel((int) ($object['level'] ?? 0));
        $passive->setRace((string) ($object['race'] ?? ''));
        $passive->setCategory((string) ($object['category'] ?? ''));
        $passive->setText((string) ($object['text'] ?? ''));
        $passive->setPrerequisites((string) ($object['prerequisites'] ?? ''));
        $passive->setTraits($this->traits($object));
        $passive->setConditions(is_array($object['conditions'] ?? null) ? $object['conditions'] : null);
    }

    /**
     * @param array<string, mixed> $object
     * @return array<int, mixed>
     */
    private function traits(array $object): array
    {
        return is_array($object['traits'] ?? null) ? array_values($object['traits']) : [];
    }

    /**
     * @param array<string, true> $seen
     */
    private function isDuplicate(ImportReport $report, array &$seen, string $name): bool
    {
        if (isset($seen[$name])) {
            $report->reject($name, 'Doublon : « ' . $name . " » apparaît plusieurs fois dans le lot.");
            return true;
        }
        $seen[$name] = true;

        return false;
    }
}
