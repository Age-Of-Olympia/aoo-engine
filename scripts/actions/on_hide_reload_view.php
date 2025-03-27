<?php

echo '<div id="data" style="display: none;">';

include('scripts/view.php');

echo '</div>';

?>
<script>
$(document).ready(function(){
    // Ensure data contains only the inner HTML of #data
    const data = $('#data').find('#view').contents();

    // Replace the contents of #view with data
    $('#view').html(data);

    $(document).on('click', '.case', function(e){
        e.preventDefault();
        e.stopPropagation();
        updateView();
    });

    const targetNode = document.getElementById('ui-card');

    function checkVisibility() {
        if (!$(targetNode).is(':visible')) {
            updateView();
        }
    }

    function updateView() {
        // Update only the necessary parts of the DOM instead of reloading the page
        $('#view').html(data);
    }

    const observer = new MutationObserver(function(mutationsList) {
        for (const mutation of mutationsList) {
            if (mutation.attributeName === 'style' || mutation.attributeName === 'class') {
                checkVisibility();
            }
        }
    });

    observer.observe(targetNode, { attributes: true });
    checkVisibility();
});

</script>
