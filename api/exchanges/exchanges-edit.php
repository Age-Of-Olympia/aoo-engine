<?php
use App\Factory\PlayerFactory;
use Classes\Exchange;
use Classes\Market;
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
      $offeringPlayer = PlayerFactory::legacy((int) $exchange->playerId);
      $targetPlayer = PlayerFactory::legacy((int) $exchange->targetId);
      //refund items
      $exchange->give_items(from_player: $offeringPlayer, to_player: $offeringPlayer);
      $exchange->give_items(from_player: $targetPlayer, to_player: $targetPlayer);

      /* Les lignes disparaissent avec l'échange : elles y survivaient,
       * et un exemplaire rendu serait resté rattaché à un échange clos —
       * sa ligne étant la seule preuve d'un séquestre légitime. */
      $exchange->purge_items();
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
    /* Tout est repris puis reposé : le client envoie l'état voulu, pas
     * un delta. Chaque ligne est visée par sa CLÉ PRIMAIRE — avant, on
     * la visait par ses valeurs, ce qui emportait les lignes jumelles. */
    $instanceService = new App\Service\ItemInstanceService();

    foreach ($exchange->items as $exchange_item) {
     if($exchange_item->player_id != $player->id)continue;

     $exchange->remove_item_line((int) $exchange_item->id);

     /* Exemplaire : il revient au coffre par sa localisation, pas en
      * recréditant une pile — sans quoi on fabriquerait une unité
      * vierge et on laisserait l'exemplaire séquestré à jamais. */
     if(!empty($exchange_item->instance_id)){
       $instanceService->releaseFromExchange((int) $exchange_item->instance_id, (int) $player->id);
       continue;
     }

     $item = new Item($exchange_item->item_id);
     $item->add_item($player, $exchange_item->n, true);
    }

      // add new items
      foreach ($objects as $decodedObject) {
        /* itemId quand la ligne désigne un exemplaire : sa clé de liste
         * côté client est l'id d'INSTANCE (deux épées usées sont deux
         * entrées, pas une entrée « x2 »), l'objet catalogue voyage donc
         * à part. Jamais d'id d'instance dans itemId : deux paramètres
         * distincts, le serveur refuse l'ambigu. */
        $item = new Item($decodedObject['itemId'] ?? $decodedObject['id']);
        $count = abs($decodedObject['n']);

        /* Exemplaire choisi par le joueur : il quitte la banque pour
         * l'échange. Aucune pile n'est débitée — c'était le piège du
         * marché, un vendeur possédant aussi une pile voyait celle-ci
         * partir à la place de son objet usé. */
        $instanceId = isset($decodedObject['instanceId']) && $decodedObject['instanceId'] !== ''
          ? (int) $decodedObject['instanceId']
          : null;

        if ($instanceId !== null) {
          $instanceService->escrowForExchange($instanceId, (int) $player->id);
          $exchange->add_item_to_exchange($item->id, 1, $player->id, $instanceId);
          continue;
        }

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
