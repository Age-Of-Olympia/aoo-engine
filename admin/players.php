<?php
include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/header.php');
?>

<h1>Joueurs</h1>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom</th>
                <th>Email</th>
                <th>Dernière Connexion</th>
                <th>Email Notifications</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $db = new Db();
            $sql = "SELECT id, name, plain_mail, last_connection, email_bonus 
                   FROM players 
                   ORDER BY last_connection DESC";
            $result = $db->exe($sql);
            
            while ($player = $result->fetch_object()) {
                echo "<tr>";
                echo "<td>{$player->id}</td>";
                echo "<td>{$player->name}</td>";
                echo "<td>{$player->plain_mail}</td>";
                echo "<td>" . date('Y-m-d H:i', strtotime($player->last_connection)) . "</td>";
                echo "<td>" . ($player->email_bonus ? 'Oui' : 'Non') . "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<?php
include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/footer.php');
?>
