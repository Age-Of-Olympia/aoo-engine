<?php

namespace App\Service;

use App\Action\EffectInstruction\EffectInstructionFactory;
use App\Entity\EntityManagerFactory;
use App\Entity\EffectInstruction;
use App\Interface\EffectInstructionInterface;
use Doctrine\ORM\NoResultException;
use Exception;

class EffectInstructionService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Returns a EffectInstruction entity that matches the given type, or null if not found.
     */
    public function getEffectInstructionByTypeByEffect(string $type, int $effectId): ?EffectInstructionInterface
    {
        //$query = $this->entityManager->createQuery('SELECT EffectInstruction FROM App\\Entity\\EffectInstruction EffectInstruction WHERE EffectInstruction INSTANCE OF App\\EffectInstruction\\'.$type.'EffectInstruction');
                                                    //'SELECT action FROM App\\Action\\'.$type.'Action action'
        $query = $this->entityManager->createQuery('SELECT effect_instructions FROM App\\Action\\EffectInstruction\\'.$type.' effect_instructions WHERE effect_instructions.effect = :id');
        $query->setParameter("id",$effectId);
        $log = $query->getSQL();
        $effectInstruction = null;
        try {
            $effectInstruction = $query->getSingleResult();
        } catch (NoResultException) {
            return null;
        }
        return $effectInstruction;
    }

    public function getEffectInstructionsByEffect(int $effectId): array
    {
        $instructionTypes = EffectInstructionFactory::initialize("src/Action/EffectInstruction");

        $effectInstructions = array();
        foreach ($instructionTypes as $instruction) {
            $effectInstruction = $this->getEffectInstructionByTypeByEffect($instruction, $effectId);
            if ($effectInstruction != null) {
                array_push($effectInstructions, $effectInstruction);
            }
        }
        
        return $effectInstructions;
    }

    // /**
    //  * Returns the ID of the EffectInstruction that matches the given type, or null if not found.
    //  */
    // public function getEffectInstructionIdByType(string $type): ?int
    // {
    //     $EffectInstruction = $this->getEffectInstructionByType($type);
    //     return $EffectInstruction ? $EffectInstruction->getId() : null;
    // }
}