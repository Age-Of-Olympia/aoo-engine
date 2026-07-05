<?php

namespace App\Service\ImportExport;

use App\Entity\ActionPassive;
use App\Entity\EntityManagerFactory;
use App\Service\Action\ActionPassiveCatalogService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Imports passive payloads produced by {@see PassiveExporter}. A passive is flat
 * (no STI, no children), so create-or-update by name just overwrites scalars and
 * the two JSON columns — no type whitelisting or child rebuild.
 *
 * Proves the {@see AbstractObjectImporter} skeleton generalises beyond actions.
 */
final class PassiveImporter extends AbstractObjectImporter
{
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

    protected function accept(ImportReport $report, array &$seen, mixed $object, int $index): mixed
    {
        $name = $this->classify($report, $object, $index);
        if ($name === null || $this->isDuplicate($report, $seen, $name)) {
            return null;
        }

        $this->record($report, $name);
        /** @var array<string, mixed> $object */

        return $object;
    }

    protected function applyPlan(mixed $plan): void
    {
        /** @var array<string, mixed> $plan */
        $this->apply($plan);
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
     * Mirror ActionPassiveSaveService::parseTraits: a clean list of trimmed,
     * non-empty strings. Imported traits are matched with in_array() at runtime
     * and run through strval() in the editor, so a non-scalar or blank value
     * would corrupt both — coerce here instead of storing the bundle verbatim.
     *
     * @param array<string, mixed> $object
     * @return array<int, string>
     */
    private function traits(array $object): array
    {
        $raw = is_array($object['traits'] ?? null) ? $object['traits'] : [];

        $clean = [];
        foreach ($raw as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $trait = trim((string) $value);
            if ($trait !== '') {
                $clean[] = $trait;
            }
        }

        return array_values($clean);
    }
}
