<?php
use Classes\Player;
use Classes\Db;
use Classes\Item;
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

            // craft
            foreach ($recipeIngredients as $ingredient) {


                // needed item
                $neededJson = $json->decode('items', $ingredient->name);

                // remove item recipe
                $itemRecipe = new Item($ingredient->id);
                
                if(!$itemRecipe->add_item($player, -$ingredient->n)) {
                    ExitError("Vous n\'avez pas assez de {$ingredient->name} pour créer {$artName}");
                }
            }

            $itemCrafted = Item::get_item_by_name($artName);
            $itemCrafted->add_item($player, $craftedByN);
            
            $db->commit();
            ExitSuccess("Vous avez créé {$artName} ({$craftedByN})");
        } catch (Exception $e) {
            $db->rollback();
            ExitError("Erreur lors de la création de l\'objet: {$e->getMessage()}");
        }
} else {

    function PrintReciep($recipe)
    {
        echo $recipe->GetName() . '<br>';
        echo ($recipe->GetRace() ?$recipe->GetRace()->GetName() :"common") . '<br>';
        foreach ($recipe->GetRecipeIngredients() as $ingredient) {
            echo 'Ingredient: ' . $ingredient->GetItem()->GetId() . ' x' . $ingredient->GetCount() . '<br>';
        }
        foreach ($recipe->GetRecipeResults() as $result) {
            echo 'Result: ' . $result->GetItem()->GetId() . ' x' . $result->GetCount() . '<br><br><br><br>';
        }
        echo '<hr>';
    }
    $recipeService = new RecipeService();

    $recipes = $recipeService->getRecipes($player);//bois =89
    echo count($recipes).'found<br>';
    foreach ($recipes as $recipe) {
        PrintReciep($recipe);
    }
   $id=75; //gladius 19 / casque 75
   echo " recette pour l'item {$id}<br>";
   $singleRecipe = $recipeService->getRecipes($player, null, $id );
    if (count($singleRecipe)) {
       PrintReciep($singleRecipe[0]);
    } else {
        echo 'No recipe found';
    }
   // var_dump();
   if(count($recipes)) {
        

        // Get a single recipe by ID
   $singleRecipe = $recipeService->getRecipeById($recipes[0]->GetId());
    if ($singleRecipe) {
       PrintReciep($singleRecipe);
    } else {
        echo 'No recipe found .';
    }
}
else 
{
    echo 'No recipes found ';
}
}
