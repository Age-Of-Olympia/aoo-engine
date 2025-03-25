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
    public function getOutcomeInstructionByTypeByEffect(string $type, int $effectId): ?OutcomeInstructionInterface
    {
        //$query = $this->entityManager->createQuery('SELECT OutcomeInstruction FROM App\\Entity\\OutcomeInstruction OutcomeInstruction WHERE OutcomeInstruction INSTANCE OF App\\OutcomeInstruction\\'.$type.'OutcomeInstruction');
                                                    //'SELECT action FROM App\\Action\\'.$type.'Action action'
        $query = $this->entityManager->createQuery('SELECT outcome_instructions FROM App\\Action\\OutcomeInstruction\\'.$type.' effect_instructions WHERE effect_instructions.effect = :id');
        $query->setParameter("id",$effectId);
        $log = $query->getSQL();
        $OutcomeInstruction = null;
        try {
            $OutcomeInstruction = $query->getSingleResult();
        } catch (NoResultException) {
            return null;
        }
        return $OutcomeInstruction;
    }

    public function getOutcomeInstructionsByEffect(int $effectId): array
    {
        $instructionTypes = OutcomeInstructionFactory::initialize("src/Action/OutcomeInstruction");

        $OutcomeInstructions = array();
        foreach ($instructionTypes as $instruction) {
            $OutcomeInstruction = $this->getOutcomeInstructionByTypeByEffect($instruction, $effectId);
            if ($OutcomeInstruction != null) {
                array_push($OutcomeInstructions, $OutcomeInstruction);
            }
        }
        
        return $OutcomeInstructions;
    }

}