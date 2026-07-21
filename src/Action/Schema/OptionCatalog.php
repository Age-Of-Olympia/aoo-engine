<?php

namespace App\Action\Schema;

use App\Entity\EntityManagerFactory;
use App\Service\ActionPassiveService;
use App\Service\RecipeService;

/**
 * Single source for the real, enumerable game values that schema/simulation
 * fields select from: effects, passives, weapon types and equipment slots.
 * Each lookup degrades to an empty list when its source is unavailable (e.g.
 * constants not loaded, DB unreachable in tests), so callers never need to guard.
 */
final class OptionCatalog
{
    private ?ActionPassiveService $passiveService;
    private ?RecipeService $recipeService;
    private ?\App\Service\EffectService $effectService;

    public function __construct(
        ?ActionPassiveService $passiveService = null,
        ?RecipeService $recipeService = null,
        ?\App\Service\EffectService $effectService = null
    ) {
        $this->passiveService = $passiveService;
        $this->recipeService = $recipeService;
        $this->effectService = $effectService;
    }

    /**
     * @return array<string, string> effect name => human label
     */
    public function effects(): array
    {
        try {
            $service = $this->effectService ??= new \App\Service\EffectService();
            $names = $service->getGameplayEffectNames();
        } catch (\Throwable) {
            return [];
        }

        $effects = [];
        foreach ($names as $name) {
            $effects[$name] = $service->getLabel($name);
        }
        ksort($effects);

        return $effects;
    }

    /**
     * The game caracs, keyed by code (the CARACS constant is the single source).
     *
     * @return array<string, string> carac code => label
     */
    public function caracs(): array
    {
        return defined('CARACS') ? CARACS : [];
    }

    /**
     * Every CHARACTER race, PNJ/system ones included (the races table is
     * the single source) : une action ou un passif se restreint aussi à
     * une race non jouable — ame, animal, dieu… (retour saison 3 : le
     * sélecteur n'offrait que les jouables). Les types de structures
     * (kind 'structure') restent exclus : players.race d'un personnage
     * ne les porte jamais.
     *
     * @return array<string, string> race => label
     */
    public function races(): array
    {
        $races = [];
        foreach ((new \App\Service\RaceService())->getRacesByKind('character') as $race) {
            $races[$race->getName()] = $race->getLabel();
        }

        return $races;
    }

    /**
     * @return array<string, string> passive name => display name
     */
    public function passives(): array
    {
        try {
            $service = $this->passiveService ??= new ActionPassiveService();

            return $service->getAllNames();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The combat weapon types an action can require (item `subtype` values).
     *
     * @return array<string, string>
     */
    public function weaponTypes(): array
    {
        return ['melee' => 'Mêlée', 'tir' => 'Tir', 'jet' => 'Jet', 'bouclier' => 'Bouclier'];
    }

    /**
     * Equipment slots a weapon-type condition can read.
     *
     * @return array<string, string>
     */
    public function emplacements(): array
    {
        return ['main1' => 'Main 1', 'main2' => 'Main 2', 'tronc' => 'Tronc', 'tete' => 'Tête'];
    }

    /**
     * Distinct crafting materials, from the existing recipe ingredients.
     *
     * @return array<string, string>
     */
    public function craftingMaterials(): array
    {
        try {
            $recipes = ($this->recipeService ??= new RecipeService())->adminGetAllRecipes();
        } catch (\Throwable) {
            return [];
        }

        $materials = [];
        foreach ($recipes as $recipe) {
            foreach ($recipe->getRecipeIngredients() as $ingredient) {
                $name = (string) $ingredient->getItem()->getName();
                $materials[$name] = $this->humanize($name);
            }
        }
        ksort($materials);

        return $materials;
    }

    /**
     * Every item as id => name, for fields that reference a specific item
     * (e.g. a required ammunition). Names beat raw ids for a human.
     *
     * @return array<string, string>
     */
    public function items(): array
    {
        try {
            $rows = EntityManagerFactory::getEntityManager()
                ->createQuery('SELECT i.id AS id, i.name AS name FROM App\Entity\Item i ORDER BY i.name ASC')
                ->getArrayResult();
        } catch (\Throwable) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $items[(string) $row['id']] = $this->humanize((string) $row['name']);
        }

        return $items;
    }

    /**
     * The distinct, non-empty action categories in use (e.g. melee-off,
     * spell-support). Drives the passive "category" condition picker so it offers
     * the real categories an action can carry instead of free text.
     *
     * @return array<string, string> category => human label
     */
    public function actionCategories(): array
    {
        try {
            $rows = EntityManagerFactory::getEntityManager()
                ->createQuery("SELECT DISTINCT a.category AS category FROM App\Entity\Action a WHERE a.category IS NOT NULL AND a.category != '' ORDER BY a.category ASC")
                ->getArrayResult();
        } catch (\Throwable) {
            return [];
        }

        $categories = [];
        foreach ($rows as $row) {
            $name = (string) $row['category'];
            $categories[$name] = $this->humanize($name);
        }

        return $categories;
    }

    /**
     * Options for a catalog-backed field type, or [] for non-catalog types.
     *
     * @return array<string, string>
     */
    public function optionsFor(FieldType $type): array
    {
        return match ($type) {
            FieldType::EFFECT => $this->effects(),
            FieldType::PASSIVE => $this->passives(),
            FieldType::WEAPON_TYPE => $this->weaponTypes(),
            FieldType::EMPLACEMENT => $this->emplacements(),
            FieldType::MATERIAL => $this->craftingMaterials(),
            FieldType::ITEM => $this->items(),
            FieldType::ACTION => $this->actions(),
            FieldType::PLAN => $this->plans(),
            default => [],
        };
    }

    /**
     * Map planes (the coords table is the single source). Ephemeral per-session
     * tutorial instances (plan LIKE 'tut_…') are excluded; the base 'tutorial'
     * plane is kept.
     *
     * @return array<string, string> plan => plan
     */
    public function plans(): array
    {
        try {
            $names = EntityManagerFactory::getEntityManager()->getConnection()
                ->fetchFirstColumn("SELECT DISTINCT plan FROM coords WHERE plan IS NOT NULL AND plan != '' AND plan NOT LIKE 'tut\\_%' ORDER BY plan ASC");
        } catch (\Throwable) {
            return [];
        }

        $plans = [];
        foreach ($names as $name) {
            $plans[(string) $name] = (string) $name;
        }

        return $plans;
    }

    /**
     * Action names (the actions table is the single source).
     *
     * @return array<string, string> name => name
     */
    public function actions(): array
    {
        try {
            $rows = EntityManagerFactory::getEntityManager()
                ->createQuery("SELECT a.name AS name FROM App\Entity\Action a ORDER BY a.name ASC")
                ->getArrayResult();
        } catch (\Throwable) {
            return [];
        }

        $actions = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $actions[$name] = $name;
        }

        return $actions;
    }

    private function humanize(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name));
    }
}
