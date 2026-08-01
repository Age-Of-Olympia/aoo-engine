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
 * Fabrique la recette désignée au geste (POST recipeId) via
 * RecipeService::TryCraftRecipe — la SOURCE UNIQUE des règles de craft
 * (connaissance, ingrédients, résultat), qui valide et consomme
 * atomiquement : un refus (triche, ingrédients manquants) ne coûte
 * rien, l'action `fabriquer` n'a pas d'autre coût. L'artisanat restant
 * en sommeil (CRAFT_ENABLED), cette action câble le moteur sans
 * exposer d'UI : le bouton reviendra avec le bâtiment d'artisanat —
 * qui s'exprimera ici en CONDITIONS (proximité de l'atelier).
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
