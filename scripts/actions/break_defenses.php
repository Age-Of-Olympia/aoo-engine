<?php


// random emplacement
$emplacements = array(
    'main2'=>"Le bouclier",
    'tronc'=>"L'armure",
    'tete'=>"Le casque"
);

foreach($emplacements as $k=>$e){


    if(!empty($target->emplacements->{$k}) && !$target->emplacements->{$k}->row->enchanted){


        continue;
    }

    // unset emplacements with no equipement

    unset($emplacements[$k]);
}


if(count($emplacements)){


    $emp = array_rand($emplacements);
    $empName = $emplacements[$emp];


    $breakChance = ITEM_BREAK;

    $corrupted = array();

    $corruptions = ITEM_CORRUPTIONS;

    $corruptBreackChance = ITEM_CORRUPT_BREAKCHANCES;


    foreach($corruptions as $k=>$e){


        if($target->haveEffect($k)){


            if($target->emplacements->{$emp}->is_crafted_with($e)){


                $breakChance = $corruptBreackChance[$k];

                $corrupted = $e;

                break;
            }
        }
    }


    if(rand(1,100) <= $breakChance || AUTO_BREAK){


        $recup = array();


        $recipe = $target->emplacements->{$emp}->get_recipe();

        foreach($corrupted as $e){

            unset($recipe[$e]);
        }


        foreach($recipe as $k=>$e){


            $craftedWithItem = Item::get_item_by_name($k);

            $rand = rand(0,$e);

            if($rand){


                $craftedWithItem->add_item($target, $rand);

                $craftedWithItem->get_data();

                $recup[] = $craftedWithItem->data->name .' x'. $rand;
            }
        }

        $target->equip($target->emplacements->{$emp});

        $target->emplacements->{$emp}->add_item($target, -1);

        $recupTxt = (count($recup)) ? implode(', ', $recup) : 'rien';

        $text = $empName .' de '. $target->data->name .' s\'est cassée. Récupéré: '. $recupTxt .'.';

        Log::put($target, $target, $text, $type="break");

        echo '<div><font color="cyan">'. $empName .' de '. $target->data->name .' se casse.</font></div>';
    }
}

