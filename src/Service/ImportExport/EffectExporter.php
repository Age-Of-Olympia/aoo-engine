<?php

namespace App\Service\ImportExport;

use App\Entity\Effect;
use App\Entity\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Exports {@see Effect} entities to natural-key payloads. Identity is the
 * effect `name` (referenced by players_effects and action parameters).
 * The payload carries everything the effect editor manages : identité
 * (label, description, icône), drapeaux (caché, marqueur), buff/debuff,
 * la liste des effets annulés et la corruption (chance + matériaux).
 */
final class EffectExporter implements ObjectExporter
{
    private ?EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        // Lazy: le factory ouvre une connexion DB, toArray() doit rester pur.
        $this->entityManager = $entityManager;
    }

    public function objectType(): string
    {
        return 'effect';
    }

    public function exportAll(): array
    {
        $entityManager = $this->entityManager ??= EntityManagerFactory::getEntityManager();
        $effects = $entityManager->getRepository(Effect::class)->findBy([], ['name' => 'ASC']);

        return array_map(fn (Effect $effect): array => $this->toArray($effect), $effects);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $entity): array
    {
        if (!$entity instanceof Effect) {
            throw new InvalidArgumentException('EffectExporter can only export Effect entities.');
        }

        return [
            'name'                  => $entity->getName(),
            'label'                 => $entity->getLabel(),
            'description'           => $entity->getDescription(),
            'icon'                  => $entity->getIcon(),
            'hidden'                => $entity->isHidden(),
            'isMapMarker'           => $entity->isMapMarker(),
            'buffCarac'             => $entity->getBuffCarac(),
            'debuffCarac'           => $entity->getDebuffCarac(),
            'rollAttackMod'         => $entity->getRollAttackMod(),
            'rollDefenseMod'        => $entity->getRollDefenseMod(),
            'damageDealtMod'        => $entity->getDamageDealtMod(),
            'damageTakenMod'        => $entity->getDamageTakenMod(),
            'pushAttackMod'         => $entity->getPushAttackMod(),
            'pushDefenseMod'        => $entity->getPushDefenseMod(),
            'damageTakenFactor'     => $entity->getDamageTakenFactor(),
            'blockRecovery'         => $entity->getBlockRecovery(),
            'turnRegen'             => $entity->isTurnRegen(),
            'turnMvtMalus'          => $entity->isTurnMvtMalus(),
            'controls'              => $entity->getControlNames(),
            'corruptionBreakChance' => $entity->getCorruptionBreakChance(),
            'corruptionMaterials'   => $entity->getCorruptionMaterialNames(),
        ];
    }
}
