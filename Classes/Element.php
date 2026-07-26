<?php
namespace Classes;

class Element{


    /**
     * @param bool $refreshWatchers purger le damier en cache de ceux qui
     *        voient la case. À FAUX quand l'appelant purge déjà lui-même
     *        la zone (traces de pas : Player::go le fait pour l'origine
     *        ET la destination du pas) — sinon on paie deux fois la même
     *        purge à chaque déplacement, l'action la plus fréquente du jeu.
     */
    public static function put($name, $coords, $duration=THREE_DAYS, bool $refreshWatchers=true){


        if(!(new \App\Service\EffectService())->exists($name)){

            exit('error element '. $name);
        }


        $endTime = time() + $duration;

        if(is_numeric($coords)){

            $coords_id = $coords;
        }
        else{

            $coords_id = View::get_coords_id($coords);
        }

        // Log coords_id for debugging foreign key issues
        if ($coords_id === NULL || $coords_id === '') {
            error_log("[Element::put] WARNING: coords_id is NULL/empty for element '{$name}'");
            error_log("[Element::put] Stack trace: " . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3), true));
            return; // Don't try to insert with NULL coords_id
        }

        $db = new Db();

        // CRITICAL: Validate that coords_id actually exists in database
        // This prevents foreign key constraint violations
        $result = $db->exe("SELECT id FROM coords WHERE id = ?", [$coords_id]);
        $coordsExists = $result && $result->num_rows > 0;
        if (!$coordsExists) {
            error_log("[Element::put] ERROR: coords_id {$coords_id} does not exist in database for element '{$name}'");
            error_log("[Element::put] Stack trace: " . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true));
            return; // Don't try to insert with invalid coords_id
        }

        $sql = '
        INSERT INTO
        map_elements
        (`name`,`coords_id`,`endTime`)
        VALUE(?, ?, ?)
        ON DUPLICATE KEY UPDATE
        endTime = VALUES(endTime);
        ';

        $db->exe($sql, array($name, $coords_id, $endTime));

        if($refreshWatchers){

            self::refreshWatchers($db, (int) $coords_id);
        }
    }

    /**
     * Le damier de chaque joueur est un SVG mis en cache sur disque, et
     * rien ne l'invalidait quand un élément apparaissait dessus. Le sang
     * d'un coup porté était donc bien écrit en base, mais le joueur
     * continuait de recevoir sa vieille image : il ne le voyait qu'après
     * s'être déplacé. Le rafraîchissement côté client, lui, était déjà
     * en place — il rapatriait simplement le même SVG périmé.
     *
     * Le rayon est celui d'un déplacement (±20 cases), et c'est un choix
     * assumé : Player::go() purge EXACTEMENT de la même façon à chaque
     * pas, et l'on se déplace bien plus souvent qu'on ne se bat. Le coût
     * est donc déjà celui du jeu ordinaire.
     */
    private static function refreshWatchers(Db $db, int $coordsId): void
    {
        $res = $db->exe('SELECT x, y, z, plan FROM coords WHERE id = ?', array($coordsId));

        if (!$res || !$res->num_rows) {
            return;
        }

        View::refresh_players_svg($res->fetch_object());
    }
}
