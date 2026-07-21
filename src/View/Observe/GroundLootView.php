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

        if ($loot['stacks'] === [] && $loot['instances'] === []) {
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

        /* Sa propre case : on est déjà dessus, marcher n'est pas une option —
         * bouton de ramassage direct (drop accidentel, plus besoin de sortir
         * puis revenir). Ailleurs : le rappel marche-dessus. */
        if ($x === (int) $player->coords->x && $y === (int) $player->coords->y) {
            /* action--direct : échappe au cycle en deux temps de
             * js/observe.js — un clic ramasse, point. */
            echo '<button class="action action--direct" id="pickup-own-tile">'
                . '<span class="ra ra-hand"></span> <span class="action-name">Ramasser</span></button>';
        } else {
            echo '<sup>Marchez sur la case pour ramasser.</sup>';
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

        return 'img/items/' . $name . '.webp';
    }
}
