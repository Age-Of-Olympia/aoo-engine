<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Entity\OutcomeInstruction;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\RecipeService;
use Classes\Player;
use Doctrine\ORM\Mapping as ORM;

/**
 * Crafts the recipe named by the gesture (POST recipeId) through
 * RecipeService::TryCraftRecipe — the single source of the craft rules
 * (knowledge, ingredients, result), which validates and consumes
 * atomically: a refusal (cheating, missing ingredients) costs nothing,
 * and `fabriquer` has no other cost. The craft panel (CraftView) posts
 * here; its entries stay hidden while CRAFT_ENABLED is off. The
 * atelier building will express itself as CONDITIONS (workshop
 * proximity), not here.
 */
#[ORM\Entity]
class CraftRecipeOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $recipeId = $_POST['recipeId'] ?? null;
        if (!is_numeric($recipeId)) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Aucune recette fournie au geste.']);
        }

        $service = new RecipeService();
        $recipe = $service->getRecipeById((int) $recipeId);
        if ($recipe === null) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Recette inconnue.']);
        }

        if ($actor->isSimulated()) {
            return new OutcomeResult(true, outcomeSuccessMessages: ['Fabriquerait la recette #' . (int) $recipeId . '.'], outcomeFailureMessages: array());
        }

        $message = '';
        if (!$service->TryCraftRecipe($recipe, $actor, $message)) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: [$message !== '' ? $message : 'Fabrication impossible.']);
        }

        return new OutcomeResult(true, outcomeSuccessMessages: [$message !== '' ? $message : 'Fabrication réussie.'], outcomeFailureMessages: array());
    }
}
