<?php
use App\Factory\PlayerFactory;
use Classes\Exchange;
use Classes\Item;
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

// Joueur ACTIF (tutoriel / PNJ) : la vue d'échanges qui inclut ce
// fichier travaille déjà sur l'actif, et l'API vérifie son id.
$player = PlayerFactory::active();
$player->get_data();

if ($player->id != $exchange->targetId && $player->id != $exchange->playerId) {
  ExitError('Current player is not part of the exchange');
}
$otherPlayer = PlayerFactory::legacy((int) ($player->id == $exchange->playerId ? $exchange->targetId : $exchange->playerId));
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

              /* Exemplaire : sa clé de liste est son id d'INSTANCE, pour
               * que deux exemplaires du même objet restent deux entrées
               * distinctes — et qu'en retirer un ne retire pas l'autre.
               * Il s'affiche avec son nom et son usure, jamais avec une
               * quantité : il est unique. */
              $isInstance = !empty($exchange_item->instance_id);
              $key = $isInstance ? 'i' . (int) $exchange_item->instance_id : $exchange_item->item_id;

              $label = $isInstance
                ? \App\Service\ItemInstanceService::label($exchange_item->custom_name, (string) $item->data->name)
                    . ' <small>'
                    . \App\Service\ItemInstanceService::stateLine($exchange_item, withBreak: false)
                    . '</small>'
                : $item->data->name . ' - Quantité: ' . $exchange_item->n;

              echo '<div>Objet : ' . $label
                . '<button class="delete" data-id="' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '">X</button></div>';

              $entry = ['id' => $key, 'name' => $item->data->name, 'n' => $isInstance ? 1 : $exchange_item->n];
              if ($isInstance) {
                $entry['itemId'] = (int) $exchange_item->item_id;
                $entry['instanceId'] = (int) $exchange_item->instance_id;
              }
              $objects[] = $entry;
            }
            ?>
            </div>
            <hr>
            <h3>Votre Inventaire :</h3>
            <?php
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
<script src="js/progressive_loader.js?v=20260716"></script>
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
          aooReload();
        });
      });
      
      function deleteObject(e){
        e.preventDefault();
        objects=objects.filter(obj => obj.id !== $(e.target).data("id"));
        updateObjectList();
      }
      $('.delete').click(deleteObject);
      /* Sélecteur restreint au bouton posé juste au-dessus : « action »
       * seule est partagée avec l'inventaire et les contrats du marché,
       * un panneau voisin voyait ses boutons détournés ici. */
      $('.preview-action .action[data-action="add-to-exchange"]').click(function(e){
        e.preventDefault();

        /* Exemplaire individualisé : la ligne cliquée EST l'objet, il
         * n'y a pas de quantité à demander. Il entre dans l'échange par
         * son id d'instance, et sa clé de liste est cet id — deux épées
         * usées du même catalogue sont deux entrées distinctes, pas une
         * entrée « x2 ». */
        var instanceId = window.instanceId || null;

        if(instanceId){
          var key = 'i' + instanceId;
          if(!objects.find(obj => obj.id === key)){
            objects.push({ id: key, itemId: window.id, instanceId: instanceId, name: window.name, n: 1 });
            updateObjectList();
            $('#validate-button').prop('disabled', false);
          }
          return;
        }

        aooPrompt('Combien?', window.n).then(function(n){
          if(n == null){
            return;
          }
          var objectId= window.id;
          let allreadyInTrade =0;
          var existingObjectIndefaults = defaultobjects.find(obj => obj.id === objectId);
          if (existingObjectIndefaults)
              allreadyInTrade = existingObjectIndefaults.n;

          if(n == '' || n < 1 || n > (window.n+allreadyInTrade)){
            aooAlert('Nombre invalide!');
            return;
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

        });
      })
    function updateObjectList() {
        $('#object-list').empty(); 
        objects.forEach(function(obj) {
          $('#object-list').append('<div>Objet : ' + obj.name + ' - Quantité: ' + obj.n + '<button class="delete" data-id="'+obj.id+'">X</button></div>').on( "click", "button",deleteObject);
        });
    }
  });
 
</script>