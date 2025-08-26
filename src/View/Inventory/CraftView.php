<?php

namespace App\View\Inventory;

use Classes\Player;
use Classes\Item;
use App\Service\RecipeService;
use Classes\Ui;
use Classes\Str;

class CraftView
{
    public static function renderCraft(): void
    {

        $player = new Player($_SESSION['playerId']);
        $player->get_data();
        $recipeService = new RecipeService();
        ob_start();




        if (!isset($_GET['itemId'])) {

            $recipeList = $recipeService->getRecipes($player);
            $ingredientList = array();

            foreach ($recipeList as $e) {

                foreach ($e->GetRecipeIngredients() as $i) {

                    $ingredientList[$i->GetItem()->GetId()] = true;
                }
            }

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

            $return = (object) array('data', 'id');
            if (!$return->data = json()->decode('items', $item->getName())) {

                echo 'error ' . $item->getName();
            }

            $return->data->mini = 'img/items/' . $item->getName() . '_mini.webp';
            $return->id = $item->getId();
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


            $recipeList = $recipeService->getRecipes($player, fromItemId: null, forItemId: $item->id);


            // recette exists
            if (count($recipeList)) {
                echo '
        <div id="item-recipe">
            ';

                foreach ($recipeList as $ingredientItem => $n) {

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

        $recipeList = $recipeService->getRecipes($player, fromItemId: $item->id);

        echo '
<table border="1" class="marbre">
    <tr>
        <th></th>
        <th>Objet</th>
        <th>Ingrédients</th>
        <th></th>
    </tr>
    ';




        foreach ($recipeList as $recipe) {

            // artComplete
            $hasAllIngredients = true;

            $reciepName = $recipe->GetName();
            $recipeId = $recipe->getId();
            $artItem = get_json_item($recipe->getRecipeResults()[0]->GetItem());

            // print
            echo '
        <tr>
            <td width="50">

              <a href="item.php?itemId=' . $artItem->id . '"><img src="' . $artItem->data->mini . '" /></a>

            </td>
            <td>
                ' . $artItem->data->name . '<br />
                
                ';



            echo '
            </td>
            <td align="left">
                ';

            // recipe
            foreach ($recipe->GetRecipeIngredients() as $ingredient) {


                $ingredientItem = get_json_item($ingredient->GetItem());


                // color
                if (!isset($playerItemN[$ingredientItem->data->name])) {
                    $color = 'red';
                    $hasAllIngredients = false;
                } elseif ($playerItemN[$ingredientItem->data->name] >= $ingredient->getCount()) {
                    $color = 'green';
                } else {
                    $color = 'orange';
                    $hasAllIngredients = false;
                }

                echo '
                    <a href="item.php?itemId=' . $ingredientItem->id . '"><img src="' . $ingredientItem->data->mini . '" /></a>
                    <font color="' . $color . '">x' . $ingredient->getCount() . '</font>
                    ';
            }

            echo '
            </td>
            ';

            if ($hasAllIngredients) {

                echo '
                <td valign="top">
                    <input type="button" value="Créer" itemId="' . $recipeId . '" style="width: 100%; height: 50px;" />
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
        if (!count($recipeList)) {
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
