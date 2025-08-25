<?php
use Classes\Exchange;
use Classes\Item;
use Classes\ActorInterface;
use Classes\Ui;

if (!isset($_GET['editExchange'])) {
  exit('exchange not set');
}
$exchange = new Exchange($_GET['editExchange']);
$exchange->get_base_data();
if (!$exchange->is_in_progress()) {
  exit('cet echange n\'est plus de l\'actualité');
}
$exchange->get_items_data();
$objects = [];

$player = new ActorInterface($_SESSION['playerId']);
$player->get_data();

if ($player->id != $exchange->targetId && $player->id != $exchange->playerId) {
  ExitError('Current player is not part of the exchange');
}
$otherPlayer = new ActorInterface($player->id == $exchange->playerId ? $exchange->targetId : $exchange->playerId);
$otherPlayer->get_data();
?>
<div class="section">
  <div class="section-title">Modification de l'échange</div>
  <div> Pour envoyer un objet il doit être en banque</div>
  
  <form id="object-list-form">
      <div class="new-exchange-container hidden">
        <div>
            <h3>Objets à envoyer à <span id="exchange-recipient"> <?php echo $otherPlayer->data->name ?> </span></h3> 
            <div id="object-list">
            <?php 
            foreach ($exchange->items as $exchange_item) {
              if($exchange_item->player_id != $player->id)continue;
              $item = new Item($exchange_item->item_id);
              $item->get_data();
              echo '<div>Objet : ' . $item->data->name . ' - Quantité: ' . $exchange_item->n . '<button class="delete" data-id="'.$exchange_item->item_id.'">X</button></div>';
              $objects[] = ['id' => $exchange_item->item_id, 'name' => $item->data->name, 'n' => $exchange_item->n];
            }
            ?>
            </div>
            <hr>
            <h3>Votre Inventaire :</h3>
            <?php
            $player = new ActorInterface($_SESSION['playerId']);
            $itemList = Item::get_item_list($player, bank:true);
            echo Ui::print_inventory($itemList);
            ?>
        </div>
        <button id="cancel-button" >Annuler</button>
        <button  id="validate-button" class="exchange-button" disabled><span class="ra ra-scroll-unfurled"></span> Modifier l'échange</button>
      </div>
  </form>
  <div class="button-container">
    <a href="merchant.php?targetId=<?php echo $target->id ?>&exchanges">
      <button>Retour aux échanges</button>
    </a>
  </div>
</div>
<script src="js/progressive_loader.js"></script>
<script>
  $(function() {
    var objects = <?php echo json_encode($objects); ?>;
    var defaultobjects=<?php echo json_encode($objects); ?>;
    var $actions = $('.preview-action');
    $actions
    .append('<button class="action" data-action="add-to-exchange">+-Modifier </button><br />');
  
    $('#cancel-button').click(function(e) {
        objects = <?php echo json_encode($objects); ?>;
        updateObjectList();
        $('#validate-button').prop('disabled', true);
        e.preventDefault();
    });
    $('#validate-button').click(function(e) {
        e.preventDefault(); 
        $('#validate-button').prop('disabled', true);
        let payload = {
                  action: 'objects',
                  id: <?php echo $exchange->id ?>,
                  playerid: <?php echo $player->id ?>,
                  objects: objects
              };
          let url= 'api/exchanges/exchanges-edit.php?targetId=<?php echo $target->id ?>';
          aooFetch(url,payload,null)
          .then(data => {
          if(data.error) {
            alert(data.error);
            $('#validate-button').prop('disabled', false);
          }
          else if(data.result) {
            alert(data.result);
            window.location.href= 'merchant.php?exchanges&targetId=<?php echo $_GET['targetId'] ?>&exchange';
          }
        
        })
        .catch((error) => {
          console.error('Error:', error);
          location.reload();
        });
      });
      
      function deleteObject(e){
        e.preventDefault();
        objects=objects.filter(obj => obj.id !== $(e.target).data("id"));
        updateObjectList();
      }
      $('.delete').click(deleteObject);
      $('.action').click(function(e){
        e.preventDefault();
          var n = 0;
          n = prompt('Combien?', window.n);
          if(n == null){
            return false;
          }
          var objectId= window.id;
          let allreadyInTrade =0;
          var existingObjectIndefaults = defaultobjects.find(obj => obj.id === objectId);
          if (existingObjectIndefaults) 
              allreadyInTrade = existingObjectIndefaults.n;

          if(n == '' || n < 1 || n > (window.n+allreadyInTrade)){
            alert('Nombre invalide!');
            return false;
          }
          var objectName = window.name;
          
          var objectCount  = n;
          var existingObject = objects.find(obj => obj.id === objectId);
          if (existingObject) {
            existingObject.n = objectCount;
          } else {
            objects.push({ id: objectId, name:objectName ,n: objectCount });
          }
          updateObjectList();

            $('#validate-button').prop('disabled', false);

      })
    function updateObjectList() {
        $('#object-list').empty(); 
        objects.forEach(function(obj) {
          $('#object-list').append('<div>Objet : ' + obj.name + ' - Quantité: ' + obj.n + '<button class="delete" data-id="'+obj.id+'">X</button></div>').on( "click", "button",deleteObject);
        });
    }
  });
 
</script>