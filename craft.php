<?php

require_once('config.php');


// player
$player = new Player($_SESSION['playerId']);
$player->get_data();

$item = new Item($_GET['item'], false);

$item->get_data();

$ui = new Ui('Artisanat');

echo '
<div id="craft-wrapper">

<div><a href="inventory.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>

<h2>Artisanat - '.$item->data->name.'</h2>
<br/>

 <a href="item.php?item='.$item->data->id.'" class="source-link">
        <img src="'. $item->data->img .'" />
    </a>
   ';


// recipe
if(!isset($item->data->occurence) || $item->data->occurence == 'co' || $item->data->race == $player->data->race){


    // craft
    $craft = new Craft($item->data->name);

    // recette exists
    if(count($craft->itemRecipe)){
        echo '
        <div id="item-recipe">
            ';

        foreach($craft->itemRecipe as $ingredientItem=>$n){

            $ingredient = new Item($craft-> $ingredientItem->id);

            $ingredient->get_data();

            echo '
                <img src="'. $ingredient->data->mini .'" /> x'. $n .'
                ';
        }

        echo '<br />';

        if($craft->cost)
            echo 'Coût des matériaux ~'. $craft->cost .'Po<br />';

        echo 'Revendu ~'. floor($item->data->price*2/3) .'Po<br />';
        echo 'Acheté ~'. $item->data->price .'Po';

        echo '
        </div>
        ';
    }
}


echo '<br/>
<h2>Recettes</h2>
<sup>Créer un objet</sup><br />
';


// race craft forbidden
if(!empty($raceJson->forbidCraft)){
    echo ERROR_RACE_FORBIDDEN;

    exit();
}


// list all item in Inventory and number

$itemList = Item::get_item_list($player->id);

foreach($itemList as $playerItem){
    $playerItemN[$playerItem->name] = $playerItem->n;
}


// list of craft
$craftList = array();



// recette json
$json = new Json();
$recipesJson = $json->decode('artisanat', 'recette');

echo '
<table border="1" class="dialog-table">
    <tr>
        <th></th>
        <th>Objet</th>
        <th>Ingrédients</th>
        <th></th>
    </tr>
    ';

// common recipes
$recipeList = $recipesJson->common;

// add race recipes
$playerRace = $player->data->race;

// merge with racial recipes
if(!empty($recipesJson->$playerRace)){

    $recipeList = array_merge($recipeList, $recipesJson->$playerRace);
}


// at least one art
$atLeastOne = false;

foreach($recipeList as $recipe){

    // artShow
    $artShow = false;

    // artComplete
    $artComplete = true;



    $recipeIngredients = $recipe->recette;

    // search for item in recipe
    foreach($recipeIngredients as $ee){
        if($ee->name != strtolower($item->data->name)){
            continue;
        }

        $artShow = true;
        $atLeastOne = true;
    }


    // art have NOT item in recipe
    if(!$artShow){
        continue;
    }

    $artItem = new Item($recipe->id);
    $artItem->get_data();

    // print
    echo '
        <tr>
            <td width="50">
                <a href="item.php?item='. $recipe->name .'"><img src="'. $artItem->data->mini .'" /></a>
            </td>
            <td>
                '. $artItem->data->name .'<br />
                
                ';



    echo '
            </td>
            <td>
                ';

    // recipe
    foreach($recipeIngredients as $ingredient){


        $ingredientItem = new Item($ingredient->id);
        $ingredientItem->get_data();



        // color
        if(!isset($playerItemN[$ingredient->name])){
            $color = 'red';
            $artComplete = false;
        }
        elseif($playerItemN[$ingredient->name] >= $ingredient->n){
            $color = 'green';
        }
        else{
            $color = 'orange';
            $artComplete = false;
        }

        echo '
                    <a href="item.php?item='. $ingredient->name .'"><img width="25" src="'. $ingredientItem->data->mini .'" /></a>
                    <sup><font color="'. $color .'">x'. $ingredient->n .'</font></sup>
                    ';

        // crafting
        if(!empty($_POST['create'])){

            // this item
            if($_POST['create'] == $artName){

                // create art if complete
                if($artComplete){


                    echo 1;


                    // artJson
                    $artJson = $json->decode('item', $artName);


                    // crafted by n
                    $craftedByN = (!empty($artJson->craftedByN)) ? $artJson->craftedByN : 1;


                    // script when crafted
                    if(file_exists('item/craft_script/'. $artName .'.php')){
                        include('item/craft_script/'. $artName .'.php');
                    }


                    // craft
                    foreach($recipeIngredients as $ee){


                        // needed item
                        $neededJson = $json->decode('item', $ee->name);


                        // add when crafted item
                        if(!empty($neededJson->whenCrafted)){

                            foreach($neededJson->whenCrafted as $eee){

                                Inventory::add_item($player, $eee->name, $eee->n);
                            }
                        }


                        // remove item recipe
                        Inventory::add_item($player, $ee->name, -$ee->n);

                    }

                    // add craft item
                    Inventory::add_item($player, $artName, $craftedByN);


                    // put mvt
                    $player->put_used_carac('mvt', ITEM_CRAFT_COST);

                    // reload menu
                    $player->delete_cache('main');


                    // log
                    Log::put(array(
                        'player'=>$player,
                        'text'=>$player->name .' a créé un objet.',
                        'targetId'=>0,
                        'type'=>'craft',
                        'details'=>$artJson->name .' x'. $craftedByN
                    ));

                    exit();
                }

                break;
            }

            continue;
        }
    }

    echo '
            </td>
            ';

    if($artComplete){

        echo '
                <td valign="top">
                    <input type="button" value="Créer" item="'. $artItem->data->name .'" />
                </td>
                ';
    }
    else{
        echo '
                <td></td>
                ';
    }

    echo '
        </tr>
        ';

}


// no recipies
if(!$atLeastOne){
    echo '<tr><td colspan="3" align="center">Vous ne connaissez aucun artisanat en lien avec cet objet.</td></tr>';
}


echo '
</table> 
</div>
';


?>
  <script>
    $('input[type="button"]').click(function(e){

      var artName = $(this).attr('item');

      var option = $('select[item="'+artName+'"]').val();

      $(this).attr('disabled', true);

      $.ajax({
        type: "POST",
        url: 'craft.php?item=<?php echo $_GET['item'] ?>',
        data: {'create':artName, 'option':option},
        success: function(data)
        {

          alert('Artisanat effectué.');
          // alert(data);
          location.reload();
        }
      });
    });
  </script>
<?php

exit();
