<?php
// scripts/map/local_map.php

// Display current player's Z-coordinate
if (isset($playerZ)) {
    echo '<div class="player-info">';
    echo '<h2>Votre Position</h2>';
    echo '<p><font style="font-size: 88%;">Niveau (Z): ' . $playerZ . '</font></p>';
    echo '</div>';
} else {
    echo '<p><font color="red">Erreur: Impossible de récupérer votre position.</font></p>';
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