<?php

namespace App\Service\Action;

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Entity\Action;
use App\Entity\ActionTypeInstruction;
use App\Entity\EntityManagerFactory;
use App\Entity\OutcomeInstruction;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves the type-level instructions an action inherits, as ready-to-run
 * OutcomeInstruction objects. For each type key in the action's class ancestry
 * (e.g. a MeleeAction inherits "melee" and "attack") it loads the configured
 * ActionTypeInstruction rows and rebuilds the STI instruction with its params.
 *
 * Order: broadest ancestor first (e.g. "attack" before "melee"), then by
 * orderIndex within a type — so parent-type defaults run before more specific
 * ones, mirroring how the old code added them on the base class.
 */
final class ActionTypeInstructionResolver
{
    private EntityManagerInterface $entityManager;
    private ActionTypeRegistry $registry;

    public function __construct(?EntityManagerInterface $entityManager = null, ?ActionTypeRegistry $registry = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
        $this->registry = $registry ?? new ActionTypeRegistry();
    }

    /**
     * @return array<int, OutcomeInstruction>
     */
    public function resolve(Action $action): array
    {
        $keys = $this->registry->typeKeysForAction($action);
        if ($keys === []) {
            return [];
        }

        // typeKeysForAction is concrete-first ([melee, attack]); reverse so the
        // broadest ancestor sorts first.
        $priority = array_flip(array_reverse(array_values($keys)));

        /** @var array<int, ActionTypeInstruction> $configs */
        $configs = $this->entityManager->getRepository(ActionTypeInstruction::class)
            ->findBy(['typeKey' => $keys]);

        usort($configs, static function (ActionTypeInstruction $a, ActionTypeInstruction $b) use ($priority): int {
            return [$priority[$a->getTypeKey()], $a->getOrderIndex()]
                <=> [$priority[$b->getTypeKey()], $b->getOrderIndex()];
        });

        $instructions = [];
        foreach ($configs as $config) {
            $instruction = $this->build($config);
            if ($instruction !== null) {
                $instructions[] = $instruction;
            }
        }

        return $instructions;
    }

    private function build(ActionTypeInstruction $config): ?OutcomeInstruction
    {
        $class = OutcomeInstructionFactory::typeMap()[$config->getInstructionType()] ?? null;
        if ($class === null) {
            return null;
        }

        /** @var OutcomeInstruction $instruction */
        $instruction = new $class();
        $instruction->setParameters($config->getParameters() ?? []);

        return $instruction;
    }
}
