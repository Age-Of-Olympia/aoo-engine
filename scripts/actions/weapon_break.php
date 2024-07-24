<?php


if(rand(1,100) <= ITEM_BREAK || AUTO_BREAK){


    if($player->main1->data->name != 'Poing' && !$player->main1->row->enchanted){


        $recipe = $player->main1->get_recipe();


        $recup = array();

        foreach($recipe as $k=>$e){


            $craftedWithItem = Item::get_item_by_name($k);

            $rand = rand(0,$e);

            if($rand){

                $craftedWithItem->add_item($player, -$rand);

                $craftedWithItem->get_data();

                $recup[] = $craftedWithItem->data->name .'x'. $rand;
            }
        }

        $player->equip($player->main1);

        $player->main1->add_item($player, -1);

        $recupTxt = (count($recup)) ? implode(', ', $recup) : 'rien';

        $text = 'L\'arme de '. $player->data->name .' s\'est cassée. Récupéré: '. $recupTxt .'.';

        Log::put($player, $player, $text, $type="break");

        echo '<div><font color="red">Votre arme se casse.</font></div>';

        exit();
    }
}
