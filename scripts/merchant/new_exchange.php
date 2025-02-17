<?php



echo '<div>Pour échanger des objets avec d\'autres personnages par le biais des marchands, c\'est ici. </div>';


?>

<div class="section">
  <div class="section-title">Nouvel échange</div>
  <div>Si vous souhaitez proposer un échange à un autre personnage, sélectionnez le dans la liste<br/>
  (Pour envoyer un objet il doit être en banque)</div>

  <div class="button-container">
    <div>
      <input id="autocomplete" type="text" placeholder="Rechercher">
      <span id="exchange-recipient"></span>
    </div>
  </div>

  <form id="object-list-form">
      <div class="new-exchange-container hidden">
        <button id="cancel-button" disabled>Annuler</button>
        <button  id="validate-button" class="exchange-button" disabled><span class="ra ra-scroll-unfurled"></span> Creer l'échange</button>
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
        $('#validate-button').prop('disabled', false);
        $('#autocomplete').hide();
        $('#cancel-button').prop('disabled', false);
      }
    }).data("ui-autocomplete")._renderItem = function(ul, item) {
      return $("<li>")
      .append("<div>" + item.label + "</div>")
      .appendTo(ul);
    };

    $('#cancel-button').click(function(e) {
       // objects = [];
       // updateObjectList();
        $('#validate-button').prop('disabled', true);
        $('#cancel-button').prop('disabled', true);
        $('#exchange-recipient').text('');
        $('#autocomplete').show();
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
        $('#validate-button').prop('disabled', true);
        $('#cancel-button').prop('disabled', true);
        $.ajax({
              url: 'api/exchanges/exchanges-create.php?targetId=<?php echo $target->id ?>',  
              method: 'POST',
              dataType: 'json',
              data: $('#object-list-form').serialize(), // Serialize form data
              success: function(response) {
                if(response.error){
                  alert(response.error);
                  return;
                }
                window.location.href= 'merchant.php?exchanges&targetId=<?php echo $_GET['targetId'] ?>&editExchange='+response.result;
              },
              error: function(xhr, status, error) {
                alert('Erreur technique.');
                $('#validate-button').prop('disabled', false);
                $('#cancel-button').prop('disabled', false);
              }
          });
      });



  });


 
</script>