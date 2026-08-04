<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\ConstructionSiteService;
use App\Service\LockService;

/**
 * The gesture works ON a construction site: the target is a site under
 * construction, and the actor is one of its people —
 * LockService::mayActOn, the household rule without the latch: the
 * owner, a member of the site's faction, and a site with neither
 * belongs to everyone.
 *
 * Declared `display_context` so the hammer only shows on a site the
 * actor may advance.
 */
class RequiresConstructionSiteCondition extends BaseCondition implements HasParameterSchemaInterface
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
            return new ConditionResult(false, array(), ["Il n'y a pas de chantier ici."]);
        }

        // The workbench simulation has no board and no sites.
        if ($actor->isSimulated() || $target->isSimulated()) {
            return new ConditionResult(true, array(), array());
        }

        if (!(new ConstructionSiteService())->isUnderConstruction((int) $target->getId())) {
            return new ConditionResult(false, array(), ["Ce n'est pas un chantier."]);
        }

        if (!(new LockService())->mayActOn((int) $target->getId(), (int) $actor->getId())) {
            return new ConditionResult(false, array(), ["Ce chantier n'est pas le vôtre."]);
        }

        return new ConditionResult(true, array(), array());
    }
}
