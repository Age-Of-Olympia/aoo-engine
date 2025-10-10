<?php
use App\Service\ForumCookieService;

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

$result = ["message" => ""];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $POST_DATA = json_decode(file_get_contents('php://input'), true);
  try {
    $forumCookieService = new ForumCookieService();
    
    if(empty($forumCookieService->getForumCookie($POST_DATA['player-id'],$POST_DATA['post-name']))){
      $forumCookieService->giveCookie($POST_DATA['player-id'],$POST_DATA['post-name']);
      $result["message"].= "Cookie donné !";
    }else{
      $result["message"].= "Cookie déjà donné.";
    }
    exit(json_encode($result));
  } catch (Throwable $th) {
    ExitError('Erreur lors de la modification du player pnj');
  }
}
