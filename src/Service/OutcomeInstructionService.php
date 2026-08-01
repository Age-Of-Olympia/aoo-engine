<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;

class OutcomeInstructionService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * All outcome instructions for an outcome, ordered by orderIndex.
     *
     * OutcomeInstruction is single-table inheritance, so one query over the base
     * class returns every row already hydrated to its concrete subtype — instead
     * of one query per instruction type (this runs in several hot loops).
     *
     * @return array<int, \App\Entity\OutcomeInstruction>
     */
    public function getOutcomeInstructionsByOutcome(int $outcomeId): array
    {
        $query = $this->entityManager->createQuery(
            'SELECT oi FROM App\\Entity\\OutcomeInstruction oi WHERE oi.outcome = :id ORDER BY oi.orderIndex ASC'
        );
        $query->setParameter('id', $outcomeId);

        return $query->getResult();
    }

    /**
     * Instructions d'un outcome filtrées par type (nom court de classe,
     * ex. "LifeLossOutcomeInstruction").
     *
     * Rétabli après la refonte mono-requête (843c68b9) qui l'avait
     * supprimé en oubliant deux appelants (scripts/upgrades/spells.php,
     * scripts/tools/generate_actions_wiki.php) — fatal sur la page
     * Sorts pour tout personnage connaissant un sort. Réutilise la
     * requête unique puis filtre en mémoire : le gain de la refonte
     * est conservé.
     *
     * @return array<int, \App\Entity\OutcomeInstruction>
     */
    public function getOutcomeInstructionByTypeByOutcome(string $type, int $outcomeId): array
    {
        $fqcn = 'App\\Action\\OutcomeInstruction\\' . $type;

        return array_values(array_filter(
            $this->getOutcomeInstructionsByOutcome($outcomeId),
            static function ($instruction) use ($fqcn) {
                return $instruction instanceof $fqcn;
            }
        ));
    }
}
