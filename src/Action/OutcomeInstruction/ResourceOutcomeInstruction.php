<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\ResourceService;
use Doctrine\ORM\Mapping as ORM;
use Classes\Dice;
use Classes\Item;
use Classes\Player;

#[ORM\Entity]
class ResourceOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    /**
     * Dé injectable. Nul en jeu — un dé de la bonne taille est fabriqué à
     * chaque tirage, et il passe par DiceLog comme ceux du combat, donc le
     * jet de récolte devient visible dans le détail d'action au lieu d'être
     * reconstruit à la main dans le message.
     *
     * STATIQUE, comme pour ResourceService et PlantsService : l'instruction
     * est hydratée par Doctrine depuis le graphe d'action, un test n'a donc
     * aucune prise sur l'instance pour lui passer un dé.
     */
    private static ?Dice $dice = null;

    public static function setDiceForTests(?Dice $dice): void
    {
        self::$dice = $dice;
    }

    /** Un dé à $sides faces, une fois. */
    private function roll(int $sides): int
    {
        $dice = self::$dice ?? new Dice(max(1, $sides));

        return (int) $dice->roll(1)[0];
    }

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        $ressources = array();
        $yields = (new \App\Service\Map\HarvestCatalogService())->yieldsFor((string) $actor->coords->plan);
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

            /* Skip if this wall type has no resource defined in biomes */
            if(!isset($biomes[$row->name])){
                continue;
            }

            $resourceName = $biomes[$row->name];
            if(array_key_exists($resourceName, $ressources))
                $ressources[$resourceName] += $row->max;
            else
                $ressources[$resourceName] = $row->max;

        }

        $outcomeSuccessMessages = array();
        // TOTAL des unités récoltées : c'est lui qui borne l'épuisement. Le
        // tirage était relu après cette boucle, donc seul le dernier rendement
        // comptait — récolter cinq bois et une pierre n'autorisait qu'un filon.
        $harvested = 0;

        foreach($ressources as $k=>$v){
            $max = $v;
            $item = Item::get_item_by_name($k);
            $item->get_data();
            $rand = $this->roll($max);
            $harvested += $rand;

            $item->add_item($actor, $rand);

            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous trouvez '. ucfirst($item->data->name) .' x'. $rand .' ! (1d'. $max .' = '. $rand .')';
        }

        //Une fois la récolte terminée, on regarde si les ressources s'épuisent
        $res = ResourceService::getResourcesAround($actor); //TODO refactor this to avoid double query
        $rows = [];
        while($row = $res->fetch_object()){
            $rows[] = $row;
        }

        $resourcesIdArray = ResourceService::pickExhausted($yields, $rows, $harvested);
    
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
