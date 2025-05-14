<?php

namespace App\View\Inventory;

use Classes\Player;
use Classes\Item;
use Classes\Craft;
use Classes\Json;
use Classes\Ui;
use Classes\Str;

class CraftView
{
    public static function renderCraft(): void
    {

        $player = new Player($_SESSION['playerId']);
        $player->get_data();

        ob_start();

        // recette json
        $json = new Json();
        $recipesJson = $json->decode('', 'crafts');

        // common recipes
        $recipeList = $recipesJson->common;

        // add race recipes
        $playerRace = $player->data->race;

        // merge with racial recipes
        if (!empty($recipesJson->$playerRace)) {

            $recipeList = array_merge($recipeList, $recipesJson->$playerRace);
        }


        $ingredientList = array();

        foreach ($recipeList as $e) {

            foreach ($e->recette as $i) {

                $ingredientList[$i->id] = true;
            }
        }


        if (!isset($_GET['itemId'])) {


            $itemList = Item::get_item_list($player->id);


            foreach ($itemList as $k => $item) {


                if (!empty($ingredientList[$item->id])) {

                    continue;
                }


                unset($itemList[$k]);
            }


            $data = Ui::print_inventory($itemList);


            echo $data;

?>
            <style>
                .inventory-preview {
                    display: none;
                }
            </style>
            <script src="js/progressive_loader.js"></script>
            <script>
                $(document).ready(function() {


                    $('.inventory-preview').html('<h1>Artisanat</h1> Voici les objets dans votre inventaire susceptibles d\'être utilisés dans l\'Artisanat.<br />Cliquez sur l\'un d\'eux pour voir les recettes associées.').fadeIn();

                    $('.item-case').click(function(e) {

                        e.preventDefault();

                        document.location = 'inventory.php?craft&itemId=' + $(this).data('id');
                    });
                });
            </script>
        <?php

            exit();
        }


        function get_json_item($item)
        {

            $return = (object) array('data');
            if (!$return->data = json()->decode('items', $item->name)) {

                echo 'error ' . $item->name;
            }

            $return->data->mini = 'img/items/' . $item->name . '_mini.webp';

            return $return;
        }


        $item = new Item($_GET['itemId'], false);

        $item->get_data();

        echo '
<div id="craft-wrapper">

<h1>Artisanat: ' . $item->data->name . '</h1>
<br/>

 <a href="item.php?itemId=' . $item->id . '" class="source-link">
        <img src="' . $item->data->img . '" />
    </a>
   ';


        // recipe
        if (!isset($item->data->occurence) || $item->data->occurence == 'co' || $item->data->race == $player->data->race) {


            // craft
            $craft = new Craft($item->data->name);

            // recette exists
            if (count($craft->itemRecipe)) {
                echo '
        <div id="item-recipe">
            ';

                foreach ($craft->itemRecipe as $ingredientItem => $n) {

                    // $ingredient = new Item($craft-> $ingredientItem->id);
                    //
                    // $ingredient->get_data();

                    $ingredient = get_json_item((object) array('name' => $ingredientItem));

                    echo '
                <img src="' . $ingredient->data->mini . '" /> x' . $n . '
                ';
                }

                echo '<br />';

                if ($craft->cost)
                    echo 'Coût des matériaux ~' . $craft->cost . 'Po<br />';

                echo 'Revendu ~' . floor($item->data->price * 2 / 3) . 'Po<br />';
                echo 'Acheté ~' . $item->data->price . 'Po';

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

        foreach ($itemList as $playerItem) {
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

        foreach ($recipeList as $recipe) {

            // artShow
            $artShow = false;

            // artComplete
            $artComplete = true;



            $recipeIngredients = $recipe->recette;

            // search for item in recipe
            foreach ($recipeIngredients as $ee) {
                if ($ee->id != strtolower($item->data->id)) {
                    continue;
                }

                $artShow = true;
                $atLeastOne = true;
            }


            // art have NOT item in recipe
            if (!$artShow) {
                continue;
            }

            $artItem = get_json_item($recipe);

            $artName = $recipe->name;

            $artId = $recipe->id;

            // print
            echo '
        <tr>
            <td width="50">
                <a href="item.php?itemId=' . $artId . '"><img src="' . $artItem->data->mini . '" /></a>
            </td>
            <td>
                ' . $artItem->data->name . '<br />
                
                ';



            echo '
            </td>
            <td align="left">
                ';

            // recipe
            foreach ($recipeIngredients as $ingredient) {


                $ingredientItem = get_json_item($ingredient);


                // color
                if (!isset($playerItemN[$ingredient->name])) {
                    $color = 'red';
                    $artComplete = false;
                } elseif ($playerItemN[$ingredient->name] >= $ingredient->n) {
                    $color = 'green';
                } else {
                    $color = 'orange';
                    $artComplete = false;
                }

                echo '
                    <a href="item.php?itemId=' . $ingredient->id . '"><img src="' . $ingredientItem->data->mini . '" /></a>
                    <font color="' . $color . '">x' . $ingredient->n . '</font>
                    ';
            }

            echo '
            </td>
            ';

            if ($artComplete) {

                echo '
                <td valign="top">
                    <input type="button" value="Créer" itemId="' . $artId . '" style="width: 100%; height: 50px;" />
                </td>
                ';
            } else {
                echo '
                <td></td>
                ';
            }

            echo '
        </tr>
        ';
        }


        // no recipies
        if (!$atLeastOne) {
            echo '<tr><td colspan="4" align="center">Vous ne connaissez aucun artisanat en lien avec cet objet.</td></tr>';
        }


        echo '
</table> 
</div>
';

        echo Str::minify(ob_get_clean());

        ?>
        <script src="js/progressive_loader.js"></script>
        <script>
            $('input[type="button"]').click(function(e) {

                var artId = $(this).attr('itemId');

                $(this).attr('disabled', true);

                aooFetch('api/player/craft_item.php', {
                        'craft_id': artId
                    }, null)
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                        } else if (data.message) {
                            alert(data.message);
                        }
                        location.reload();
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        location.reload();
                    });

            });
        </script>
<?php
    }
}
