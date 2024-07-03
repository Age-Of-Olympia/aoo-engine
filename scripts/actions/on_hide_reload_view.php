<?php

echo '<div id="data" style="display: none;">';

include('scripts/view.php');

echo '</div>';


?>
<script>
$(document).ready(function(){


    var data = $('#data').html();

    $('#view').html('').html(data);


    $('.case').click(function(e){

        e.preventDefault();
        e.stopPropagation();

        document.location.reload();
    });



    // watch the disapearance of #ui-card to reload view

    var targetNode = document.getElementById('ui-card');

    // Function to check visibility
    function checkVisibility() {
        if ($(targetNode).is(':visible')) {
        } else {

            // div is invisible
            document.location.reload();
        }
    }

    // MutationObserver configuration
    var observer = new MutationObserver(function(mutationsList, observer) {
        for(var mutation of mutationsList) {
            if (mutation.attributeName === 'style' || mutation.attributeName === 'class') {
                checkVisibility();
            }
        }
    });

    // Start observing the target node for configured mutations
    observer.observe(targetNode, { attributes: true, childList: false, subtree: false });

    // Initial check
    checkVisibility();
});
</script>
