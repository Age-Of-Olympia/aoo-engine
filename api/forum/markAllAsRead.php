<?php
use App\Service\ForumService;
use App\Service\PlayerService;
use Classes\Forum;

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

$result = ["message" => ""];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  try {
    $forumService = new ForumService();
    $playerService = new PlayerService($_SESSION['playerId']);
    $player = $playerService->GetPlayer($_SESSION['playerId']);
    $player->get_data(false);
    $unreadTopics = $forumService->GetAllUnreadTopics($player);
    $currentTime = round(microtime(true) * 1000); // same as in Forum::put_post()
    foreach ($unreadTopics as $topic) {
        $topJson = $topic["topicJson"];

        Forum::put_view($topJson, $currentTime);
    }
    ExitSuccess(["message" => "Topics Lu", "redirect" => "forum.php?lastPosts"]);

  } catch (Throwable $th) {
    ExitError('Erreur lors de lecture des posts');
  }
}
