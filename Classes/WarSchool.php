<?php
namespace Classes;

class WarSchool
{
    private $trainer;          // l'entraîneur
    private $disciplines = []; // cache des disciplines


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
     * Récupère les entraînements disponibles pour une discipline
     * ex : contact, distance, magic, spells, stealth, survival
     */
    public function getDiscipline(string $discipline): array
    {
        if (isset($this->disciplines[$discipline])) {
            return $this->disciplines[$discipline];
        }

        $allowed = [
            'melee',
            'range',
            'magic',
            'spells',
            'stealth',
            'survival'
        ];

        if (!in_array($discipline, $allowed)) {
            exit('error discipline');
        }

        $db = new Db();

        $sql = '
            SELECT *
            FROM warschool_training
            WHERE trainer_id = ?
              AND discipline = ?
            ORDER BY level ASC
        ';

        $res = $db->exe($sql, [$this->trainer->id, $discipline]);

        $return = [];

        while ($row = $res->fetch_object()) {
            $return[] = $row;
        }

        $this->disciplines[$discipline] = $return;

        return $return;
    }


    /**
     * Applique un entraînement au joueur
     */
    public function train(Player $player, object $training, int $quantity = 1): string
    {
        // coût total
        $totalCost = $training->price * $quantity;

        if ($player->data->gold < $totalCost) {
            return 'Or insuffisant.';
        }

        // vérification niveau requis
        if ($player->data->level < $training->required_level) {
            return 'Niveau insuffisant.';
        }

        // paiement
        $player->removeGold($totalCost);

        // gain
        $player->addXp(
            $training->skill,
            $training->xp * $quantity
        );

        return 'Entraînement effectué avec succès.';
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
