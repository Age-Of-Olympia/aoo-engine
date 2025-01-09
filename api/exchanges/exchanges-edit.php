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

  $result = ["message" => ""];
  if (isset($_POST['accept']) || isset($_POST['refuse'])) {
    $exchangeId = !empty($_POST['accept']) ? $_POST['accept'] : $_POST['refuse'];
    $exchange = new Exchange($exchangeId);
    if (!$exchange->is_in_progress()) {
      ExitError('Echange déjà cloturé');
    }
    $exchange->db->start_transaction('accept_or_reffuse_exchange');
    try {
      $exchange->get_base_data();
      if ($player->id != $exchange->targetId && $player->id != $exchange->playerId) {
        ExitError('Current player is not part of the exchange');
      }
      $isTarget = $player->id == $exchange->targetId;

      $offeringPlayer = new Player($isTarget ? $exchange->playerId : $exchange->targetId);
      $targetPlayer = new Player($isTarget ? $exchange->targetId : $exchange->playerId);
      $offeringPlayer->get_data();
      if (isset($_POST['accept'])) {
        if (!$exchange->is_in_progress()) ExitError('echange n\'est plus de l\'actualité');
        if (!isset($_POST['lastModification']) || $exchange->updateTime != $_POST['lastModification']) {
          ExitError('l\'échange à été modifié entre l\'affichage et l\'acceptation, revérifiez les objets');
        }
        $exchange->accept_exchange($isTarget);
        $result["message"] += "<b>Vous avez accepté l'échange proposé par " . $offeringPlayer->data->name . " </b>";
        if ($exchange->playerOk == 1 && $exchange->targetOk == 1) {
          $result["message"] += "<b>Vous avez validé l'échange proposé par " . $offeringPlayer->data->name . " </b>";

          $exchange->give_items(from_player: $offeringPlayer, to_player: $targetPlayer);
          $exchange->give_items(from_player: $targetPlayer, to_player: $offeringPlayer);
        }
      } else if (isset($_POST['refuse'])) {
        $exchange->refuse_exchange(Istarget: $isTarget, IsPlayer: !$isTarget);
        $result["message"] += "<b>Vous avez refusé l'échange proposé par " . $offeringPlayer->data->name . " </b>";
      }
    } catch (Throwable $th) {
      $exchange->db->rollback_transaction('create_exchange');
      ExitError('Erreur lors de l\'acceptation/refus de l\'échange');
    }
    $exchange->db->commit_transaction('accept_or_reffuse_exchange');

    exit(json_encode($result));
  }

  if (isset($_POST['cancel'])) {
    $exchange = new Exchange($_POST['cancel']);
    $exchange->get_base_data();
    if (!$exchange->is_in_progress()) {
      ExitError('Echange déjà cloturé');
    }
    $exchange->db->start_transaction('cancel_exchange');
    try {
      if ($player->id !== $exchange->playerId && $player->id !== $exchange->targetId) {
        ExitError('Current player is not the part of the exchange');
      }
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

  if (isset($_POST['accept']) || isset($_POST['refuse'])) {
    $exchangeId = !empty($_POST['accept']) ? $_POST['accept'] : $_POST['refuse'];
    $exchange = new Exchange($exchangeId);
    if (!$exchange->is_in_progress()) {
      ExitError('Echange déjà cloturé');
    }
    $exchange->db->start_transaction('accept_or_reffuse_exchange');
    try {
      $exchange->get_base_data();
      if ($player->id != $exchange->targetId && $player->id != $exchange->playerId) {
        ExitError('Current player is not part of the exchange');
      }
      $isTarget = $player->id == $exchange->targetId;

      $offeringPlayer = new Player($isTarget ? $exchange->playerId : $exchange->targetId);
      $targetPlayer = new Player($isTarget ? $exchange->targetId : $exchange->playerId);
      $offeringPlayer->get_data();
      if (isset($_POST['accept'])) {
        if (!$exchange->is_in_progress()) exit('echange n\'est plus de l\'actualité');
        $exchange->accept_exchange($isTarget);
        echo "<b>Vous avez accepté l'échange proposé par " . $offeringPlayer->data->name . " </b>";
        if ($exchange->playerOk == 1 && $exchange->targetOk == 1) {
          echo "<b>Vous avez validé l'échange proposé par " . $offeringPlayer->data->name . " </b>";

          $exchange->give_items(from_player: $offeringPlayer, to_player: $targetPlayer);
          $exchange->give_items(from_player: $targetPlayer, to_player: $offeringPlayer);
        }
      } else if (isset($_POST['refuse'])) {
        $exchange->refuse_exchange(Istarget: $isTarget, IsPlayer: !$isTarget);
        echo "<b>Vous avez refusé l'échange proposé par " . $offeringPlayer->data->name . " </b>";
      }
    } catch (Throwable $th) {
      $exchange->db->rollback_transaction('create_exchange');
      ExitError('Erreur lors de l\'acceptation/refus de l\'échange');
    }
    $exchange->db->commit_transaction('accept_or_reffuse_exchange');
  }



  ExitError('Invalid request');
}
