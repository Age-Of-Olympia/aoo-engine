<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use Db;
use Player;
use View;

//add enum to display correctly the weapon type names (melee, distance, multipurpose, etc)

class RequiresAmmoCondition extends BaseCondition
{
    public bool $toRemove;

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true);
        $details = array();
        $costIsAffordable = false;

        $munition = $actor->getMunition($actor->emplacements->main1, true);
        if ($actor->emplacements->main1->data->subtype == 'tir' && $munition == null) { 
            array_push($details, "Pas assez de munitions.");
        } else {
            $costIsAffordable = true;
            $this->toRemove = true;
        }
        
        if (!$costIsAffordable) {
            $result = new ConditionResult(false, null, $details);
        }

        return $result;
    }

    public function toRemove(): bool {
        return $this->toRemove;
    }

    public function applyCosts(ActorInterface $actor, ?ActorInterface $target, ActionCondition $conditionToPay): array
    {
        $result = array();
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
    
                // Player::refresh_views_at_z($dropCoords->z);
    
                // include('scripts/actions/on_hide_reload_view.php');
                $text = 'Vous perdez '. $actor->emplacements->main1->data->name .'.';
                array_push($result, $text);
            } else {
                $text = 'Vous gardez '. $actor->emplacements->main1->data->name .'.';
                array_push($result, $text);
            }

        }

        return $result;
    }

}
