<?php

namespace App\View;
use Classes\Player;



class OnHideReloadView
{
    public static function render(Player $player): void
    {

        // The caracs were read before the action: re-read them, or a
        // perception buff redraws (and caches) the board at the old size
        $player->get_caracs();

        echo '<div id="data" style="display: none;">';

        MainView::render($player);

        echo '</div>';

?>

        <script>
            $(document).ready(function() {
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
                    $('.case').off('click').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        //updateView();
                    });
                }

                // Initial view update and event listener attachment
                updateView();

            });
        </script>
        <script src="js/blocked-tiles.js?v=20260727"></script>
        <script src="js/view.js?v=20261020f"></script>
<?php
    }
}
