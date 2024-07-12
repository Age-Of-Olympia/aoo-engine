<?php

class NewTurnCmd extends Command
{
    public function __construct() {
        parent::__construct("new_turn",[]);
        parent::setDescription(<<<EOT
déclenche un nouveau tour pour la session en cours 
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        $player = new Player($_SESSION['playerId']);

        $sql = 'UPDATE players SET nextTurnTime = ? WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array(time(), $player->id));

        $player->refresh_data();

        return 'New turn for player '. $player->id. ' done.';
    }
}
