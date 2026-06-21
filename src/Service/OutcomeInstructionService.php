<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;

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
}
