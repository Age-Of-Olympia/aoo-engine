<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use App\Entity\Recipe;
use App\Entity\Item;
use Classes\Player;
use Classes\Db;
use Exception;

class RecipeService
{
    private $entityManager;

    public function __construct()
    {
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getRecipes($player, ?int $fromItemId = null, ?int $forItemId = null): array
    {
        $player->get_data(false);
        $qb = $this->entityManager->createQueryBuilder();
        //get recipes and it's ingredients
        $qb->select('re,ri,rr,ra')
            ->from(Recipe::class, 're')
            ->leftJoin('re.races', 'ra')
            ->leftJoin('re.recipeIngredients', 'ri')
            ->leftJoin('ri.item', 'i')
            ->leftJoin('re.recipeIngredients', 'rif')//this is a filter not selected
            ->leftJoin('rif.item', 'if')//this is a filter not selected 
            ->leftJoin('re.recipeResults', 'rr')
            ->leftJoin('rr.item', 'r')
            ->where('(ra.name = :racename OR ra.id IS NULL)')
            ->setParameter('racename', $player->data->race);

        if ($fromItemId) {
            $qb->andWhere('if.id = :itemId')
                ->setParameter('itemId', $fromItemId);
        }
        if ($forItemId) {
            $qb->andWhere('r.id = :forItemId')
                ->setParameter('forItemId', $forItemId);
        }

        $query = $qb->getQuery();
        //$sql = $query->getSQL();
        $results = $query->getResult();
        return $results;
    }

    public function adminGetAllRecipes(): array
    {
        if(!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true)
        {
            return array();
        }
        
        $qb = $this->entityManager->createQueryBuilder();
        //get recipes and it's ingredients
        $qb->select('re,ri,rr,ra')
            ->from(Recipe::class, 're')
            ->leftJoin('re.races', 'ra')
            ->leftJoin('re.recipeIngredients', 'ri')
            ->leftJoin('ri.item', 'i')
            ->leftJoin('re.recipeResults', 'rr')
            ->leftJoin('rr.item', 'r');
        $query = $qb->getQuery();

        $results = $query->getResult();
        return $results;
    }

    public function getRecipeById(int $id): ?Recipe
    {
        $repo = $this->entityManager->getRepository(Recipe::class);
        return $repo->findOneBy(['id' => $id]);;
    }
    /**
     * Checks if the player can craft the given recipe. knowledge, not ingredients
     */
    public function IsPlayerAllowedCraftRecipe(Recipe $recipe, Player $player): bool
    {

        $races = $recipe->getRaces();
        if($races->count() > 0)
        {
            $playerRace = $player->getRace();
           
            foreach($races as $race)
            {
                if($race->getName() == $playerRace)
                    return true;
            }
            return false;
        }
        else
            return true;
    }
    public function TryCraftRecipe(Recipe $recipe, Player $player, &$message): bool
    {
        if (!$this->IsPlayerAllowedCraftRecipe($recipe, $player)) {
            $auditService = new AuditService();
            $auditService->addAuditLog("Tentative de triche craft");
            $message = "Vous ne pouvez pas créer cette recette.";
            return false;
        }
        $recipeIngredients = $recipe->GetRecipeIngredients();
        $recipeResults = $recipe->GetRecipeResults();
        $db = new Db();
        $db->beginTransaction();
        try {

            // craft
            foreach ($recipeIngredients as $ingredient) {
                // remove item recipe
                $itemRecipe = new \Classes\Item($ingredient->getItem()->getId());

                if (!$itemRecipe->add_item($player, -$ingredient->GetCount())) {
                    $message = "Vous n\'avez pas assez de {$ingredient->name} pour la recette {$recipe->getName()}";
                    return false;
                }
            }
            foreach ($recipeResults as $result) {
                $itemCrafted = \Classes\Item::get_item_by_name($result->getItem()->GetName());
                $itemCrafted->add_item($player, $result->GetCount());
                $message .= "Vous avez créé {$result->getItem()->GetName()} ({$result->GetCount()}) \n";
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollback();
            $message = "Erreur lors de la création de l\'objet: {$e->getMessage()}";
        }
        return false;
    }
}
