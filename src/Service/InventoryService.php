<?php

namespace App\Service;

use Classes\Db;
use Classes\Player;
use Classes\Item;
use Classes\Log;
use App\Enum\EquipResult;


class InventoryService
{
    /**
     * @param int|null $instanceId instance PRÉCISE à jeter (clic sur une
     *        ligne d'instance — arme usée…) : elle descend au sol avec
     *        son identité via dropAt (entité posée au sol), là où la
     *        pile part en bourse de case (map_items)
     */
    public static function dropItem(Player $player, Item $item, ?int $instanceId = null)
    {
        if ($item->row->cursed) {

            echo '<div id="data">Objet Maudit!</div>';
            exit();
        }

        if ($instanceId !== null) {
            try {
                (new ItemInstanceService())->dropAt($instanceId, (int) $player->data->coords_id);
            } catch (\InvalidArgumentException $e) {
                echo '<div id="data">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
                exit();
            }

            $text = $player->data->name . ' a déposé ' . $item->data->name . '.';

            Log::put($player, $player, $text, type: 'use');
            // The bourse marker joins the boards: purge the cached views.
            \Classes\View::refresh_players_svg($player->getCoords());
            return;
        }

        if (!is_numeric($_POST['n']) || (int)$_POST['n'] < 1) {
            echo '<div id="data">Mauvais nombre</div>';
            exit();
        }
        $countToDrop=(int)$_POST['n'];
        $player->drop($item, $countToDrop);
        // The bourse marker joins the boards: purge the cached views.
        \Classes\View::refresh_players_svg($player->getCoords());


        $text = $player->data->name . ' a déposé ' . $item->data->name . ' x' . $countToDrop . '.';

        Log::put($player, $player, $text, type: 'use');
    }

    /** Un « Utiliser » peut équiper ou consommer. */
    public const USE_EQUIP = 'equip';
    public const USE_CONSUME = 'consume';

    /**
     * Ce que « Utiliser » ferait pour cet objet — null quand RIEN : un
     * consommable sans aucun bonus, tout objet sans usage. Source unique
     * du bouton (grisé, print_inventory et inventUi.js via
     * data-use-kind) ET du refus serveur (useItem) : un clic qui ne fait
     * rien n'existe plus.
     */
    public static function useKind(Item $item): ?string
    {
        if (!empty($item->data->emplacement)) {
            return self::USE_EQUIP;
        }
        if (($item->data->type ?? '') === 'consommable' && self::hasConsumablePayload($item->data)) {
            return self::USE_CONSUME;
        }

        return null;
    }

    /** Au moins un bonus/effet que la consommation appliquerait. */
    private static function hasConsumablePayload(object $data): bool
    {
        foreach (['pv', 'pm', 'mvt', 'a', 'ae', 'malus', 'pr', 'pf'] as $key) {
            if (!empty($data->$key)) {
                return true;
            }
        }

        return !empty($data->effet);
    }

    /**
     * @param int|null $instanceId instance PRÉCISE à équiper (clic sur
     *        une ligne d'instance) — transmis jusqu'à
     *        ItemInstanceService::equipCatalogItem
     */
    public static function useItem(Player $player, Item $item, ?int $instanceId = null, ?bool $clickedEquippedLine = null): void
    {
        /* détail de consommation : visible du seul buveur, via le
         * hiddenText des événements */
        $hiddenText = '';

        if (self::useKind($item) === null) {

            exit('Cet objet ne peut pas être utilisé.');
        }

        $text = $player->data->name . ' a utilisé ' . $item->data->name . '.';


        if (!empty($item->data->emplacement)) {


            $return = $player->equip($item, instanceId: $instanceId, clickedEquippedLine: $clickedEquippedLine);

            if ($return == EquipResult::Equip) {

                if ($player->getRemaining('ae') < 1) {


                    // undo equip
                    $player->equip($item);

                    exit('error ae');
                }

                $text = $player->data->name . ' a équipé ' . $item->data->name . '.';

                $ae = 1;
            } elseif ($return == EquipResult::Unequip) {

                $text = $player->data->name . ' a déséquipé ' . $item->data->name . '.';
            }
        } elseif ($item->data->type == 'consommable') {
            //cas des objets consommables :
            //coûte 1A pour être consommés

            //On verifie que le joueur a assez d'action
            if ($player->getRemaining('a') < 1) {

                exit('error a');
            }

            $details = self::applyConsumablePayload($player, $item);

            //on enlève l'action utilisée
            $player->putBonus(array('a' => -1));

            //on enlève un exemplaire de l'objet
            $item->add_item($player, -1);

            $text = $player->data->name . ' a consommé ' . $item->data->name . '.';
            if (!empty($details)) {

                $hiddenText = 'Effet : ' . implode(', ', $details) . '.';
            }

            //coût en Ae à 0
            $ae = 0;
        }


        // use ae
        if (!empty($ae)) {

            $player->putBonus(array('ae' => -$ae));
        }


        Log::put($player, $player, $text, type: 'use', hiddenText: $hiddenText);
    }

    /**
     * Applique la CHARGE d'un consommable (bonus pv/pm/mvt/a/ae, malus,
     * PR, PF, effets ±) sans toucher au coût ni à la pile — source unique
     * partagée entre le geste d'inventaire (useItem) et l'action
     * générique « consommer » (ApplyConsumableOutcomeInstruction).
     *
     * @return list<string> détail lisible de chaque effet appliqué
     *                      (les effets cachés — poison… — restent muets)
     */
    public static function applyConsumablePayload(Player $player, Item $item): array
    {
        $details = [];

        foreach ($item->data as $bonus => $qte) {

            switch ($bonus) {
                case "pv":
                case "pm":
                case "mvt":
                case "a":
                case "ae":
                    /* les colonnes à 0 (la fiche item les porte toutes)
                     * ne font rien : ni bonus, ni ligne de message */
                    if ((int) $qte === 0) {
                        break;
                    }
                    $player->putBonus([$bonus => $qte]);
                    $details[] = sprintf('%+d %s', $qte, $bonus === 'ae' ? 'Ae' : strtoupper($bonus));
                    break;

                case "malus":
                    if ((int) $qte === 0) {
                        break;
                    }
                    $player->put_malus($qte);
                    $details[] = sprintf('%+d malus', $qte);
                    break;

                case "pr":
                    if ((int) $qte === 0) {
                        break;
                    }
                    $player->put_pr($qte*COEFFICIENT_PR);
                    $details[] = sprintf('%+d PR', $qte*COEFFICIENT_PR);
                    break;

                case "pf":
                    if ((int) $qte === 0) {
                        break;
                    }
                    $player->put_pf($qte);
                    $details[] = sprintf('%+d PF', $qte);
                    break;

                case "effet":
                    //dans le json de l'objet, les effet sont dans un tableau du type ["-sang","poison"]
                    foreach ($qte as $effet) {
                        //supression d'un effet
                        if (str_starts_with($effet, '-')) {

                            $player->end_effect(str_replace("-", "", $effet));
                            $details[] = 'dissipe ' . str_replace("-", "", $effet);
                        }
                        //ajout d'un effet
                        else {
                            if ($player->effectService->isHidden($effet) || $effet == "poison" || $effet == "poison_magique") {

                                /* Les effets cachés (poison…) ne s'éteignent
                                 * pas d'eux-mêmes : il faut être soigné. Avec
                                 * les durées en tours, zéro veut dire
                                 * « terminé » — l'infini est explicite. */
                                $player->add_effect($effet, PlayerEffectService::DURATION_INFINITE);
                            } else {

                                $player->add_effect($effet, 1);
                                /* les effets cachés (poison…) restent
                                 * muets dans le message de retour */
                                $details[] = 'effet ' . $effet;
                            }
                        }
                    }
                    break;
            }
        }

        return $details;
    }
}
