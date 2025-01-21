<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!isset($_GET['targetId'])) {

    exit('error no merchant');
  }

  $target = new Player($_GET['targetId']);
  if (!$target->have_option('isMerchant')) {
    exit('error not merchant');
  }

  $player = new Player($_SESSION['playerId']);
  $player->get_data();
  
  $recipient = Player::get_player_by_name($_POST['recipient']);

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
