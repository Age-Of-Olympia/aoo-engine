<?php
include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/header.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/src/Service/PlayerService.php');

use App\Service\PlayerService;

$playerService = new PlayerService();
?>

<h1>Tableau de bord</h1>
<div class="admin-dashboard">
    <div class="dashboard-stats">
        <div class="stat-card">
            <h3>Total Joueurs</h3>
            <?php
            $totalPlayers = $playerService->getTotalPlayersCount();
            echo "<p class='stat-number'>{$totalPlayers}</p>";
            ?>
        </div>
        <div class="stat-card">
            <h3>Joueurs Actifs (7j)</h3>
            <?php
            $activeUsers7 = $playerService->getActivePlayersCount(7);
            echo "<p class='stat-number'>{$activeUsers7}</p>";
            ?>
        </div>
        <div class="stat-card">
            <h3>Joueurs Actifs (30j)</h3>
            <?php
            $activeUsers30 = $playerService->getActivePlayersCount(30);
            echo "<p class='stat-number'>{$activeUsers30}</p>";
            ?>
        </div>
        <div class="stat-card">
            <h3>Nouveaux Joueurs (7j)</h3>
            <?php
            $newUsers7 = $playerService->getNewPlayersCount(7);
            echo "<p class='stat-number'>{$newUsers7}</p>";
            ?>
        </div>
        <div class="stat-card">
            <h3>Nouveaux Joueurs (30j)</h3>
            <?php
            $newUsers30 = $playerService->getNewPlayersCount(30);
            echo "<p class='stat-number'>{$newUsers30}</p>";
            ?>
        </div>
        <div class="stat-card">
            <h3>Emails Renseignés</h3>
            <?php
            $plainEmails = $playerService->getPlainEmailCount();
            echo "<p class='stat-number'>{$plainEmails}</p>";
            ?>
        </div>
        <div class="stat-card">
            <h3>Notifications Activées</h3>
            <?php
            $notificationUsers = $playerService->getNotificationUsersCount();
            echo "<p class='stat-number'>{$notificationUsers}</p>";
            ?>
        </div>
    </div>
</div>

<?php include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/footer.php'); ?>
