<?php
use App\Service\BuildingService;
use Classes\AdminCommand;
use Classes\Argument;

class BuildingCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("building", [
            new Argument('action', true),
            new Argument('type', true),
            new Argument('x', true),
            new Argument('y', true),
            new Argument('plan', true),
        ]);
        parent::setDescription(<<<EOT
Pose ou retire un bâtiment (ligne players de type 'building', PV portés par sa pseudo-race).
Exemple:
> building place [type] [x] [y] [z] [plan]  (z par défaut : 0, plan : gaia)
> building place palissade 3 -2
> building remove [id]
> building repair-avatars  (re-résout les avatars vides/cassés des structures — une conversion déployée tourne sans img/)
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

        if ($action === 'repair-avatars') {
            return $this->repairAvatars();
        }

        return "Action inconnue. Utiliser : building place [type] [x] [y] [z] [plan] | building remove [id] | building repair-avatars";
    }

    private function place(array $argumentValues): string
    {
        if (!isset($argumentValues[1], $argumentValues[2], $argumentValues[3])) {
            return 'Arguments manquants : building place [type] [x] [y] [z] [plan]';
        }

        $goCoords = (object) [
            'x' => (int) $argumentValues[2],
            'y' => (int) $argumentValues[3],
            'z' => (int) ($argumentValues[4] ?? 0),
            'plan' => $argumentValues[5] ?? 'gaia',
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

    /**
     * Re-résout l'avatar des structures dont il est vide ou pointe un
     * fichier absent : les CONVERSIONS déployées (migrations) tournent
     * depuis le checkout git, sans img/ — l'avatar y est figé vide et le
     * damier retombe sur les initiales. À lancer depuis le jeu (docroot,
     * img/ présent) après toute conversion de masse ; le damier
     * s'auto-répare aussi au rendu, ceci soigne tout d'un coup.
     */
    private function repairAvatars(): string
    {
        $db = new \Classes\Db();
        $res = $db->exe("SELECT id, race, avatar FROM players WHERE player_type IN ('building', 'unique')");

        $healed = 0;
        $bare = 0;
        while ($row = $res->fetch_object()) {
            if ($row->avatar !== '' && file_exists($row->avatar)) {
                continue;
            }
            $resolved = BuildingService::resolveAvatar((string) $row->race);
            if ($resolved === '') {
                $bare++;
                continue; // vraiment sans visuel : initiales au rendu, normal
            }
            $db->exe('UPDATE players SET avatar = ?, portrait = ? WHERE id = ?', array($resolved, $resolved, (int) $row->id));
            BuildingService::purgeEntityCaches((int) $row->id);
            $healed++;
        }

        return "Avatars réparés : {$healed} structure(s) ; sans visuel (initiales) : {$bare}.";
    }
}
