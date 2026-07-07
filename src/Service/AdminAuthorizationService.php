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
            // check admin
            $playerToCheck = new Player(self::realPlayerId());
            if (!$playerToCheck->have_option('isAdmin')) {
                exit('Action réservée aux admin');
            } else {
                $_SESSION['isAdmin'] = true;
            }
        }
    }

    public static function DoSuperAdminCheck(): void
    {
        if (!isset($_SESSION['playerId'])) {
            exit();
        }

        // check super admin
        if (!isset($_SESSION['isSuperAdmin'])) {
            // check super admin
            $playerToCheck = new Player(self::realPlayerId());
            if (!$playerToCheck->have_option('isSuperAdmin')) {
                exit('Action réservée aux super administrateurs');
            } else {
                $_SESSION['isSuperAdmin'] = true;
            }
        }
    }

    /**
     * Les droits appartiennent à l'HUMAIN connecté, pas au personnage
     * incarné : un animateur qui a basculé sur un PNJ (pnjs.php,
     * session open) garde ses pouvoirs. Vérifier playerId rendait les
     * droits dépendants de l'ORDRE des actions — la première commande
     * admin lancée en incarnant un PNJ sans option refusait l'accès,
     * alors que la même commande lancée avant la bascule le mettait en
     * cache session pour toute la suite.
     *
     * originalPlayerId est posé au login (login.php) depuis la ligne
     * authentifiée — repli sur playerId pour les sessions antérieures.
     */
    private static function realPlayerId(): int
    {
        return (int) ($_SESSION['originalPlayerId'] ?? $_SESSION['playerId']);
    }
}
