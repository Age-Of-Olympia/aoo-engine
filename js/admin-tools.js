// Shared admin tools for both tiled editor and main game view

function teleport(coords){
    if(!confirm('Téléporter vers ' + coords + ' ?')){
        return false;
    }

    // Remember current selection (for tiled editor)
    var $selected = $('.map.selected');
    var selectedToolName = $selected.data('name');
    var selectedToolSrc = $selected.attr('src');
    var selectedParams = $selected.data('params') ? $('#' + $selected.data('type') + '-params').val() : '';

    $.ajax({
        type: "POST",
        url: 'tiled.php',
        data: {
            'coords': coords,
            'type': 'tp',
            'src': 1
        },
        success: function(data) {
            // If we're in tiled editor (has map-view-container), reload only the map view
            if($('#map-view-container').length) {
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
            } else {
                // For main game view, reload the entire page
                document.location.reload();
            }
        }
    });
}
