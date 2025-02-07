<?php
include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/header.php');
?>

<h1>Administration</h1>
<div class="admin-dashboard">
    <div class="dashboard-stats">
        <div class="stat-card">
            <h3>Joueurs Actifs</h3>
            <?php
            $db = new Db();
            $sql = "SELECT COUNT(*) as count FROM players WHERE lastLoginTime > DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $result = $db->exe($sql);
            $activeUsers = $result->fetch_object()->count;
            echo "<p class='stat-number'>{$activeUsers}</p>";
            ?>
        </div>
        <div class="stat-card">
            <h3>Notifications Envoyées</h3>
            <?php
            $sql = "SELECT COUNT(*) as count FROM players WHERE email_bonus = 1";
            $result = $db->exe($sql);
            $notificationUsers = $result->fetch_object()->count;
            echo "<p class='stat-number'>{$notificationUsers}</p>";
            ?>
        </div>
    </div>
</div>

<?php
include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/footer.php');
?>
