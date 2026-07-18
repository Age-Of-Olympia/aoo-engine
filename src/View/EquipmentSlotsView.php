<?php

namespace App\View;

use Classes\Item;
use Classes\Str;

/**
 * Bandeau d'équipement porté — thème papier (option newHud).
 *
 * Rend les objets équipés d'un personnage en « alvéoles » : tuiles
 * parchemin fixes de 46px, creusées dans la feuille (ombre interne),
 * précédées du titre gravé « Équipement » (mêmes petites capitales
 * filetées que les sections de la vue de sélection). Utilisé par la
 * fiche de personnage en panneau (load_infos.php) et par la vue de
 * sélection du plateau sur écrans larges (observe.php + js/hud.js).
 *
 * Les <img> gardent la classe .infos-item et les data-* de la fiche
 * héritée : dans le panneau Personnage, js/infos.js continue d'ouvrir
 * l'aperçu de l'objet au clic ; dans le bandeau de sélection, sans
 * infos.js, elles sont inertes et le title suffit.
 */
final class EquipmentSlotsView
{
    /** Chaîne vide si le personnage ne porte rien. */
    public static function render(int $targetId): string
    {
        $itemList = Item::get_equiped_list($targetId);

        if (empty($itemList)) {

            return '';
        }

        ob_start();

        echo '<div class="equip-strip">';
        echo '<div class="equip-strip-title">Équipement</div>';
        echo '<div class="equip-slots">';

        foreach ($itemList as $row) {

            $item = new Item($row->id, $row);
            $item->get_data();

            $itemName = Item::get_formatted_name(ucfirst($item->data->name), $row);
            $caracs = implode(', ', Item::get_item_carac($item->data));
            $type = (!empty($item->data->type)) ? $item->data->type : '';

            /* Certains objets n'ont pas de vignette _mini : l'image
             * pleine existe toujours, l'alvéole la réduit. */
            $img = $item->data->mini;
            if (!is_file($img)) {
                $img = 'img/items/' . $item->row->name . '.webp';
            }

            /* get_item_carac renvoie du HTML (<font>, <del>…) : le
             * title natif l'afficherait littéralement. */
            $title = strip_tags($itemName);
            if ($caracs !== '') {
                $title .= ' — ' . strip_tags($caracs);
            }
            if (!empty($row->equiped)) {
                $title .= ' (' . $row->equiped . ')';
            }

            /* Usure (docs/design-items-instances.md §3.5) : jauge fine sous l'icône dès que la
             * ligne est une instance — brisé = fissure, caracs coupées. */
            $wearGauge = '';
            $slotClass = 'equip-slot';
            if (isset($row->durability, $row->durability_max) && (int) $row->durability_max > 0) {
                $durability = (int) $row->durability;
                $pct = max(0, min(100, (int) round($durability / (int) $row->durability_max * 100)));
                $level = $durability <= 0 ? 'broken' : ($pct < 20 ? 'low' : ($pct < 50 ? 'mid' : 'ok'));
                $title .= $durability <= 0
                    ? ' — BRISÉ'
                    : ' — durabilité ' . $durability . '/' . (int) $row->durability_max;
                if ($durability <= 0) {
                    $slotClass .= ' equip-slot--broken';
                }
                $wearGauge = '<span class="equip-wear equip-wear--' . $level . '">'
                    . '<span class="equip-wear-fill" style="width:' . $pct . '%"></span></span>';
            }

            echo '<span class="' . $slotClass . '">'
                . '<img
                    class="infos-item"
                    data-id="' . $row->id . '"
                    data-name="' . $itemName . '"
                    data-n="' . $row->n . '"
                    data-text="' . $item->data->text . '"
                    data-price="' . $item->data->price . '"
                    data-type="' . $type . '"
                    data-img="img/items/' . $item->row->name . '.webp"
                    data-caracs="' . htmlspecialchars($caracs, ENT_QUOTES) . '"
                    title="' . htmlspecialchars($title, ENT_QUOTES) . '"
                    alt="' . htmlspecialchars(strip_tags($itemName), ENT_QUOTES) . '"
                    src="' . $img . '" />'
                . $wearGauge
                . '</span>';
        }

        echo '</div></div>';

        return Str::minify(ob_get_clean());
    }
}
