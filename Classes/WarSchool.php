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
        // Le rôle n'est plus une option de personne : un BÂTIMENT enseigne
        // parce que son dialogue mène à l'école.
        $buildingService = new \App\Service\BuildingService();
        if (!$buildingService->servesCounter((int) $potentialTrainer->id, 'warschool.php')) {
            return 'error not trainer';
        }

        // Fermée, l'école n'enseigne à personne — même règle de fermeture
        // unique que le comptoir du marchand.
        $closedNotice = $buildingService->closedCounterNotice($potentialTrainer);
        if ($closedNotice !== null) {
            return $closedNotice;
        }

        // distance à la case la plus proche : une bâtisse multi-cases
        // enseigne par chacun de ses côtés (point déclaré pour un personnage)
        $distance = View::get_distance_to_entity(
            $player->getCoords(),
            (int) $potentialTrainer->id,
            $potentialTrainer->getCoords()
        );

        if ($distance > 1) {
            return ERROR_DISTANCE;
        }

        // États incompatibles (catalogue : blocks_trading, ex-adrénaline)
        $effectService = new \App\Service\EffectService();
        if ($blocker = $effectService->tradingBlocker($player->getEffects())) {
            return 'Vous ne pouvez pas apprendre de nouvelles techniques sous l’effet « ' . $blocker->getLabel() . ' ».';
        }

        if ($effectService->tradingBlocker($potentialTrainer->getEffects()) !== null) {
            return 'Cet entraîneur n’est pas en état d’enseigner.';
        }

        return null;
    }
}
