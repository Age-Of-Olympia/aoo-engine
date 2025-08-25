<?php

namespace Tests\Action\Mock;

// Mock pour simuler la classe View statique
class ViewMock
{
    private static int $distance = 2;
    
    public static function setDistance(int $distance): void
    {
        self::$distance = $distance;
    }
    
    public static function get_distance($coords1, $coords2): int
    {
        // Calcul simple basé sur les coordonnées pour les tests
        $dx = abs($coords1->x - $coords2->x);
        $dy = abs($coords1->y - $coords2->y);
        return max($dx, $dy);
    }
    
    public static function get_walls_between($coords1, $coords2): void
    {
        // Mock - pas d'obstacles par défaut
    }
}

// Constants pour les tests si pas déjà définies
if (!defined('CARACS')) {
    define('CARACS', [
        'cc' => 'CC',
        'ct' => 'CT', 
        'f' => 'F',
        'e' => 'E',
        'agi' => 'AGI',
        'fm' => 'FM',
        'm' => 'M',
        'r' => 'R',
        'pm' => 'PM',
        'pv' => 'PV',
        'a' => 'A',
        'mvt' => 'MVT',
        'p' => 'P'
    ]);
}

if (!defined('EFFECTS_RA_FONT')) {
    define('EFFECTS_RA_FONT', [
        'adrenaline' => 'ra-heart',
        'paralysie' => 'ra-frozen-orb',
        'corruption_du_metal' => 'ra-rust'
    ]);
}