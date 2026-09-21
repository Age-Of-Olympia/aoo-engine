<?php
namespace Classes;

class Element{

    /**
     * Élément que le temps n'use pas. Écrit endTime = 0 en base, la
     * valeur que le cron horaire laisse passer — le pendant, côté carte,
     * de PlayerEffectService::DURATION_INFINITE.
     */
    public const DURATION_INFINITE = -1;


    /**
     * Lays an element on a cell. A cell holds ONE element: another one
     * already there wins and nothing is laid; the same one again only
     * refreshes its clock. Nothing is laid where there is no floor either
     * — a flier bleeding over the void stains nothing.
     *
     * @param int  $duration durée de vie en TOURS, comme celle des
     *        effets — mais convertie en durée réelle, car un élément de
     *        carte n'appartient à aucun joueur : aucun tour ne le
     *        décrémente, c'est le cron horaire
     *        (scripts/crons/hourly/delete_elements.php) qui l'efface.
     *        La conversion passe par le tour de référence
     *        (TurnScheduleService, vitesse 16 → 18 h), pour que « quatre
     *        tours » veuille dire la même chose des deux côtés.
     *        Element::DURATION_INFINITE pour un élément que rien n'use
     *        (l'eau de pêche) : il est écrit endTime = 0, la convention
     *        que le cron ne purge jamais.
     * @param int  $rotation 0, 90, 180 or 270: the image is drawn turned
     *        that much, so one file serves every direction.
     * @return bool false when the cell refused it
     */
    public static function put($name, $coords, $duration=4, int $rotation=0): bool{

        /* Durée écrite en tours, vécue en temps réel : c'est le cron
         * horaire qui efface, pas le tour d'un joueur. */
        $endTime = $duration < 0
            ? 0
            : time() + ($duration * \App\Service\TurnScheduleService::referenceTurnSeconds());

        if(is_numeric($coords)){

            $coords_id = (int) $coords;
        }
        else{

            $coords_id = (int) View::get_coords_id($coords);
        }

        $db = new Db();

        if(!self::hasFloor($db, $coords_id)){

            return false;
        }

        $taken = $db->exe('SELECT 1 FROM map_elements WHERE coords_id = ? AND name != ?', array($coords_id, $name));

        if($taken->num_rows){

            return false;
        }

        $sql = '
        INSERT INTO
        map_elements
        (`name`,`coords_id`,`endTime`,`rotation`)
        VALUE(?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        endTime = VALUES(endTime),
        rotation = VALUES(rotation);
        ';

        $db->exe($sql, array($name, $coords_id, $endTime, $rotation));

        self::refreshWatchers($db, $coords_id);

        return true;
    }

    /**
     * Is there ground under this cell? The sky is a cell above ground
     * level with no tile — the same rule go.php uses to demand flight.
     * Below that, a cell without a tile is still walked on, so it counts
     * as floor. A coords id that does not exist has none.
     */
    public static function hasFloor(Db $db, int $coordsId): bool
    {
        $res = $db->exe(
            'SELECT 1 FROM coords c
              LEFT JOIN map_tiles t ON t.coords_id = c.id
             WHERE c.id = ? AND (c.z <= 0 OR t.coords_id IS NOT NULL)
             LIMIT 1',
            array($coordsId)
        );

        return $res && $res->num_rows > 0;
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
