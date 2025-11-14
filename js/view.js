$(document).ready(function(){


    window.clickedCases = [];

    // Caracs panel persistence (save/restore state across reloads)
    $(document).on('click', '#show-caracs', function() {
        // Wait for panel to toggle, then save state
        setTimeout(function() {
            var isOpen = $('#load-caracs').is(':visible');
            sessionStorage.setItem('caracs_panel_open', isOpen ? 'true' : 'false');
            console.log('[View] Saved caracs panel state:', isOpen);
        }, 100);
    });

    // Auto-restore caracs panel if it was open before reload
    if (sessionStorage.getItem('caracs_panel_open') === 'true') {
        console.log('[View] Restoring open caracs panel');
        // Trigger click to open panel
        setTimeout(function() {
            $('#show-caracs').click();
        }, 500);
    }


    $('.case').click(function(e){


        $('#destroy-rect').hide();
        $('#destroy-img').hide();

        $('#go-rect').hide();
        $('#go-img').hide();


        var coords = $(this).data('coords');

        var i = $(this).attr('x');
        var j = $(this).attr('y');


        var $case = $('[x="'+ i +'"][y="'+ j +'"]');

        if($case.not('.case, [data-table="tiles"], [data-table="foregrounds"], [data-table="plants"], [data-table="items"], [data-table="elements"], [data-table="routes"], #go-img, #go-rect, #destroy-img, #destroy-rec')[0]){


            if($('.clicked-cases-reseter[data-coords="'+ coords +'"]')[0] != null){

                $('.clicked-cases-reseter[data-coords="'+ coords +'"]').remove();
                var remove = true;
            }


            if(window.clickedCases[coords] && !remove){


                let data = window.clickedCases[coords];

                $('#ajax-data').html(data);

                return false;
            }


            $.ajax({
                type: "POST",
                url: 'observe.php',
                data: {'coords':coords}, // serializes the form's elements.
                success: function(data)
                {
                    // alert(data);

                    console.log('db query');

                    $('#ajax-data').html(data);

                    window.clickedCases[coords] = data;
                }
            });

            return false;
        }


        let [x, y] = coords.split(',');


        // show coords button
        $('#ajax-data').html('<div id="case-coords"><button OnClick="copyToClipboard(this);">x'+ x +',y'+ y +'</button></div>');


        if($case.hasClass('go')){


            $('#go-rect')
                .show()
                .attr({'x': i, 'y': j})
                .data('coords', x +','+ y);

            var imgY = j - 20 ;

            $('#go-img').show().attr({'x': i, 'y': imgY});
        }
    });


    $('#go-rect').click(function(e){

        var coords = $(this).data('coords');

        $('#go-rect').off('click');
        $('#go-img').attr('href', 'img/ui/view/gear.webp');
        // $('#view').css({'filter':'grayscale(1)', 'transition':'filter 0.5s'});

        $.ajax({
            type: "POST",
            url: 'go.php',
            data: {'coords':coords}, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);

                if(data.trim() != ''){


                    $('#ajax-data').html(data);

                    return false;
                }

                // Notify tutorial system about successful movement
                if (window.tutorialUI && window.tutorialUI.isActive) {
                    console.log('[View] Notifying tutorial about movement to:', coords);

                    // Send validation but skip UI update (page will reload)
                    window.notifyTutorial('movement', {
                        action: 'move',  // Required for validation
                        coords: coords,
                        timestamp: Date.now()
                    }, true); // skipUIUpdate = true to avoid showing next step before reload

                    // Give tutorial 100ms to save, then reload
                    setTimeout(function() {
                        document.location.reload();
                    }, 100);
                } else {
                    // No tutorial active, reload immediately
                    document.location.reload();
                }
            }
        });
    });
});
