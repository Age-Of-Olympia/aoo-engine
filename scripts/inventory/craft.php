<?php


// player
$player = new Player($_SESSION['playerId']);
$player->get_data();


// recette json
$json = new Json();
$recipesJson = $json->decode('', 'crafts');

// common recipes
$recipeList = $recipesJson->common;

// add race recipes
$playerRace = $player->data->race;

// merge with racial recipes
if(!empty($recipesJson->$playerRace)){

    $recipeList = array_merge($recipeList, $recipesJson->$playerRace);
}


$ingredientList = array();

foreach($recipeList as $e){

    foreach($e->recette as $i){

        $ingredientList[$i->id] = true;
    }
}


if(!isset($_GET['itemId'])){


    echo '<h1>Artisanat</h1>';

    $itemList = Item::get_item_list($player->id);


    foreach($itemList as $k=>$item){


        if(!empty($ingredientList[$item->id])){

            continue;
        }


        unset($itemList[$k]);
    }


    $data = Ui::print_inventory($itemList);


    echo $data;

    ?>
    <style>.inventory-preview{display: none; text-align: left;}</style>
    <script src="js/progressive_loader.js"></script>
    <script>
    $(document).ready(function(){


        $('.inventory-preview').html('Voici les objets dans votre inventaire susceptibles d\'être utilisés dans l\'Artisanat.<br />Cliquez sur l\'un d\'eux pour voir les recettes associées.').fadeIn();

        $('.item-case').click(function(e){

            e.preventDefault();

            document.location = 'inventory.php?craft&itemId='+ $(this).data('id');
        });
    });
    </script>
    <?php

    exit();
}


function get_json_item($item){

    $return = (object) array('data');
    $return->data = json()->decode('items', $item->name);
    $return->data->mini = 'img/items/'. $item->name .'_mini.png';

    return $return;
}


$item = new Item($_GET['itemId'], false);

$item->get_data();

echo '
<div id="craft-wrapper">

<h1>Artisanat: '.$item->data->name.'</h1>
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

            // $ingredient = new Item($craft-> $ingredientItem->id);
            //
            // $ingredient->get_data();

            $ingredient = get_json_item($craft->$ingredientItem->name);

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


echo '
<p>Voici la liste des objets que vous pouvez créer avec cet objet.</p>
';


// list all item in Inventory and number

$itemList = Item::get_item_list($player->id);

foreach($itemList as $playerItem){
    $playerItemN[$playerItem->name] = $playerItem->n;
}


// list of craft
$craftList = array();


echo '
<table border="1" class="marbre">
    <tr>
        <th></th>
        <th>Objet</th>
        <th>Ingrédients</th>
        <th></th>
    </tr>
    ';


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

    $artItem = get_json_item($recipe);

    $artName = $recipe->name;

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
            <td align="left">
                ';

    // recipe
    foreach($recipeIngredients as $ingredient){


        $ingredientItem = get_json_item($ingredient);


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
                    <a href="item.php?item='. $ingredient->name .'"><img src="'. $ingredientItem->data->mini .'" /></a>
                    <font color="'. $color .'">x'. $ingredient->n .'</font>
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

                                // Inventory::add_item($player, $eee->name, $eee->n);

                                $whenCraftItem = Item::get_item_by_name($eee->name);
                                $whenCraftItem->add_item($player, $eee->n);
                            }
                        }


                        // remove item recipe
                        // Inventory::add_item($player, $ee->name, -$ee->n);

                        $itemRecipe = new Item($ee->id);
                        $itemRecipe->add_item($player, -$ee->n);

                    }

                    // add craft item
                    // Inventory::add_item($player, $artName, $craftedByN);

                    $itemCrafted = Item::get_item_by_name($artName);
                    $itemCrafted->add_item($player, $craftedByN);


                    // CRAFT COST (A)


                    // log


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
                    <input type="button" value="Créer" item="'. $artItem->data->name .'" style="width: 100%; height: 50px;" />
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
    echo '<tr><td colspan="4" align="center">Vous ne connaissez aucun artisanat en lien avec cet objet.</td></tr>';
}


echo '
</table> 
</div>
';


?>
<script src="js/progressive_loader.js"></script>
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
        alert(data);
        location.reload();
        }
    });
    });
</script>
<?php

exit();
