<?php

namespace App\Service;

use Db;
use Player;

class PlayerService
{
    private Db $db;
    private int $playerId;
    private $playerCache = [];

    public function __construct(int $playerId)
    {
        $this->playerId = $playerId;
        $this->db = new Db();
    }

    private function getPlayerField(string $field): mixed
    {
        $fields = $this->getPlayerFields([$field]);
        return $fields[$field] ?? null;
    }

    public function getPlainEmail(int $playerId): ?string
    {
        return $this->getPlayerField($playerId, 'plain_mail');
    }

    public function getEmailBonus(int $playerId): bool
    {
        return $this->getPlayerField($playerId, 'email_bonus') ?? false;
    }

    public function getPlayerFields(array $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        $sql = "SELECT " . implode(', ', $fields) . " FROM players WHERE id = ?";
        $res = $this->db->exe($sql, array($this->playerId));

        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_object();
            $result = [];
            foreach ($fields as $field) {
                $result[$field] = $row->$field ?? null;
            }
            return $result;
        }

        return array_fill_keys($fields, null);
    }

    /**
     * Helper function to calculate if a login time is considered inactive
     * @param int $lastLoginTime The last login time to check
     * @return bool True if inactive, false otherwise
     */
    public function isInactive(int $lastLoginTime): bool
    {
        $current_time = time();
        $inactive_threshold = $current_time - (INACTIVE_TIME);
        return $lastLoginTime < $inactive_threshold;
    }

    public function searchNonAnonymePlayer(string $searchKey): array
    {
        
        $sql = 'select players.name 
                from players
                left JOIN players_options on players_options.player_id=players.id and players_options.name = "anonymeMode"
                where players.name like ?
                and players_options.player_id is null
                ';

        $res = $this->db->exe($sql, '%'.$searchKey.'%');
        $list= array();

        while($row = $res->fetch_object()){
            $list[]=$row->name;
        }
        return $list;
    }

    public function GetPlayer($id, bool $readCache=true, bool $writeCache=true)
    {
        if($readCache && isset($this->playerCache[$id])){
            return $this->playerCache[$id];
        }
        $result = new Player($id);

        if($writeCache){
            $this->playerCache[$id] = $result;
        }

        return $result;
    }

    public function updateLastActionTime(): void {
        $sql = '
            UPDATE
            players
            SET
            lastActionTime = '. time() .'
            WHERE
            id = ?
            ';

        $this->db->exe($sql, $this->playerId);
    }
}
