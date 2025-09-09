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

        $player->getCoords();

        echo '
<div id="tooltip"><div class="text">tooltip</div><div class="tooltip-next"><a href="#" class="next">[suite]</a></div></div>
';

?>
        <script>
            window.playerId = <?php echo $_SESSION['playerId'] ?>;
            window.dataCoords = "<?php echo $player->coords->x + 1 ?>,<?php echo $player->coords->y ?>";
        </script>
        <script src="js/tutorial.js"></script>
<?php
    }
}
