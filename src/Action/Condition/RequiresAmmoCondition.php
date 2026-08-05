<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Classes\Db;
use Classes\Item;
use Classes\View;

class RequiresAmmoCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('itemId', FieldType::ITEM, 'Munition requise', help: "Laisser vide pour utiliser la munition de l'arme équipée."),
            new ParameterField('itemQuantity', FieldType::INT, 'Quantité consommée', default: 1),
        );
    }

    public bool $toRemove = false;

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        /* Remise à zéro : l'instance est partagée entre occurrences d'une
         * même action, un échec ne doit pas hériter du toRemove d'une
         * occurrence précédente (même garde que RequiresItemCondition). */
        $this->toRemove = false;

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
                $distance = $target !== null ? View::get_distance_to_entity($actor->getCoords(), $target->getId(), $target->getCoords()) : 0;
                if($target !== null && $distance > 2){
                    // A simulation must not drop the weapon onto the real map; still report the loss.
                    if (!$actor->isSimulated()) {
                        $dropCoords = clone $target->coords;
                        $coordsId = View::get_free_coords_id_arround($dropCoords, $p=1);
                        /* Upsert: the unique key merges the thrown weapon
                         * into the tile's existing stack line, if any. */
                        $db = new Db();
                        $db->exe(
                            'INSERT INTO map_items (item_id, coords_id, n) VALUES (?, ?, 1)
                             ON DUPLICATE KEY UPDATE n = n + 1',
                            [$actor->emplacements->main1->id, $coordsId]
                        );

                        $actor->emplacements->main1->add_item($actor, -1);

                        View::refresh_players_svg($dropCoords);
                        $conditionToPay->getAction()->setRefreshScreen(true);
                    }

                    $text = 'Vous perdez '. $actor->emplacements->main1->data->name .'.';
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
