<?php

$path = 'datas/private/players/'. $target->id .'.kills.html';

if(!file_exists($path) || !CACHED_KILLS){
    ob_start();

    $sql = 'SELECT * FROM players_kills WHERE player_id = ? OR target_id = ? ORDER BY time DESC';
    $res = $db->exe($sql, array($target->id, $target->id));
    $kills = $res->fetch_all(MYSQLI_ASSOC);

    echo '<h1>Exploits Guerriers</h1>';
    $killsTxt = '<h2>Importateur de viandes pour les Limbes</h2>';
    $killsTxt .= '
    <table border="1" align="center" class="marbre" width="100%">
    <tr>
    ';

    $deaths = array();
    $assists = array();

    foreach($kills as $row){
        $row = (object) $row;

        if($row->target_id == $target->id){
            if($row->assist){
                continue;
            }
            $deaths[] = $row;
            continue;
        }

        if($row->assist){
            $assists[] = $row;
            continue;
        }


        $showKills = true;
        $assistant = $target;

        $killed = new Player($row->target_id);
        $killed->get_data();
        $planJson = json()->decode('plans', $row->plan);

        $killsTxt .= '
        <tr>
            ';
            $killsTxt .= '<td width="50"><img src="'. $killed->data->avatar .'" /></td>';
            $killsTxt .= '<td width="1%">'. date('d/m/Y', $row->time) .'</td>';
            $killsTxt .= '<td><a href="infos.php?targetId='. $target->id .'">'. $target->data->name .'</a> (rang '. $row->player_rank .') a tué <a href="infos.php?targetId='. $killed->id .'">'. $killed->data->name .'</a> (rang '. $row->target_rank .')';
            if($row->is_inactive) {
                $killsTxt .= ' (joueur inactif au moment des faits)';
            }
            $killsTxt .= '</td>';
            $killsTxt .= '<td width="1%">'. $planJson->name .'</td>';
            $killsTxt .= '<td width="1%">'. $row->xp .'Xp</td>';
            $killsTxt .= '
        </tr>
        ';
    }

    $killsTxt .= '
    </tr>
    </table>
    ';

    if(!empty($showKills)){
        echo $killsTxt;
    }

    if(!count($assists)){
        // echo '<h2>'. $target->data->name .' n\'a jamais filé un seul coup de main.</h2>';
    }

    else{
        echo '<h2>Assistant du Roi des Enfers</h2>';
        echo '
        <table border="1" align="center" class="marbre" width="100%">
        <tr>
        ';

        foreach($assists as $row){
            $assistant = $target;
            $killed = new Player($row->target_id);
            $killed->get_data();
            $planJson = json()->decode('plans', $row->plan);

            echo '
            <tr>
                ';
                echo '<td width="50"><img src="'. $killed->data->avatar .'" /></td>';
                echo '<td width="1%">'. date('d/m/Y', $row->time) .'</td>';
                echo '<td><a href="infos.php?targetId='. $target->id .'">'. $target->data->name .'</a> (rang '. $row->player_rank .') a contribué à la mort de <a href="infos.php?targetId='. $killed->id .'">'. $killed->data->name .'</a> (rang '. $row->target_rank .')';
                if($row->is_inactive) {
                    echo ' (joueur inactif au moment des faits)';
                }
                echo '</td>';
                echo '<td width="1%">'. $planJson->name .'</td>';
                echo '<td width="1%">'. $row->xp .'Xp</td>';
                echo '
            </tr>
            ';
        }

        echo '
        </tr>
        </table>
        ';
    }

    if(!count($deaths)){
        // echo '<h2>'. $target->data->name .' ne connaît pas la défaite.</h2>';
    }

    else{
        echo '<h2>Rameur sur le Styx</h2>';
        echo '
        <table border="1" align="center" class="marbre" width="100%">
        <tr>
        ';

        foreach($deaths as $row){
            $assistant = new Player($row->player_id);
            $assistant->get_data();
            $killed = $target;
            $planJson = json()->decode('plans', $row->plan);

            echo '
            <tr>
                ';
                echo '<td width="50"><img src="'. $assistant->data->avatar .'" /></td>';
                echo '<td width="1%">'. date('d/m/Y', $row->time) .'</td>';
                echo '<td><a href="infos.php?targetId='. $assistant->id .'">'. $assistant->data->name .'</a> (rang '. $row->player_rank .') a tué <a href="infos.php?targetId='. $killed->id .'">'. $killed->data->name .'</a> (rang '. $row->target_rank .')';
                if($row->is_inactive) {
                    echo ' (joueur inactif au moment des faits)';
                }
                echo '</td>';
                echo '<td width="1%">'. $planJson->name .'</td>';
                echo '<td width="1%">'. $row->xp .'Xp</td>';
                echo '
            </tr>
            ';
        }

        echo '
        </tr>
        </table>
        ';
    }

    $data = ob_get_clean();
    File::write($path, $data);
}

else{
    $data = file_get_contents($path);
}

echo $data;
