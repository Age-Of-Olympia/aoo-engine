<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Service\ResourceService;
use Doctrine\ORM\Mapping as ORM;
use Item;
use Player;
use Str;
use View;

#[ORM\Entity]
class ResourceOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target): OutcomeResult {
        $ressources = array();
        $biomes = array();

        $coords = $actor->getCoords();
        $planJson = json()->decode('plans', $coords->plan);
        if(!empty($planJson->biomes)){
            foreach($planJson->biomes as $e){
                $biomes[$e->wall] = $e->ressource;
            }
        }

        $res = ResourceService::findResourcesAround($actor);
        while($row = $res->fetch_object()){

            if(array_key_exists($biomes[$row->name], $ressources))
                $ressources[$biomes[$row->name]] += $row->max;
            else
                $ressources[$biomes[$row->name]] = $row->max;

        }

        $outcomeSuccessMessages = array();

        foreach($ressources as $k=>$v){
            $max = $v;
            $item = Item::get_item_by_name($k);
            $item->get_data();
            $rand = rand(1, $max);

            $item->add_item($actor, $rand);

            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous trouvez '. ucfirst($item->data->name) .' x'. $rand .' ! (1d'. $max .' = '. $rand .')';
        }

        //Une fois la récolte terminée, on regarde si les ressources s'épuisent
        $res = ResourceService::getResourcesAround($actor); //TODO refactor this to avoid double query
        $resourcesIdArray = [];
        $countTryExhaust=0;
        while($row = $res->fetch_object()){
            $countTryExhaust++;
             ResourceService::createExhaustArray($planJson, $resourcesIdArray, $row, $rand);
             if($countTryExhaust >= $rand) { // On ne veut pas épuiser plus de ressources que le nombre de ressources récoltées
                 break;
             }
        }
    
        if(!empty($resourcesIdArray)){
            if(count($resourcesIdArray) > 1){
                $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Plusieurs filons sont épuisés...';
            }
            else{
                $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Un des filons n\'a plus rien à récolter...';
            }
            ResourceService::exhaustResources($resourcesIdArray);
            $this->getOutcome()->getAction()->setRefreshScreen(true);
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());

    }

}
