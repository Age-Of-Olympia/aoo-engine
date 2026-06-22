<?php

namespace App\Service\Action;

use App\Entity\ActionPassive;
use App\Entity\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Save the editable fields of a passive from the workbench form. Scalars are
 * coerced, traits come in as a comma-separated list, and conditions as a JSON
 * blob (empty = none).
 */
final class ActionPassiveSaveService
{
    private EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function saveFields(int $id, array $fields): void
    {
        $passive = $this->entityManager->find(ActionPassive::class, $id);
        if ($passive === null) {
            throw new InvalidArgumentException("Passif introuvable : {$id}.");
        }

        $name = trim((string) ($fields['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Le nom du passif est requis.');
        }

        $passive->setName($name);
        $passive->setDisplayName(trim((string) ($fields['displayName'] ?? '')) ?: $name);
        $passive->setType(trim((string) ($fields['type'] ?? '')));
        $passive->setCarac(trim((string) ($fields['carac'] ?? '')));
        $passive->setValue((float) ($fields['value'] ?? 0));
        $passive->setLevel((int) ($fields['level'] ?? 0));
        $passive->setRace(trim((string) ($fields['race'] ?? '')));
        $passive->setCategory(trim((string) ($fields['category'] ?? '')));
        $passive->setText(trim((string) ($fields['text'] ?? '')));
        $passive->setPrerequisites(trim((string) ($fields['prerequisites'] ?? '')));
        $passive->setTraits($this->parseTraits((string) ($fields['traits'] ?? '')));
        $passive->setConditions($this->parseConditions((string) ($fields['conditions'] ?? '')));

        $this->entityManager->flush();
    }

    /**
     * @return array<int, string>
     */
    private function parseTraits(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($t): bool => $t !== ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseConditions(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Conditions : JSON invalide.');
        }

        return $decoded;
    }
}
