<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;
use Classes\View;

#[ORM\Entity]
class TeleportOutcomeInstruction extends OutcomeInstruction
{
    public function execute(ActorInterface $actor, ActorInterface $target): OutcomeResult {
        $params =$this->getParameters();
        // e.g. { "coords": "target" }

        $coords = $params['coords'];
        $outcomeSuccessMessages = array();
        switch ($coords) {
            case 'target':
                $goCoords = $target->coords;
                $coordsId = View::get_free_coords_id_arround($goCoords);
                $outcomeSuccessMessages[0] = $actor->data->name . ' saute sur ' .$target->data->name. ' !';
                $actor->go($coordsId);
                break;
            case 'projected':
                $goCoords = $actor->coords;
                $coordsId = View::get_free_coords_id_arround($goCoords);
                $target->go($coordsId);
                $outcomeSuccessMessages[0] = $target->data->name . ' est projeté !';
                break;
            default:
                $explodedCoord = explode(',', $coords);
                $coordX = $explodedCoord[0] == "x"?$actor->coords->x:$explodedCoord[0];
                $coordY = $explodedCoord[1] == "y"?$actor->coords->y:$explodedCoord[1];
                $coordZ = $explodedCoord[2] == "z"?$actor->coords->z:$explodedCoord[2];
                $plan = $explodedCoord[3] == "plan"?$actor->coords->plan:$explodedCoord[3];
                $tpCoords = (object) array(
                    'x'=>$coordX,
                    'y'=>$coordY,
                    'z'=>$coordZ,
                    'plan'=>$plan
                );
                $actor->go($tpCoords);
                break;
        }

        $this->getOutcome()->getAction()->setRefreshScreen(true);

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());
    }

}
