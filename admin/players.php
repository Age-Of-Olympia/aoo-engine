<?php
include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/header.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/src/Service/PlayerService.php');

use App\Service\PlayerService;

$playerService = new PlayerService();

// Handle notification updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['player_id'])) {
    $notifications = [
        'email_bonus' => isset($_POST['email_bonus']) ? 1 : 0,
        'notify_season' => isset($_POST['notify_season']) ? 1 : 0,
        'notify_quest' => isset($_POST['notify_quest']) ? 1 : 0,
        'notify_turn' => isset($_POST['notify_turn']) ? 1 : 0,
        'notify_missive' => isset($_POST['notify_missive']) ? 1 : 0
    ];
    $playerService->updatePlayerNotifications((int)$_POST['player_id'], $notifications);
}

$players = $playerService->getAllPlayers();
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
                <th>Notifications</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($players as $player): ?>
            <tr>
                <td><?php echo $player->id; ?></td>
                <td><?php echo $player->name; ?></td>
                <td><?php echo $player->plain_mail; ?></td>
                <td><?php echo date('Y-m-d H:i', strtotime($player->lastLoginTime)); ?></td>
                <td>
                    <form method="POST" class="notification-form">
                        <input type="hidden" name="player_id" value="<?php echo $player->id; ?>">
                        <label title="Activer les notifications">
                            <input type="checkbox" name="email_bonus" 
                                   <?php echo $player->email_bonus ? 'checked' : ''; ?> 
                                   onchange="this.form.submit()">
                        </label>
                        <div class="notification-options <?php echo $player->email_bonus ? 'active' : ''; ?>">
                            <label title="Nouvelle saison">
                                <input type="checkbox" name="notify_season" 
                                       <?php echo $player->notify_season ? 'checked' : ''; ?>
                                       onchange="this.form.submit()">
                                <span>Saison</span>
                            </label>
                            <label title="Nouvelle quête globale">
                                <input type="checkbox" name="notify_quest" 
                                       <?php echo $player->notify_quest ? 'checked' : ''; ?>
                                       onchange="this.form.submit()">
                                <span>Quête</span>
                            </label>
                            <label title="Nouveau tour">
                                <input type="checkbox" name="notify_turn" 
                                       <?php echo $player->notify_turn ? 'checked' : ''; ?>
                                       onchange="this.form.submit()">
                                <span>Tour</span>
                            </label>
                            <label title="Nouvelle missive">
                                <input type="checkbox" name="notify_missive" 
                                       <?php echo $player->notify_missive ? 'checked' : ''; ?>
                                       onchange="this.form.submit()">
                                <span>Missive</span>
                            </label>
                        </div>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.notification-form {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.notification-options {
    display: none;
    gap: 0.5rem;
}

.notification-options.active {
    display: flex;
}

.notification-options label {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.9em;
    white-space: nowrap;
}
</style>

<script>
document.querySelectorAll('input[name="email_bonus"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const options = this.closest('form').querySelector('.notification-options');
        if (this.checked) {
            options.classList.add('active');
        } else {
            options.classList.remove('active');
        }
    });
});
</script>

<?php include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/footer.php'); ?>
