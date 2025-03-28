<?php

echo '<div id="data" style="display: none;">';

include('scripts/view.php');

echo '</div>';

?>

<script>
$(document).ready(function(){
    // Ensure data contains only the inner HTML of #data
    const data = $('#data').find('#view').contents();

    // Function to update the view and reattach event listeners
    function updateView() {
        // Replace the contents of #view with data
        $('#view').empty().append(data);

        // Reattach click event listeners
        attachEventListeners();
    }

    // Function to attach click event listeners
    function attachEventListeners() {
        $('.case').off('click').on('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            //updateView();
        });
    }

    // Initial view update and event listener attachment
    updateView();

    // const targetNode = document.getElementById('ui-card');

    // function checkVisibility() {
    //     if (!$(targetNode).is(':visible')) {
    //         updateView();
    //     }
    // }

    // const observer = new MutationObserver(function(mutationsList) {
    //     for (const mutation of mutationsList) {
    //         if (mutation.attributeName === 'style' || mutation.attributeName === 'class') {
    //             checkVisibility();
    //         }
    //     }
    // });

    // observer.observe(targetNode, { attributes: true });
    // checkVisibility();
});

</script>
<script src="js/view.js"></script>