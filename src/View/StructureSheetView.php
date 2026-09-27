<?php

namespace App\View;

use App\Entity\Structure;
use App\Service\BuildingService;
use App\Service\RaceService;
use App\View\Entity\EntityParts;
use App\View\Entity\EntityProfile;
use Classes\Player;
use Classes\Ui;

/**
 * Fiche d'une STRUCTURE (bâtiment, objet unique) pour infos.php /
 * load_infos.php — le pendant de InfosSheetView pour la branche
 * structure de l'arbre STI, qui sortait « error target id ».
 *
 * Un ÉDIFICE ouvert porteur d'un dialogue le présente FAÇON MARCHAND
 * (Ui::get_dialog plein panneau : grand avatar + boîte de dialogue) —
 * même ergonomie que merchant.php/warschool.php. Fermé (endommagé,
 * en construction, en ruine ou volontairement), la fiche montre l'état
 * et « Fermé » à la place de la conversation
 * (BuildingService::closureReason, source unique de la règle).
 *
 * PORTÉE : la conversation exige d'être sur une case ADJACENTE au
 * bâtiment (distance Chebyshev <= 1, celle des 8 voisines) — même
 * mécanisme que le MDJ limité à la Perception dans InfosSheetView,
 * la garde est côté serveur : trop loin, le dialogue n'est pas rendu.
 */
final class StructureSheetView
{
    /**
     * @param bool $hudPanel rendu en fragment dans le panneau glissant
     *                       du HUD (load_infos.php) — pas de bouton
     *                       Retour, le panneau a sa propre fermeture.
     */
    public static function render(Player $player, Structure $entity, bool $hudPanel = false): void
    {
        $profile = EntityProfile::of($player, (int) $entity->getId());
        $target = $profile->target;
        $details = $profile->details;
        $closure = $profile->closure;
        $typeLabel = $profile->typeLabel();
        $race = (new RaceService())->getRaceByName($entity->getRace());

        ob_start();

        // Le HUD titre ses panneaux d'après l'URL (infos → « Personnage ») ;
        // ce marqueur lui fait dire « Structure » pour cette fiche (js/hud.js).
        echo '<div hidden data-panel-title="Structure"></div>';

        if (!$hudPanel) {
            echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';
        }

        echo '
        <table border="1" align="center" cellspacing="0" class="marbre" style="width: 100%;">
        <tr>
            <td width="210" class="infos-portrait" valign="top">
                <div style="position: relative; display: inline-block;">
                    <img src="' . $profile->portraitUrl() . '" style="max-width: 200px;" />
                    ' . Ui::get_pv_veil((int) $profile->pvPct, $race?->getWoundColor()) . '
                </div>
            </td>
            <td valign="top" style="text-align: left; padding: 10px;">
                <h2 style="margin-top: 0;">' . htmlspecialchars($entity->getName(), ENT_QUOTES, 'UTF-8') . '</h2>
                ' . ($typeLabel !== $entity->getName() ? '<p>' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</p>' : '') . '
                ';

        echo EntityParts::statusHtml($profile) . EntityParts::ownerHtml($profile);

        /* Its INSCRIPTION (players.text, the column of a character's
         * message): the creation text does not count, and out of reach
         * the sheet says there is something to read rather than falling
         * silent (EntityProfile::textHtml). */
        $text = $profile->textHtml();
        if ($text !== '') {
            echo '<p><sup>' . $text . '</sup></p>';
        }

        echo '
            </td>
        </tr>
        </table>
        ';

        // Conversation — façon marchand : plein panneau, grand avatar.
        // Garde de PORTÉE côté serveur : il faut être sur une case adjacente.
        if ($details !== null && $details->getDialog() !== '') {

            if ($closure !== null) {
                echo '<div class="building-status building-status--closed" style="margin: 14px auto; text-align: center;">'
                    . '<span class="building-status-door building-status-door--closed">Fermé'
                    . ($closure !== BuildingService::CLOSED_BY_HAND ? ' (' . $closure . ')' : '') . '</span>'
                    . '<span class="building-status-state">Personne ne répond.</span>'
                    . '</div>';
            } elseif (!$profile->visibility->isBeside((int) $entity->getId())) {
                echo '<div class="building-status" style="margin: 14px auto; text-align: center;">'
                    . '<span class="building-status-state">Il faut être directement à côté du bâtiment'
                    . ' pour pouvoir parler au tenancier.</span>'
                    . '</div>';
            } else {
                echo Ui::get_dialog($player, [
                    'name' => $entity->getName(),
                    'avatar' => $profile->portraitUrl(),
                    'dialog' => $details->getDialog(),
                    'text' => '',
                    'player' => $player,
                    'target' => $target,
                ]);
            }
        }

        echo \Classes\Str::minify(ob_get_clean());

        // Outside the minifier: the inventory component carries its own scripts.
        echo EntityParts::contentsHtml($profile);
    }
}
