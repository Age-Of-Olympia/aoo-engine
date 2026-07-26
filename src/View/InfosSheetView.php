<?php

namespace App\View;

use App\Entity\EntityManagerFactory;
use App\Entity\Character;
use App\Entity\RealPlayer;
use App\Service\FactionService;
use App\Service\PlayerEffectService;
use App\Service\PlayerService;
use App\Service\RaceService;
use Classes\Item;
use Classes\Player;
use Classes\Str;
use Classes\View;

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
        $playerEffectService = new PlayerEffectService();

        ob_start();

        echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


        echo '
        <table border="1" align="center" cellspacing="0" class="marbre" style="width: 100%;">
        <tr>
            <td width="210" class="infos-portrait" valign="top">
                ';


        $caracsJson = $player->get_caracsJson();

        $player->getCoords();

        // Target coords via entity. Shape-compatible with
        // View::get_distance which only reads ->x / ->y / ->plan.
        $conn = EntityManagerFactory::getEntityManager()->getConnection();
        $targetCoords = $targetEntity->getCoords($conn);

        $distance = View::get_distance($player->coords, $targetCoords);

        /* Voile de sang sur le portrait : même règle de perception que
         * les effets, le mdj et l'équipement ci-dessous — soi-même ou
         * une cible à portée (retours joueurs juillet 2026). */
        $pvVeil = '';

        if (
            $player->id == $targetEntity->getId()
            ||
            $distance <= $caracsJson->p
        ) {

            $target = \App\Factory\PlayerFactory::legacy($targetEntity->getId());
            $target->get_data();
            $target->get_caracs();

            $pvPct = ($target->caracs->pv > 0)
                ? (int) floor($target->getRemaining('pv') / $target->caracs->pv * 100)
                : 100;

            $pvVeil = \Classes\Ui::get_pv_veil($pvPct, (new \App\Service\RaceService())->getRaceWoundColor($target->data->race ?? null));

            echo '<div class="infos-effects">';

            $playerEffects = $playerEffectService->getEffectsByPlayerId($targetEntity->getId());
            $effectService = new \App\Service\EffectService();

            foreach ($playerEffects as $effect) {

                if ($effectService->isHidden($effect->getName())) {

                    continue;
                }

                if (
                    $targetEntity->getId() == $player->id
                    ||
                    $targetEntity->getFaction() == $player->data->faction
                    ||
                    $targetEntity->getSecretFaction() == $player->data->secretFaction
                ) {

                    $endTime = PlayerEffectService::describeRemaining($effect->getEndTime());
                } else {

                    $endTime = '';
                }

                echo '<a href="https://age-of-olympia.net/wiki/doku.php?id=regles:effets#' . $effect->getName() . '"><span class="ra ' . $effectService->getIcon($effect->getName()) . '"></span><span style="font-size: 88%;">(' . $effect->getValue() . ') ' . $endTime . '</span></a><br />';
            }

            echo '</div>';
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


        $factionJson = (new FactionService())->getFactionData($targetEntity->getFaction());

        echo '<div><a href="faction.php?faction=' . $targetEntity->getFaction() . '">' . $factionJson->name . '</a> <span style="font-size: 1.3em" class="ra ' . $factionJson->raFont . '"></span> (<i>' . $factionJson->role[$targetEntity->getFactionRole()]->name . '</i>) </div>';

        $targetSecretFaction = $targetEntity->getSecretFaction();
        if (!empty($targetSecretFaction) && ($player->data->secretFaction == $targetSecretFaction || $player->have_option('isAdmin'))) {
            $secretFactionJson = (new FactionService())->getFactionData($targetSecretFaction);

            echo '<div class="secret-faction"><a href="faction.php?faction=' . $targetSecretFaction . '">' . $secretFactionJson->name . '</a> <span style="font-size: 1.3em" class="ra ' . $secretFactionJson->raFont . '"></span> (<i>' . $secretFactionJson->role[$targetEntity->getSecretFactionRole()]->name . '</i>) </div>';
        }

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


        /* Texte libre du joueur : mise en forme simple tolérée, tout le
         * reste neutralisé (Str::richText). Il était rendu brut. */
        $text = Str::richText($targetEntity->getText());

        if ($player->id != $targetEntity->getId() && $distance > $caracsJson->p) {

            $text = '<i>Ce personnage est trop éloigné pour l\'entendre parler.</i>';
        }


        echo '<div class="infos-text">' . $text . '</div>';

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


        if ($player->coords->plan == $targetCoords->plan && $distance <= $caracsJson->p) {


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

            foreach ($itemList as $row) {


                $item = new Item($row->id, $row);
                $item->get_data();


                $itemName = Item::get_formatted_name(ucfirst($item->data->name), $row);
                $caracs = implode(', ', Item::get_item_carac($item->data));

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

                ' . Str::richText($targetEntity->getStory()) . '
            </td>
        </tr>
        </table>
        ';

        echo Str::minify(ob_get_clean());

        echo '<script src="js/infos.js?v=20250529"></script>';
    }
}
