<?php

namespace App\View;

use App\Entity\Character;
use App\Entity\RealPlayer;
use App\Service\PlayerService;
use App\Service\RaceService;
use App\View\Entity\EntityParts;
use App\View\Entity\EntityProfile;
use Classes\Item;
use Classes\Player;
use Classes\Str;

/**
 * Corps de la fiche de personnage, extrait d'infos.php pour être
 * rendu soit en page complète (infos.php), soit en fragment dans le
 * panneau glissant du HUD (load_infos.php). Contenu déplacé tel quel,
 * seule l'enveloppe (Ui, validation) reste dans les contrôleurs.
 */
final class InfosSheetView
{
    /**
     * @param bool $hudPanel rendu en panneau glissant du HUD : l'équipement
     *                       passe en alvéoles compactes (EquipmentSlotsView)
     *                       au lieu de la table marbre pleine largeur.
     *                       La page complète (infos.php) est inchangée.
     */
    public static function render(Player $player, Character $targetEntity, bool $hudPanel = false): void
    {

        ob_start();

        echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


        echo '
        <table border="1" align="center" cellspacing="0" class="marbre" style="width: 100%;">
        <tr>
            <td width="210" class="infos-portrait" valign="top">
                ';


        $player->getCoords();

        /* PV veil, effects, factions and message come from the entity's
         * profile, the one the tile card reads too (EntityProfile). */
        $profile = EntityProfile::of($player, (int) $targetEntity->getId());

        $pvVeil = '';

        if ($profile->detailed) {

            $pvVeil = \Classes\Ui::get_pv_veil((int) $profile->pvPct, (new \App\Service\RaceService())->getRaceWoundColor($profile->target->data->race ?? null));

            echo '<div class="infos-effects">' . EntityParts::effectsListHtml($profile) . '</div>';
        }


        /* display:block sur l'image : en inline, la boîte de ligne
         * dépasse l'image de quelques pixels (jambage) et le voile
         * débordait sous le portrait. */
        echo '<div style="position: relative; display: inline-block;">'
            . '<img src="' . $targetEntity->getPortrait() . '" height="330" style="display: block;" />'
            . $pvVeil
            . '</div>';


        echo '
            </td>
            <td valign="top">
                ';


        echo '
                <div id="infos-player">
                    ';


        echo '<h1>' . $targetEntity->getName() . '</h1>';


        $raceJson = (new RaceService())->getRaceData($targetEntity->getRace());

        $pnjText = $targetEntity->getId() < 0 ? ' - PNJ' : '';
        // isInactive is runtime-computed (RealPlayer domain method from !384).
        // Only meaningful for real players.
        $isInactive = ($targetEntity instanceof RealPlayer)
            && $targetEntity->isInactive(new PlayerService($targetEntity->getId()));
        $inactifText = ($targetEntity->getId() > 0 && $isInactive) ? ' (inactif)' : '';

        echo '<div>' . $raceJson->name . $pnjText . $inactifText . ' - <a href="infos.php?targetId=' . $targetEntity->getId() . '&reputation">' . Str::get_reput(floor($targetEntity->getPr() / COEFFICIENT_PR)) . '</a> Rang ' . $targetEntity->getRank() . ' <span style="opacity: 0.6; font-size: 88%; white-space: nowrap;">· mat. ' . $targetEntity->getDisplayId() . '</span></div>';


        echo EntityParts::factionLinesHtml($profile);

        /* Dieu vénéré — sur sa propre fiche uniquement (la foi ne
         * regarde personne d'autre). Le dieu est un personnage : lien
         * vers sa fiche, bougie de l'action Vénérer en rappel. */
        if ($player->id == $targetEntity->getId() && $targetEntity->getGodId() != 0) {

            try {
                $god = \App\Factory\PlayerFactory::legacy($targetEntity->getGodId());
                $god->get_data(false);

                echo '<div class="infos-god">Vénère <a href="infos.php?targetId=' . $god->id . '">' . $god->data->name . '</a> <span style="font-size: 1.3em" class="ra ra-candle"></span></div>';
            } catch (\Throwable $e) {
                /* godId orphelin (dieu supprimé) : la fiche reste muette. */
            }
        }

        echo '<img src="' . $targetEntity->getAvatar() . '" />';


        $text = $profile->textHtml();


        /* hud-plaque : same speech plaque as the board card (css/hud.css) */
        echo '<div class="infos-text hud-plaque">' . $text . '</div>';

        echo '
                </div>
                ';


        echo '
                <div id="preview-item" style="display: none;">
                    <h1></h1>
                    <div class="preview-img">
                        <img src="img/ui/fillers/150.png" />
                    </div>
                    <p class="preview-text"></p>
                    <p class="preview-caracs"></p>
                </div>
                ';


        echo '
            </td>
        </tr>
        ';


        if ($profile->detailed) {


            if ($hudPanel) {

                echo '
                <tr>
                    <td colspan="2">
                        ' . EquipmentSlotsView::render($targetEntity->getId()) . '
                    </td>
                </tr>
                ';
            } else {

            echo '
            <tr>
                <td colspan="2">
                    ';

            echo '
                    <table align="center" border="1" class="marbre" cellspacing="0">
                        <tr>
                            ';

            // Item::get_item_list already accepts int id (legacy signature branches on is_numeric),
            // so pass the entity's id directly instead of the legacy object.
            $itemList = Item::get_equiped_list($targetEntity->getId());

            $strikeByItem = (new \App\Service\ItemEffectService())->mapForItems(array_column($itemList, 'id'));

            foreach ($itemList as $row) {


                $item = new Item($row->id, $row);
                $item->get_data();


                $itemName = Item::get_formatted_name(ucfirst($item->data->name), $row);
                $caracs = implode(', ', Item::get_item_carac($item->data, $strikeByItem[(int) $row->id] ?? []));

                $type = (!empty($item->data->type)) ? $item->data->type : '';


                echo '<td><img
                                class="infos-item"
                                data-id="' . $row->id . '"
                                data-name="' . $itemName . '"
                                data-n="' . $row->n . '"
                                data-text="' . $item->data->text . '"
                                data-price="' . $item->data->price . '"
                                data-type="' . $type . '"
                                data-img="img/items/' . $item->row->name . '.webp"
                                data-caracs="' . htmlspecialchars($caracs, ENT_QUOTES) . '"
                                src="' . $item->data->mini . '" /></td>';
            }

            echo '
                        </tr>
                    </table>
                    ';

            echo '
                </td>
            </tr>
            ';
            }
        }

        /* Sur sa propre fiche : l'histoire s'édite ici (l'entrée du
         * Profil du HUD a été retirée — retours joueurs juillet 2026).
         * account.php?story s'ouvre en panneau via js/hud.js, en page
         * complète dans l'habillage hérité. */
        $storyEdit = ($player->id == $targetEntity->getId())
            ? ' <a href="account.php?story"><button><span class="ra ra-quill-ink"></span> Modifier</button></a>'
            : '';

        echo '
        <tr>
            <td colspan="2" align="left">

                <h2>Histoire:' . $storyEdit . '</h2>

                <div class="infos-story hud-plaque">' . Str::richText($targetEntity->getStory()) . '</div>
            </td>
        </tr>
        </table>
        ';

        echo Str::minify(ob_get_clean());

        echo '<script src="js/infos.js?v=20250529"></script>';
    }
}
