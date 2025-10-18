<?php
use App\Service\RecipeService;
require_once __DIR__ . '/layout.php';
ob_start();
$recipeService = new RecipeService();
$recipes = $recipeService->adminGetAllRecipes();
foreach ($recipes as $recipe) {
    PrintReciep($recipe);
}
$content = ob_get_clean();
echo admin_layout('Recettes de craft', $content);
function PrintReciep($recipe)
{
    echo $recipe->GetName() . '<br>';
    if($recipe->getRaces()->count() > 0) {
        foreach($recipe->getRaces() as $race) {
            echo $race->getName() . '<br>';
        }
    }
    else {
        echo "common<br>";
    }
    foreach ($recipe->GetRecipeIngredients() as $ingredient) {
        echo 'Ingredient: ' . $ingredient->GetItem()->GetName() . ' (' . $ingredient->GetItem()->GetId() . ') x' . $ingredient->GetCount() . '<br>';
    }
    foreach ($recipe->GetRecipeResults() as $result) {
        echo 'Result: '. $ingredient->GetItem()->GetName() . ' (' . $ingredient->GetItem()->GetId() . ') x' . $result->GetCount() . '<br><br><br><br>';
    }
    echo '<hr>';
}
