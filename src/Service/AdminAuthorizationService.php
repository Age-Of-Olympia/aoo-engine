<?php

namespace App\Service;

use Classes\Player;

class AdminAuthorizationService
{
    public static function DoAdminCheck(): void
    {
        if (!isset($_SESSION['playerId'])) {
            exit();
        }

        // check admin (only once per session)
        if (!isset($_SESSION['isAdmin'])) {
            if (!self::sessionGrantsOption('isAdmin')) {
                exit('Action réservée aux admin');
            }
            $_SESSION['isAdmin'] = true;
        }
    }

    public static function DoSuperAdminCheck(): void
    {
        if (!isset($_SESSION['playerId'])) {
            exit();
        }

        // check super admin
        if (!isset($_SESSION['isSuperAdmin'])) {
            if (!self::sessionGrantsOption('isSuperAdmin')) {
                exit('Action réservée aux super administrateurs');
            }
            $_SESSION['isSuperAdmin'] = true;
        }
    }

    /**
     * Le droit est accordé si le PERSONNAGE INCARNÉ **ou** l'HUMAIN
     * connecté porte l'option.
     *
     * - Personnage incarné : le pouvoir se donne en jeu en confiant un
     *   PNJ porteur (les « oiseaux » : PNJ à id négatif avec
     *   isSuperAdmin) — l'incarner confère le pouvoir.
     * - Humain (originalPlayerId, posé au login) : un animateur qui a
     *   basculé sur un PNJ sans option garde ses propres droits.
     *   Sans cette branche, le droit dépendait de l'ORDRE des actions :
     *   la première commande admin lancée en incarnant un PNJ nu était
     *   refusée, la même commande lancée avant la bascule mettait le
     *   drapeau en cache session pour toute la suite.
     */
    private static function sessionGrantsOption(string $option): bool
    {
        $candidates = array_unique(array_filter([
            (int) $_SESSION['playerId'],
            (int) ($_SESSION['originalPlayerId'] ?? 0),
        ]));

        foreach ($candidates as $playerId) {
            if ((new Player($playerId))->have_option($option)) {
                return true;
            }
        }

        return false;
    }
}
