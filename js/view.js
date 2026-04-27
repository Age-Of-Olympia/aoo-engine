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

    // Right-click coordinate tool (available for everyone, TP button only for admins)
    $(document).on('contextmenu', '.case', function(e) {
        e.preventDefault();

        var coords = $(this).data('coords');

        if(!coords) {
            return;
        }

        let [x, y] = coords.split(',');

        // Build HTML with coords button (always shown) and TP button (admin only)
        var html = '<button id="admin-coords-close" title="Fermer">✕</button><div id="case-coords"><button OnClick="copyToClipboard(this);">x'+ x +',y'+ y +'</button>';

        if(window.isAdmin) {
            html += '<br><button onclick="teleport(\'' + coords + '\')">TP</button>';
        }

        html += '</div>';

        $('#admin-coords').html(html);

        // Bind close button
        $('#admin-coords-close').off('click').on('click', function(e) {
            e.stopPropagation();
            $('#admin-coords').html('');
        });
    });



    $('.case').click(function(e){

        // Block clicks if tutorial overlay is in blocking mode
        if ($('#tutorial-overlay').hasClass('blocking')) {
            return false;
        }

        $('#destroy-rect').hide();
        $('#destroy-img').hide();

        $('#go-rect').hide();
        $('#go-img').hide();


        var coords = $(this).data('coords');

        var i = $(this).attr('x');
        var j = $(this).attr('y');


        var $case = $('[x="'+ i +'"][y="'+ j +'"]');

        let [x, y] = coords.split(',');


        // show go button if applicable (no player standing on the case)
        var hasPlayer = $('image[data-table="players"][x="'+ i +'"][y="'+ j +'"]').length > 0;

        if($case.hasClass('go') && !hasPlayer){

            $('#go-rect')
                .show()
                .attr({'x': i, 'y': j})
                .data('coords', x +','+ y);

            var imgY = j - 20;

            $('#go-img').show().attr({'x': i, 'y': imgY});
        }


        if($('.clicked-cases-reseter[data-coords="'+ coords +'"]')[0] != null){

            $('.clicked-cases-reseter[data-coords="'+ coords +'"]').remove();

        } else if(window.clickedCases[coords]){

            let data = window.clickedCases[coords];

            $('#ajax-data').html(data);

            return false;
        }


        $.ajax({
            type: "POST",
            url: 'observe.php',
            data: {'coords':coords},
            success: function(data)
            {
                $('#ajax-data').html(data);

                window.clickedCases[coords] = data;
            }
        });

        return false;
    });


    $('#go-rect').click(function(e){

        var coords = $(this).data('coords');

        /* Validate coords before sending */
        if (!coords || typeof coords !== 'string' || !coords.includes(',')) {
            console.error('Invalid coords for movement:', coords);
            alert('Erreur: coordonnées invalides');
            document.location.reload();
            return false;
        }

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
