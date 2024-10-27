<?php


echo '<h1>Echanges</h1>';


if(isset($_GET['create'])) {

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $objects = $_POST['objects'] ?? [];
    $recipient = Player::get_player_by_name($_POST['recipient']);

    $exchange = new Exchange();
    $exchange->create($player->id,$recipient->id);

    foreach ($objects as $object) {
        $decodedObject = json_decode($object, true);
        $item = new Item($decodedObject['id']);
        $item->add_item($player, -$decodedObject['n'], true);
        $exchange->add_item_to_exchange($item->id, $decodedObject['n']);
    }
  }
}

if(isset($_GET['accept']) || isset($_GET['refuse'])) {
  $exchangeId= !empty($_GET['accept']) ? $_GET['accept'] : $_GET['refuse'];
  $exchange = new Exchange($exchangeId);
  $exchange->get_base_data();
  if ($player->id != $exchange->targetId ){
    exit('Current player is not the target of the exchange');
  }
  $offeringPlayer = new Player($exchange->playerId);
  $offeringPlayer->get_data();
  if(isset($_GET['accept'])){
    $exchange->get_items_data();
    $exchange->give_items($player);
    $exchange->accept_exchange();
    echo "<b>Vous avez accepté l'échange proposé par ".$offeringPlayer->data->name." </b>";
  }
  if(isset($_GET['refuse'])){
    $exchange->get_items_data();
    $exchange->give_items($offeringPlayer);
    $exchange->refuse_exchange();
    echo "<b>Vous avez refusé l'échange proposé par ".$offeringPlayer->data->name." </b>";
  }
}


if(isset($_GET['cancel']) ) {
  $exchange = new Exchange($_GET['cancel']);
  $exchange->get_base_data();
  if ($player->id !== $exchange->playerId ){
    exit('Current player is not the source of the exchange');
  }
  $exchange->get_items_data();
  $offeringPlayer = new Player($exchange->playerId);
  $exchange->give_items($offeringPlayer);
  $exchange->cancel_exchange();

}

if(isset($_GET['newExchange'])){

    include('scripts/merchant/new_exchange.php');

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
              echo 'Echange reçu de '.$fromPlayer->data->name. ' le '.date('d/m/Y H:i', $exchange->updateTime) . '. 
              <a href="merchant.php?targetId='.$target->id.'&exchanges&accept='.$exchange->id.'">Accepter</a> - 
              <a href="merchant.php?targetId='.$target->id.'&exchanges&refuse='.$exchange->id.'">Refuser</a> <br/>';
              foreach ($exchange->items as $exchangeItem) {
                $item  = new Item($exchangeItem->item_id);
                $item->get_data();
                echo '<li>'. $exchangeItem->n . ' ' . $item->data->name. '</li>';
              }
              echo '</ul> <br/>';
          }
        }
        echo '<br/>';
        foreach ($exchanges as $exchange) {
            if ($exchange->playerId == $player->id){
                $targetPlayer = new Player($exchange->targetId);
                $targetPlayer->get_data();
                echo 'Echange proposé à '.$targetPlayer->data->name. ' le '.date('d/m/Y H:i', $exchange->updateTime). '.
                <a href="merchant.php?targetId='.$target->id.'&exchanges&cancel='.$exchange->id.'">Annuler</a> 
                <br><ul class="compact-list">';
                foreach ($exchange->items as $exchangeItem) {
                  $item  = new Item($exchangeItem->item_id);
                  $item->get_data();
                  echo '<li>'. $exchangeItem->n . ' ' . $item->data->name. '</li>';
                }
                echo '</ul> <br/>';
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




