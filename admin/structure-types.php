<?php
/**
 * Types de bâtiments (admin dashboard → Bâtiments → Types) : le second
 * VISAGE de la table races — les sortes « structure » (murs, statues,
 * coffres…), séparées des races de personnages pour ne plus confondre
 * peuples et murs (décision du 2026-07-19). Toute la mécanique vit dans
 * admin/races.php ; ce wrapper pose le mode avant de l'inclure.
 */
$_GET['kind'] = 'structure';

require __DIR__ . '/races.php';
