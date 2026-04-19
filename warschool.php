<?php
use App\Factory\PlayerFactory;
use Classes\Ui;
use Classes\WarSchool;

use App\View\WarSchool\MeleeView;
use App\View\WarSchool\DistanceView;
use App\View\WarSchool\MagicView;
use App\View\WarSchool\SpellView;
use App\View\WarSchool\StealthView;
use App\View\WarSchool\SurvivalView;

require_once('config.php');

$ui = new Ui('École de guerre', true);

$player = PlayerFactory::legacy($_SESSION['playerId']);
$player->get_data();

if (!isset($_GET['targetId'])) {
    exit('error no trainer');
}

$trainer = PlayerFactory::legacy($_GET['targetId']);

// check access
$accessError = WarSchool::checkAccess($player, $trainer);
if ($accessError !== null) {
    exit($accessError);
}

// menu
if (!isset($_GET['hideMenu'])) {

    echo '
    <div>
        <a href="index.php">
            <button><span class="ra ra-sideswipe"></span> Retour</button>
        </a>

        <a href="warschool.php?targetId=' . $trainer->id . '">
            <button><span class="ra ra-speech-bubbles"></span></button>
        </a>

        <a href="warschool.php?targetId=' . $trainer->id . '&melee">
            <button><span class="ra ra-crossed-swords"></span> Mêlée</button>
        </a>

        <a href="warschool.php?targetId=' . $trainer->id . '&distance">
            <button><span class="ra ra-archer"></span> Distance</button>
        </a>

        <a href="warschool.php?targetId=' . $trainer->id . '&magic">
            <button><span class="ra ra-fairy-wand"></span> Magie</button>
        </a>

        <a href="warschool.php?targetId=' . $trainer->id . '&spells">
            <button><span class="ra ra-book"></span> Sorts</button>
        </a>

        <a href="warschool.php?targetId=' . $trainer->id . '&stealth">
            <button><span class="ra ra-hood"></span> Furtivité</button>
        </a>

        <a href="warschool.php?targetId=' . $trainer->id . '&survival">
            <button><span class="ra ra-campfire"></span> Survie</button>
        </a>
    </div>';
}

$warschool = new WarSchool($trainer);

if (isset($_GET['melee'])) {
    MeleeView::render($player, $trainer);
}
elseif (isset($_GET['distance'])) {
    DistanceView::render($player, $trainer);
}
elseif (isset($_GET['magic'])) {
    MagicView::render($player, $trainer);
}
elseif (isset($_GET['spells'])) {
    SpellView::render($player, $trainer);
}
elseif (isset($_GET['stealth'])) {
    StealthView::render($player, $trainer);
}
elseif (isset($_GET['survival'])) {
    SurvivalView::render($player, $trainer);
}
else {
    $bg = 'img/dialogs/bg/' . $trainer->id . '.webp';
    if (!file_exists($bg)) { $bg = 'img/dialogs/bg/marchande.webp'; }

    $options = [
        'name'   => $trainer->data->name,
        'avatar' => $bg,
        'dialog' => 'trainer',
        'text'   => 'C\'est un plaisir de te revoir. Besoin d\'un entraînement ?',
        'player' => $player,
        'target' => $trainer
    ];

    echo Ui::get_dialog($player, $options);
}

?>
