<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use Db;

$db = new Db();

class PlayerService
{
    public function getPlainEmail(int $playerId): ?string
    {
        $res = null;
        $db = new Db();
        $sql = 'SELECT plain_mail FROM players WHERE id = ?';
        $res = $db->exe($sql, array($playerId));
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_object();
            $res = $row->plain_mail;
        }
        return $res;
    }
}
