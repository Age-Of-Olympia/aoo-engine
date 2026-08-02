<?php

namespace App\Service;

use App\Enum\EntityCategory;
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
     * The one definition of the "inactive" cutoff: no login within INACTIVE_TIME.
     * Static so roster/list code can reuse it without constructing a
     * PlayerService (whose id it does not need). The instance method below
     * delegates here for the existing entity/service callers.
     */
    public static function isInactiveSince(int $lastLoginTime): bool
    {
        return $lastLoginTime < time() - INACTIVE_TIME;
    }

    /**
     * Helper function to calculate if a login time is considered inactive
     * @param int $lastLoginTime The last login time to check
     * @return bool True if inactive, false otherwise
     */
    public function isInactive(int $lastLoginTime): bool
    {
        return self::isInactiveSince($lastLoginTime);
    }

    public function searchNonAnonymePlayer(string $searchKey): array
    {
        
        $sql = 'select players.name
                from players
                left JOIN players_options on players_options.player_id=players.id and players_options.name = "anonymeMode"
                where players.name like ?
                and players_options.player_id is null
                and players.player_type = "real"
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

        // Une structure ne meurt pas comme un personnage : pas de partage
        // d'XP (elle n'en porte pas), pas de compteurs de kills, pas d'âme.
        // Branche destruction du plan bâtiments (§4.7) — via EntityCategory
        // pour couvrir TOUTE la branche structure (building ET unique).
        if (EntityCategory::fromPlayerType($target->getPlayerType()) === EntityCategory::Structure) {
            self::processStructureDestruction($player, $target);
            return;
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

    /**
     * Branche destruction du chemin de mort pour les structures
     * (docs/design-buildings-entities.md §4.7).
     *
     * Journalise la destruction des deux côtés puis :
     * - bâtiment : il DISPARAÎT du plateau (BuildingService::vanish) —
     *   pas d'enfers, la ligne players est remisée hors-plateau pour que
     *   les événements restent vrais et que l'id ne soit pas recyclé ;
     * - objet unique : l'entité disparaît et l'instance enveloppée tombe
     *   BRISÉE au sol (bourse) — cohérent avec les deux états de la carte
     *   et réparable un jour.
     * Le butin de matériaux est la question ouverte n°4 du plan ; il se
     * greffera ici sans retoucher le chemin de mort des personnages.
     */
    private static function processStructureDestruction(Player $player, Player $target): void
    {
        $timestamp = time();

        /* Un exemplaire POSÉ ne disparaît pas : il tombe brisé sur sa case et
         * garde sa ligne. Les objets uniques d'animateur suivent le même
         * chemin tant qu'ils enveloppent un exemplaire. */
        if ($target->getPlayerType() === \App\Service\ItemInstanceService::ENTITY_TYPE) {
            // L'entité disparaît (sa ligne players et ses logs avec) : on
            // détruit d'abord, puis on journalise côté attaquant seulement
            // — un log ciblant la ligne supprimée violerait la FK.
            (new PlacedExemplarService())->destroyToGround($target->id);

            $text = $player->data->name . ' a détruit ' . $target->data->name . '.';
            Log::put($player, $player, $text, type: "kill", hiddenText: '', logTime: $timestamp);
        } else {
            $text = $player->data->name . ' a détruit ' . $target->data->name . '.';
            Log::put($player, $target, $text, type: "kill", hiddenText: '', logTime: $timestamp);

            $text = $target->data->name . ' a été détruit par ' . $player->data->name . '.';
            Log::put($target, $player, $text, type: "kill", hiddenText: '', logTime: $timestamp);

            (new BuildingService())->vanish($target->id);
        }

        echo '<b><font color="red">Vous détruisez la structure.</font></b>';

        OnHideReloadView::render($player);
    }

    public function playerUpdateVisible($value): void
    {
        $sql = '
            UPDATE
            players
            SET
            visible = ?
            WHERE
            id = ?
            ';

        $this->db->exe($sql, array($value, $this->playerId));
    }

}
