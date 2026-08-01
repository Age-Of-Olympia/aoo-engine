<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;
use Classes\Item;
use Classes\Player;

#[ORM\Entity]
class DamageObjectOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('player', FieldType::ENUM, 'Casser l\'objet de', default: 'BOTH', options: [
                'ACTOR' => 'Acteur',
                'TARGET' => 'Cible',
                'BOTH' => 'Les deux',
            ]),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        $result = new OutcomeResult(false);
        $outcomeSuccessMessages = array();
        $outcomeSuccessMessages[0] = null;
        $params = $this->getParameters() ?? [];
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
                    $breakChance = $this->getBreakChance($player, 'main1');

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
                    $breakChance = $this->getBreakChance($player, $equipmentToDamage);

                    if(rand(1,100) <= $breakChance || AUTO_BREAK){
                        $equipment = $player->emplacements->{$equipmentToDamage};
                        $player->equip($equipment);
                        $equipment->add_item($player, -1);
                        $result = $equipment;
                    }
                }
                break;
            default:
                break;
        }
        return $result;
    }

    private function getBreakChance(ActorInterface $player, $equipmentToDamage)
    {
        $breakChance = ITEM_BREAK;
        $effectService = new \App\Service\EffectService();
        $corruptions = $effectService->getCorruptionMaterials();
        $corruptBreakChance = $effectService->getCorruptionBreakChances();
        foreach($corruptions as $k => $e){
            if($player->have_effect($k)){
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
        $corruptions = (new \App\Service\EffectService())->getCorruptionMaterials();
    
        foreach($corruptions as $k=>$e){
            if($actor->have_effect($k)){
                if($actor->emplacements->main1->is_crafted_with($e)){
                    array_push($corrupted, $e);
                    break;
                }
            }
        }

        $recup = array();
        $recipe = $object->get_recipe();

        // $corrupted contient des LISTES de matériaux (une par corruption
        // active) : chaque matériau corrompu est perdu, pas restitué.
        foreach($corrupted as $materials){
            foreach((array) $materials as $material){
                unset($recipe[$material]);
            }
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
