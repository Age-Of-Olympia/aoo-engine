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

        /* Un objet au sol EST une entité, comme un personnage ou un édifice, et
         * se répare de la même façon. Mais son entité est un ÉTUI TEMPORAIRE :
         * `UniqueObjectService::takeInstance` la supprime dès qu'on ramasse.
         * Une vie portée par elle serait oubliée au ramassage — les coups pris
         * au sol comme la réparation qu'on vient d'y faire.
         *
         * La durabilité est le registre qui SURVIT à l'étui ; c'est pourquoi le
         * code existant synchronise « entité détruite → durabilité 0 », et
         * jamais l'inverse. On lit donc la vie là où elle dure.
         *
         * Accessoirement l'étui n'a même pas de PV à lire : il porte la race
         * « objet », que le catalogue ne connaît pas. Lire ses PV laissait
         * passer n'importe quel objet, intact compris — et réparer un objet
         * intact rend l'XP sans rien soigner, le défaut même que cette
         * condition existe pour fermer. */
        if ((string) ($target->data->player_type ?? '') === 'unique') {
            return $this->checkObjectWear($target);
        }

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

    /**
     * Un objet s'use et se répare entre 0 exclu et son maximum.
     *
     * Deux bornes, deux refus différents :
     *  - à son maximum, il n'y a rien à réparer ;
     *  - BRISÉ (durabilité tombée à 0), il ne se répare plus — décision de jeu,
     *    c'est ce qui donne son prix à l'artisanat.
     */
    private function checkObjectWear(ActorInterface $target): ConditionResult
    {
        $conn = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();

        $instanceId = \App\Service\UniqueObjectService::instanceIdOf($conn, (int) $target->getId());

        if ($instanceId === null) {
            return new ConditionResult(false, array(), array("Il n'y a rien à réparer ici."));
        }

        $wear = $conn->fetchAssociative(
            'SELECT durability, durability_max FROM item_instances WHERE id = ?',
            [$instanceId]
        );

        if ($wear === false) {
            return new ConditionResult(false, array(), array("Il n'y a rien à réparer ici."));
        }

        if (\App\Service\ItemInstanceService::isBroken((int) $wear['durability'])) {
            return new ConditionResult(false, array(), array('Brisé : cela ne se répare plus.'));
        }

        if ((int) $wear['durability'] >= (int) $wear['durability_max']) {
            return new ConditionResult(false, array(), array('La cible est intacte.'));
        }

        return new ConditionResult(true, array(), array());
    }
}
