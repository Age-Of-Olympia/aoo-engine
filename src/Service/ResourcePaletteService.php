<?php

namespace App\Service;

use App\Service\Map\StructureTypeService;

/**
 * Règle unique de ce qui se pose encore sur la couche « resources », depuis la
 * conversion des obstacles/décor en entités bâtiment
 * (Version20260719280000_WallsToEntities, docs/design-walls-to-entities.md) :
 * la couche porte les RESSOURCES récoltables, plus deux survivants (autels,
 * types unique_*) et les plans de tutoriel, dont les murs d'enceinte sont
 * clonés par session et restent hors conversion.
 *
 * Les obstacles (mur_*, statues, coffres…) se posent en tant que
 * bâtiments : admin → Bâtiments. Cette règle est partagée par les deux
 * éditeurs (extension Tiled via le catalogue/l'import, éditeur web via
 * la palette et la pose) pour qu'aucun chemin ne recrée l'ancien système.
 */
class ResourcePaletteService
{
    /** Préfixes de noms qui restent des ressources quel que soit le plan */
    private const SPECIAL_PREFIXES = ['autel', 'altar', 'unique_'];

    /** Un mur récoltable : le catalogue dit que sa nature est « ressource ». */
    public static function isResourceName(string $name): bool
    {
        return StructureTypeService::isHarvestable($name);
    }

    /** Les murs des plans de tutoriel sont clonés par session, hors conversion */
    public static function isTutorialPlan(string $plan): bool
    {
        return $plan === 'tutorial' || str_starts_with($plan, 'tut_');
    }

    /** Le nom est-il encore posable sur la couche resources de ce plan ? */
    public static function isAuthorable(string $name, string $plan): bool
    {
        if (self::isTutorialPlan($plan)) {
            return true;
        }

        if (self::isResourceName($name)) {
            return true;
        }

        foreach (self::SPECIAL_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filtre une liste de noms de murs sur ceux posables sur le plan.
     *
     * @param string[] $names
     * @return string[]
     */
    public static function filterNames(array $names, string $plan): array
    {
        return array_values(array_filter(
            $names,
            fn(string $name) => self::isAuthorable($name, $plan)
        ));
    }
}
