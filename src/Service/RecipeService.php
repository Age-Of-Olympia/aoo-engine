<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use App\Entity\Recipe;

class RecipeService
{
    private $entityManager;

    public function __construct()
    {
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getRecipes($player,?int $itemid=null): array
    {
        $repo = $this->entityManager->getRepository(Recipe::class);
       
            $player->get_data(false);
            // $raceService = new RaceService();

            // // Fetch Race by name
            // $race = $raceService->getRaceByName($player->data->race);
            //$selectedRaceId = $race->getId();
            $qb=$this->entityManager->createQueryBuilder(); 
            //get recipes and it's ingredients
            $qb->select('re,ri,rr,ra')
                ->from(Recipe::class, 're')
                ->leftJoin('re.race', 'ra')
                ->leftJoin('re.recipeIngredients', 'ri')
                ->leftJoin('re.recipeResults', 'rr')
                ->where('(ra.name = :racename OR ra.id IS NULL)')
                ->setParameter('racename', $player->data->race);

                if($itemid){
                    $qb->andWhere('ri.item_id = :itemId')
                        ->setParameter('itemId', $itemid);
                }
                // ->leftJoin('r.recipeIngredients', 'ri')
                // ->leftJoin('r.recipeResults', 'rr');

                $query = $qb->getQuery();
                $sql = $query->getSQL();
                $results = $query->getResult();
        return $results;
    }
    // optionally filter by player race
    public function getRecipeById(int $id,$player=null): ?Recipe
    {
        $repo = $this->entityManager->getRepository(Recipe::class);
        return $repo->findOneBy(['id' => $id]);;
    }
}
