<?php


exit('Pour le moment, les échanges ne sont pas possibles.<br />Revenez plus tard!');


if(!empty($_POST['action']) && !empty($_POST['itemId']) && !empty($_POST['n'])) {

  if($_POST['action'] == 'add-to-exchange'){
      $toPlayer = Player::get_player_by_name($_POST['to']);
      $exchange = new Exchange();
      $exchange->create_and_get($_POST['from'], $toPlayer->id);

      $exchange->add_players_items_exchange($_POST['itemId'],$_POST['n'],$_POST['from'], $toPlayer->id);
  }

}


echo '<h1>Echanges</h1>';

echo '<div>Pour échanger des objets avec d\'autres personnages par le biais des marchands, c\'est ici. <br/>
Le prix d\'un échange est de 15PO payés par celui qui propose l\'échange </div>';


?>

<div class="section">
  <div class="section-title">Nouvel échange</div>
  <div>Si vous souhaitez proposer un échange à un autre personnage, sélectionnez le dans la liste</div>

  <div class="button-container">
    <div>
      <input id="autocomplete" type="text" placeholder="Rechercher">
      <button id="propose-button" disabled>Proposer à ...</button>
    </div>
  </div>
  <div class="new-exchange-container hidden">
    <div>
        <h3>Objets à envoyer à <span id="exchange-recipient"> </span></h3> (Pour envoyer un objet il doit être en banque)

        <?php

        $player = new Player($_SESSION['playerId']);

        $itemList = Item::get_item_list($player, $bank=true);

        echo Ui::print_inventory($itemList);

        ?>

    </div>

    <button id="cancel-button" >Annuler</button>
    <button id="validate-button" >Valider</button>
  </div>
</div>

<div class="section">
  <div class="section-title">Echanges en cours</div>

    <?php

        $exchanges = Exchange::get_open_exchanges($player->id);
        foreach ($exchanges as $exchange) {

          if ($exchange->playerId == $player->id){
            $targetPlayer = new Player($exchange->targetId);
            $targetPlayer->get_data();
            echo 'Echange proposé à '.$targetPlayer->data->name. ' le '.date('Y-m-d H:i:s', $exchange->updateTime);
          }

          if ($exchange->playerId != $player->id){
              $fromPlayer = new Player($exchange->targetId);
              $fromPlayer->get_data();
              echo 'Echange reçu de '.$fromPlayer->data->name. ' le '.date('Y-m-d H:i:s', $exchange->updateTime);
          }
        }
    ?>
  
  </div>
</div>


<script src="js/progressive_loader.js"></script>

<script>


  $(function() {

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
        $("#propose-button").text("Proposer à " + ui.item.label).prop("disabled", false);
      }
    }).data("ui-autocomplete")._renderItem = function(ul, item) {
      return $("<li>")
      .append("<div>" + item.label + "</div>")
      .appendTo(ul);
    };

    $('#propose-button').click(function() {
      var recipient = $('#autocomplete').val().trim();
      $('#exchange-recipient').text(recipient);
      $('.new-exchange-container').removeClass('hidden');
    });

    $('#cancel-button').click(function() {
      $('#exchange-recipient').text('');
      $('.new-exchange-container').addClass('hidden');
    });

    $("#autocomplete").on("input", function() {
      $("#propose-button").text("Proposer à ...").prop("disabled", true);
    });

    $('.action').click(function(e){


      var action = $(this).data('action');
      var n = 0;

      n = prompt('Combien?', window.n);

      if(n == null){

        return false;
      }
      if(n == '' || n < 1 || n > window.n){

        alert('Nombre invalide!');
        return false;
      }

      $.ajax({
        type: "POST",
        url: 'merchant.php?targetId=<?php echo $target->id ?>&exchanges',
        data: {'action': action,'itemId': window.id,'n': n,
          'from':<?php echo $player->id ?> ,
          'to':$('#exchange-recipient').text() }, // serializes the form's elements.
        success: function(data)
        {
           alert(data);
          //document.location.reload();
        }
      });
    })

  });



</script>
