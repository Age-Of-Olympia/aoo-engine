<?php

namespace App\Service\ImportExport;

use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Exports {@see Race} entities to natural-key payloads. Identity is the race
 * `name` (the lowercase code stored in players.race). The payload carries
 * everything the race editor manages : identité, drapeaux, couleurs,
 * faction / plan / animateur, les 16 CARACS et les deux listes de noms.
 * Les compteurs de portraits/avatars sont volontairement absents : état
 * d'environnement, pas de la définition de la race.
 */
final class RaceExporter implements ObjectExporter
{
    private ?EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        // Lazy: le factory ouvre une connexion DB, toArray() doit rester pur.
        $this->entityManager = $entityManager;
    }

    public function objectType(): string
    {
        return 'race';
    }

    public function exportAll(): array
    {
        $entityManager = $this->entityManager ??= EntityManagerFactory::getEntityManager();
        $races = $entityManager->getRepository(Race::class)->findBy([], ['name' => 'ASC']);

        return array_map(fn (Race $race): array => $this->toArray($race), $races);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $entity): array
    {
        if (!$entity instanceof Race) {
            throw new InvalidArgumentException('RaceExporter can only export Race entities.');
        }

        return [
            'name'           => $entity->getName(),
            'label'          => $entity->getLabel(),
            'description'    => $entity->getDescription(),
            'playable'       => $entity->getPlayable(),
            'hidden'         => $entity->getHidden(),
            'kind'           => $entity->getKind(),
            'structureNature' => $entity->getStructureNature(),
            'bgColor'        => $entity->getBgColor(),
            'color'          => $entity->getColor(),
            'faction'        => $entity->getFaction(),
            'plan'           => $entity->getPlan(),
            'animateurId'    => $entity->getAnimateurId(),
            'caracs'         => $entity->getCaracs(),
            'starterActions' => $entity->getStarterActionNames(),
            'spells'         => $entity->getSpellNames(),
        ];
    }
}
