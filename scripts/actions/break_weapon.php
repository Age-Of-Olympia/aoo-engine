<?php


if($player->emplacements->main1->data->name != 'Poing' && !$player->emplacements->main1->row->enchanted){


    $breakChance = ITEM_BREAK;

    $corrupted = array();

    $corruptions = ITEM_CORRUPTIONS;

    $corruptBreackChance = ITEM_CORRUPT_BREAKCHANCES;


    foreach($corruptions as $k=>$e){


        if($player->haveEffect($k)){


            if($player->emplacements->main1->is_crafted_with($e)){


                $breakChance = $corruptBreackChance[$k];

                $corrupted = $e;

                break;
            }
        }
    }


    if(rand(1,100) <= $breakChance || AUTO_BREAK){


        $recup = array();


        $recipe = $player->emplacements->main1->get_recipe();

        foreach($corrupted as $e){

            unset($recipe[$e]);
        }


        foreach($recipe as $k=>$e){


            $craftedWithItem = Item::get_item_by_name($k);

            $rand = rand(0,$e);

            if($rand){


                $craftedWithItem->add_item($player, $rand);

                $craftedWithItem->get_data();

                $recup[] = $craftedWithItem->data->name .' x'. $rand;
            }
        }

        $player->equip($player->emplacements->main1);

        $player->emplacements->main1->add_item($player, -1);

        $recupTxt = (count($recup)) ? implode(', ', $recup) : 'rien';

        $text = 'L\'arme de '. $player->data->name .' s\'est cassée. Récupéré: '. $recupTxt .'.';

        Log::put($player, $player, $text, $type="break");

        echo '<div><font color="red">Votre arme se casse.</font></div>';
    }
}
