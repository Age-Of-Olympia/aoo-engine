<?php
/**
 * Types de plantes (admin → Cartes → Plantes) : le CINQUIÈME visage de la
 * table races — fleurs, herbes et ronces, que l'on cueille sans les frapper et
 * sur lesquelles on marche. Séparées des récoltables : les deux se récoltent,
 * mais rien d'autre ne les rapproche, et mêler les listes obligerait
 * l'animateur à trier lui-même ce qui bloque de ce qui ne bloque pas.
 *
 * Toute la mécanique vit dans admin/races.php ; ce wrapper pose le visage avant
 * de l'inclure.
 *
 * Valeurs littérales : l'autoload n'est enregistré qu'à l'inclusion de
 * races.php. Même contrainte que harvest-types.php.
 */

$_GET['kind'] = 'structure';
$_GET['nature'] = 'plante';

require __DIR__ . '/races.php';
