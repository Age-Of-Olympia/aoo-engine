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
            $playerToCheck = new Player($_SESSION['playerId']);
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
            $playerToCheck = new Player($_SESSION['playerId']);
            if (!$playerToCheck->have_option('isSuperAdmin')) {
                exit('Action réservée aux super administrateurs');
            } else {
                $_SESSION['isSuperAdmin'] = true;
            }
        }
    }
}
