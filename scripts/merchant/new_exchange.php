<?php



echo '<div>Pour échanger des objets avec d\'autres personnages par le biais des marchands, c\'est ici. <br/>
Le prix d\'un échange est de 15PO payés par celui qui propose l\'échange </div>';


?>

<div class="section">
  <div class="section-title">Nouvel échange</div>
  <div>Si vous souhaitez proposer un échange à un autre personnage, sélectionnez le dans la liste<br/>
  (Pour envoyer un objet il doit être en banque)</div>

  <div class="button-container">
    <div>
      <input id="autocomplete" type="text" placeholder="Rechercher">
    </div>
  </div>

  <form id="object-list-form">
      <div class="new-exchange-container hidden">
        <div>
            <h3>Objets à envoyer à <span id="exchange-recipient"> </span></h3> 
            <div id="object-list">
              <!-- Objects to be exchanged -->
            </div>

            <?php

            $player = new Player($_SESSION['playerId']);

            $itemList = Item::get_item_list($player, $bank=true);

            echo Ui::print_inventory($itemList);

            ?>

        </div>

        <button id="cancel-button" >Annuler</button>
        <button  id="validate-button" class="exchange-button" disabled><span class="ra ra-scroll-unfurled"></span> Proposer l'échange</button>
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

    var objects = [];

    var $actions = $('.preview-action');

    $actions
    .append('<button class="action" data-action="add-to-exchange">+ Ajouter</button><br />');

    $("#autocomplete").autocomplete({
      source: function(request, response) {
        $.ajax({
          url: "reference_data.php",
          type: "GET",
          dataType: "json",
          data: {
            data_type:"player_name",
            term: request.term
          },
          success: function(data) {
            response(data);
          }
        });
      },
      minLength: 2,
      select: function(event, ui) {
        $('#exchange-recipient').text(ui.item.label);
        if(objects.length > 0 ){
          $('#validate-button').prop('disabled', false);
        }
      }
    }).data("ui-autocomplete")._renderItem = function(ul, item) {
      return $("<li>")
      .append("<div>" + item.label + "</div>")
      .appendTo(ul);
    };

    $('#cancel-button').click(function(e) {
        objects = [];
        updateObjectList();
        $('#exchange-recipient').text('');
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
              url: 'merchant.php?targetId=<?php echo $target->id ?>&exchanges&create',  
              method: 'POST',
              data: $('#object-list-form').serialize(), // Serialize form data
              success: function(response) {
                window.location.href= 'merchant.php?exchanges&targetId=<?php echo $_GET['targetId'] ?>';
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
