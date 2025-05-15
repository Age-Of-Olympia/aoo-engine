<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_GET['targetId'])) {

    ExitError('error no merchant');
  }
  
  $player = new Player($_SESSION['playerId']);
  $player->get_data();
  
  $target = new Player($_GET['targetId']);

  $marketAccessError = Market::CheckMarketAccess($player, $target);
  if($marketAccessError !=null){

      ExitError($marketAccessError);
  }

  $POST_DATA = json_decode(file_get_contents('php://input'), true);
  if(!isset($POST_DATA['action']) || !in_array($POST_DATA['action'], ['accept', 'refuse', 'cancel', 'objects'])) {
    ExitError('Invalid request');
  }
  if(!isset($POST_DATA['id'])) {
    ExitError('Invalid request');
  }

  if(isset($POST_DATA['playerid']))
  {
    if($_SESSION['playerId'] != $POST_DATA['playerid'])
    {
      ExitError('account changed');
    }
  }
  $exchange = new Exchange($POST_DATA['id']);
  $exchange->get_base_data();
  
  if (!$exchange->is_in_progress()) {
    ExitError('echange n\'est plus de l\'actualité');
  }



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
      $targetPlayer->get_data();
      if ($POST_DATA['action'] == 'accept') {
  
        if (!isset($POST_DATA['lastmodification']) || $exchange->updateTime != $POST_DATA['lastmodification']) {
          ExitError('l\'échange à été modifié entre l\'affichage et l\'acceptation, revérifiez les objets');
        }
        $exchange->accept_exchange($isTarget);
        $result["message"] .= "Vous avez accepté l'échange avec " . $offeringPlayer->data->name;
        if ($exchange->playerOk == 1 && $exchange->targetOk == 1) {
          $result["message"] .= ". Cela a validé l'échange.";
          $exchange->get_items_data();
          $fromOfferingToTarget = $exchange->give_items(from_player: $offeringPlayer, to_player: $targetPlayer);
          $fromTargetToOffering = $exchange->give_items(from_player: $targetPlayer, to_player: $offeringPlayer);

          $logTime = time();
          $targetLog = "Vous avez échangé avec " . $targetPlayer->data->name;
          $objects = "vous avez donné : " . $fromOfferingToTarget . " et vous avez reçu : " . $fromTargetToOffering;
          Log::put($offeringPlayer, $targetPlayer, $targetLog, "hidden_action", $objects, $logTime);

          $targetLog = "Vous avez échangé avec " . $offeringPlayer->data->name;
          $objects = "vous avez donné : " . $fromTargetToOffering . " et vous avez reçu : " . $fromOfferingToTarget;
          Log::put($targetPlayer, $offeringPlayer, $targetLog, "hidden_action", $objects, $logTime);
        }
      } else if ($POST_DATA['action'] =='refuse') {
        $exchange->refuse_exchange(Istarget: $isTarget, IsPlayer: !$isTarget);
        $result["message"] .= "Vous avez refusé l'échange avec " . $offeringPlayer->data->name;
      }
    } catch (Throwable $th) {
      $exchange->db->rollback_transaction('create_exchange');
      ExitError('Erreur lors de l\'acceptation/refus de l\'échange');
    }
    $exchange->db->commit_transaction('accept_or_reffuse_exchange');

    exit(json_encode($result));
  }
  else if ($POST_DATA['action'] =='cancel') {
   
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
      ExitError('Erreur lors de l\'annulation de l\'échange');
    }
    $exchange->db->commit_transaction('cancel_exchange');
    exit();
  }
  else if ($POST_DATA['action'] =='objects') {
    $objects = $POST_DATA['objects'] ?? [];
    $exchange->db->start_transaction('edit_objects_exchange');
    $exchange->get_items_data();
    try {
    //refund all items 
    foreach ($exchange->items as $exchange_item) {
     if($exchange_item->player_id != $player->id)continue;
     $exchange->remove_item_from_exchange($exchange_item->item_id, $exchange_item->n, $player->id);
     $item = new Item($exchange_item->item_id);
     $item->add_item($player, $exchange_item->n, true);
    }

      // add new items
      foreach ($objects as $decodedObject) {
        $item = new Item($decodedObject['id']);
        $count = abs($decodedObject['n']);
        if (!$item->add_item($player, -$count, true)) {
          throw new Exception('Erreur lors de l\'ajout de l\'objet à l\'échange');
        }
        $exchange->add_item_to_exchange($item->id, $count, $player->id);
      }
    } catch (Throwable $th) {
      $exchange->db->rollback_transaction('edit_objects_exchange');
      ExitError('Erreur lors de l\'edition de l\'échange');
    }
    $exchange->db->commit_transaction('edit_objects_exchange');
    ExitSuccess('echange modifié');
  }

  ExitError('Invalid request');
}
