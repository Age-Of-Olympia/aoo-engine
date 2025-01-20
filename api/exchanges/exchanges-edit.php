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
  $POST_DATA = json_decode(file_get_contents('php://input'), true);
  if(!isset($POST_DATA['action']) || !in_array($POST_DATA['action'], ['accept', 'refuse', 'cancel', 'objects'])) {
    ExitError('Invalid request');
  }
  if(!isset($POST_DATA['id'])) {
    ExitError('Invalid request');
  }

  $exchange = new Exchange($POST_DATA['id']);
  $exchange->get_base_data();
  
  if (!$exchange->is_in_progress()) {
    ExitError('echange n\'est plus de l\'actualité');
  }

  $player = new Player($_SESSION['playerId']);
  $player->get_data();

  if ($player->id != $exchange->targetId && $player->id != $exchange->playerId) {
    ExitError('Current player is not part of the exchange');
  }
  

  $result = ["message" => ""];
  if ($POST_DATA['action'] == 'accept' || $POST_DATA['action'] =='refuse') {
   
    $exchange->db->start_transaction('accept_or_reffuse_exchange');
    try {
    
      $isTarget = $player->id == $exchange->targetId;

      $offeringPlayer = new Player($isTarget ? $exchange->playerId : $exchange->targetId);
      $targetPlayer = new Player($isTarget ? $exchange->targetId : $exchange->playerId);
      $offeringPlayer->get_data();
      if ($POST_DATA['action'] == 'accept') {
  
        if (!isset($POST_DATA['lastmodification']) || $exchange->updateTime != $POST_DATA['lastmodification']) {
          ExitError('l\'échange à été modifié entre l\'affichage et l\'acceptation, revérifiez les objets');
        }
        $exchange->accept_exchange($isTarget);
        $result["message"] .= "Vous avez accepté l'échange proposé par " . $offeringPlayer->data->name;
        if ($exchange->playerOk == 1 && $exchange->targetOk == 1) {
          $result["message"] .= "cela à validé l'échange proposé par " . $offeringPlayer->data->name;

          $exchange->give_items(from_player: $offeringPlayer, to_player: $targetPlayer);
          $exchange->give_items(from_player: $targetPlayer, to_player: $offeringPlayer);
        }
      } else if ($POST_DATA['action'] =='refuse') {
        $exchange->refuse_exchange(Istarget: $isTarget, IsPlayer: !$isTarget);
        $result["message"] .= "Vous avez refusé l'échange proposé par " . $offeringPlayer->data->name;
      }
    } catch (Throwable $th) {
      $exchange->db->rollback_transaction('create_exchange');
      ExitError('Erreur lors de l\'acceptation/refus de l\'échange');
    }
    $exchange->db->commit_transaction('accept_or_reffuse_exchange');

    exit(json_encode($result));
  }

  if ($POST_DATA['action'] =='cancel') {
   
    $exchange->db->start_transaction('cancel_exchange');
    try {
      $exchange->get_items_data();
      $offeringPlayer = new Player($exchange->playerId);
      $targetPlayer = new Player($exchange->targetId);
      //refund items
      $exchange->give_items(from_player: $offeringPlayer, to_player: $offeringPlayer);
      $exchange->give_items(from_player: $targetPlayer, to_player: $targetPlayer);

      $exchange->cancel_exchange();
    } catch (Throwable $th) {
      $exchange->db->rollback_transaction('cancel_exchange');
      exit('Erreur lors de l\'annulation de l\'échange');
    }
    $exchange->db->commit_transaction('cancel_exchange');
    exit();
  }

  ExitError('Invalid request');
}
