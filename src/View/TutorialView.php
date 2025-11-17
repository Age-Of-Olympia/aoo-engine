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

        <!-- Tutorial CSS -->
        <link href="css/tutorial/tutorial.css?v=20251115a" rel="stylesheet">

        <!-- Tutorial JavaScript - NEW MODULAR SYSTEM -->
        <script src="js/tutorial/TutorialUI.js?v=20251115m"></script>
        <script src="js/tutorial/TutorialHighlighter.js?v=20251115b"></script>
        <script src="js/tutorial/TutorialTooltip.js?v=20251116c"></script>
        <script src="js/tutorial/TutorialInit.js?v=20251115a"></script>
<?php
    }
}
