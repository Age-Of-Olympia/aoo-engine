<?php
/* The eraser: every layer the info panel deletes one by one. The board is
 * refreshed once by erase_or_create_tile.php. */
foreach ((new \App\Service\Map\TileEraserService())->eraseAll((int) $coordsId) as $notice) {
    echo '<div class="erase-notice">' . $notice . '</div>';
}
