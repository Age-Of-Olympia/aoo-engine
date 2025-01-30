function selectPreviousTool($customCursor){
  let selectedTool = getParameterByName('selectedTool');

  if (selectedTool) {
    $('.map').filter(function() {

      return $(this).data('name') === selectedTool;
    }).each(function() {
      $(this).addClass('selected').css('border', '1px solid red');

      $customCursor.attr('src', $(this).attr('src')).show();

      $('body').on('mousemove', function(e) {
        $customCursor.css({
          left: e.pageX - 25 +'px',
          top: e.pageY - 25+'px'
        });
      });


      var $paramsField = $('#' + $(this).data('type') + '-params');

      if($paramsField != null){

        let selectedParams = getParameterByName('selectedParams');

        $paramsField.val(selectedParams);
      }
    });
  }

}

function getParameterByName(name, url = window.location.href) {
  name = name.replace(/[\[\]]/g, '\\$&');
  let regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
      results = regex.exec(url);
  if (!results) return null;
  if (!results[2]) return '';
  return decodeURIComponent(results[2].replace(/\+/g, ' '));
}

function setZoneBeginCoords(x,y){
  $("#zone-params-begin-x").val(x);
  $("#zone-params-begin-y").val(y);
}

function setZoneEndCoords(x,y){
  $("#zone-params-end-x").val(x);
  $("#zone-params-end-y").val(y);
}

function validateZoneData(zoneData) {
  var integerRegex = /^-?\d+$/;

  for (var key in zoneData) {
    var value = zoneData[key];
    if (value === '' || !integerRegex.test(value)) {
      return false;
    }
  }
  return true;
}


function retrieveCaseData(coords){
  $.ajax({
    type: "POST",
    url: 'tiled.php',
    data: {
      'coords':coords,
      'type':'info',
      'src':1
    }, // serializes the form's elements.
    success: function(response)
    {
      displayInfo($('<div>').html(response).find('#tile-info').html());
    }
  });
}

$(document).ready(function(){
  const infoModal = document.getElementById('tile-info');
  bindModalButton(infoModal);

});

//Display information with magnifying glass icon
function displayInfo(infosJson){
  
  let displayDiv = $("#info-display");

  let data = JSON.parse(infosJson);

  displayDiv.empty();

  
  data.forEach((item, index) => {
      let params = item.params ? item.params : "N/A"; 
      let line = `
          <div class="info-row" data-index="${index}">
              <span>Type: ${item.type}, Name: ${item.name}, Params: ${params}</span>
              <button class="delete-btn" data-coord-id="${item.coords_id}"  data-type="${item.type}">Supprimer</button>
          </div>
      `;
      displayDiv.append(line); 
  });
  
  
  const infoModal = document.getElementById('tile-info');
  //$('#info-modal').fadeIn();
  showModal(infoModal);
}


//Delete button on "info" modal
$(document).on("click", ".delete-btn", function () {
  $.ajax({
    type: "POST",
    url: 'tiled.php',
    data: {
      'delete':1,
      'coord-id':$(this).data("coord-id"),
      'type': $(this).data("type")
    }, 
    success: function()
    {
      document.location='tiled.php';
    }
  });
});