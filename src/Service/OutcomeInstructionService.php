<?php

namespace App\Service;

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Entity\EntityManagerFactory;
use App\Entity\OutcomeInstruction;
use App\Interface\OutcomeInstructionInterface;
use Doctrine\ORM\NoResultException;
use Exception;

class OutcomeInstructionService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Returns a OutcomeInstruction entity that matches the given type, or null if not found.
     */
    public function getOutcomeInstructionByTypeByOutcome(string $type, int $outcomeId): ?OutcomeInstructionInterface
    {
        //$query = $this->entityManager->createQuery('SELECT OutcomeInstruction FROM App\\Entity\\OutcomeInstruction OutcomeInstruction WHERE OutcomeInstruction INSTANCE OF App\\OutcomeInstruction\\'.$type.'OutcomeInstruction');
                                                    //'SELECT action FROM App\\Action\\'.$type.'Action action'
        $query = $this->entityManager->createQuery('SELECT outcome_instructions FROM App\\Action\\OutcomeInstruction\\'.$type.' outcome_instructions WHERE outcome_instructions.outcome = :id ORDER BY outcome_instructions.orderIndex ASC');
        $query->setParameter("id",$outcomeId);
        $log = $query->getSQL();
        $OutcomeInstruction = null;
        try {
            $OutcomeInstruction = $query->getSingleResult();
        } catch (NoResultException) {
            return null;
        }
        return $OutcomeInstruction;
    }

    public function getOutcomeInstructionsByOutcome(int $outcomeId): array
    {
        $instructionTypes = OutcomeInstructionFactory::initialize("src/Action/OutcomeInstruction");

        $outcomeInstructions = array();
        foreach ($instructionTypes as $instruction) {
            $outcomeInstruction = $this->getOutcomeInstructionByTypeByOutcome($instruction, $outcomeId);
            if ($outcomeInstruction != null) {
                array_push($outcomeInstructions, $outcomeInstruction);
            }
        }
        
        usort($outcomeInstructions, function($a, $b) {
            return $a->getOrderIndex() <=> $b->getOrderIndex();
        });
        return $outcomeInstructions;
    }

}