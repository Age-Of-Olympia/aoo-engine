<?php
namespace Classes;

class WarSchool
{
    private $trainer;          // l'entraîneur


    public function __construct($trainer)
    {
        $this->trainer = $trainer;
    }


    public function hasTrainer(): bool
    {
        return $this->trainer !== null;
    }


    public function getTrainer()
    {
        return $this->trainer;
    }

    /**
     * Vérification d’accès à l’école de guerre
     * null = OK / string = erreur
     */
    public static function checkAccess(Player $player, Player $potentialTrainer): ?string
    {
        if (!$potentialTrainer->have_option('isTrainer')) {
            return 'error not trainer';
        }

        // distance
        $distance = View::get_distance(
            $player->getCoords(),
            $potentialTrainer->getCoords()
        );

        if ($distance > 1) {
            return ERROR_DISTANCE;
        }

        // états incompatibles
        if ($player->haveEffect('adrenaline')) {
            return 'Vous ne pouvez pas vous entraîner sous l’adrénaline du combat.';
        }

        if ($potentialTrainer->haveEffect('adrenaline')) {
            return 'Cet entraîneur n’est pas en état d’enseigner.';
        }

        return null;
    }
}
