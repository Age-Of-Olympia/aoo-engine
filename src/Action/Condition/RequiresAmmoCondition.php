<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use Db;
use Item;
use View;

class RequiresAmmoCondition extends BaseCondition
{
    public bool $toRemove;

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());
        $details = array();
        $costIsAffordable = false;

        $params = $condition->getParameters();
        $itemId = $params["itemId"] ?? null; // { "itemId" : 86 }

        if ($itemId == null) {
            $munition = $actor->getMunition($actor->emplacements->main1, true);
            if ($actor->emplacements->main1->data->subtype == 'tir' && $munition == null) { 
                array_push($details, "Pas assez de munitions.");
            } else {
                $costIsAffordable = true;
                $this->toRemove = true;
            }
            
        } else {
            $item = new Item($itemId);
            $item->get_data();
            $itemsEquiped = $item->get_item_list($actor, false, true);
            if (sizeof($itemsEquiped) == 0) {
                array_push($details, "Pas de ".$item->data->name . ' équipé.');
            } else {
                $costIsAffordable = true;
                $this->toRemove = true;
            }

        }

        if (!$costIsAffordable) {
            $result = new ConditionResult(false, array(), $details);
        }

        return $result;
    }

    public function toRemove(): bool {
        return $this->toRemove;
    }

    public function applyCosts(ActorInterface $actor, ?ActorInterface $target, ActionCondition $conditionToPay): array
    {
        $result = array();

        $params = $conditionToPay->getParameters();
        $itemId = $params["itemId"] ?? null;
        $itemQuantity = $params["itemQuantity"] ?? 1;


        if ($itemId == null) {
            $munition = $actor->getMunition($actor->emplacements->main1, true);
            if($actor->emplacements->main1->data->subtype == 'tir') {
                $munition->add_item($actor, -1);
                $text = "Vous avez dépensé une munition.";
                array_push($result, $text);
            }
    
            if($actor->emplacements->main1->data->subtype == 'jet'){
                $distance = View::get_distance($actor->getCoords(), $target->getCoords());
                if($distance > 2){
                    $dropCoords = clone $target->coords;
                    $coordsId = View::get_free_coords_id_arround($dropCoords, $p=1);
                    $values = array(
                    'item_id'=>$actor->emplacements->main1->id,
                    'coords_id'=>$coordsId,
                    'n'=>1
                    );
        
                    $db = new Db();
                    $db->insert('map_items', $values);
        
                    $actor->emplacements->main1->add_item($actor, -1);
            
                    $text = 'Vous perdez '. $actor->emplacements->main1->data->name .'.';
                    View::refresh_players_svg($dropCoords);
                    $conditionToPay->getAction()->setRefreshScreen(true);
                    array_push($result, $text);
                } else {
                    $text = 'Vous gardez '. $actor->emplacements->main1->data->name .'.';
                    array_push($result, $text);
                }
    
            }
        } else {
            $item = new Item($itemId);
            $item->get_data();
            $item->add_item($actor, -$itemQuantity);
            $text = 'Vous dépensez '. $itemQuantity. ' ' . $item->data->name .'.';
            array_push($result, $text);
        }

        return $result;
    }

}
