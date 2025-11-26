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

function teleport(coords){
  
  if(!confirm('TP?')){
      return false;
  }

  $.ajax({
      type: "POST",
      url: 'tiled.php',
      data: {
          'coords':coords,
          'type':'tp',
          'src':1
      }, // serializes the form's elements.
      success: function(data)
      {
          // alert(data);
        document.location='tiled.php';
      }
  });


}


$(document).ready(function(){


  $('img').each(function(e){

      $(this).attr('title', $(this).data('name'));
  });


  var $customCursor = $('<img>', {
      class: 'custom-cursor',
      src: $('.map').attr('src')
  }).appendTo('body').hide();


  selectPreviousTool($customCursor);


$('.case').on('contextmenu', function(e) {
    e.preventDefault();

    var coords = $(this).data('coords');

    var coordsFull = $(this).data('coords-full');

    let [x, y] = coords.split(',');

    // show coords button
    $('#ajax-data').html('<div id="case-coords"><button OnClick="copyToClipboard(this);">x'+ x +',y'+ y +'</button><br>' +
        '<button OnClick="copyToClipboard(this);">'+coordsFull+'</button><br>'+
        '<button onclick="teleport(\'' +coords + '\')">TP</button><br>'+
        '<button OnClick="setZoneBeginCoords('+x+','+y+');" title="Debut de zone"><span class="ra ra-overhead"/></button>' +
        '<button OnClick="setZoneEndCoords('+x+','+y+');" title="Fin de zone"><span class="ra ra-underhand"/></button></div>');


  });

  $('.case').click(function(e){

      // Block clicks if tutorial overlay is in blocking mode
      if ($('#tutorial-overlay').hasClass('blocking')) {
          return false;
      }

      var $selected = $('.selected');

      if(!$selected[0]){
        teleport($(this).data('coords'));

        return false;

      }else if( $selected.hasClass('select-name') && $selected.data('name') === 'info'){
          retrieveCaseData($(this).data('coords'));
          return false;
      }

      var src = $selected.attr('src');

      var params = '';

      if($selected.hasClass('ele')){

          src = $selected.data('element');
      }

      if($selected.hasClass('select-name')){

          src = $selected.data('name');
      }

      if($selected.data('params') != null){

          params = $('#'+ $selected.data('type') +'-params').val();
      }


      $.ajax({
          type: "POST",
          url: 'tiled.php',
          data: {
            'coords':$(this).data('coords'),
            'type':$selected.data('type'),
            'src':src,
            'params':params
          }, // serializes the form's elements.
          success: function(data)
          {
            // alert(data);
            document.location='tiled.php?selectedTool='+$selected.data('name')+'&selectedParams='+params;
          }
      });
  });

  $('.map').click(function(e){
      if (!$(this).hasClass('selected')) {

        var $paramsField = $('#' + $(this).data('type') + '-params');

        if ($(this).data('params')) {

          let params = $(this).data('params');

          // if($paramsField.val() == ''){

              $paramsField.val(params);
          // }

          $paramsField.focus().select();
        }
        else{
          $paramsField.val('');
        }

        $('.map').removeClass('selected').css('border', '0px');
        $(this).addClass('selected').css('border', '1px solid red');


        // Position de l'image sur la page
        var offsetX = e.offsetX - 25; // 25 pour centrer l'image (50/2)
        var offsetY = e.offsetY - 25; // 25 pour centrer l'image (50/2)

        $customCursor.css({
            left: e.pageX + offsetX + 'px',
            top: e.pageY + offsetY + 'px'
        }).attr('src', $(this).attr('src')).show();

          $('body').on('mousemove', function(e) {
              $customCursor.css({
                  left: e.pageX - 25 +'px',
                  top: e.pageY - 25+'px'
              });
          });

      } else{
        $(this).removeClass('selected').css('border', '0px');
      }
  });


  $(document).on('click', function(e) {
      if (!["map", "modal-bg", "closeButton", "modal-content", "modal"].some(cls => $(e.target).hasClass(cls)) 
        && $(e.target).attr('type') !== 'text' ) {
          $customCursor.hide();
          $('body').off('mousemove');
          $('.map').removeClass('selected').css('border', '0px');
      }
  });

});