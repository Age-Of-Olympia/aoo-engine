<?php
use App\Factory\PlayerFactory;
use Classes\Exchange;
use App\Service\Counter\CounterAccessService;
use App\Service\Counter\CounterCatalog;
use Classes\Item;
use Classes\Log;
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_GET['targetId'])) {

    ExitError('error no merchant');
  }

  // Joueur ACTIF (tutoriel / PNJ), comme le reste du flux marchand.
  $player = PlayerFactory::active();
  $player->get_data();

  $target = PlayerFactory::legacy((int) $_GET['targetId']);

  // Same guards as the screen, tab included: the dialog decides.
  $accessError = (new CounterAccessService())->check($player, $target, CounterCatalog::MERCHANT, 'exchanges');
  if ($accessError !== null) {
      ExitError($accessError);
  }

  $POST_DATA = json_decode(file_get_contents('php://input'), true);
  if(!isset($POST_DATA['action']) || !in_array($POST_DATA['action'], ['accept', 'refuse', 'cancel', 'stack-give', 'stack-take', 'exemplar-give', 'exemplar-take'])) {
    ExitError('Invalid request');
  }
  if(!isset($POST_DATA['id'])) {
    ExitError('Invalid request');
  }

  // Garde « le personnage a changé dans un autre onglet » : la vue
  // envoie l'id du joueur ACTIF, la comparaison doit porter sur le même.
  if(isset($POST_DATA['playerid']))
  {
    if(PlayerFactory::activeId() != $POST_DATA['playerid'])
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

      $offeringPlayer = PlayerFactory::legacy((int) ($isTarget ? $exchange->playerId : $exchange->targetId));
      $targetPlayer = PlayerFactory::legacy((int) ($isTarget ? $exchange->targetId : $exchange->playerId));
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

          /* L'échange est réglé : ses lignes n'ont plus lieu d'être.
           * Elles y survivaient — un exemplaire livré serait resté
           * rattaché à un échange clos, et rien n'aurait distingué un
           * séquestre légitime d'un vestige. */
          $exchange->purge_items();
        }
      } else if ($POST_DATA['action'] =='refuse') {
        $exchange->refuse_exchange(Istarget: $isTarget, IsPlayer: !$isTarget);
        $result["message"] .= "Vous avez retiré votre accord à l'échange avec " . $offeringPlayer->data->name . '.';
      }
    } catch (Throwable $th) {
      $exchange->db->rollback_transaction('create_exchange');
      ExitError('Erreur lors de l\'acceptation/refus de l\'échange');
    }
    $exchange->db->commit_transaction('accept_or_reffuse_exchange');

    exit(json_encode($result));
  }
  else if ($POST_DATA['action'] =='cancel') {
   
    $other = PlayerFactory::legacy((int) ($player->id == $exchange->playerId ? $exchange->targetId : $exchange->playerId));
    $other->get_data();
    $exchange->db->start_transaction('cancel_exchange');
    try {
      $exchange->get_items_data();
      $exchange->cancelReturns($player, $other);

      /* Les lignes disparaissent avec l'échange : elles y survivaient,
       * et un exemplaire rendu serait resté rattaché à un échange clos —
       * sa ligne étant la seule preuve d'un séquestre légitime. */
      $exchange->purge_items();
      $exchange->cancel_exchange();
    } catch (Throwable $th) {
      $exchange->db->rollback_transaction('cancel_exchange');
      ExitError($th instanceof InvalidArgumentException ? $th->getMessage() : 'Erreur lors de l\'annulation de l\'échange');
    }
    $exchange->db->commit_transaction('cancel_exchange');
    exit(json_encode(['message' => 'Échange annulé : vos objets sont revenus dans votre sac, ceux de ' . $other->data->name . ' dans sa banque.']));
  }
  else {
    // Pane moves (ExchangePanesView): one pile or one exemplar between the bag and the exchange
    [$kind, $direction] = explode('-', $POST_DATA['action']);
    $instanceId = $kind === 'exemplar' ? (int) ($POST_DATA['instanceId'] ?? 0) : null;
    $itemId = $kind === 'stack' ? (int) ($POST_DATA['itemId'] ?? 0) : null;
    $n = $kind === 'stack' ? (int) ($POST_DATA['n'] ?? 0) : 1;

    $exchange->db->start_transaction('move_exchange_item');
    $exchange->get_items_data();
    try {
      if ($direction === 'give') {
        $exchange->give($player, $n, $itemId, $instanceId);
      } else {
        $exchange->takeBack($player, $n, $itemId, $instanceId);
      }
    } catch (Throwable $th) {
      $exchange->db->rollback_transaction('move_exchange_item');
      ExitError($th instanceof InvalidArgumentException ? $th->getMessage() : 'Erreur lors de la modification de l\'échange');
    }
    $exchange->db->commit_transaction('move_exchange_item');
    ExitSuccess(['message' => '']);
  }

  ExitError('Invalid request');
}
