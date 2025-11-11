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
  // Remember current selection
  var $selected = $('.map.selected');
  var selectedToolName = $selected.data('name');
  var selectedToolSrc = $selected.attr('src');
  var selectedParams = $selected.data('params') ? $('#' + $selected.data('type') + '-params').val() : '';

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
      // Reload only the map view
      $.ajax({
        type: "GET",
        url: 'tiled.php',
        data: {'view_only': 1},
        success: function(viewHtml) {
          $('#map-view-container').html(viewHtml);
          // Close the modal
          const infoModal = document.getElementById('tile-info');
          if(infoModal) {
            infoModal.style.display = 'none';
          }

          // Reselect tool if there was one
          if(selectedToolName) {
            $('.map').filter(function() {
              return $(this).data('name') === selectedToolName;
            }).each(function() {
              $(this).addClass('selected').css('border', '1px solid red');
              var $customCursor = $('.custom-cursor');
              $customCursor.attr('src', selectedToolSrc).show();

              // Rebind mousemove handler for cursor tracking
              $('body').off('mousemove.customcursor').on('mousemove.customcursor', function(e) {
                $customCursor.css({
                  left: e.pageX - 25 +'px',
                  top: e.pageY - 25+'px'
                });
              });

              if(selectedParams) {
                $('#' + $(this).data('type') + '-params').val(selectedParams);
              }
            });
          }
        }
      });
    }
  });
});

function teleport(coords){

  if(!confirm('TP?')){
      return false;
  }

  // Remember current selection
  var $selected = $('.map.selected');
  var selectedToolName = $selected.data('name');
  var selectedToolSrc = $selected.attr('src');
  var selectedParams = $selected.data('params') ? $('#' + $selected.data('type') + '-params').val() : '';

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
        // Reload only the map view
        $.ajax({
          type: "GET",
          url: 'tiled.php',
          data: {'view_only': 1},
          success: function(viewHtml) {
            $('#map-view-container').html(viewHtml);

            // Reselect tool if there was one
            if(selectedToolName) {
              $('.map').filter(function() {
                return $(this).data('name') === selectedToolName;
              }).each(function() {
                $(this).addClass('selected').css('border', '1px solid red');
                var $customCursor = $('.custom-cursor');
                $customCursor.attr('src', selectedToolSrc).show();

                // Rebind mousemove handler for cursor tracking
                $('body').off('mousemove.customcursor').on('mousemove.customcursor', function(e) {
                  $customCursor.css({
                    left: e.pageX - 25 +'px',
                    top: e.pageY - 25+'px'
                  });
                });

                if(selectedParams) {
                  $('#' + $(this).data('type') + '-params').val(selectedParams);
                }
              });
            }
          }
        });
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


  // Use event delegation so it works with dynamically loaded map content
  $(document).on('contextmenu', '.case', function(e) {
    e.preventDefault();

    var coords = $(this).data('coords');

    var coordsFull = $(this).data('coords-full');

    let [x, y] = coords.split(',');

    // show coords button
    $('#ajax-data').html('<button id="ajax-data-close" title="Fermer">✕</button><div id="case-coords"><button OnClick="copyToClipboard(this);">'+coordsFull+'</button><br>'+
        '<button onclick="teleport(\'' +coords + '\')">TP</button><br>'+
        '<button OnClick="setZoneBeginCoords('+x+','+y+');" title="Debut de zone"><span class="ra ra-overhead"/></button>' +
        '<button OnClick="setZoneEndCoords('+x+','+y+');" title="Fin de zone"><span class="ra ra-underhand"/></button></div>');

    $('#ajax-data').addClass('has-content');

    // Rebind close button
    $('#ajax-data-close').off('click').on('click', function(e) {
        e.stopPropagation();
        $('#ajax-data').html('<button id="ajax-data-close" title="Fermer">✕</button>').removeClass('has-content');
    });


  });

  // Use event delegation so it works with dynamically loaded map content
  $(document).on('click', '.case', function(e){

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


      // Store current selection before AJAX
      var selectedToolName = $selected.data('name');
      var selectedToolSrc = $selected.attr('src');

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
            // Reload only the map view instead of the entire page
            $.ajax({
              type: "GET",
              url: 'tiled.php',
              data: {
                'view_only': 1,
                'selectedTool': selectedToolName,
                'selectedParams': params
              },
              success: function(viewHtml) {
                $('#map-view-container').html(viewHtml);

                // Reselect the tool after map reload
                $('.map').filter(function() {
                  return $(this).data('name') === selectedToolName;
                }).each(function() {
                  $(this).addClass('selected').css('border', '1px solid red');

                  // Update cursor and rebind mousemove
                  var $customCursor = $('.custom-cursor');
                  $customCursor.attr('src', selectedToolSrc).show();

                  // Rebind mousemove handler for cursor tracking
                  $('body').off('mousemove.customcursor').on('mousemove.customcursor', function(e) {
                    $customCursor.css({
                      left: e.pageX - 25 +'px',
                      top: e.pageY - 25+'px'
                    });
                  });

                  // Restore params if any
                  var $paramsField = $('#' + $(this).data('type') + '-params');
                  if($paramsField.length && params) {
                    $paramsField.val(params);
                  }
                });
              }
            });
          }
      });
  });

  $('.map').click(function(e){
      if (!$(this).hasClass('selected')) {

        var $paramsField = $('#' + $(this).data('type') + '-params');

        if ($(this).data('params')) {

          let params = $(this).data('params');

          // Only set default params if field is empty - preserve user input
          if($paramsField.val() == ''){
              $paramsField.val(params);
          }

          // Only focus on desktop to avoid unwanted scrolling on mobile
          if (window.innerWidth > 768) {
            $paramsField.focus().select();
          }
        }
        else{
          // Only clear field if it's empty - preserve user input for tools without default params
          if($paramsField.val() == ''){
            $paramsField.val('');
          }
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