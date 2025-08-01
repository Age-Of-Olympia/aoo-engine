<?php
use Classes\Exchange;
use Classes\Market;
use Classes\Player;
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!isset($_GET['targetId'])) {

    exit('error no merchant');
  }
  $player = new Player($_SESSION['playerId']);
  $player->get_data();

  $target = new Player($_GET['targetId']);

  $marketAccessError = Market::CheckMarketAccess($player, $target);
  if($marketAccessError !=null){

      ExitError($marketAccessError);
  }
  
  $recipient = Player::get_player_by_name($_POST['recipient']);
  if($player->id == $recipient->id){
    ExitError('Vous ne pouvez pas vous échanger des objets à vous même');
  }
  $exchange = new Exchange();
  $exchange->db->start_transaction('create_exchange');
  try {
    $exchange->create($player->id, $recipient->id);

  } catch (Throwable $th) {
    $exchange->db->rollback_transaction('create_exchange');
    ExitError('Erreur lors de la création de l\'échange');
  }
  $exchange->db->commit_transaction('create_exchange');
  ExitSuccess($exchange->db->get_last_id("items_exchanges"));
}
ExitError('Invalid request');
