<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Entity\Race;
use App\Entity\Recipe;
use App\Entity\Item;
use Classes\Player;
use Classes\Db;
use Exception;

class RecipeService
{
    /** How far the crafter may stand from the workshop, in cells. */
    public const WORKSHOP_RANGE = 1;

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
     * Ingredients required to craft one item, as name => count.
     *
     * The craft_recipes catalog is the single recipe source. When several
     * recipes yield the same item, the one bearing the item's name wins,
     * then the oldest — a later variant never changes what an existing
     * object is considered made of.
     *
     * @return array<string, int>
     */
    public function ingredientsForResult(string $itemName): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('re, ri, ii')
            ->from(Recipe::class, 're')
            ->join('re.recipeResults', 'rr')
            ->join('rr.item', 'ir')
            ->leftJoin('re.recipeIngredients', 'ri')
            ->leftJoin('ri.item', 'ii')
            ->where('ir.name = :name')
            ->orderBy('re.id', 'ASC')
            ->setParameter('name', $itemName);

        $recipes = $qb->getQuery()->getResult();
        if ($recipes === []) {
            return [];
        }

        $recipe = $recipes[0];
        foreach ($recipes as $candidate) {
            if ($candidate->getName() === $itemName) {
                $recipe = $candidate;
                break;
            }
        }

        $ingredients = [];
        foreach ($recipe->getRecipeIngredients() as $ingredient) {
            $ingredients[$ingredient->getItem()->getName()] = $ingredient->getCount();
        }

        return $ingredients;
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
        if (!$this->workshopReached($recipe, $player, $message)) {
            return false;
        }
        $recipeIngredients = $recipe->GetRecipeIngredients();
        $recipeResults = $recipe->GetRecipeResults();

        /* The bag-lines rule, decided BEFORE the transaction (capacity
         * reads go through the Doctrine connection, blind to uncommitted
         * legacy writes): the outputs must fit once the ingredients are
         * gone — a wholly consumed stack frees its line. */
        $capacityService = new ContainerService();
        $bagCapacity = $capacityService->capacityOf((int) $player->id);
        if ($bagCapacity !== null) {
            $resultIds = [];
            foreach ($recipeResults as $result) {
                $resultIds[] = (int) $result->getItem()->getId();
            }
            $newLines = 0;
            foreach (array_unique($resultIds) as $resultId) {
                if ($capacityService->stackNeedsRoom((int) $player->id, $resultId)) {
                    $newLines++;
                }
            }
            $freedLines = 0;
            foreach ($recipeIngredients as $ingredient) {
                $ingredientId = (int) $ingredient->getItem()->getId();
                if ($ingredient->getItem()->getName() === 'or' || in_array($ingredientId, $resultIds, true)) {
                    continue;
                }
                $stock = (new \Classes\Item($ingredientId))->get_n($player, includeInstances: false);
                if (!$capacityService->stackNeedsRoom((int) $player->id, $ingredientId)
                    && $ingredient->GetCount() >= $stock
                ) {
                    $freedLines++;
                }
            }
            if ($newLines > 0
                && $capacityService->lineCountOf((int) $player->id) - $freedLines + $newLines > $bagCapacity
            ) {
                $message = 'Votre sac est plein.';
                return false;
            }
        }

        $db = new Db();
        $db->beginTransaction();
        try {

            // craft
            foreach ($recipeIngredients as $ingredient) {
                // remove item recipe
                $itemRecipe = new \Classes\Item($ingredient->getItem()->getId());

                if (!$itemRecipe->add_item($player, -$ingredient->GetCount())) {
                    // rollback obligatoire : sans lui la transaction restait
                    // OUVERTE sur ce chemin de refus (bug latent)
                    $db->rollback();
                    $message = "Vous n'avez pas assez de {$ingredient->getItem()->getName()} pour la recette {$recipe->getName()}";
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

    /**
     * The advanced level's gate: a recipe naming a workshop is crafted at
     * one — an OPEN building of that type within WORKSHOP_RANGE of the
     * crafter. Server-side truth; the craft panel only mirrors it.
     */
    public function workshopReached(Recipe $recipe, Player $player, &$message): bool
    {
        $workshop = $recipe->getWorkshop();
        if ($workshop === null) {
            return true;
        }

        $coords = $player->getCoords();
        $nearby = $coords !== null
            ? (new BuildingService())->openBuildingNearby($coords, [$workshop], self::WORKSHOP_RANGE)
            : ['open' => null, 'shut' => null];

        if ($nearby['open'] !== null) {
            return true;
        }

        if ($nearby['shut'] !== null) {
            $message = "Le bâtiment est {$nearby['shut']}.";
            return false;
        }

        $race = (new RaceService())->getRaceByName($workshop);
        $label = $race !== null ? $race->getLabel() : $workshop;
        $message = "Il faut être près de : {$label}.";

        return false;
    }
}
