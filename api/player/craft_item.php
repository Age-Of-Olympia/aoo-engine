<?php

use App\Entity\Recipe;
use App\Service\RecipeService;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
$player = new Player($_SESSION['playerId']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $POST_DATA = json_decode(file_get_contents('php://input'), true);

    if (!isset($POST_DATA['craft_id'])) {
        ExitError('Invalid request');
    }

    $reciep = $recipeService->getRecipeById($_POST['craft_id'], $player);
    // this item
    if (!$reciep){
        ExitError('Recette introuvable');
    }
    $recipeIngredients = $reciep->GetRecipeIngredients();
    $db = new Db();
    $db->beginTransaction();
       try {
            // artJson
            $artJson = $json->decode('items', $artName);


            // crafted by n
            $craftedByN = $reciep->GetCraftedByN();


            // script when crafted
            if (file_exists('item/craft_script/' . $artName . '.php')) {
                include('item/craft_script/' . $artName . '.php');
            }


            // craft
            foreach ($recipeIngredients as $ingredient) {


                // needed item
                $neededJson = $json->decode('items', $ingredient->name);

                // remove item recipe
                $itemRecipe = new Item($ingredient->id);
                $itemRecipe->add_item($player, -$ingredient->n);
            }


            $itemCrafted = Item::get_item_by_name($artName);
            $itemCrafted->add_item($player, $craftedByN);
            
            $db->commit();
            ExitSuccess('Vous avez créé ' . $artName . '('.$craftedByN.')');
        } catch (Exception $e) {
            $db->rollback();
            ExitError('Erreur lors de la création de l\'objet: ' . $e->getMessage());
           
        }
} else {

    $recipeService = new RecipeService();

    $recipes = $recipeService->getRecipes($player, 1);//bois =89
    echo count($recipes).'found<br>';
    foreach ($recipes as $recipe) {
        echo $recipe->GetName() . '<br>';
        echo ($recipe->GetRace() ?$recipe->GetRace()->GetName() :"common") . '<br>';
        foreach ($recipe->GetRecipeIngredients() as $ingredient) {
            echo 'Ingredient: ' . $ingredient->GetItemId() . ' x' . $ingredient->GetCount() . '<br>';
        }
        foreach ($recipe->GetRecipeResults() as $result) {
            echo 'Result: ' . $result->GetItemId() . ' x' . $result->GetCount() . '<br><br><br><br>';
        }
    }

   // var_dump();
   $singleRecipe = $recipeService->getRecipeById($recipes[0]->GetId());
    if ($singleRecipe) {
        echo '<br><br>Single Recipe: ' . $singleRecipe->GetName() . '<br>';
        echo ($recipe->GetRace() ?$recipe->GetRace()->GetName() :"common") . '<br>';
        foreach ($singleRecipe->GetRecipeIngredients() as $ingredient) {
            echo 'Ingredient: ' . $ingredient->GetItemId() . ' x' . $ingredient->GetCount() . '<br>';
        }
        foreach ($singleRecipe->GetRecipeResults() as $result) {
            echo 'Result: ' . $result->GetItemId() . ' x' . $result->GetCount() . '<br>';
        }
    } else {
        echo 'No recipe found with ID 1.';
    }
}
