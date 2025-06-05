<?php
use Classes\Item;

echo '<textarea style="width: 100vw; height: 50vw;">';


$artJson = json()->decode('', 'crafts');


foreach($artJson as $occ=>$e){


    switch($occ){


                case 'common':
            $occName = 'Objets Communs';
                break;

                case 'elfe':
            $occName = 'Objets Elfiques';
                break;

                case 'geant':
            $occName = 'Objets Géants';
                break;

                case 'nain':
            $occName = 'Objets Nains';
                break;

                case 'olympien':
            $occName = 'Objets Olympiens';
                break;

                case 'hs':
            $occName = 'Objets Homme-Sauvages';
                break;

                case 'lutin':
            $occName = 'Objets Lutins';
                break;

                case 'ressource':
            $occName = 'Ressources';
                break;

                default:
            $occName = false;
                break;
    }

    if(!$occName) continue;

    echo '==== '. $occName .' ====
';


    echo '^ # ^ Objet ^ Infos ^
';


    $n = 1;

    foreach($e as $item){


        $item = new Item($item->id);

        $item->get_data();

        $infos = Item::get_item_carac($item->data);


        $formattedInfos = implode(' ', $infos);


        $formattedInfos = str_replace('<font color="blue">', '', $formattedInfos);
        $formattedInfos = str_replace('<font color="red">', '', $formattedInfos);
        $formattedInfos = str_replace('</font>', '', $formattedInfos);


        echo '| '. $n .' | {{https://aootest.net/img/items/'. $item->row->name .'_mini.webp}} [[https://age-of-olympia.net/item.php?itemId='. $item->id .'|'. $item->data->name .']] | '. $formattedInfos .' |
';


        $n++;
    }


}

/*
foreach(RACES as $race){


    $raceJson = json()->decode('races', $race);

    echo '==== '. $raceJson->name .' ====
^ # ^ Nom de l\'objet ^ Type ^ Coût ^ Description ^
';

    $n = 1;

    foreach($raceJson->actionsPack as $action){

        // ToDo when wiki generation will be reworked
        $actionJson = json()->decode('actions', $action);

        if(!isset($actionJson->type) || $actionJson->type != 'sort'){

            continue;
        }


        $type = (!empty($actionJson->subtype)) ? 'technique' : 'sort';


        echo '| '. $n .' | {{https://aootest.net/img/spells/'. $action .'.jpeg}} '. $actionJson->name .' | '. $type .' | '. $actionJson->costs->pm .'PM | '. $actionJson->text .' |
';

        $n++;
    }
}
*/


echo '</textarea>';
