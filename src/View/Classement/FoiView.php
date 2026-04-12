<?php

namespace App\View\Classement;

use Classes\Db;

class FoiView
{
    public static function renderFoi(): void
    {
        echo '<h1>Classement de la Foi</h1>';

        $path = 'datas/public/classements/foi.html';

        if (file_exists($path) && CACHED_CLASSEMENTS) {
            echo file_get_contents($path);
            return;
        }

        ob_start();

        $db = new Db();

        $sql = '
        SELECT
            g.id,
            g.name,
            COALESCE(SUM(f.pf), 0) AS total_foi,
            COUNT(f.id) AS nb_fideles,
            top.name AS top_prieur_name,
            top.id AS top_prieur_id
        FROM players AS g
        LEFT JOIN players AS f ON f.godId = g.id AND f.id > 0 AND f.lastLoginTime >= (UNIX_TIMESTAMP() - '. INACTIVE_TIME .')
        LEFT JOIN players AS top ON top.id = (
            SELECT id FROM players
            WHERE godId = g.id AND id > 0 AND lastLoginTime >= (UNIX_TIMESTAMP() - '. INACTIVE_TIME .')
            ORDER BY pf DESC
            LIMIT 1
        )
        WHERE g.race = "dieu"
        AND EXISTS (
            SELECT 1 FROM map_triggers
            WHERE map_triggers.params = g.id AND map_triggers.name = "altar"
        )
        GROUP BY g.id, top.id, top.name
        ORDER BY total_foi DESC
        ';

        $res = $db->exe($sql);

        echo '
        <h2>Dieux les plus vénérés</h2>
        <table border="1" align="center" class="marbre" cellspacing="0">
        <tr>
            <th>#</th>
            <th>Dieu</th>
            <th>Foi cumulée</th>
            <th>Fidèles</th>
            <th>Fidèle parmi les fidèles</th>
        </tr>
        ';

        $rank = 1;
        while ($row = $res->fetch_object()) {
            $topPrieur = $row->top_prieur_name
                ? '<a href="infos.php?targetId='. $row->top_prieur_id .'">'. $row->top_prieur_name .'</a>'
                : '—';
            echo '
            <tr>
                <td align="center">'. $rank .'</td>
                <td><a href="infos.php?targetId='. $row->id .'">'. $row->name .'</a></td>
                <td align="center">'. $row->total_foi .'</td>
                <td align="center">'. $row->nb_fideles .'</td>
                <td>'. $topPrieur .'</td>
            </tr>
            ';
            $rank++;
        }

        echo '</table>';

        $data = ob_get_clean();

        $myfile = fopen($path, 'w') or die('Unable to open file!');
        fwrite($myfile, $data);
        fclose($myfile);

        echo $data;
    }
}
