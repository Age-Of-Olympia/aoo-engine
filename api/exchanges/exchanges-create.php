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
  
  $objects = $_POST['objects'] ?? [];
  $recipient = Player::get_player_by_name($_POST['recipient']);

  $exchange = new Exchange();
  $exchange->db->start_transaction('create_exchange');
  try {
    $exchange->create($player->id, $recipient->id);

    foreach ($objects as $object) {
      $decodedObject = json_decode($object, true);
      $item = new Item($decodedObject['id']);
      $count = abs($decodedObject['n']);
      if (!$item->add_item($player, -$count, true)) {
        throw new Exception('Erreur lors de l\'ajout de l\'objet à l\'échange');
      }
      $exchange->add_item_to_exchange($item->id, $count, $player->id);
    }
  } catch (Throwable $th) {
    $exchange->db->rollback_transaction('create_exchange');
    ExitError('Erreur lors de la création de l\'échange');
  }
  $exchange->db->commit_transaction('create_exchange');
  ExitSuccess($exchange->db->get_last_id("items_exchanges"));
}
ExitError('Invalid request');
