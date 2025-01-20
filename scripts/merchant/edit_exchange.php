<?php
if(!isset($_GET['editExchange'])){
  exit('exchange not set');
}
    $exchange = new Exchange($_GET['editExchange']);
    $exchange->get_base_data();
    if (!$exchange->is_in_progress()) {
      exit('cet echange n\'est plus de l\'actualité');
    }
    $exchange->get_items_data();
    $objects = [];

    $player = new Player($_SESSION['playerId']);
    $player->get_data();
  
    if ($player->id != $exchange->targetId && $player->id != $exchange->playerId) {
      ExitError('Current player is not part of the exchange');
    }
    $otherPlayer = new Player( $player->id == $exchange->playerId ? $exchange->targetId : $exchange->playerId);
    $otherPlayer->get_data();
?>
<div class="section">
  <div class="section-title">Nouvel échange</div>
  <div> Pour envoyer un objet il doit être en banque</div>
  
  <form id="object-list-form">
      <div class="new-exchange-container hidden">
        <div>
            <h3>Objets à envoyer à <span id="exchange-recipient"> <?php echo $otherPlayer->data->name ?> </span></h3> 
            <div id="object-list">
            <?php 
            foreach ($exchange->items as $exchange_item) {
              $item = new Item($exchange_item->item_id);
              $item->get_data();
              echo '<div>Objet : ' . $item->data->name . ' - Quantité: ' . $exchange_item->n . '</div>';
              $objects[] = ['id' => $exchange_item->item_id, 'name' => $item->data->name, 'n' => $exchange_item->n];
            }
            ?>
            </div>
            <?php
            $player = new Player($_SESSION['playerId']);
            $itemList = Item::get_item_list($player, $bank=true);
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
    var $actions = $('.preview-action');
    $actions
    .append('<button class="action" data-action="add-to-exchange">+ Ajouter</button><br />');
  
    $('#cancel-button').click(function(e) {
        objects = <?php echo json_encode($objects); ?>;
        updateObjectList();
     
        e.preventDefault();
    });
    $('#validate-button').click(function(e) {
        var recipient = $('#exchange-recipient').text().trim();
        if (recipient) {
            $('<input>').attr({
                type: 'hidden',
                name: 'recipient',
                value: recipient
            }).appendTo('#object-list-form');
        }
        e.preventDefault(); 
        $('#object-list-form input[name="objects[]"]').remove();
        objects.forEach(function(object, index) {
              $('<input>').attr({
                  type: 'hidden',
                  name: 'objects[]',
                  value: JSON.stringify(object)  
              }).appendTo('#object-list-form');
          });
          $.ajax({
              url: 'api/exchanges/exchanges-edit.php?targetId=<?php echo $target->id ?>',  
              method: 'POST',
              dataType: 'json',
              data: {
                  action: 'objects',
                  id: <?php echo $exchange->id ?>,
                  objects: objects
              },  
              success: function(response) {
                if(response.error){
                  alert(response.error);
                  return;
                }
                alert('Echange modifié');
                window.location.href= 'merchant.php?exchanges&targetId=<?php echo $_GET['targetId'] ?>&exchange';
              },
              error: function(xhr, status, error) {
                alert('Erreur technique.')
              }
          });
      });
      $('.action').click(function(e){
          var n = 0;
          n = prompt('Combien?', window.n);
          if(n == null){
            return false;
          }
          if(n == '' || n < 1 || n > window.n){
            alert('Nombre invalide!');
            return false;
          }
          var objectName = window.name;
          var objectId= window.id;
          var objectCount  = n;
          var existingObject = objects.find(obj => obj.id === objectId);
          if (existingObject) {
            existingObject.n = objectCount;
          } else {
            objects.push({ id: objectId, name:objectName ,n: objectCount });
          }
          updateObjectList();
          if($('#exchange-recipient').text().trim() !== ""){
            $('#validate-button').prop('disabled', false);
          }
          e.preventDefault();
      })
    function updateObjectList() {
        $('#object-list').empty(); 
        objects.forEach(function(obj) {
          $('#object-list').append('<div>Objet : ' + obj.name + ' - Quantité: ' + obj.n + '</div>');
        });
    }
  });
 
</script>