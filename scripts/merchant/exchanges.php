<?php


echo '<h1>Echanges</h1>';


// if(isset($_GET['create'])) {

//   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
//     $objects = $_POST['objects'] ?? [];
//     $recipient = Player::get_player_by_name($_POST['recipient']);

//     $exchange = new Exchange();
//     $exchange->db->start_transaction('create_exchange');
//     try {
//       $exchange->create($player->id,$recipient->id);

//       foreach ($objects as $object) {
//           $decodedObject = json_decode($object, true);
//           $item = new Item($decodedObject['id']);
//           $count = abs($decodedObject['n']);
//           if(!$item->add_item($player, -$count, true))
//           {
//             throw new Exception('Erreur lors de l\'ajout de l\'objet à l\'échange');
//           }
//           $exchange->add_item_to_exchange($item->id, $count,$player->id);
//       }
//     } catch (Throwable $th) {
//       $exchange->db->rollback_transaction('create_exchange');
//       ExitError('Erreur lors de la création de l\'échange');
//     }
//     $exchange->db->commit_transaction('create_exchange');
//     ExitSuccess($exchange->db->get_last_id("items_exchanges"));
//   }
//   exit();
// }

// if(isset($_GET['accept']) || isset($_GET['refuse'])) {
//   $exchangeId= !empty($_GET['accept']) ? $_GET['accept'] : $_GET['refuse'];
//   $exchange = new Exchange($exchangeId);
//   if(!$exchange->is_in_progress()){
//     exit('Echange déjà cloturé');
//   }
//   $exchange->db->start_transaction('accept_or_reffuse_exchange');
//   try {
//     $exchange->get_base_data();
//     if ($player->id != $exchange->targetId && $player->id != $exchange->playerId){
//       exit('Current player is not part of the exchange');
//     }
//     $isTarget = $player->id == $exchange->targetId;

//     $offeringPlayer = new Player(  $isTarget ? $exchange->playerId : $exchange->targetId);
//     $targetPlayer = new Player( $isTarget ? $exchange->targetId:$exchange->playerId );
//     $offeringPlayer->get_data();
//     if(isset($_GET['accept'])){
//       if(!$exchange->is_in_progress())exit('echange n\'est plus de l\'actualité');
//       $exchange->accept_exchange($isTarget);
//       echo "<b>Vous avez accepté l'échange proposé par ".$offeringPlayer->data->name." </b>";
//       if($exchange->playerOk == 1 && $exchange->targetOk == 1){
//         echo "<b>Vous avez validé l'échange proposé par ".$offeringPlayer->data->name." </b>";

//         $exchange->give_items(from_player:$offeringPlayer , to_player:$targetPlayer);
//         $exchange->give_items(from_player:$targetPlayer , to_player:$offeringPlayer);
    
//       }
//     }
//     else if(isset($_GET['refuse'])){
//       $exchange->refuse_exchange(Istarget:$isTarget,IsPlayer:!$isTarget);
//       echo "<b>Vous avez refusé l'échange proposé par ".$offeringPlayer->data->name." </b>";
//     }
//   } catch (Throwable $th) {
//     $exchange->db->rollback_transaction('create_exchange');
//     exit('Erreur lors de l\'acceptation/refus de l\'échange');
//   }
//   $exchange->db->commit_transaction('accept_or_reffuse_exchange');
// }


// if(isset($_GET['cancel']) ) {
//   $exchange = new Exchange($_GET['cancel']);
//   $exchange->get_base_data();
//   if(!$exchange->is_in_progress()){
//     exit('Echange déjà cloturé');
//   }
//   $exchange->db->start_transaction('cancel_exchange');
//   try {
//     if ($player->id !== $exchange->playerId && $player->id !== $exchange->targetId){
//       exit('Current player is not the part of the exchange');
//     }
//     $exchange->get_items_data();
//     $offeringPlayer = new Player($exchange->playerId);
//     $targetPlayer = new Player($exchange->targetId);
//     //refund items
//     $exchange->give_items(from_player:$offeringPlayer , to_player:$offeringPlayer);
//     $exchange->give_items(from_player:$targetPlayer , to_player:$targetPlayer);

//     $exchange->cancel_exchange();
//   } catch (Throwable $th) {
//     $exchange->db->rollback_transaction('cancel_exchange');
//     exit('Erreur lors de l\'annulation de l\'échange');
//   }
//   $exchange->db->commit_transaction('cancel_exchange');
// }

if(isset($_GET['newExchange'])){

    include('scripts/merchant/new_exchange.php');

    exit();
}
if(isset($_GET['editExchange'])){

    //include('scripts/merchant/exchanges.php');

    exit();
}

echo '<div>Pour échanger des objets avec d\'autres personnages par le biais des marchands, c\'est ici. </div>';


?>


<div class="section">
  <div class="section-title">Echanges en cours</div>

    <?php

        $exchanges = Exchange::get_open_exchanges($player->id);
        foreach ($exchanges as $exchange) {
          if ($exchange->playerId != $player->id){
              $fromPlayer = new Player($exchange->playerId);
              $fromPlayer->get_data();
              echo '<section style="background-color: #f0f8ff5e;">';
              echo 'Echange reçu de <a href="infos.php?targetId='.$fromPlayer->id.'">'.$fromPlayer->data->name.'('. $fromPlayer->id .')</a> le '.date('d/m/Y H:i', $exchange->updateTime) . '. 
              <br> L\'échange sera validé quand les deux joueurs auront accepté.<br>';
              if($exchange->playerOk == 1){
                echo $targetPlayer->data->name. 'a accepté';
              }
              if($exchange->targetOk == 1){
               echo 'Vous avez accepté. => <a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId='.$target->id.'" data-action="refuse" data-id="'.$exchange->id.'">Refuser</a> ( n\'annule pas l\'échange)<br>';
              }
              else
                echo '<a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId='.$target->id.'" data-action="accept" data-id="'.$exchange->id.'">Accepter l\'échange</a><br>';
              echo '<a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId='.$target->id.'" data-action="cancel" data-id="'.$exchange->id.'">Annuler ( suprimer )</a><br>';
              echo '<a href="merchant.php?targetId='.$target->id.'&exchanges&editExchange='.$exchange->id.'">Modifier</a> <br>';
              echo '<ul class="compact-list">
              <li style="font-weight: bold;">Vous recevez : </li>';
              echo $exchange->render_items_for_player($exchange->playerId);
              echo '<li style="font-weight: bold;">Vous donnez : </li>';
              echo $exchange->render_items_for_player(  $exchange->targetId);
              echo '</ul> <br/>';
              echo '</section>';
          }
        }
        echo '<br/>';
        foreach ($exchanges as $exchange) {
            if ($exchange->playerId == $player->id){
                echo '<section style="background-color: #f0f8ff5e;">';
                $targetPlayer = new Player($exchange->targetId);
                $targetPlayer->get_data();
                echo 'Echange proposé à <a href="infos.php?targetId='.$targetPlayer->id.'">'.$targetPlayer->data->name.'('. $targetPlayer->id .')</a> le '.date('d/m/Y H:i', $exchange->updateTime). '.
                <br> L\'échange sera validé quand les deux joueurs auront accepté.<br>';

                if($exchange->targetOk == 1){
                    echo $targetPlayer->data->name. 'a accepté';
                }
                if($exchange->playerOk == 1){
                 echo 'Vous avez accepté. => <a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId='.$target->id.'" data-action="refuse" data-id="'.$exchange->id.'">Refuser</a> ( n\'annule pas l\'échange) <br>';
                }
                else 
                  echo '<a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId='.$target->id.'" data-action="accept" data-id="'.$exchange->id.'">Accepter l\'échange</a> <br>';
               
                echo '<a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId='.$target->id.'" data-action="cancel" data-id="'.$exchange->id.'">Annuler ( suprimer )</a><br>'; 
                echo '<a href="merchant.php?targetId='.$target->id.'&exchanges&editExchange='.$exchange->id.'">Modifier</a>  <br>';
                echo '<br><ul class="compact-list">
                <li style="font-weight: bold;">Vous recevez : </li>';
                echo $exchange->render_items_for_player($exchange->targetId);
                echo '<li style="font-weight: bold;">Vous donnez : </li>';
                echo $exchange->render_items_for_player( $exchange->playerId);
                echo '</ul> <br/>';
                echo '</section>';
            }
        }
    ?>
  
  </div>
  <div class="button-container">
    <a href="merchant.php?targetId=<?php echo $target->id ?>&exchanges&newExchange">
      <button class="exchange-button"><span class="ra ra-scroll-unfurled"></span> Nouvel échange</button>
    </a>
  </div>

</div>
<script>
   $('.action').click(function(e){
    e.preventDefault();
    let elem = e.currentTarget;
    let url = elem.dataset.url;
    const dataset = element.dataset;
    const payload = { ...dataset };
    delete payload.url;

    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
      console.log('Success:', data);
      
    })
    .catch((error) => {
      console.error('Error:', error);
    });
   });
</script>