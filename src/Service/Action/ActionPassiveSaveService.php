<?php

namespace App\Service\Action;

use App\Entity\ActionPassive;
use App\Factory\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Save the editable fields of a passive from the workbench form. Scalars are
 * coerced, traits come in as a comma-separated list, and conditions are built
 * from the structured picker (a `conditions_mode` selecting a weapon/category
 * whitelist) with a raw-JSON fallback.
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
        $passive = EntityFinder::orFail($this->entityManager, ActionPassive::class, $id, 'Passif');

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
        $passive->setTraits($this->parseTraits($fields['traits'] ?? []));
        $passive->setConditions($this->buildConditions($fields));

        $this->entityManager->flush();
    }

    /**
     * Build the conditions payload from the structured picker. The mode picks the
     * shape: a `weapon`/`category` whitelist from the checkboxes, none, or the raw
     * JSON fallback. An absent mode parses the raw field (backward compatible).
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>|null
     */
    private function buildConditions(array $fields): ?array
    {
        return match ((string) ($fields['conditions_mode'] ?? '')) {
            'none' => null,
            'weapon' => $this->whitelist('weapon', $fields['conditions_weapon'] ?? null),
            'category' => $this->whitelist('category', $fields['conditions_category'] ?? null),
            default => $this->parseConditions((string) ($fields['conditions'] ?? '')),
        };
    }

    /**
     * A single-key whitelist condition (e.g. {"weapon": [...]}). Blanks are
     * dropped; an empty selection means "no condition" (null), so an unfinished
     * pick never silently blocks the passive for everyone.
     *
     * @return array<string, array<int, string>>|null
     */
    private function whitelist(string $key, mixed $values): ?array
    {
        if (!is_array($values)) {
            return null;
        }
        $clean = array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $values), static fn (string $v): bool => $v !== ''));

        return $clean === [] ? null : [$key => $clean];
    }

    /**
     * Traits arrive as an array from the multi-select (passive[traits][]); a
     * legacy comma-separated string is still accepted.
     *
     * @return array<int, string>
     */
    private function parseTraits(mixed $raw): array
    {
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_filter(array_map(static fn ($t): string => trim((string) $t), $values), static fn ($t): bool => $t !== ''));
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
