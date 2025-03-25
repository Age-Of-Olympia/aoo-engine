<?php

namespace App\Action\Condition;

use App\Action\SpellAction;
use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use View;

class DodgeCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        $action = $condition->getAction();
        $type = "";
        if ($action instanceof SpellAction) {
            $type = "sort";
        }

        $errorMessages = array();
        $successMessages = array();

        if($target->haveEffect('parade')){
            $targetEffectName = 'parade';
            if(
                $target->emplacements->main1->data->subtype == 'melee'
                &&
                $actor->emplacements->main1->data->subtype == 'melee'
            ){
                $target->endEffect($targetEffectName);
                $errorMessages[sizeof($errorMessages)] = $target->data->name .' pare votre attaque grâce à sa technique ! ('.$targetEffectName.' <span class="ra '. EFFECTS_RA_FONT[$targetEffectName] .'"></span>)' ;
                $result = new ConditionResult(false, $successMessages, $errorMessages);
            }
        }
        
        if($target->haveEffect('leurre')){
            if(
                $type == 'sort'
            ){
                $targetEffectName = 'leurre';
                $target->endEffect($targetEffectName);
                $errorMessages[sizeof($errorMessages)] = $target->data->name .' pare votre attaque grâce à un sort ! ('.$targetEffectName.' <span class="ra '. EFFECTS_RA_FONT[$targetEffectName] .')"></span>' ;
                $result = new ConditionResult(false, $successMessages, $errorMessages);
            }
        }
        
        if($target->haveEffect('dedoublement')){
            $targetEffectName = 'dedoublement';
            $target->endEffect($targetEffectName);
            View::delete_double($target);
            $errorMessages[sizeof($errorMessages)] = 'Vous avez attaqué un double de '. $target->data->name .'! ('.$targetEffectName.' <span class="ra '. EFFECTS_RA_FONT[$targetEffectName] .')"></span>' ;
            $result = new ConditionResult(false, $successMessages, $errorMessages);
            $this->shouldRefresh = true;
        }
        
        if($target->haveEffect('cle_de_bras')){
            $targetEffectName = 'cle_de_bras';
            if(
                $actor->emplacements->main1->data->subtype == 'melee'
                &&
                $target->emplacements->main1->data->name == 'Poing'
            ){
                $target->endEffect($targetEffectName);
                $actor->putBonus(array('mvt'=>-$actor->getRemaining('mvt')));
                $errorMessages[sizeof($errorMessages)] = $target->data->name .' vous fait une clé de bras et vous immobilise ! ('.$targetEffectName.' <span class="ra '. EFFECTS_RA_FONT[$targetEffectName] .'"></span>)' ;
                $result = new ConditionResult(false, $successMessages, $errorMessages);
            }
        }
        
        if($target->haveEffect('pas_de_cote')){
            if(
                ( $type != 'sort' )
                &&
                $target->getRemaining('mvt') >= 1
            ){
                $targetEffectName = 'pas_de_cote';
                $target->endEffect($targetEffectName);
                $goCoords = $target->coords;
                //$coordsId = View::get_free_coords_id_arround($target->coords);
        
                $target->go($goCoords);

                $errorMessages[sizeof($errorMessages)] = $target->data->name .' esquive votre attaque avec un pas de côté ! ('.$targetEffectName.' <span class="ra '. EFFECTS_RA_FONT[$targetEffectName] .')"></span>' ;
                $result = new ConditionResult(false, $successMessages, $errorMessages);        
                $this->shouldRefresh = true;
            }
        }
        
        return $result;
    }
}