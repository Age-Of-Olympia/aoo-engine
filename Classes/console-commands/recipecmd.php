<?php
use App\Service\RaceService;
use App\Entity\EntityManagerFactory;
use Classes\AdminCommand;
use Classes\Argument;
use Classes\Item;

class RecipeCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("recipe", [new Argument('action',false),new Argument('item_id',false) ]);
        parent::setDescription(<<<EOT
Ajout ou suppression d'une recette pour un objet existant
Permet aussi d'ajouter un ingrédient.
Exemple:
> recipe create item_id race
> recipe create 26 common
> recipe delete item_id
> recipe add-ingredient recipe_item_id ingredient_item_id n 
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        $action = $argumentValues[0];

        if($action == "create"){
            $item = new Item($argumentValues[1]);
            if(!isset($item->row->name)){
                return '<font color="red">Item id does not exist in DB '.$argumentValues[1].'</font>';
            }
            $recipes = read_recipe_json_file();
            if ($recipes === null) {
                return '<font color="red"> Erreur lors de la lecture du fichier JSON.</font>';
            }
            if (!isset($recipes[$argumentValues[2]] )) {
                return '<font color="red"> race inconnue dans le fichier recette.</font>';
            }
            $newRecipe = [
                "name" => $item->row->name,
                "id" => $item->row->id,
                "recette" => []
            ];
            $recipes[$argumentValues[2]][] = $newRecipe;
            save_recettes_file($recipes);
            return 'Recipe created';
        }

        if($action == "delete"){
            $recipes = read_recipe_json_file();
            if ($recipes === null) {
                return '<font color="red"> Erreur lors de la lecture du fichier JSON.</font>';
            }
            foreach ($recipes as &$race) {
                foreach ($race as $key =>  &$recette) {
                    if($recette['id'] == $argumentValues[1]){
                        unset($race[$key]);
                    }
                }
            }

            save_recettes_file($recipes);
            return 'Recipe deleted';
        }

        if($action == "add-ingredient"){
            $recipes = read_recipe_json_file();
            if ($recipes === null) {
                return '<font color="red"> Erreur lors de la lecture du fichier JSON.</font>';
            }
            foreach ($recipes as &$race) {
                foreach ($race as &$recette) {
                    if($recette['id'] == $argumentValues[1]){
                        $item = new Item($argumentValues[2]);
                        $newIngredient = [
                            "name" => $item->row->name,
                            "id" => $item->row->id,
                            "n" => $argumentValues[3]
                        ];
                        $recette['recette'] = $newIngredient;
                    }

                }
            }
            save_recettes_file($recipes);
            return 'Ingredient added';
        }
        if ($action == 'import') {
            $recipes = read_recipe_json_file();
            $count = 0;
            $raceService = new RaceService();
            $em = EntityManagerFactory::getEntityManager();
            $itemRepo = $em->getRepository(App\Entity\Item::class);
            foreach ($recipes as $race => $recette) {

                $race = $raceService->getRaceByName($race);
                foreach ($recette as $recetteData) {
                    $recipe = new App\Entity\Recipe();
                    $recipe->setName($recetteData['name']);
                    $recipe->setRace($race);
                   
                    foreach ($recetteData['recette'] as $ingredient) {
                        $ingredientObj = new App\Entity\RecipeIngredient();
                        $item = new Item($ingredient['id']);
                        $item->get_data();
                        if($item->row->name!= $ingredient['name']){
                            $this->result->Error("Item name mismatch recipe '{$recetteData['name']}' use '{$item->row->name} but say it use {$ingredient['name']}'");
                        }
                        $itemEntity = $itemRepo->find($ingredient['id']);
                        $ingredientObj->setItem( $itemEntity);
                        $ingredientObj->setCount($ingredient['n']);
                        $recipe->addRecipeIngredient($ingredientObj);
                    }
                    $resultObj = new App\Entity\RecipeResult();
                    $itemEntity = $itemRepo->find($recetteData['id']);
                    $resultObj->setItem($itemEntity);
                    
                    $item = new Item($recetteData['id']);
                    $item->get_data();
                    if($item->row->name!= $recetteData['name']){
                        $this->result->Log("Item name mismatch recipe '{$recetteData['name']}' create '{$item->row->name}'");
                    }
                    $craftedByN = isset($item->data->craftedByN) ? $item->data->craftedByN : 1;
                    $resultObj->setCount($craftedByN);
                    $recipe->addRecipeResult($resultObj);
                    $em->persist($recipe);
                    $em->flush();//
                    $count++;
                }
            }
            

            $this->result->Log($count.' recettes importées');
            return '';
        }

        return '<font color="orange">Action : '.$action.' unknown</font>';


    }
}

function read_recipe_json_file () {

    $jsonString = file_get_contents('datas/public/crafts.json');
    return json_decode($jsonString, true);
}

function save_recettes_file ($recipes) {
    $newJsonString = json_encode($recipes, JSON_PRETTY_PRINT);
    file_put_contents('datas/public/artisanat/recette.json', $newJsonString);
}
