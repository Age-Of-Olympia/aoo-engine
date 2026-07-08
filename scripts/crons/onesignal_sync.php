#!/usr/bin/env php
<?php

/*
 * Synchro / backfill des contacts OneSignal. Premier run = backfill complet.
 * À planifier quotidiennement : 0 4 * * * php .../scripts/crons/onesignal_sync.php
 * Mode témoin (1 contact, à vérifier avant le backfill complet) :
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

// Filtres : real exclut PNJ (id<0, sans email) + tutoriel ; id>1 car OneSignal
// bloque l'external_id "1" (admin) ; email requis. delete_account = option legacy
// de suppression, pour désabonner aussi ces joueurs au backfill.
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
