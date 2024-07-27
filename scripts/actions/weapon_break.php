<?php


if($player->main1->data->name != 'Poing' && !$player->main1->row->enchanted){


    $breakChance = ITEM_BREAK;

    $corrupted = array();

    $corruptions = array(
        'corruption_du_metal'=>array('bronze','nickel'),
        'corruption_du_bronze'=>array('bronze'),
        'corruption_du_bois'=>array('bois','bois_petrifie'),
        'corruption_des_plantes'=>array('adonis','cafe','astral','houblon','lichen_sacre','lotus_noir','menthe','pavot'),
        'corruption_du_cuir'=>array('cuir')
    );

    $corruptBreackChance = array(
        'corruption_du_metal'=>15,
        'corruption_du_bronze'=>10,
        'corruption_du_bois'=>20,
        'corruption_des_plantes'=>15,
        'corruption_du_cuir'=>5
    );


    foreach($corruptions as $k=>$e){


        if($player->have_effect($k)){


            if($player->main1->is_crafted_with($e)){


                $breakChance = $corruptBreackChance[$k];

                $corrupted = $e;

                break;
            }
        }
    }


    if(rand(1,100) <= $breakChance || AUTO_BREAK){


        $recup = array();


        $recipe = $player->main1->get_recipe();

        foreach($corrupted as $e){

            unset($recipe[$e]);
        }


        foreach($recipe as $k=>$e){


            $craftedWithItem = Item::get_item_by_name($k);

            $rand = rand(0,$e);

            if($rand){


                $craftedWithItem->add_item($player, -$rand);

                $craftedWithItem->get_data();

                $recup[] = $craftedWithItem->data->name .' x'. $rand;
            }
        }

        $player->equip($player->main1);

        $player->main1->add_item($player, -1);

        $recupTxt = (count($recup)) ? implode(', ', $recup) : 'rien';

        $text = 'L\'arme de '. $player->data->name .' s\'est cassée. Récupéré: '. $recupTxt .'.';

        Log::put($player, $player, $text, $type="break");

        echo '<div><font color="red">Votre arme se casse.</font></div>';
    }
}
