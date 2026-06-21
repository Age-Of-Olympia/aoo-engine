<?php

namespace App\Service\Action;

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Entity\Action;
use App\Entity\ActionOutcome;
use App\Entity\EntityManagerFactory;
use App\Entity\OutcomeInstruction;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final class ActionOutcomeEditService
{
    private EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
    }

    /**
     * Instruction types that can be added, sorted; sourced from the STI map.
     *
     * @return array<int, string>
     */
    public function availableInstructionTypes(): array
    {
        $types = array_keys(OutcomeInstructionFactory::typeMap());
        sort($types);

        return $types;
    }

    public function addOutcome(int $actionId, bool $onSuccess): ActionOutcome
    {
        $action = $this->entityManager->find(Action::class, $actionId);
        if ($action === null) {
            throw new InvalidArgumentException("Action introuvable : {$actionId}.");
        }

        $outcome = new ActionOutcome();
        $outcome->setOnSuccess($onSuccess);
        $outcome->setAction($action);
        $action->addOutcome($outcome);

        $this->entityManager->persist($outcome);
        $this->entityManager->flush();

        return $outcome;
    }

    public function removeOutcome(int $outcomeId): void
    {
        $outcome = $this->entityManager->find(ActionOutcome::class, $outcomeId);
        if ($outcome === null) {
            throw new InvalidArgumentException("Outcome introuvable : {$outcomeId}.");
        }

        // Detach from the owning action so orphanRemoval deletes it and its
        // instructions (cascade remove + orphanRemoval on the instructions).
        $action = $outcome->getAction();
        if ($action !== null) {
            $action->removeOutcome($outcome);
        } else {
            $this->entityManager->remove($outcome);
        }

        $this->entityManager->flush();
    }

    public function addInstruction(int $outcomeId, string $type): OutcomeInstruction
    {
        $outcome = $this->entityManager->find(ActionOutcome::class, $outcomeId);
        if ($outcome === null) {
            throw new InvalidArgumentException("Outcome introuvable : {$outcomeId}.");
        }

        $map = OutcomeInstructionFactory::typeMap();
        if (!isset($map[$type])) {
            throw new InvalidArgumentException("Type d'instruction inconnu : {$type}.");
        }

        $class = $map[$type];
        /** @var OutcomeInstruction $instruction */
        $instruction = new $class();
        $instruction->setParameters([]);
        $instruction->setOrderIndex($outcome->getInstructions()->count());
        $instruction->setOutcome($outcome);
        $outcome->addInstruction($instruction);

        $this->entityManager->persist($instruction);
        $this->entityManager->flush();

        return $instruction;
    }

    public function removeInstruction(int $instructionId): void
    {
        $instruction = $this->entityManager->find(OutcomeInstruction::class, $instructionId);
        if ($instruction === null) {
            throw new InvalidArgumentException("Instruction introuvable : {$instructionId}.");
        }

        $outcome = $instruction->getOutcome();
        if ($outcome !== null) {
            $outcome->removeInstruction($instruction);
        } else {
            $this->entityManager->remove($instruction);
        }

        $this->entityManager->flush();
    }
}
