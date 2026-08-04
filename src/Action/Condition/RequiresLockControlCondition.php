<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Factory\EntityManagerFactory;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\ContainerService;
use App\Service\LockService;

/**
 * The hand on a lock: the target must be lockable, the actor must
 * CONTROL it (its owner, or a member whose rank carries the useChest
 * flag — {@see ContainerService::mayTurnLock()}), and the lock must
 * stand in the state the gesture leaves behind's opposite — `fermer`
 * only shuts what is open, `ouvrir` only opens what is shut.
 *
 * Parameters: {"open": 0|1} — the state the gesture PRODUCES.
 *
 * Declare it `display_context` so each button appears only on the
 * thing it can actually turn, to the hand that may turn it.
 */
class RequiresLockControlCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(
        ActorInterface $actor,
        ?ActorInterface $target,
        ActionCondition $condition,
        ConditionObject $conditionObject
    ): ConditionResult {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);

        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        if ($target === null) {
            return new ConditionResult(false, array(), array('Il n\'y a pas de serrure ici.'));
        }

        $targetId = (int) $target->getId();

        if (!(new LockService())->isLockable($targetId)) {
            return new ConditionResult(false, array(), array('Cela ne se ferme pas.'));
        }

        if (!(new ContainerService())->mayTurnLock($targetId, (int) $actor->getId())) {
            return new ConditionResult(false, array(), array('Cette serrure ne vous connaît pas.'));
        }

        $producesOpen = !empty($condition->getParameters()['open']);
        $isOpen = (bool) EntityManagerFactory::getEntityManager()->getConnection()
            ->fetchOne('SELECT is_open FROM players WHERE id = ?', [$targetId]);

        if ($isOpen === $producesOpen) {
            return new ConditionResult(
                false,
                array(),
                array($producesOpen ? 'C\'est déjà ouvert.' : 'C\'est déjà fermé.')
            );
        }

        return new ConditionResult(true, array(), array());
    }
}
