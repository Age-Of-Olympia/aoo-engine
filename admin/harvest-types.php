<?php
/**
 * Types récoltables (admin → Cartes → Ressources → Types récoltables) : le
 * QUATRIÈME visage de la table races — les 39 types que la récolte moissonne,
 * séparés des types de bâtiments pour ne pas noyer la liste qu'un animateur
 * parcourt quand il pose un mur. Toute la mécanique vit dans admin/races.php ;
 * ce wrapper pose le visage avant de l'inclure.
 *
 * Valeurs littérales : l'autoload n'est enregistré qu'à l'inclusion de
 * races.php. Même contrainte que scenery-types.php.
 */

$_GET['kind'] = 'structure';
$_GET['nature'] = 'ressource';

require __DIR__ . '/races.php';
