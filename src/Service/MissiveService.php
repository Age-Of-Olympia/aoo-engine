<?php

namespace App\Service;

use Classes\Db;
use Classes\Forum;
use Classes\Player;

class MissiveService
{
    public function sendNewMissive(int $authorId, array $destPlayersIds, string $title, string $message) {

        $player = new Player($authorId);

        $forumJson = json()->decode('forum', 'forums/Missives');

        $topJson = Forum::put_topic($player, $forumJson, $title,  $message);

        $db = new Db();
        
        //loop on all dest
        foreach ($destPlayersIds as $destId) {
            $values = array('player_id'=>$destId, 'name'=>$topJson->name);    
            $db->insert('players_forum_missives', $values);
        }

      
    }
}
