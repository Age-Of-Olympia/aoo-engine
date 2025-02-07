<?php
include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/header.php');
?>

<h1>Notifications</h1>
<div class="table-container">
    <form method="post" action="send_notification.php" class="upload-form">
        <div class="form-group">
            <label>Type de notification:</label>
            <select name="notification_type" required>
                <option value="new_season">Nouvelle Saison</option>
                <option value="new_scenario">Nouveau Scénario</option>
                <option value="custom">Message Personnalisé</option>
            </select>
        </div>
        <div class="form-group">
            <label>Titre:</label>
            <input type="text" name="title" required>
        </div>
        <div class="form-group">
            <label>Message:</label>
            <textarea name="message" required></textarea>
        </div>
        <button type="submit" class="btn">Envoyer Notification</button>
    </form>
</div>

<?php
include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/footer.php');
?>
