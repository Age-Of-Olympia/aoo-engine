<?php

namespace App\Service;

use App\View\OnHideReloadView;
use Classes\Db;
use Classes\Player;
use Classes\Log;


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
        return $this->getPlayerField( 'plain_mail');
    }

    public function getEmailBonus(int $playerId): bool
    {
        return $this->getPlayerField( 'email_bonus') ?? false;
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

    public function getAllPlayers(): array
    {
        $sql = "SELECT * FROM players ORDER BY name ASC";
        $db = new Db();
        $result = $db->exe($sql);
        
        $players = [];
        while ($row = $result->fetch_assoc()) {
            $players[] = $row;
        }
        
        return $players;
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

    public function getNumberOfSpellAvailable() : int{
        $player = $this->GetPlayer($this->playerId);
        $spellList = $player->get_spells();
        $spellsN = count($spellList);
        $numberOfSpellsAvailable = $player->get_spells_available($spellsN);
        return $numberOfSpellsAvailable;
    }

    public static function ProcessTargetDeath(Player $player, Player $target): void
    {
        if ($target->getRemaining('pv') > 0) {

            exit('error not dead');
        }


        $timestamp = time();
        $text = $player->data->name . ' a tué ' . $target->data->name . '.';

        Log::put($player, $target, $text, type: "kill", hiddenText: '', logTime: $timestamp);

        $text = $target->data->name . ' a été tué par ' . $player->data->name . '.';

        Log::put($target, $player, $text, type: "kill", hiddenText: '', logTime: $timestamp);


        echo '<b><font color="red">Vous tuez votre adversaire.</font></b>';


        echo '
<div class="action-details">
    ';

        $distributedXp = $target->distribute_xp();

        foreach ($distributedXp as $k => $e) {
            if ($k == 'xp_to_distribute') {
                if ($e == 0 && $target->data->isInactive) {
                    echo 'Partage de ' . $e . 'Xp (joueur inactif):<br />';
                } else {
                    echo 'Partage de ' . $e . 'Xp:<br />';
                }
                continue;
            }
            if ($k == 'remaining_xp') {
                echo $player->data->name . ' +' . $e . 'Xp bonus<br />';
                $player->put_xp($e);
                continue;
            }
            if (is_numeric($k)) {
                $assistant = new Player($k);
                $assistant->get_data();
                $assistant->put_xp($e);
                $assist = ($assistant->id == $player->id) ? 0 : 1;
                $assistant->put_kill($target, $e, $assist, ($target->data->isInactive ? 1 : 0));
                echo $assistant->data->name . ' +' . $e . 'Xp<br />';
            }
        }
        $target->refresh_kills(); //clear html cache pour le tué 
        echo '
</div>
';

        //Retrait de 10xRang XP/PI au personnage tué (param dans constants.php)
        $target->put_xp(-DEATH_XP * $target->data->rank);

        $target->death();


        OnHideReloadView::render($player);
    }

    public function playerResetVisible(): void
    {
        $sql = '
            UPDATE
            players
            SET
            visible = NULL
            WHERE
            id = ?
            ';

        $this->db->exe($sql, $this->playerId);
    }

}
