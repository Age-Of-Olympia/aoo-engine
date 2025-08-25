<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;
use Classes\Item;

#[ORM\Entity]
class DamageObjectOutcomeInstruction extends OutcomeInstruction
{
    public function execute(ActorInterface $actor, ActorInterface $target): OutcomeResult {
        $result = new OutcomeResult(false);
        $outcomeSuccessMessages = array();
        $outcomeSuccessMessages[0] = null;
        $player = $params['player'] ?? 'BOTH';
        switch ($player) {
            case 'ACTOR':
                $objectBroken = $this->breakObject($actor, "ATTACK");
                if ($objectBroken != null) {
                    $outcomeSuccessMessages[0] = "Vous cassez votre ".$objectBroken->data->name;
                    $outcomeSuccessMessages[1] = $this->getRecipeElementBack($actor, $objectBroken);
                }
                break;
            case 'TARGET':
                $objectBroken = $this->breakObject($target, "DEFENSE");
                if ($objectBroken != null) {
                    $outcomeSuccessMessages[0] = $objectBroken->data->name .' de '. $target->data->name .' s\'est cassée.';
                    $outcomeSuccessMessages[1] = $this->getRecipeElementBack($target, $objectBroken);
                }
                break;
            default:
            $objectBroken = $this->breakObject($actor, "ATTACK");
            if ($objectBroken != null) {
                $outcomeSuccessMessages[0] = "Vous cassez votre ".$objectBroken->data->name;
                $outcomeSuccessMessages[1] = $this->getRecipeElementBack($actor, $objectBroken);
            }
            $defenseBroken = $this->breakObject($target, "DEFENSE");
            if ($defenseBroken) {
                array_push($outcomeSuccessMessages, $defenseBroken->data->name .' de '. $target->data->name .' s\'est cassée.');
                array_push($outcomeSuccessMessages, $this->getRecipeElementBack($target, $defenseBroken));
            }
            break;
        }
        if ($outcomeSuccessMessages[0] != null) {
            $result = new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages);
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
                
                    //$corruptedMaterial = $this->getCorruptedMaterial($player, $equipmentToDamage);
                    $breakChance = $this->getBreakChance($player, $equipmentToDamage);

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

    private function getBreakChance(ActorInterface $player, $equipmentToDamage)
    {
        $breakChance = ITEM_BREAK;
        $corruptions = ITEM_CORRUPTIONS;
        $corruptBreakChance = ITEM_CORRUPT_BREAKCHANCES;
        foreach($corruptions as $k => $e){
            if($player->haveEffect($k)){
                if($player->emplacements->{$equipmentToDamage}->is_crafted_with($e)){
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
}
