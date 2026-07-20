<?php

namespace App\View\Observe;

use App\Factory\PlayerFactory;
use Classes\Db;
use Classes\Item;
use Classes\Player;
use Classes\Str;
use Classes\Ui;

/**
 * Carte d'un MUR de carte (map_resources) ou d'un AUTEL pour le panneau
 * d'observation. Depuis la conversion murs→entités (2026-07-19), les
 * map_resources restants sont les RESSOURCES (récoltable/épuisé,
 * indestructibles ici), les murs de tutoriel et l'AUTEL — seul
 * destructible restant (destroy.php ne vit plus que pour lui, il
 * mourra avec la refonte des autels).
 */
final class ResourceCardView
{
    /**
     * @param \mysqli_result $res lignes map_resources de la case
     * @param int|string     $x   coordonnées observées (script destroy)
     * @param int|string     $y
     *
     * @return string la carte à échoer en fin de panneau ('' si aucune)
     */
    public static function render(Player $player, \mysqli_result $res, $x, $y): string
    {
        $card = '';
        $wallId = 0;

        while ($row = $res->fetch_object()) {
            $wallId = $row->id;

            echo '
            <div class="case-infos">
                <img src="img/walls/' . $row->name . '.png" title="#' . $row->id . '"/>

                <div class="text">
                    Structure non-passable.<br />
                    ';

            echo self::wallStatus($row->name, (int) $row->damages);
            echo '<br />';
            echo self::resourceStatus((int) $row->damages);

            $altarCard = self::altarCard($player, (int) $row->coords_id);

            echo '
                </div>
            </div>
            ';

            // L'autel garde la priorité ; sinon la première carte posée reste.
            if ($altarCard !== '') {
                $card = $altarCard;
            } elseif ($card === '') {
                $card = self::wallCard($row->name, (int) $row->damages);
            }
        }

        echo '
        <script>
        var $wall = $(\'#resources' . $wallId . '\');
        var x = ' . $x . ';
        var y = ' . $y . ';
        </script>
        <script src="js/observe_destroy.js?v=20260715"></script>
        ';

        return $card;
    }

    /** « Destructible (état). » ou « Indestructible. » selon RESOURCES_PV. */
    private static function wallStatus(string $wallName, int $damages): string
    {
        if (!empty(RESOURCES_PV[$wallName]) && RESOURCES_PV[$wallName] > 0) {
            return 'Destructible (' . Str::get_status($damages, RESOURCES_PV[$wallName]) . ').';
        }

        return 'Indestructible.';
    }

    /** État de ressource : -1 = récoltable, -2 = épuisée, sinon rien. */
    private static function resourceStatus(int $damages): string
    {
        if ($damages == -1) {
            return '<br /><span class="resource-status resource-harvestable" style="color:green;"><b>Récoltable.</b></span> <br />';
        }
        if ($damages == -2) {
            return '<br /><span class="resource-status resource-exhausted" style="color:red;"><b>Épuisée.</b></span> <br />';
        }

        return '';
    }

    /**
     * L'autel de la case, s'il y en a un : ligne dans le texte (échouée)
     * + sa carte avec le bouton Vénérer.
     *
     * @return string la carte de l'autel ('' si pas d'autel)
     */
    private static function altarCard(Player $player, int $coordsId): string
    {
        $res = (new Db())->exe('SELECT * FROM map_triggers WHERE name = "altar" AND coords_id= ?', $coordsId);
        if (!$res->num_rows) {
            return '';
        }

        $row = $res->fetch_object();

        $god = PlayerFactory::legacy($row->params);
        $god->get_data();

        echo 'Altar du Dieu ' . $god->data->name . '.';

        $actions = '';
        $dataText = 'Vous vénérez déjà ce Dieu.';

        if ($god->id != $player->data->godId) {
            $actions = '
            <button
                class="action"
                data-url="worship.php"
                data-action="worship"
                data-target-id="' . $row->id . '"
            ><span class="ra ra-candle"></span>
            <span class="action-name">Vénérer</span>
            </button><br/>';

            $dataText = 'Vénérez ce Dieu pour pouvoir lui adresser vos prières.';
        }

        return Ui::get_card((object) [
            'bg' => $god->data->portrait,
            'name' => '<a href="infos.php?targetId=' . $god->id . '">Altar du Dieu ' . $god->data->name . '</a>',
            'img' => $actions,
            'type' => 'Altar',
            'race' => 'dieu',
            'text' => $dataText,
        ]);
    }

    /**
     * La carte mutualisée du mur (Ui::get_card — LE composant de la
     * palissade et de l'autel) : nom du catalogue, voile de dégâts,
     * état brisé, description.
     */
    private static function wallCard(string $wallName, int $wallDamages): string
    {
        $wallBaseName = str_replace('_broken', '', $wallName);
        $isBroken = strpos($wallName, '_broken') !== false;

        $wallLabel = ucfirst(str_replace('_', ' ', $wallBaseName));
        $wallText = '';
        $wallCatalogItem = Item::get_item_by_name($wallBaseName);
        if ($wallCatalogItem) {
            $wallCatalogItem->get_data();
            $wallLabel = ucfirst(str_replace('_', ' ', $wallCatalogItem->data->name));
            $wallText = (string) ($wallCatalogItem->data->text ?? '');
        }

        $wallPvMax = (!empty(RESOURCES_PV[$wallName]) && RESOURCES_PV[$wallName] > 0) ? (int) RESOURCES_PV[$wallName] : 0;

        $data = (object) [
            'bg' => 'img/walls/' . $wallName . '.png',
            'name' => $wallLabel . ($isBroken ? ' — <font color="red">brisé</font>' : ''),
            'img' => '',
            'type' => 'Structure',
            'race' => 'common',
            'text' => self::wallStatus($wallName, $wallDamages)
                . ($wallText !== '' ? '<br /><sup>' . $wallText . '</sup>' : ''),
        ];

        if ($wallPvMax > 0) {
            $data->pvPct = max(0, (int) floor(($wallPvMax - $wallDamages) / $wallPvMax * 100));
        }

        return Ui::get_card($data);
    }
}
