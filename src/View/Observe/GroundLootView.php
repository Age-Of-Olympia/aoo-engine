<?php

namespace App\View\Observe;

use App\Service\GroundLootService;
use Classes\Player;

/**
 * Bourse au sol d'une case (piles map_items + instances) pour le
 * panneau d'observation — extrait d'observe.php (ménage n°2), données
 * via GroundLootService::listAt (le pendant lecture de collect()).
 *
 * Sur SA PROPRE case, un bouton Ramasser direct (géré par
 * js/observe.js) ; ailleurs, le rappel marche-dessus.
 */
final class GroundLootView
{
    public static function render(Player $player, int $x, int $y, object $coords): void
    {
        $loot = (new GroundLootService())->listAt($x, $y, (int) $coords->z, (string) $coords->plan);

        if ($loot['stacks'] === [] && $loot['instances'] === [] && $loot['plants'] === []) {
            return;
        }

        echo '<div class="case-infos">';
        echo '<img src="img/tiles/loot.png" title="Bourse" />';
        echo '<div class="text"><b>Au sol :</b><br />';

        foreach ($loot['stacks'] as $row) {

            $groundItem = new \Classes\Item($row->item_id);
            $groundItem->get_data();

            echo '<img src="'. self::mini((string) $row->name, (string) ($groundItem->data->mini ?? '')) .'" style="max-height:22px;vertical-align:middle;" alt="" /> '
                . $groundItem->data->name .' x'. (int) $row->n .'<br />';
        }

        foreach ($loot['instances'] as $row) {

            $label = $row->custom_name !== ''
                ? '« '. htmlspecialchars($row->custom_name, ENT_QUOTES, 'UTF-8') .' » ('. ucfirst($row->name) .')'
                : ucfirst($row->name);

            $state = \App\Service\ItemInstanceService::isBroken((int) $row->durability)
                ? ' — <font color="red"><b>brisé</b></font>'
                : ' — durabilité '. (int) $row->durability .'/'. (int) $row->durability_max;

            echo '<img src="'. self::mini((string) $row->name, 'img/items/'. $row->name .'_mini.webp') .'" style="max-height:22px;vertical-align:middle;" alt="" /> '
                . $label . $state .'<br />';
        }

        /* Les plantes se montrent avec le reste : on ne les cueille plus en
         * marchant, il faut donc les VOIR pour penser à les prendre. Ce
         * qu'elles rendent est tiré au sort à la cueillette, d'où l'absence
         * de quantité ici. */
        foreach ($loot['plants'] as $row) {

            echo '<img src="'. self::mini((string) $row->name, 'img/plants/'. $row->name .'.png') .'" style="max-height:22px;vertical-align:middle;" alt="" /> '
                . ucfirst((string) $row->name) .' <sup>(à cueillir)</sup><br />';
        }

        /* Le bouton n'apparaît que sur SA case : ramasser demande d'être
         * dessus. Ailleurs, on dit où aller — et non plus « marchez dessus
         * pour ramasser », qui décrivait le ramassage automatique. */
        if ($x === (int) $player->coords->x && $y === (int) $player->coords->y) {
            /* action--direct : échappe au cycle en deux temps de
             * js/observe.js — un clic ramasse, point. */
            echo '<button class="action action--direct" id="pickup-own-tile">'
                . '<span class="ra ra-hand"></span> <span class="action-name">Ramasser</span></button>';
        } else {
            echo '<sup>Allez sur la case pour pouvoir ramasser.</sup>';
        }

        echo '</div></div>';
    }

    /**
     * Vignette d'un objet : l'image préférée si elle existe, sinon le
     * repli img/items/{name}.webp — LA règle, partagée par les piles
     * et les instances (avant, deux copies divergentes).
     */
    private static function mini(string $name, string $preferred): string
    {
        if ($preferred !== '' && is_file($preferred)) {
            return $preferred;
        }

        // The exemplar chain: items art, walls sprite, initials frame.
        return \Classes\View::exemplarSprite($name, $name);
    }
}
