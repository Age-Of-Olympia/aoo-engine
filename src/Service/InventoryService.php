<?php

namespace App\Service;

use Classes\Db;
use Classes\Player;
use Classes\Item;
use Classes\Log;
use App\Enum\EquipResult;


class InventoryService
{
    public static function dropItem(Player $player, Item $item)
    {
        if ($item->row->cursed) {

            echo '<div id="data">Objet Maudit!</div>';
            exit();
        }
        if (!is_numeric($_POST['n']) || (int)$_POST['n'] < 1) {
            echo '<div id="data">Mauvais nombre</div>';
            exit();
        }
        $countToDrop=(int)$_POST['n'];
        $player->drop($item, $countToDrop);


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
    public static function useItem(Player $player, Item $item, ?int $instanceId = null): void
    {
        /* détail de consommation : visible du seul buveur, via le
         * hiddenText des événements */
        $hiddenText = '';

        if (self::useKind($item) === null) {

            exit('Cet objet ne peut pas être utilisé.');
        }

        $text = $player->data->name . ' a utilisé ' . $item->data->name . '.';


        if (!empty($item->data->emplacement)) {


            $return = $player->equip($item, instanceId: $instanceId);

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

            //ajout des bonus de l'objet consommé
            //(en notant chaque effet pour le message de retour au joueur)
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

                                    $player->add_effect($effet, 0);
                                } else {

                                    $player->add_effect($effet, ONE_DAY);
                                    /* les effets cachés (poison…) restent
                                     * muets dans le message de retour */
                                    $details[] = 'effet ' . $effet;
                                }
                            }
                        }
                        break;
                }
            }

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
}
