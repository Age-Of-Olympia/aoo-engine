<?php

namespace App\Enum;

/**
 * LA source unique des 16 clés de caractéristiques du jeu.
 *
 * Toute liste de caracs dans le code référence cette constante —
 * jamais de liste littérale (revue DRY 2026-07-18). La constante
 * globale CARACS (config/constants.php) reste la carte clé => libellé
 * d'affichage ; ses clés DOIVENT égaler Caracs::KEYS (épinglé par
 * tests/Various/CaracsSingleSourceTest).
 */
final class Caracs
{
    public const KEYS = [
        'a', 'mvt', 'p', 'pv', 'cc', 'ct', 'f', 'e',
        'agi', 'pm', 'fm', 'm', 'r', 'rm', 'spd', 'ae',
    ];
}
