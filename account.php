<?php

require_once('config.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/src/Service/EmailNotificationService.php');

use App\Service\EmailNotificationService;

// Define available options
define('OPTIONS', array(
    'raceHint' => "Indice de Race <sup>Affiche une bordure de couleur autour du personnage</sup>",
    'raceHintMax' => "Indice de Race maximale <sup>Colore également l'arrière plan du personnage</sup>",
    'hideGrid' => "Cacher le damier de la Vue <sup>La grille ne s'affichera plus</sup>",
    'noMask' => "Désactiver les masques <sup>Les effets de brumes et de pluie ne s'afficheront plus</sup>",
    'showActionDetails' => "Afficher les détails des Actions <sup>Affiche les calculs et les jets</sup>",
    'noTrain' => "Interdire les entraînements <sup>Les actions de formation ne seront plus possibles</sup>",
    'dlag' => "DLA glissante <sup>Décale l'heure du prochain tour</sup>",
    'deleteAccount' => "Demander la suppression du compte <sup>Votre compte sera supprimé sous 7 jours</sup>",
    'reloadView' => "Rafraichir la Vue <sup>Si cette dernière est buguée</sup>",
    'showTuto' => "Rejouer le tutoriel",
    'incognitoMode' => "Mode Incognito (admin) <sup>Invisible sur la carte et dans les évènements</sup>",
));

$player = new Player($_SESSION['playerId']);
$player->get_data();
$emailService = new EmailNotificationService();

// Get notification settings
$notifications = $emailService->getPlayerNotifications($_SESSION['playerId']);
if (!$notifications) {
    $notifications = (object)[
        'email_bonus' => $player->data->email_bonus ?? 0,
        'notify_season' => 0,
        'notify_quest' => 0,
        'notify_turn' => 0,
        'notify_missive' => 0
    ];
}

// Handle notification updates
if (isset($_POST['update_notifications'])) {
    $emailService->updatePlayerNotifications($_SESSION['playerId'], [
        'email_bonus' => isset($_POST['email_bonus']) ? 1 : 0,
        'notify_season' => isset($_POST['notify_season']) ? 1 : 0,
        'notify_quest' => isset($_POST['notify_quest']) ? 1 : 0,
        'notify_turn' => isset($_POST['notify_turn']) ? 1 : 0,
        'notify_missive' => isset($_POST['notify_missive']) ? 1 : 0
    ]);
    
    $notifications = $emailService->getPlayerNotifications($_SESSION['playerId']);
}

$ui = new Ui('Options du Profil', true);

ob_start();

// Top navigation buttons
echo '<div class="account-nav">';
echo '<a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a>';
echo '</div>';

// Add CSS file
echo '<link rel="stylesheet" href="css/account.css">';

// Main content container with two columns
echo '<div class="account-container">';

// Left column - In-game character settings
echo '<div class="account-column character-settings">';
echo '<h2>Personnage</h2>';

// Character portrait and avatar
echo '<div class="settings-section">';
echo '<h3>Apparence</h3>';
echo '<div class="settings-content">';

echo '<div class="settings-row">';
echo '<div class="settings-cell">';
echo 'Changer de Portrait';
echo '<sup class="settings-help">Vous pouvez faire une demande de Portrait sur le <a href="https://age-of-olympia.net/forum.php?topic=1725177169" target="_blank">forum</a></sup>';
echo '</div>';
echo '<div class="settings-cell">';
echo '<a href="account.php?portraits"><img src="'. $player->data->mini .'" /></a>';
echo '</div>';
echo '</div>';

echo '<div class="settings-row">';
echo '<div class="settings-cell">';
echo 'Changer d\'Avatar';
echo '<sup class="settings-help">Vous pouvez faire une demande d\'Avatar sur le <a href="https://age-of-olympia.net/forum.php?topic=1725177169" target="_blank">forum</a></sup>';
echo '</div>';
echo '<div class="settings-cell">';
echo '<a href="account.php?avatars"><img src="'. $player->data->avatar .'" width="50" /></a>';
echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';

// Character story and MDJ
echo '<div class="settings-section">';
echo '<h3>Histoire</h3>';
echo '<div class="settings-content">';
echo '<button onclick="window.location=\'account.php?mdj\'">Modifier le MDJ</button>';
echo '<sup class="settings-help">Votre Mot Du Jour actuel : ' . (explode("\n", $player->data->text)[0] ?? '') . ' [...]</sup>';
echo '<button onclick="window.location=\'account.php?story\'">Modifier l\'Histoire</button>';
echo '</div>';
echo '</div>';

// Game preferences
echo '<div class="settings-section">';
echo '<h3>Préférences de jeu</h3>';
echo '<div class="settings-content">';

foreach (OPTIONS as $option => $label) {
    if (strpos($label, '<sup>') !== false) {
        list($title, $explanation) = explode('<sup>', $label);
        $explanation = trim($explanation, '</sup>');
        echo '<div class="settings-option">';
        echo '<label class="checkbox-label tooltip-trigger">';
        echo '<input type="checkbox" class="option-checkbox" data-option="' . $option . '"' . ($player->have_option($option) ? ' checked' : '') . '>';
        echo $title;
        echo '<span class="tooltip">' . $explanation . '</span>';
        echo '</label>';
        echo '</div>';
    } else {
        echo '<div class="settings-option">';
        echo '<label class="checkbox-label">';
        echo '<input type="checkbox" class="option-checkbox" data-option="' . $option . '"' . ($player->have_option($option) ? ' checked' : '') . '>';
        echo $label;
        echo '</label>';
        echo '</div>';
    }
}

echo '</div>';
echo '</div>';

echo '</div>'; // End left column

// Right column - Real-world player settings
echo '<div class="account-column player-settings">';
echo '<h2>Compte Joueur</h2>';

// Account settings
echo '<div class="settings-section">';
echo '<h3>Paramètres du Compte</h3>';
echo '<div class="settings-content">';
echo '<button data-change="name">Changer Nom</button>';
echo '<button onclick="window.location=\'account.php?changePsw\'">Changer Mot de Passe</button>';
echo '<button onclick="window.location=\'account.php?changeMail\'">Changer Email</button>';
if (!empty($player->data->plain_mail)) {
    echo '<sup class="settings-help">Email actuel : ' . htmlspecialchars($player->data->plain_mail) . '</sup>';
}
echo '</div>';
echo '</div>';

// Email notifications
echo '<div class="settings-section">';
echo '<h3>Notifications</h3>';
echo '<div class="settings-content">';
echo '<form method="post">';
echo '<input type="hidden" name="update_notifications" value="1">';

echo '<label class="checkbox-label tooltip-trigger">';
echo '<input type="checkbox" name="email_bonus" ' . ($notifications->email_bonus ? 'checked' : '') . '>';
echo ' Activer les notifications par email';
echo '<span class="tooltip">Recevez des notifications par email pour rester informé des événements importants.</span>';
echo '</label>';

echo '<div class="notification-options ' . ($notifications->email_bonus ? 'active' : '') . '">';

echo '<div class="notification-group">';
echo '<label class="checkbox-label tooltip-trigger">';
echo '<input type="checkbox" name="notify_season" ' . ($notifications->notify_season ? 'checked' : '') . '>';
echo ' Nouvelle saison';
echo '<span class="tooltip">Soyez informé quand une nouvelle saison commence.</span>';
echo '</label>';
echo '</div>';

echo '<div class="notification-group">';
echo '<label class="checkbox-label tooltip-trigger">';
echo '<input type="checkbox" name="notify_quest" ' . ($notifications->notify_quest ? 'checked' : '') . '>';
echo ' Nouvelle quête globale';
echo '<span class="tooltip">Recevez une notification quand une nouvelle quête globale est disponible.</span>';
echo '</label>';
echo '</div>';

echo '<div class="notification-group">';
echo '<label class="checkbox-label tooltip-trigger">';
echo '<input type="checkbox" name="notify_turn" ' . ($notifications->notify_turn ? 'checked' : '') . '>';
echo ' Nouveau tour';
echo '<span class="tooltip">Soyez notifié quand un nouveau tour commence.</span>';
echo '</label>';
echo '</div>';

echo '<div class="notification-group">';
echo '<label class="checkbox-label tooltip-trigger">';
echo '<input type="checkbox" name="notify_missive" ' . ($notifications->notify_missive ? 'checked' : '') . '>';
echo ' Nouvelle missive';
echo '<span class="tooltip">Recevez une notification quand vous recevez une nouvelle missive.</span>';
echo '</label>';
echo '</div>';

echo '<button type="submit" class="save-button">Sauvegarder mes préférences</button>';
echo '</div>';

echo '</form>';
echo '</div>';
echo '</div>';

// Account management
echo '<div class="settings-section">';
echo '<h3>Gestion du Compte</h3>';
echo '<div class="settings-content">';
if (isset(OPTIONS['deleteAccount'])) {
    echo '<button class="danger-button" onclick="confirmDeleteAccount()">Demander la suppression du compte</button>';
    echo '<sup class="settings-help">Votre compte sera supprimé sous 7 jours.</sup>';
}
echo '</div>';
echo '</div>';

echo '</div>'; // End right column
echo '</div>'; // End container

?>

<script>
// Toggle notification options
document.querySelector('input[name="email_bonus"]').addEventListener('change', function() {
    const options = document.querySelector('.notification-options');
    if (this.checked) {
        options.classList.add('active');
    } else {
        options.classList.remove('active');
    }
});

// Handle game option toggles
document.querySelectorAll('.option-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const option = this.dataset.option;
        fetch('account.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'option=' + encodeURIComponent(option)
        });
    });
});

function confirmDeleteAccount() {
    if (confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action sera effective dans 7 jours.')) {
        const checkbox = document.querySelector('[data-option="deleteAccount"]');
        if (checkbox) checkbox.click();
    }
}
</script>

<?php
$content = ob_get_clean();
echo Str::minify($content);
?>
