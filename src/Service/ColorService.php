<?php

namespace App\Service;

class ColorService {
    public static function initializePastelColors(): array {
        // Pastel colors for terrains
        return [
            // Natural terrain
            'desert_de_l_egeon' => [247, 234, 215],  // Original: [238, 214, 175]
            'jungle_sauvage' => [188, 193, 203],     // Original: [121, 132, 151]
            'eryn_dolen' => [190, 204, 158],         // Original: [115, 153, 61]
            'foret_petrifiee' => [212, 212, 212],    // Original: [169, 169, 169]
            'monts_de_l_oubli' => [197, 196, 196],   // Original: [139, 137, 137]
            'cimes_geantes' => [135, 135, 135],      // Original: [105, 105, 105]
            'falaise' => [192, 192, 192],            // Original: [128, 128, 128]
            'lac_cenedril' => [167, 174, 168],       // Original: [79, 93, 81]
            'lac_thetis' => [231, 238, 243],         // Original: [206, 220, 231]
            'lac_pegasus' => [219, 197, 133],        // Original: [184, 134, 11]
            'archipel' => [127, 231, 232],           // Original: [0, 206, 209]
            'havres' => [190, 204, 158],             // Original: [218, 165, 32]
            'redora' =>[85, 85, 85],
            'cimetiere_des_betes_sacrees' => [165, 165, 165],
            'fort_turok' => [190, 185, 180],        // Original: [139, 69, 19]
            'sol_gris' => [226, 180, 131],            

            // Elements
            'boue' => [197, 162, 137],              // Original: [139, 69, 19]
            'eau' => [127, 180, 198],               // Original: [0, 105, 148]
            'lave' => [245, 60, 80],              // Original: [255, 69, 0]
            'ronce' => [145, 162, 145],             // Original: [34, 70, 34]
    
            // Roads and passages
            'route' => [152, 152, 152],             // Original: [50, 50, 50]
            'escalier_vers_le_bas' => [207, 161, 137], // Original: [160, 82, 45]
            'escalier_vers_le_haut' => [207, 161, 137], // Original: [160, 82, 45]
            'echelle' => [197, 162, 137],           // Original: [139, 69, 19]
            'carreaux' => [223, 223, 223],          // Original: [192, 192, 192]
    
            // Walls
            "arbre1" => [140, 100, 90],   // Soft Bark Brown
            "arbre2" => [150, 165, 190],  // Dusty Indigo
            "arbre3" => [195, 190, 145],  // Soft Olive
            "arbre4" => [200, 230, 235],  // Frosted Sky
            "arbre5" => [240, 245, 240],  // Snow Veil

            // Dungeons and caves
            'caverne' => [197, 196, 196],           // Original: [72, 61, 139]
            'mines' => [151, 164, 164],             // Original: [47, 79, 79]
            'faille_naine' => [140, 140, 183],      // Original: [25, 25, 112]
            'pit' => [127, 127, 127],               // Original: [0, 0, 0]
            'enfers' => [197, 127, 127],            // Original: [139, 0, 0]
    
            // Settlements and structures
            'campement_redoraan' => [234, 225, 213], // Original: [213, 194, 171]
            'praetorium' => [219, 197, 133],        // Original: [184, 134, 11]
            'manoir_tiroloin' => [197, 162, 137],   // Original: [139, 71, 38]
            'taverne_d_olympia' => [234, 225, 213],
            'barge_stellaire' => [70, 130, 180],
            'fefnir' => [178, 34, 34],
            
            // Lieux spéciaux
            'arbre_sacre-02' => [85, 107, 47],
            'banniere_velue' => [192, 192, 192],
            
            // Runes
            'rune1' => [148, 0, 211],
            'rune3' => [148, 0, 211],
            'rune4' => [148, 0, 211],
            'rune10' => [148, 0, 211],
            'rune11' => [148, 0, 211],
            'rune16' => [148, 0, 211],
            
            // Zones de butin
            'loot' => [255, 215, 0],
            
            // Couleur par défaut pour les types inconnus
            'default' => [100, 100, 100]
        ];
    }

    public static function initializeColors(): array {
        // Couleurs de base pour les terrains
        return [
            // Terrain naturel
            'desert_de_l_egeon' => [238, 214, 175],
            'jungle_sauvage' => [121, 132, 151],
            'eryn_dolen' => [115, 153, 61],
            'foret_petrifiee' => [169, 169, 169],
            'monts_de_l_oubli' => [139, 137, 137],
            'cimes_geantes' => [105, 105, 105],
            'falaise' => [128, 128, 128],
            'lac_cenedril' => [79, 93, 81],
            'lac_thetis' => [206, 220, 231],
            'lac_pegasus' => [184, 134, 11],
            'archipel' => [0, 206, 209],

            // Éléments
            'boue' => [139, 69, 19],
            'eau' => [0, 105, 148],
            'lave' => [255, 69, 0],
            'ronce' => [34, 70, 34],

            // Routes et passages
            'route' => [50, 50, 50],
            'escalier_vers_le_bas' => [160, 82, 45],
            'escalier_vers_le_haut' => [160, 82, 45],
            'echelle' => [139, 69, 19],
            'carreaux' => [192, 192, 192],
            
            // Donjons et grottes
            'caverne' => [72, 61, 139],
            'mines' => [47, 79, 79],
            'faille_naine' => [25, 25, 112],
            'pit' => [0, 0, 0],
            'enfers' => [139, 0, 0],
            
            // Établissements et structures
            'havres' => [218, 165, 32],
            'fort_turok' => [139, 69, 19],
            'campement_redoraan' => [213, 194, 171],
            'praetorium' => [184, 134, 11],
            'manoir_tiroloin' => [139, 71, 38],
            'taverne_d_olympia' => [205, 149, 12],
            'barge_stellaire' => [70, 130, 180],
            'redora' => [205, 92, 92],
            'fefnir' => [178, 34, 34],
            
            // Lieux spéciaux
            'cimetiere_des_betes_sacrees' => [105, 105, 105],
            'arbre_sacre-02' => [85, 107, 47],
            'banniere_velue' => [192, 192, 192],
            
            // Runes
            'rune1' => [148, 0, 211],
            'rune3' => [148, 0, 211],
            'rune4' => [148, 0, 211],
            'rune10' => [148, 0, 211],
            'rune11' => [148, 0, 211],
            'rune16' => [148, 0, 211],
            
            // Zones de butin
            'loot' => [255, 215, 0],
            
            // Couleur par défaut pour les types inconnus
            'default' => [100, 100, 100]
        ];
    }
}
