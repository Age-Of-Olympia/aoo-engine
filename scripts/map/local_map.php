<?php
use Classes\Player;
// scripts/map/local_map.php

// Display current player's Z-coordinate and full position
if (isset($playerZ) && isset($player->coords)) { 
    echo '<div class="player-info" style="text-align: center; margin-bottom: 10px;">'; 
    echo '<h2>Votre Position</h2>';
    echo '<p style="font-size: 90%;">'; 
    echo htmlspecialchars($planJson->name) . ' / ';
    $displayZLevelName = (strpos($zLevelName, ' - ') === 0) ? substr($zLevelName, 3) : $zLevelName;
    echo 'Niveau: ' . htmlspecialchars($displayZLevelName) . ' (Z' . htmlspecialchars($playerZ) .  ') <br>'; 
    echo 'Coords (X: ' . htmlspecialchars($player->coords->x) . ', Y: ' . htmlspecialchars($player->coords->y) . ', Z: ' . htmlspecialchars($player->coords->z) . ')';
    echo '</p>';
    echo '</div>';
} else {
    echo '<div class="player-info-error" style="text-align: center; color: red; margin-bottom: 10px;">';
    echo '<p>Erreur: Impossible de récupérer les informations de position.</p>';
    echo '</div>';
}

// Check if there's a PNJ assigned to the plan
if (!empty($planJson->pnj)) {
    // Fetch PNJ data
    $pnj = new Player($planJson->pnj);
    $pnj->get_data();

    // Fetch race information
    $raceJson = json()->decode('races', $pnj->data->race);

    // Display PNJ information
    echo '
    <div class="pnj-info">
        <h2>PNJ</h2>
        <table border="1" align="center" class="marbre">
            <tr>
                <td><img src="' . $pnj->data->avatar . '" /></td>
                <td align="left">
                    <a href="infos.php?targetId=' . $pnj->id . '">' . $pnj->data->name . '</a><br />
                    <font style="font-size: 88%;">' . $raceJson->name . ', rang ' . $pnj->data->rank . '</font>
                </td>
            </tr>
        </table>
    </div>';
} else {
    // No PNJ assigned
    echo '<div class="pnj-info">';
    echo '<h2>PNJ</h2>';
    echo '<p><font color="red">Il n\'y a pas de PNJ assigné à ce Territoire.</font></p>';
    echo '</div>';
}
?>