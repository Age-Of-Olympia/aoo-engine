<?php

use App\Service\Mail\MailContactSyncService;

/*
 * Synchro / backfill des contacts OneSignal. Premier run = backfill complet.
 * Runs with $db in scope (CronService or the admin crons page).
 */

$mailContactSync = new MailContactSyncService();

// Filtres : real exclut PNJ (id<0, sans email) + tutoriel ; id>1 car OneSignal
// bloque l'external_id "1" (admin) ; email requis. delete_account = option legacy
// de suppression, pour désabonner aussi ces joueurs au backfill.
$sql = '
    SELECT p.id, a.plain_mail, p.name, p.race, a.last_login_time AS lastLoginTime,
           p.deletion_asked, o.player_id AS delete_account
    FROM players p
    JOIN accounts a ON a.player_id = p.id
    LEFT JOIN players_options o ON o.player_id = p.id AND o.name = "deleteAccount"
    WHERE p.player_type = "real"
      AND p.id > 1
      AND a.plain_mail <> ""
';

$res = $db->exe($sql);

$syncedCount = 0;
if ($res) {
    while ($row = $res->fetch_object()) {
        $mailContactSync->syncPlayer($row);
        $syncedCount++;
    }
}

echo 'onesignal sync done: ' . $syncedCount . ' contact(s)';
