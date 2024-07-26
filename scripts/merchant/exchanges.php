<?php

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
</div>


<script>


  $(function() {
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
  });

  $("#autocomplete").on("input", function() {
    $("#propose-button").text("Proposer à ...").prop("disabled", true);
  });
</script>
