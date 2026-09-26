<?php
/* The info panel's « Supprimer »: one layer of the cell. */
try {
    $notices = (new \App\Service\Map\TileEraserService())->eraseLayer((int) $coordsId, (string) $type, !empty($_POST['force']));
} catch (\InvalidArgumentException) {
    exit('error type');
}

foreach ($notices as $notice) {
    echo '<div class="erase-notice">' . $notice . '</div>';
}

\Classes\View::refresh_players_svg_at((int) $coordsId);
