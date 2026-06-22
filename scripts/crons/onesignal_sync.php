#!/usr/bin/env php
<?php

/*
 * Synchro / backfill des contacts OneSignal.
 *
 * Parcourt chaque joueur réel ayant un email et réconcilie son contact
 * OneSignal : (ré)attache external_id = players.id, rafraîchit les tags de
 * segmentation (full_name / is_new / is_inactive / race) et aligne l'état de
 * l'abonnement email sur l'état de suppression (colonne `deletion_asked` du
 * système actuel OU option legacy `players_options.deleteAccount`).
 *
 * Premier run == backfill complet : remplace l'import manuel historique et pose
 * l'external_id sur les contacts qui n'étaient clés que par email.
 *
 * À planifier quotidiennement, ex. crontab :
 *     0 4 * * * php /var/www/html/scripts/crons/onesignal_sync.php
 *
 * Mode témoin (recommandé avant le premier backfill complet) : passer un id de
 * joueur pour synchroniser ce seul contact et vérifier dans le dashboard que
 * l'external_id est correctement attaché :
 *     php scripts/crons/onesignal_sync.php 102
 */

use App\Service\Mail\MailContactSyncService;
use Classes\Db;

if (!defined('NO_LOGIN')) {
    define('NO_LOGIN', true);
}

require_once(__DIR__ . '/../../config.php');

$db = new Db();
$sync = new MailContactSyncService();

$witnessId = isset($argv[1]) ? (int) $argv[1] : null;

// Périmètre des contacts à synchroniser :
//  - player_type = "real" : exclut les PNJ (id négatifs) et les persos tutoriel.
//    Les PNJ n'ont de toute façon pas d'email, donc rien à pousser.
//  - plain_mail <> ""      : un email est requis pour créer un contact OneSignal.
//  - p.id > 1              : #1 est le compte admin, et surtout OneSignal REJETTE
//    l'external_id "1" (« external_id is blocked ») -> 400 à chaque passage si on
//    l'inclut. Seule la valeur "1" est bloquée côté OneSignal ; les autres
//    matricules passent. #1 n'est de toute façon pas une cible de campagne.
//
// delete_account expose l'option legacy de demande de suppression (antérieure à
// la colonne deletion_asked) pour que le backfill désabonne aussi ces joueurs.
$sql = '
    SELECT p.id, p.plain_mail, p.name, p.race, p.lastLoginTime,
           p.deletion_asked, o.player_id AS delete_account
    FROM players p
    LEFT JOIN players_options o ON o.player_id = p.id AND o.name = "deleteAccount"
    WHERE p.player_type = "real"
      AND p.id > 1
      AND p.plain_mail <> ""
';

$params = [];
if ($witnessId !== null) {
    $sql .= ' AND p.id = ?';
    $params[] = $witnessId;
}

$res = $db->exe($sql, $params);

$count = 0;
if ($res) {
    while ($row = $res->fetch_object()) {
        $sync->syncPlayer($row);
        $count++;
        echo 'synced #' . $row->id . ' (' . $row->name . ')<br />';
    }
}

echo 'onesignal sync done: ' . $count . ' contact(s) ' . date('d/m/Y H:i:s');
