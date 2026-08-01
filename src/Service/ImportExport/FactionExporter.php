<?php

namespace App\Service\ImportExport;

use App\Entity\EntityManagerFactory;
use App\Entity\Faction;
use App\Entity\FactionRole;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Exports {@see Faction} entities to natural-key payloads. Identity is the
 * faction `code` (stored in players.faction / players.secretFaction). The
 * payload carries everything the faction editor manages : identité, lore,
 * icône, plan de respawn, drapeaux et la liste ORDONNÉE des rôles avec leurs
 * drapeaux de permissions (booléens explicites — contrairement au read model
 * runtime, le bundle n'omet rien).
 */
final class FactionExporter implements ObjectExporterInterface
{
    private ?EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        // Lazy: le factory ouvre une connexion DB, toArray() doit rester pur.
        $this->entityManager = $entityManager;
    }

    public function objectType(): string
    {
        return 'faction';
    }

    public function exportAll(): array
    {
        $entityManager = $this->entityManager ??= EntityManagerFactory::getEntityManager();
        $factions = $entityManager->getRepository(Faction::class)->findBy([], ['code' => 'ASC']);

        return array_map(fn (Faction $faction): array => $this->toArray($faction), $factions);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $entity): array
    {
        if (!$entity instanceof Faction) {
            throw new InvalidArgumentException('FactionExporter can only export Faction entities.');
        }

        return [
            'code'        => $entity->getCode(),
            'name'        => $entity->getName(),
            'text'        => $entity->getText(),
            'raFont'      => $entity->getRaFont(),
            'respawnPlan' => $entity->getRespawnPlan(),
            'hidden'      => $entity->isHidden(),
            'secret'      => $entity->isSecret(),
            'roles'       => array_map(
                static fn (FactionRole $role): array => array_merge(
                    ['name' => $role->getName()],
                    $role->getFlags()
                ),
                $entity->getRoles()->getValues()
            ),
        ];
    }
}
