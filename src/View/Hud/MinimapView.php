<?php

namespace App\View\Hud;

use App\Service\ViewService;
use Classes\Db;
use Classes\Player;
use Classes\Str;

/**
 * Case minimap du HUD (coin bas-gauche de la grille).
 *
 * Réutilise les couches PNG pré-générées de la carte locale
 * (ViewService::getLocalMap — simple glob, aucun rendu GD). Les couches
 * joueurs (generateLocalPlayersLayer / generateLocalPlayerLayer) sont
 * volontairement exclues : elles déclenchent un rendu GD à chaque
 * affichage de page. La minimap est un simple repère cliquable vers
 * map.php, pas une carte temps réel.
 */
final class MinimapView
{
    /** Couches statiques empilées, dans l'ordre de dessin de map.php. */
    private const LAYERS = ['tiles', 'elements', 'walls', 'routes'];

    public static function render(Player $player): void
    {
        $coords = $player->getCoords();

        ob_start();

        echo '<div id="hud-minimap">';

        try {
            $planJson = json()->decode('plans', $coords->plan);
            if (!is_object($planJson) || empty($planJson->id)) {
                throw new \RuntimeException('plan sans carte locale');
            }

            $viewService = new ViewService(new Db(), $coords->x, $coords->y, $coords->z, $player->id, $planJson->id);
            $mapResult = $viewService->getLocalMap();

            $imgs = '';
            foreach (self::LAYERS as $layer) {
                $imagePath = $mapResult[$layer]['imagePath'] ?? null;
                if ($imagePath && file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath)) {
                    $imgs .= '<img src="' . $imagePath . '" alt="" />';
                }
            }

            if ($imgs !== '') {
                echo '<a href="map.php?local=1" class="hud-minimap-map" title="Ouvrir la carte de ' . $planJson->name . '">'
                    . $imgs . '</a>';
            } else {
                self::placeholder();
            }
        } catch (\Throwable $e) {
            self::placeholder();
        }

        echo '</div>';

        echo Str::minify(ob_get_clean());
    }

    private static function placeholder(): void
    {
        echo '<a href="map.php" class="hud-minimap-placeholder" title="Ouvrir la carte">'
            . '<span class="ra ra-scroll-unfurled"></span> Carte</a>';
    }
}
