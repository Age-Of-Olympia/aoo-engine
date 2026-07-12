<?php
/**
 * Page legacy « Importer images » : remplacée par Joueurs → Avatars &
 * portraits (admin/avatars-portraits.php), qui liste, diagnostique, ajoute
 * et supprime. Redirection permanente pour les favoris ; l'accès est
 * vérifié par la page cible (alias AdminMenuAccessService).
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

header('Location: /admin/avatars-portraits.php', true, 301);
exit;
