<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Service\MapService;
use App\Service\ResourceService;
use Doctrine\ORM\Mapping as ORM;
use Item;
use Player;
use Str;
use View;

#[ORM\Entity]
class TileTypeOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target): OutcomeResult {

        $mapService = new MapService();

        $outcomeSuccessMessages = array();

        $row = $mapService->getTileTypeAtCoord("routes", $actor->data->coords_id);

        if($row->n){
            $bonus = array("mvt"=>1);
            $actor->putBonus($bonus);
            $outcomeSuccessMessages[0] = 'Vous êtes sur une route ! (+1)';
        } 

        return new OutcomeResult(true,$outcomeSuccessMessages, array());
    }

}