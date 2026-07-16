<?php
use App\Service\BuildingService;
use Classes\AdminCommand;
use Classes\Argument;

class BuildingCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("building", [
            new Argument('action', true),
            new Argument('archetype', true),
            new Argument('x', true),
            new Argument('y', true),
            new Argument('plan', true),
        ]);
        parent::setDescription(<<<EOT
Pose ou retire un bâtiment (ligne players de type 'building', PV portés par sa pseudo-race).
Exemple:
> building place [archetype] [x] [y] [plan]  (plan par défaut : gaia)
> building place palissade 3 -2
> building remove [id]
EOT);
    }

    public function execute(array $argumentValues): string
    {
        $action = $argumentValues[0] ?? '';

        if ($action === 'place') {
            return $this->place($argumentValues);
        }

        if ($action === 'remove') {
            return $this->remove($argumentValues);
        }

        return "Action inconnue. Utiliser : building place [archetype] [x] [y] [plan] | building remove [id]";
    }

    private function place(array $argumentValues): string
    {
        if (!isset($argumentValues[1], $argumentValues[2], $argumentValues[3])) {
            return 'Arguments manquants : building place [archetype] [x] [y] [plan]';
        }

        $goCoords = (object) [
            'x' => (int) $argumentValues[2],
            'y' => (int) $argumentValues[3],
            'z' => 0,
            'plan' => $argumentValues[4] ?? 'gaia',
        ];

        try {
            $id = (new BuildingService())->place($argumentValues[1], $goCoords);
        } catch (\InvalidArgumentException $e) {
            return 'Erreur : ' . $e->getMessage();
        }

        return 'Bâtiment ' . $argumentValues[1] . ' #' . $id
            . ' posé en (' . $goCoords->x . ',' . $goCoords->y . ') sur ' . $goCoords->plan . '.';
    }

    private function remove(array $argumentValues): string
    {
        if (!isset($argumentValues[1]) || !is_numeric($argumentValues[1])) {
            return 'Arguments manquants : building remove [id]';
        }

        $id = (int) $argumentValues[1];

        if (!(new BuildingService())->remove($id)) {
            return 'Aucun bâtiment #' . $id . '.';
        }

        return 'Bâtiment #' . $id . ' retiré.';
    }
}
