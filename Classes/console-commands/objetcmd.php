<?php
use App\Service\ItemInstanceService;
use App\Service\UniqueObjectService;
use Classes\AdminCommand;
use Classes\Argument;
use Classes\Item;

class ObjetCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("objet", [
            new Argument('action', true),
            new Argument('item', true),
            new Argument('x', true),
            new Argument('y', true),
            new Argument('plan', true),
        ]);
        parent::setDescription(<<<EOT
Pose un objet unique sur la carte (instance neuve enveloppée dans une entité 'unique' — observable, ramassable, destructible).
Exemple:
> objet place [nom objet] [x] [y] [plan]  (plan par défaut : gaia)
> objet place gladius 2 -1
EOT);
    }

    public function execute(array $argumentValues): string
    {
        if (($argumentValues[0] ?? '') !== 'place' || !isset($argumentValues[1], $argumentValues[2], $argumentValues[3])) {
            return 'Utiliser : objet place [nom objet] [x] [y] [plan]';
        }

        $item = Item::get_item_by_name($argumentValues[1]);
        if (!$item) {
            return 'Objet inconnu au catalogue : ' . $argumentValues[1];
        }

        $goCoords = (object) [
            'x' => (int) $argumentValues[2],
            'y' => (int) $argumentValues[3],
            'z' => 0,
            'plan' => $argumentValues[4] ?? 'gaia',
        ];

        $admin = parent::getPlayer($_SESSION['playerId'] ?? 0);

        // Instance neuve créée au nom de l'admin puis posée aussitôt.
        $instanceId = (new ItemInstanceService())->create($admin->id, (int) $item->id, $admin->id);

        try {
            $id = (new UniqueObjectService())->placeInstance($instanceId, $goCoords);
        } catch (\InvalidArgumentException $e) {
            return 'Erreur : ' . $e->getMessage();
        }

        return 'Objet ' . $argumentValues[1] . ' posé (#' . $id . ', instance #' . $instanceId . ') en ('
            . $goCoords->x . ',' . $goCoords->y . ') sur ' . $goCoords->plan . '.';
    }
}
