<?php
namespace App\Service;

use App\Action\Effect\EffectResult;
use Player;
use App\Entity\EffectInstruction;
use App\Interface\ActorInterface;
use Item;

class EffectInstructionExecutorService
{
    /**
     * Execute a single instruction (one row from `effect_instructions`)
     *
     * @param Player $actor  The player who has made the action
     * @param Player $target  The player who will be affected
     * @param EffectInstruction $instruction The DB entity describing the operation
     */
    public function executeInstruction(Player $actor, Player $target, EffectInstruction $instruction): EffectResult
    {
        $operation = $instruction->getOperation();
        $params    = $instruction->getParameters() ?? [];

        switch ($operation) {
            case 'MODIFY_STAT': // DROP AMMO ?
                $res = $this->executeModifyStat($actor, $target, $params);
                break;

            case 'APPLY_STATUS':
                $res = $this->executeApplyStatus($actor, $target, $params);
                break;

            case 'DAMAGE_OBJECT':
                $res = $this->executeDamageObject($actor, $target, $params);
                break;

            // Add more operation cases as needed

            default:
                // Unrecognized operation
                // Could log or ignore
                break;
        }
        return $res;
    }

    private function executeModifyStat(Player $actor, Player $target, array $params): EffectResult
    {
        // e.g. { "actorDamages": "f", "targetDamages": "e" } (bonusDamage?)
        $actorTraitDamages = $params['actorDamages'];
        $targetTraitDamagesTaken = $params['targetDamages'];

        if(!empty($actorTraitDamages) && !empty($targetTraitDamagesTaken)){
            $actorDamages = (is_numeric($actorTraitDamages)) ? $actorTraitDamages : $target->caracs->{$actorTraitDamages};
            $targetDamages = (is_numeric($targetTraitDamagesTaken)) ? $targetTraitDamagesTaken : $target->caracs->{$targetTraitDamagesTaken};
            $totalDamages = $actorDamages - $targetDamages;
            if($totalDamages < 1){
                $totalDamages = 1;
            }

            //CRIT ? (devrait dépendre du scores des dés ?)
            //ESQUIVE ? (géré dans les conditions ?)
            //TANK ?
            $target->put_bonus(array('pv'=>-$totalDamages));
            $effectSuccessMessages[0] = 'Vous infligez '. $totalDamages .' dégâts à '. $target->data->name.'.';
            $effectSuccessMessages[1] = CARACS[$actorTraitDamages] .' - '. CARACS[$targetTraitDamagesTaken] .' = '. $actorDamages .' - '. $targetDamages .' = '. $totalDamages .' dégâts';

            // put assist
            $actor->put_assist($target, $totalDamages);

            //BREAK WEAPON ? -> not here, not a direct consequence
            
        } {
            //handle not working case
        }

        return new EffectResult(true, effectSuccessMessages:$effectSuccessMessages, effectFailureMessages: array(), totalDamages:$totalDamages);
        
    }

    private function executeApplyStatus(Player $actor, Player $target, array $params): EffectResult
    {
        // e.g. { "adrenaline": true, "duration": 86400 }
        $status = array_key_first($params);
        $duration = $params['duration'] ?? 0;
        $player = $params['player'] ?? 'BOTH';
        switch ($player) {
            case 'ACTOR':
                $this->applyEffect($params[$status], $status, $duration, $actor);
                $effectSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . $duration . ' TBD à ' . $actor->data->name;
                break;
            case 'TARGET':
                $this->applyEffect($params[$status], $status, $duration, $target);
                $effectSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . $duration . ' TBD à ' . $target->data->name;
                break;
            default:
            $this->applyEffect($params[$status], $status, $duration, $actor);
            $this->applyEffect($params[$status], $status, $duration, $target);
            $effectSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . $duration . ' TBD à ' . $actor->data->name;
            $effectSuccessMessages[1] = 'L\'effet '.$status.' est appliqué pour ' . $duration . ' TBD à ' . $target->data->name;
            break;
        }

        return new EffectResult(true, effectSuccessMessages:$effectSuccessMessages, effectFailureMessages: array());
    }

    private function executeDamageObject(Player $actor, Player $target, array $params): EffectResult
    {
        $result = new EffectResult(false);
        $effectSuccessMessages = array();
        $effectSuccessMessages[0] = null;
        $player = $params['player'] ?? 'BOTH';
        switch ($player) {
            case 'ACTOR':
                $objectBroken = $this->breakObject($actor, "ATTACK");
                if ($objectBroken != null) {
                    $effectSuccessMessages[0] = "Vous cassez votre ".$objectBroken->data->name;
                    $effectSuccessMessages[1] = $this->getRecipeElementBack($actor, $objectBroken);
                }
                break;
            case 'TARGET':
                $objectBroken = $this->breakObject($target, "DEFENSE");
                if ($objectBroken != null) {
                    $effectSuccessMessages[0] = $objectBroken->data->name .' de '. $target->data->name .' s\'est cassée.';
                    $effectSuccessMessages[1] = $this->getRecipeElementBack($target, $objectBroken);
                }
                break;
            default:
            $objectBroken = $this->breakObject($actor, "ATTACK");
            if ($objectBroken != null) {
                $effectSuccessMessages[0] = "Vous cassez votre ".$objectBroken->data->name;
                $effectSuccessMessages[1] = $this->getRecipeElementBack($actor, $objectBroken);
            }
            $defenseBroken = $this->breakObject($target, "DEFENSE");
            if ($defenseBroken) {
                array_push($effectSuccessMessages, $defenseBroken->data->name .' de '. $target->data->name .' s\'est cassée.');
                array_push($effectSuccessMessages, $this->getRecipeElementBack($target, $defenseBroken));
            }
            break;
        }
        if ($effectSuccessMessages[0] != null) {
            $result = new EffectResult(true, effectSuccessMessages:$effectSuccessMessages);
        } 
        return $result;
    }

    // should be a property of something like breakableInterface implemented by objects, and in fact the result of damaging objects
    private function breakObject(ActorInterface $player, $type): ?object {
        $result = null;
        switch ($type) {
            case 'ATTACK':
                $object = $player->emplacements->main1;
                if($object->data->name != 'Poing' && !$object->row->enchanted){
                    $breakChance = ITEM_BREAK;
                    $corruptions = ITEM_CORRUPTIONS;
                    $corruptBreakChance = ITEM_CORRUPT_BREAKCHANCES;
                
                    foreach($corruptions as $k=>$e){
                        if($player->haveEffect($k)){
                            if($player->emplacements->main1->is_crafted_with($e)){
                                $breakChance = $corruptBreakChance[$k];
                                break;
                            }
                        }
                    }
                
                    if(rand(1,100) <= $breakChance || AUTO_BREAK){
                        $player->equip($object);
                        $object->add_item($player, -1);
                        $result = $object;
                    }
                }
                break;
            case 'DEFENSE':
                $equipments = $this->getDamageableDefenseEquipments($player);
                if (count($equipments) > 0) {
                    $equipmentToDamage = array_rand($equipments);
                
                    $corruptedMaterial = $this->getCorruptedMaterial($player, $equipmentToDamage);
                    $breakChance = $this->getBreakChance($player, $equipmentToDamage, $corruptedMaterial);

                    if(rand(1,100) <= $breakChance || AUTO_BREAK){            
                        $player->equip($player->emplacements->{$equipmentToDamage});
                        $player->emplacements->{$equipmentToDamage}->add_item($player, -1);
                        $result = $equipmentToDamage;
                    }
                }
                break;
            default:
                break;
        }
        return $result;
    }

    private function getCorruptedMaterial($player, $equipmentToDamage): ?string
    {
        $corrupted = null;
        $corruptions = ITEM_CORRUPTIONS;
        foreach($corruptions as $k=>$e){
            if($player->haveEffect($k)){
                if($player->emplacements->{$equipmentToDamage}->is_crafted_with($e)){
                    $corrupted = $e;
                    break;
                }
            }
        }

        return $corrupted;
    }

    private function getBreakChance($player, $equipmentToDamage, $corruptedMaterial)
    {
        $breakChance = ITEM_BREAK;
        $corruptions = ITEM_CORRUPTIONS;
        $corruptBreakChance = ITEM_CORRUPT_BREAKCHANCES;
        foreach($corruptions as $k){
            if($player->haveEffect($k)){
                if($player->emplacements->{$equipmentToDamage}->is_crafted_with($corruptedMaterial)){
                    $breakChance = $corruptBreakChance[$k];
                    break;
                }
            }
        }

        return $breakChance;
    }

    private function getDamageableDefenseEquipments($player): array
    {
        $emplacements = array(
            'main2'=>"Le bouclier",
            'tronc'=>"L'armure",
            'tete'=>"Le casque"
        );
        
        foreach($emplacements as $k=>$e){
            if(!empty($player->emplacements->{$k}) && !$player->emplacements->{$k}->row->enchanted){
                continue;
            }
            // unset emplacements with no equipement
            unset($emplacements[$k]);
        }
        return $emplacements;
    }

    private function getRecipeElementBack(ActorInterface $actor, $object): string {
        $corrupted = array();
        $corruptions = ITEM_CORRUPTIONS;
    
        foreach($corruptions as $k=>$e){
            if($actor->haveEffect($k)){
                if($actor->emplacements->main1->is_crafted_with($e)){
                    array_push($corrupted, $e);
                    break;
                }
            }
        }

        $recup = array();
        $recipe = $object->get_recipe();

        foreach($corrupted as $e){
            unset($recipe[$e]);
        }

        foreach($recipe as $k=>$e){
            $craftedWithItem = Item::get_item_by_name($k);
            $rand = rand(0,$e);
            if($rand){
                $craftedWithItem->add_item($actor, $rand);
                $craftedWithItem->get_data();
                $recup[] = $craftedWithItem->data->name .' x'. $rand;
            }
        }
        $recupTxt = (count($recup)) ? implode(', ', $recup) : 'rien';
        return "Vous récupérez : ".$recupTxt;
    }

    private function applyEffect (bool $apply, string $effectName, int $duration, Player $player){
        if ($apply) {
            $player->addEffect($effectName, $duration);
        } else {
            $player->endEffect($effectName);
        } 
    }

}
