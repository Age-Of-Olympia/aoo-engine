<?php

namespace App\Action\EffectInstruction;

use App\Entity\EffectInstruction;
use Doctrine\ORM\Mapping as ORM;
use Player;
use Str;
use View;

#[ORM\Entity]
class TeleportEffectInstruction extends EffectInstruction
{
    public function execute(Player $actor, Player $target): EffectResult {
        $params =$this->getParameters();
        // e.g. { "coords": "target" }

        $coords = $params['coords'];
        $effectSuccessMessages = array();
        switch ($coords) {
            case 'target':
                $goCoords = $target->coords;
                $coordsId = View::get_free_coords_id_arround($goCoords);
                $effectSuccessMessages[0] = $actor->data->name . 'saute sur ' .$target->data->name. ' !';
                $actor->go($coordsId);
                break;
            default:
                # code... to whatever coord ToDo
                break;
        }

        return new EffectResult(true, effectSuccessMessages:$effectSuccessMessages, effectFailureMessages: array());
    }

}
