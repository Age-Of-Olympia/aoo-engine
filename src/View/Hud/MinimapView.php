<?php

namespace App\View\Hud;

use App\Service\ViewService;
use Classes\Db;
use Classes\Player;
use Classes\Str;

/**
 * Case minimap du HUD (coin bas-gauche de la grille).
 *
 * Réutilise les couches PNG pré-générées (ViewService::getGlobalMap /
 * getLocalMap — simples glob, aucun rendu GD) : carte du monde sur le
 * plan monde, carte locale ailleurs. Les couches joueurs GD sont
 * volontairement exclues ; la position du joueur est un marqueur CSS
 * placé via ViewService::getPositionPercent(). Le bloc est un repère
 * cliquable vers map.php, pas une carte temps réel.
 */
final class MinimapView
{
    /** Couches statiques empilées, dans l'ordre de dessin de map.php. */
    private const LOCAL_LAYERS = ['tiles', 'elements', 'walls', 'routes'];
    private const WORLD_LAYERS = ['tiles', 'elements', 'locations', 'routes'];

    public static function render(Player $player): void
    {
        $coords = $player->getCoords();

        ob_start();

        echo '<div id="hud-minimap">';

        try {
            $planJson = json()->decode('plans', $coords->plan);
            if (!is_object($planJson)) {
                throw new \RuntimeException('plan sans carte');
            }

            /* Certains JSON de plan n'ont pas de champ id : le slug du
             * plan sert alors d'identifiant, comme pour le nommage des
             * couches PNG (local_{plan}_{z}_{couche}_*.png). */
            $planId = $planJson->id ?? $coords->plan;

            $viewService = new ViewService(new Db(), $coords->x, $coords->y, $coords->z, $player->id, $planId);

            if ($viewService->isWorldPlan()) {
                $mapResult = $viewService->getGlobalMap();
                $layers = self::WORLD_LAYERS;
                $href = 'map.php?world';
            } else {
                $mapResult = $viewService->getLocalMap();
                $layers = self::LOCAL_LAYERS;
                $href = 'map.php?local=1';
            }

            $imgs = '';
            $aspect = '';
            foreach ($layers as $layer) {
                $imagePath = $mapResult[$layer]['imagePath'] ?? null;
                if ($imagePath && file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath)) {
                    /* Ratio pris sur la première couche : le wrapper doit
                     * avoir la proportion exacte de l'image pour que le
                     * marqueur en pourcentages tombe juste (pas de
                     * letterbox object-fit). */
                    if ($aspect === '') {
                        [$w, $h] = getimagesize($_SERVER['DOCUMENT_ROOT'] . $imagePath);
                        $aspect = (string) round($w / max(1, $h), 4);
                    }
                    $imgs .= '<img src="' . $imagePath . '" alt="" />';
                }
            }

            if ($imgs !== '') {
                $marker = '';
                $pos = $viewService->getPositionPercent();
                if ($pos !== null) {
                    $marker = '<span class="hud-minimap-me" title="Vous êtes ici" style="left: '
                        . round($pos['x'], 2) . '%; top: ' . round($pos['y'], 2) . '%;"></span>';
                }

                /* data-ratio : js/hud.js dimensionne le wrapper pour tenir
                 * dans la case en conservant la proportion de l'image, afin
                 * que le marqueur en pourcentages tombe juste. */
                echo '<a href="' . $href . '" class="hud-minimap-map" data-ratio="' . $aspect . '"'
                    . ' title="Ouvrir la carte de ' . $planJson->name . '">'
                    . $imgs . $marker . '</a>';

                /* Nom du lieu sous la carte : seul repère de
                 * localisation sur mobile, où la barre de statut
                 * masque #hud-location. */
                echo '<div class="hud-minimap-caption">' . $planJson->name . '</div>';
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
