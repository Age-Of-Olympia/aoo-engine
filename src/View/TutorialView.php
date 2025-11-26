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

        // Safety check for null coords
        $coordX = $player->coords->x ?? 0;
        $coordY = $player->coords->y ?? 0;

        echo '
<div id="tooltip"><div class="text">tooltip</div><div class="tooltip-next"><a href="#" class="next">[suite]</a></div></div>
';

?>
        <script>
            window.playerId = <?php echo $_SESSION['playerId'] ?>;
            window.dataCoords = "<?php echo $coordX + 1 ?>,<?php echo $coordY ?>";
        </script>

        <!-- Tutorial CSS -->
        <link href="css/tutorial/tutorial.css?v=20251125M" rel="stylesheet">

        <!-- Tutorial JavaScript - NEW MODULAR SYSTEM -->
        <script src="js/tutorial/TutorialPositionManager.js?v=20251118a"></script>
        <script src="js/tutorial/TutorialUI.js?v=20251126i"></script>
        <script src="js/tutorial/TutorialHighlighter.js?v=20251125e"></script>
        <script src="js/tutorial/TutorialTooltip.js?v=20251126h"></script>
        <script src="js/tutorial/TutorialInit.js?v=20251122d"></script>
<?php
    }
}
