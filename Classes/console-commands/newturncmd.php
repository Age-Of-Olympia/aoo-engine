<?php
use Classes\Command;
use Classes\Argument;
use Classes\Db;
use Classes\Player;

class NewTurnCmd extends Command
{
    public function __construct() {
        parent::__construct("new_turn",[new Argument('real',true)]);
        parent::setDescription(<<<EOT
déclenche un nouveau tour pour la session en cours. l'argument real permet de simuler un vrai tour avec non réinitialisation de l'anti-berserk. 
Exemple:
> new_turn
> new_turn real
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        $player = new Player($_SESSION['playerId']);

        if(isset($argumentValues[0]) && $argumentValues[0] == 'real'){
            $sql = 'UPDATE players SET nextTurnTime = ? WHERE id = ?';
        }
        else
        {
            $sql = 'UPDATE players SET nextTurnTime = ?, lastActionTime = 0, antiBerserkTime = 0 WHERE id = ?';
        }
        $db = new Db();

        $db->exe($sql, array(time(), $player->id));

        $player->refresh_data();

        return 'New turn for player '. $player->id. ' done';
    }
}
