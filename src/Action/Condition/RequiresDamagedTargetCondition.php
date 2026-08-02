<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Interface\HasParameterSchemaInterface;
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
 *
 * Elle refuse aussi le BRISÉ : à zéro, une chose ne se répare plus. Le seuil
 * vient de ItemInstanceService::BROKEN_AT, source unique de la règle.
 */
class RequiresDamagedTargetCondition extends BaseCondition implements HasParameterSchemaInterface
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

        /* Un objet se lit ICI comme tout le reste, et c'est neuf.
         *
         * Il fallait une seconde lecture — la durabilité, dans `item_instances`
         * — parce que l'entité d'un objet posé n'était qu'un ÉTUI temporaire
         * portant la race « objet », inconnue du catalogue : ses PV valaient
         * zéro, et l'objet intact passait. Depuis « une seule vie », un
         * exemplaire EST une entité où qu'il soit, son maximum vient de son
         * type (`items.durability_max`) et son entame du même `players_bonus`
         * que la blessure d'un personnage. La double lecture n'a plus d'objet,
         * et ses colonnes n'existent plus. */
        $target->get_caracs();

        $max = (int) ($target->caracs->pv ?? 0);
        $left = $target->getRemaining('pv');

        if ($max > 0 && $left >= $max) {
            return new ConditionResult(false, array(), array('La cible est intacte.'));
        }

        if (\App\Service\ItemInstanceService::isBroken($left)) {
            return new ConditionResult(false, array(), array('La cible est brisée : on ne la répare plus.'));
        }

        return new ConditionResult(true, array(), array());
    }
}
