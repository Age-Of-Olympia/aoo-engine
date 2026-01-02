<?php

namespace App\View;

use Classes\Player;

class TutorialView
{
    public static function renderTutorial(Player $player): void
    {
        $file = 'datas/private/players/' . $player->id . '.msg.html';
        if (file_exists($file)) {
            unlink($file); // Delete the file
        }

        // Refresh player's view to show tutorial state
        $player->refresh_view();
        $player->refresh_data();
        $player->refresh_caracs();

        $player->getCoords();

        // Safety check for null coords
        $coordX = $player->coords->x ?? 0;
        $coordY = $player->coords->y ?? 0;

        echo '
<div id="tooltip"><div class="text">tooltip</div><div class="tooltip-next"><a href="#" class="next">[suite]</a></div></div>
';

?>
        <script>
            /* Tutorial data - scripts already loaded by Ui.php */
            window.playerId = <?php echo $_SESSION['playerId'] ?>;
            window.dataCoords = "<?php echo $coordX + 1 ?>,<?php echo $coordY ?>";
        </script>
<?php
    }
}
