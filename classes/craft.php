<?php
namespace Classes;

class Craft{

    public $item;
    public $json;
    public $recetteJson;
    public $itemJson;
    public $itemRecipe;
    public $cost;
    public $resources = [];
    function __construct($itemParam){
        // item
        $item = strtolower($itemParam);
        $this->item = $item;

        // json
        $this->json = new Json();

        // recette json
        $this->recetteJson = $this->json->decode('', 'crafts');

        // item json
        $this->itemJson = $this->json->decode('items', $item);

        // recette item
        $this->itemRecipe= $this->get_item_recipe();

        // recette cost
        $this->cost = $this->get_cost();
    }


    public function get_item_recipe(){

        // default race
        $race = 'common';

        // specific race
        if(!empty($this->itemJson->occurence) && $this->itemJson->occurence != 'co'){
            $race = $this->itemJson->race;
        }

        // default return empty array
        $return = array();

        // search for recette in race recipes
        foreach($this->recetteJson->$race as $item){

            if($item->name != $this->item){
                continue;
            }

            // populate return
            foreach($item->recette as $e){

                $itemName = $e->name;

                $return[$itemName] = $e->n;
            }

            break;
        }


        return $return;
    }


    public function get_cost(){

        // default cost
        $cost = 0;

        // foreach item in recipe
        foreach($this->itemRecipe as $name=>$quantity){

            $itemJson = $this->json->decode('items', $name);

            // add cost x n
            $cost += ($itemJson->price * $quantity);

            // store json for further use
            $this->resources[$name] = $itemJson;
        }

        // crafted by n
        if(!empty($this->itemJson->craftedByN)){
            $cost = floor($cost / $this->itemJson->craftedByN);
        }

        return $cost;
    }
}
