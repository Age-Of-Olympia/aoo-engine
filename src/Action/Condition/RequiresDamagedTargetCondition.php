<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;

/**
 * La cible doit avoir quelque chose à réparer ou à soigner.
 *
 * Sans elle, une action de soin réussit sur une cible intacte : le soin est
 * plafonné au déficit (Player::putBonus), donc il vaut zéro, mais l'outcome
 * rend quand même un succès et l'XP tombe. Sur `reparer` — 3 XP par point
 * d'action, le meilleur rapport du jeu — cela ouvre une source d'XP illimitée
 * contre n'importe quel bâtiment intact.
 *
 * La condition compare le restant au maximum, ce qui couvre aussi bien la
 * blessure d'un personnage que les PV entamés d'une structure : les deux
 * vivent dans players_bonus et passent par getRemaining().
 */
class RequiresDamagedTargetCondition extends BaseCondition implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);

        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        if ($target === null) {
            return new ConditionResult(false, array(), array("Il n'y a rien à réparer ici."));
        }

        $target->get_caracs();

        $max = (int) ($target->caracs->pv ?? 0);
        $left = $target->getRemaining('pv');

        if ($max > 0 && $left >= $max) {
            return new ConditionResult(false, array(), array('La cible est intacte.'));
        }

        return new ConditionResult(true, array(), array());
    }
}
