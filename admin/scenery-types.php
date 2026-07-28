<?php
/**
 * Types de décor (admin → Cartes → Types de décor) : le TROISIÈME visage de
 * la table races — les familles de décor créées par la conversion, séparées
 * des types de bâtiments pour ne pas noyer la liste qu'un animateur parcourt
 * quand il pose un mur. Toute la mécanique vit dans admin/races.php ; ce
 * wrapper pose le visage avant de l'inclure.
 *
 * Valeurs littérales : l'autoload n'est enregistré qu'à l'inclusion de
 * races.php, donc App\View\Admin\TypeEditorFace::NATURE_DECOR n'est pas
 * encore atteignable ici. Même contrainte que structure-types.php.
 */

$_GET['kind'] = 'structure';
$_GET['nature'] = 'decor';

require __DIR__ . '/races.php';
