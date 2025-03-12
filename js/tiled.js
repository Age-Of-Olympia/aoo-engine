function selectPreviousTool($customCursor){
  let selectedTool = getParameterByName('selectedTool');

  if (selectedTool) {
    $('.map').filter(function() {

      return $(this).data('name') === selectedTool;
    }).each(function() {
      $(this).addClass('selected').css('border', '1px solid red');

      $customCursor.find('img').attr('src', $(this).attr('src')).show();

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

  // Create custom cursor container
  var $customCursor = $('<div>', {
      class: 'custom-cursor',
      css: {
          'position': 'absolute',
          'pointer-events': 'none',
          'z-index': 1000,
          'display': 'none'
      }
  }).appendTo('body');

  // Add cursor grid container
  var $cursorGrid = $('<div>', {
      class: 'cursor-grid',
      css: {
          'display': 'grid',
          'gap': '1px',
          'background': 'rgba(255,255,255,0.3)',
          'padding': '1px',
          'border-radius': '2px'
      }
  }).appendTo($customCursor);

  selectPreviousTool($customCursor);

  $('.case').on('contextmenu', function(e) {
    e.preventDefault();

    var coords = $(this).data('coords');

    var coordsFull = $(this).data('coords-full');

    let [x, y] = coords.split(',');
    x = parseInt(x);
    y = parseInt(y);

    // show coords button
    $('#ajax-data').html('<div id="case-coords"><button OnClick="copyToClipboard(this);">x'+ x +',y'+ y +'</button><br>' +
        '<button OnClick="copyToClipboard(this);">'+coordsFull+'</button><br>'+
        '<button onclick="teleport(\'' +coords + '\')">TP</button><br>'+
        '<button OnClick="setZoneBeginCoords('+x+','+y+');" title="Debut de zone"><span class="ra ra-overhead"/></button>' +
        '<button OnClick="setZoneEndCoords('+x+','+y+');" title="Fin de zone"><span class="ra ra-underhand"/></button></div>');
  });

  // Handle clicking on foreground items (both single images and split image overlays)
  $('.map.foregrounds, .clickable-overlay').click(function(e) {
    e.preventDefault();
    
    // Remove previous selection
    $('.selected').removeClass('selected');
    $('.foreground-item').css('border-color', '#eee');
    
    // Get the foreground item container
    let $item = $(this).closest('.foreground-item');
    
    // Add selection styling
    $item.css('border-color', '#ff3300');
    $(this).addClass('selected');
    
    // Clear previous cursor
    $customCursor.empty();
    $cursorGrid.empty();

    // Handle split images
    if ($(this).data('is-split') === 'true') {
      let gridSize = parseInt($(this).data('grid-size'));
      let parts = JSON.parse($(this).data('parts'));
      
      // Set up cursor grid
      $cursorGrid.css({
        'display': 'grid',
        'grid-template-columns': `repeat(${gridSize}, 50px)`,
        'gap': '1px',
        'padding': '1px'
      }).appendTo($customCursor);

      // Add all parts to cursor
      parts.forEach(part => {
        $('<img>', {
          src: part.url,
          css: {
            'width': '50px',
            'height': '50px',
            'display': 'block',
            'image-rendering': 'pixelated'
          }
        }).appendTo($cursorGrid);
      });
    } else {
      // Single image cursor
      $('<img>', {
        src: $(this).attr('src'),
        css: {
          'width': '50px',
          'height': '50px',
          'display': 'block',
          'image-rendering': 'pixelated'
        }
      }).appendTo($customCursor);
    }

    // Show cursor and set up movement
    $customCursor.show();
    
    // Update cursor position on mouse move
    $('body').off('mousemove').on('mousemove', function(e) {
      let isSplit = $('.selected').data('is-split') === 'true';
      let gridSize = isSplit ? parseInt($('.selected').data('grid-size')) : 1;
      let offset = (gridSize * 25);
      
      $customCursor.css({
        left: e.pageX - offset + 'px',
        top: e.pageY - offset + 'px'
      });
    });
  });

  // Handle placing foregrounds on the map
  $('.case').click(function(e) {
    var $selected = $('.selected');

    if(!$selected[0]){
      teleport($(this).data('coords'));
      return;
    }

    // Get the clicked tile coordinates
    let [baseX, baseY] = $(this).data('coords').split(',');
    baseX = parseInt(baseX);
    baseY = parseInt(baseY);

    // For split foregrounds, place all parts at once
    if ($selected.data('is-split') === 'true') {
      let gridSize = parseInt($selected.data('grid-size'));
      let parts = JSON.parse($selected.data('parts'));
      let baseName = $selected.data('name');
      
      // Place all parts in their correct positions
      Promise.all(parts.map((part, index) => 
        new Promise((resolve, reject) => {
          let x = baseX + (index % gridSize);
          let y = baseY + Math.floor(index / gridSize);
          $.ajax({
            type: "POST",
            url: 'tiled.php',
            data: {
              'coords': x + ',' + y,
              'type': 'foregrounds',
              'src': part.name,
              'params': JSON.stringify({
                'baseName': baseName,
                'gridSize': gridSize,
                'partIndex': index,
                'isPartOfSet': true
              })
            },
            success: resolve,
            error: reject
          });
        })
      )).then(() => {
        document.location = 'tiled.php?selectedTool=' + baseName;
      });
    } else {
      // Regular single-tile placement
      $.ajax({
        type: "POST",
        url: 'tiled.php',
        data: {
          'coords': $(this).data('coords'),
          'type': $selected.data('type'),
          'src': $selected.data('name')
        },
        success: function(data) {
          if(data == 'ok'){
            document.location = 'tiled.php?selectedTool=' + $selected.data('name');
          }
        }
      });
    }
  });

  $(document).on('click', function(e) {
      if (!["map", "modal-bg", "closeButton", "modal-content", "modal"].some(cls => $(e.target).hasClass(cls)) 
        && $(e.target).attr('type') !== 'text' ) {
          $customCursor.hide();
          $('body').off('mousemove');
          $('.map').removeClass('selected').css('border', '');
      }
  });

});