<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Item;
use Classes\Player;
use Classes\Str;

#[ORM\Entity]
class DropWeaponOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('targetLocation', FieldType::EMPLACEMENT, 'Emplacement de la cible'),
            new ParameterField('dropChance', FieldType::INT, 'Chance de chute (%)', default: 10),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        $outcomeSuccessMessages = array();
        $outcomeFailureMessages = array();
        $params =$this->getParameters();
        $result = false;
        $targetLocation = $params["targetLocation"] ?? "";
        $dropChance = $params["dropChance"] ?? 10;

        $item = $target->emplacements->{$targetLocation};
        
        if(rand(1,100) <= $dropChance){
            /* Phase 3 : une arme INSTANCIÉE tombe en gardant son identité —
             * elle rejoint la BOURSE de la case de sa victime (ramassée en
             * marchant, comme tout loot). Une instance redevenue vierge au
             * déséquipement reprend l'ancien chemin map_items ; les piles
             * héritées sont inchangées. */
            if (!$target->isSimulated() && !empty($item->row->instance_id)) {
                $instanceId = (int) $item->row->instance_id;
                $service = new \App\Service\ItemInstanceService();
                $service->unequipInstance($instanceId);
                $target->getCoords();
                try {
                    $coordsId = (int) \Classes\View::get_coords_id(clone $target->coords);
                    $service->dropAt($instanceId, $coordsId);
                } catch (\InvalidArgumentException) {
                    // Redevenue pile au déséquipement (instance vierge) : ancien chemin.
                    $target->drop($item, 1);
                }
                $target->refresh_invent();
                $target->refresh_caracs();
            } else {
                $target->drop($item, 1);
            }
            $resultText = "L'arme de votre adversaire tombe au sol.";
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $resultText;
            $result = true;
        } else {
            $resultText = "Votre adversaire était plus vigilent que prévu, son arme reste entre ses mains !";
            $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = $resultText;
        }
        
        return new OutcomeResult($result, $outcomeSuccessMessages, $outcomeFailureMessages);
    }

}
