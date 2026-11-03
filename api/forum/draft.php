<?php
use App\Service\ForumDraftService;
use App\Service\PlayerPnjService;

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

/*
 * Server copy of a forum draft, pushed by js/autosave.js (fetch while
 * typing, sendBeacon when the page goes away). Body: {owner, key, text,
 * title, at}. The owner comes from the rendered form, so it may be a pnj
 * the session no longer drives: any character of the account is accepted.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ExitError('Méthode non autorisée');
}

$POST_DATA = json_decode(file_get_contents('php://input'), true);

if (!is_array($POST_DATA) || !isset($POST_DATA['owner'], $POST_DATA['key'], $POST_DATA['text'], $POST_DATA['at'])
  || !is_numeric($POST_DATA['owner']) || !is_numeric($POST_DATA['at'])
  || !is_string($POST_DATA['key']) || !is_string($POST_DATA['text'])) {
  ExitError('Brouillon invalide');
}

$ownerId = (int) $POST_DATA['owner'];
$mainId = (int) ($_SESSION['mainPlayerId'] ?? $_SESSION['playerId']);

$allowed = (new PlayerPnjService())->controllableIds($mainId);
$allowed[] = (int) $_SESSION['playerId'];

if (!in_array($ownerId, $allowed, true)) {
  ExitError('Personnage non autorisé');
}

try {
  (new ForumDraftService())->save(
    $ownerId,
    $POST_DATA['key'],
    $POST_DATA['text'],
    is_string($POST_DATA['title'] ?? null) ? $POST_DATA['title'] : '',
    (int) $POST_DATA['at']
  );
} catch (InvalidArgumentException $e) {
  ExitError($e->getMessage());
} catch (Throwable $th) {
  ExitError('Erreur lors de l\'enregistrement du brouillon');
}

ExitSuccess('ok');
