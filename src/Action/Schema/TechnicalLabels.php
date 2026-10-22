<?php

namespace App\Action\Schema;

/**
 * French labels for the codes the admin shows: condition types, outcome
 * instructions, action types. The code itself stays the reference (stored
 * rows, imports, console); a code missing here is shown as is.
 */
final class TechnicalLabels
{
    private const CONDITIONS = [
        'Plan' => 'Plan interdit',
        'Obstacle' => 'Ligne de tir dégagée',
        'RequiresDistance' => 'Distance à la cible',
        'RequiresTraitValue' => 'Valeur de caractéristique',
        'RequiresWeaponType' => 'Type d\'arme équipée',
        'RequiresWeaponCraftedWith' => 'Arme fabriquée avec',
        'NoBerserk' => 'Délai anti-berserk',
        'ForbidIfHasEffect' => 'Interdit sous un effet',
        'ForbidOnEquipedObjectStatus' => 'Interdit selon l\'état de l\'équipement',
        'RequiresAmmo' => 'Munitions',
        'RequiresResource' => 'Ressource à proximité',
        'RequiresDamagedTarget' => 'Cible blessée ou abîmée',
        'RequiresRepairableTarget' => 'Cible réparable',
        'RepairBill' => 'Coût de la réparation',
        'RequiresLockControl' => 'Contrôle de la serrure',
        'RequiresInscriptionRight' => 'Droit d\'écrire l\'inscription',
        'InscriptionText' => 'Texte de l\'inscription',
        'RequiresItem' => 'Objet consommé',
        'ItemPick' => 'Objet choisi par le joueur',
        'BuildSite' => 'Case de construction',
        'DigSite' => 'Case à creuser',
        'RequiresBuilding' => 'Bâtiment à proximité',
        'RequiresPlanBuilding' => 'Bâtiment présent sur le plan',
        'RequiresFaction' => 'Membre d\'une faction',
        'ChestSite' => 'Emplacement d\'un coffre',
        'RequiresConstructionSite' => 'Sur un chantier',
        'TargetType' => 'Catégorie de cible',
        'TargetRace' => 'Type de cible',
        'RequiresFaith' => 'Points de foi',
        'Compute' => 'Jet opposé',
        'ComputePure' => 'Jet opposé (jet pur)',
        'MeleeCompute' => 'Jet de mêlée',
        'MeleePureCompute' => 'Jet de mêlée (jet pur)',
        'DistanceCompute' => 'Jet à distance',
        'DistancePureCompute' => 'Jet à distance (jet pur)',
        'SpellCompute' => 'Jet de sort',
        'SpellPureCompute' => 'Jet de sort (jet pur)',
        'BuffCompute' => 'Jet de sort de soutien',
        'TechniqueCompute' => 'Jet de technique',
        'TechniquePureCompute' => 'Jet de technique (jet pur)',
        'Dodge' => 'Postures de défense de la cible',
        'RequiresGodAffiliation' => 'Dieu',
        'AntiSpell' => 'Équipement qui empêche la magie',
        'Option' => 'Option de la cible',
    ];

    private const INSTRUCTIONS = [
        'addraceactions' => 'Donner les actions de la race',
        'advanceconstructionsite' => 'Faire avancer un chantier',
        'applyconsumable' => 'Appliquer un consommable',
        'applystatus' => 'Appliquer un effet',
        'craftrecipe' => 'Fabriquer une recette',
        'damageobject' => 'Abîmer l\'équipement',
        'digtunnel' => 'Creuser une galerie',
        'dropweapon' => 'Faire lâcher l\'arme',
        'enchant' => 'Enchanter',
        'equipitem' => 'Équiper ou déséquiper',
        'healing' => 'Soigner',
        'inscribe' => 'Écrire une inscription',
        'lifeloss' => 'Perte de PV',
        'malus' => 'Infliger du malus',
        'manaloss' => 'Perte de PM',
        'object' => 'Prendre ou donner un objet',
        'onlylog' => 'Journal seulement',
        'placelayer' => 'Poser une route',
        'placestructure' => 'Poser une structure',
        'player' => 'Modifier une caractéristique',
        'refreshscreen' => 'Rafraîchir l\'écran',
        'removeaction' => 'Retirer une action',
        'removemalus' => 'Retirer du malus',
        'repair' => 'Réparer',
        'resource' => 'Récolter',
        'rest' => 'Se reposer',
        'setgod' => 'Changer de dieu',
        'takeitem' => 'Ramasser un objet',
        'teleport' => 'Téléporter',
        'tiletype' => 'Bonus selon le type de case',
        'turnlock' => 'Ouvrir ou fermer une serrure',
    ];

    private const ACTION_TYPES = [
        'attack' => 'Attaque',
        'buff' => 'Sort de soutien',
        'build' => 'Construction',
        'consume' => 'Consommation',
        'craft' => 'Artisanat',
        'cursespell' => 'Malédiction',
        'dig' => 'Creusement',
        'distance' => 'Attaque à distance',
        'distancetechnique' => 'Technique à distance',
        'equip' => 'Équipement',
        'gesture' => 'Geste',
        'give' => 'Don',
        'heal' => 'Soin',
        'jettechnique' => 'Technique de jet',
        'melee' => 'Attaque de mêlée',
        'meleetechnique' => 'Technique de mêlée',
        'munitiontechnique' => 'Technique à munitions',
        'pray' => 'Prière',
        'repair' => 'Réparation',
        'rest' => 'Repos',
        'run' => 'Course',
        'search' => 'Fouille',
        'spell' => 'Sort',
        'steal' => 'Vol',
        'technique' => 'Technique',
        'train' => 'Entraînement',
        'work' => 'Travail',
    ];

    public static function condition(string $type): string
    {
        return self::CONDITIONS[$type] ?? $type;
    }

    public static function instruction(string $type): string
    {
        return self::INSTRUCTIONS[$type] ?? $type;
    }

    public static function actionType(string $key): string
    {
        return self::ACTION_TYPES[$key] ?? $key;
    }
}
