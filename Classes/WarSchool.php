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
