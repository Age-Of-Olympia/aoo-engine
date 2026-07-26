<?php

namespace App\View;

use App\Entity\Building;
use App\Entity\BuildingDetails;
use App\Entity\EntityManagerFactory;
use App\Entity\Structure;
use App\Service\BuildingService;
use App\Service\FactionService;
use App\Service\RaceService;
use Classes\Player;
use Classes\Str;
use Classes\Ui;
use Classes\View;

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
        $target = \App\Factory\PlayerFactory::legacy($entity->getId());
        $target->get_data();
        $target->get_caracs();

        $pvPct = ($target->caracs->pv > 0)
            ? (int) floor($target->getRemaining('pv') / $target->caracs->pv * 100)
            : 100;

        $race = (new RaceService())->getRaceByName($entity->getRace());
        $typeLabel = $race !== null ? $race->getLabel() : ucfirst($entity->getRace());

        $buildingService = new BuildingService();
        $details = $entity instanceof Building ? $buildingService->getDetails($entity->getId()) : null;
        $closure = $details !== null ? $buildingService->closureReason($details, $pvPct) : null;
        $isEdifice = (bool) $race?->isEdifice();

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
                    <img src="' . self::portraitOrInitials($entity) . '" style="max-width: 200px;" />
                    ' . Ui::get_pv_veil($pvPct, $race?->getWoundColor()) . '
                </div>
            </td>
            <td valign="top" style="text-align: left; padding: 10px;">
                <h2 style="margin-top: 0;">' . htmlspecialchars($entity->getName(), ENT_QUOTES, 'UTF-8') . '</h2>
                <p>' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</p>
                ';

        $stateLabels = [
            BuildingDetails::STATE_BUILT => 'Construit',
            BuildingDetails::STATE_CONSTRUCTION => 'En construction',
            BuildingDetails::STATE_RUIN => 'Ruine',
        ];

        if ($details !== null) {
            $stateLabel = $stateLabels[$details->getBuildState()] ?? ucfirst($details->getBuildState());

            echo '<div class="building-status'
                . ($isEdifice && $closure !== null ? ' building-status--closed' : '') . '">';
            if ($isEdifice) {
                echo $closure === null
                    ? '<span class="building-status-door building-status-door--open">Ouvert</span>'
                    : '<span class="building-status-door building-status-door--closed">Fermé'
                        . ($closure !== 'fermé volontairement' ? ' (' . $closure . ')' : '') . '</span>';
            }
            echo '<span class="building-status-state">' . $stateLabel . ' · PV ' . $pvPct . '%</span></div>';

            if ($details->getOwnerId() !== null) {
                $owner = \App\Factory\PlayerFactory::entity($details->getOwnerId());
                if ($owner !== null) {
                    echo '<p><small>Propriétaire : <a href="infos.php?targetId=' . $details->getOwnerId() . '">'
                        . htmlspecialchars($owner->getName(), ENT_QUOTES, 'UTF-8') . '</a></small></p>';
                }
            }

            $factionJson = $details->getFaction() !== ''
                ? (new FactionService())->getFactionData($details->getFaction())
                : null;
            if ($factionJson !== null && isset($factionJson->raFont)) {
                echo '<p><small>Faction : <a href="faction.php?faction=' . $details->getFaction() . '">'
                    . '<span class="ra ' . $factionJson->raFont . '"></span></a></small></p>';
            }
        }

        // Message du jour du bâtiment : texte libre saisi en admin, lu par
        // tous les observateurs. Même traitement que celui des joueurs —
        // mise en forme simple tolérée, le reste neutralisé.
        if (!empty($target->data->text)) {
            echo '<p><sup>' . Str::richText((string) $target->data->text) . '</sup></p>';
        }

        echo '
            </td>
        </tr>
        </table>
        ';

        /* Inscription — ce qui est ÉCRIT sur l'objet, à la place qu'occupe
         * le message du jour d'un personnage : c'est la même colonne
         * (players.text), et un bâtiment est une ligne de players.
         *
         * Hors de portée, on ne se tait PAS : dire qu'il y a quelque
         * chose d'illisible d'ici est une information ; ne rien afficher
         * ne se distingue pas d'un objet muet, et le joueur ne saura
         * jamais qu'il devait s'approcher. */
        $inscription = \App\Service\BuildingService::inscriptionOf($target);

        if ($inscription !== '') {

            $player->getCoords();
            $inscriptionCoords = $entity->getCoords(EntityManagerFactory::getEntityManager()->getConnection());
            $inscriptionDistance = $inscriptionCoords !== null
                ? View::get_distance($player->coords, $inscriptionCoords)
                : PHP_INT_MAX;

            $readableHere = ($details !== null && $details->isReadableFromAfar())
                || $inscriptionDistance <= 1;

            echo '<div class="building-inscription" style="margin: 14px auto; max-width: 34rem; text-align: center;">';

            if ($readableHere) {
                echo '<blockquote style="font-style: italic;">'
                    . \Classes\Str::richText($inscription) . '</blockquote>';
            } else {
                echo '<span class="building-status-state">Quelque chose est inscrit ici,'
                    . ' mais il faut s\'approcher pour le déchiffrer.</span>';
            }

            echo '</div>';
        }

        // Conversation — façon marchand : plein panneau, grand avatar.
        // Garde de PORTÉE côté serveur (même mécanisme que le MDJ limité
        // à la Perception) : il faut être sur une case adjacente.
        if ($details !== null && $details->getDialog() !== '') {

            $player->getCoords();
            $targetCoords = $entity->getCoords(EntityManagerFactory::getEntityManager()->getConnection());
            $distance = $targetCoords !== null
                ? View::get_distance($player->coords, $targetCoords)
                : PHP_INT_MAX;

            if ($closure !== null) {
                echo '<div class="building-status building-status--closed" style="margin: 14px auto; text-align: center;">'
                    . '<span class="building-status-door building-status-door--closed">Fermé'
                    . ($closure !== 'fermé volontairement' ? ' (' . $closure . ')' : '') . '</span>'
                    . '<span class="building-status-state">Personne ne répond.</span>'
                    . '</div>';
            } elseif ($distance > 1) {
                echo '<div class="building-status" style="margin: 14px auto; text-align: center;">'
                    . '<span class="building-status-state">Il faut être directement à côté du bâtiment'
                    . ' pour pouvoir parler au tenancier.</span>'
                    . '</div>';
            } else {
                echo Ui::get_dialog($player, [
                    'name' => $entity->getName(),
                    'avatar' => self::portraitOrInitials($entity),
                    'dialog' => $details->getDialog(),
                    'text' => '',
                    'player' => $player,
                    'target' => $target,
                ]);
            }
        }

        echo \Classes\Str::minify(ob_get_clean());
    }

    /**
     * Portrait de la structure, ou le même repli « initiales dans un
     * cadre » que le damier quand elle n'a pas de visuel (SVG, propre
     * à toute taille).
     */
    private static function portraitOrInitials(Structure $entity): string
    {
        $portrait = (string) $entity->getPortrait();

        if ($portrait !== '' && file_exists($portrait)) {
            return $portrait;
        }

        // Portrait figé vide à la conversion (migration sans img/) :
        // re-résolution par la race au rendu — même repli que le damier.
        $resolved = \App\Service\BuildingService::resolveAvatar((string) $entity->getRace());
        if ($resolved !== '') {
            return $resolved;
        }

        return \Classes\View::structureInitialsAvatar($entity->getName());
    }
}
