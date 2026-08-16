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
use App\View\WarSchool\ReassignationView;

/*
 * Corps de la page école de guerre, partagé entre la page complète
 * (warschool.php, enveloppe Ui) et le panneau glissant du HUD
 * (load_warschool.php). Les onglets restent des liens warschool.php :
 * le routeur de panneaux (js/hud.js) les réécrit en fragments.
 */

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

/* Chaque école n'enseigne que SES disciplines — celles que son dialogue
 * mentionne (BuildingService::servesCounter). Le menu suit, et la
 * branche refuse ce que le menu ne propose pas. */
$buildingService = new \App\Service\BuildingService();

$disciplineTabs = [
    'melee'    => '<button><span class="ra ra-crossed-swords"></span> Mêlée</button>',
    'distance' => '<button><span class="ra ra-archer"></span> Distance</button>',
    'magic'    => '<button><span class="ra ra-fairy-wand"></span> Magie</button>',
    'spells'   => '<button><span class="ra ra-book"></span> Sorts</button>',
    'stealth'  => '<button><span class="ra ra-hood"></span> Furtivité</button>',
    'survival' => '<button><span class="ra ra-campfire"></span> Survie</button>',
];

$servedTabs = [];
foreach (array_keys($disciplineTabs) as $tab) {
    if ($buildingService->servesCounter((int) $trainer->id, 'warschool.php', $tab)) {
        $servedTabs[] = $tab;
    }
}

foreach (array_keys($disciplineTabs) as $tab) {
    if (isset($_GET[$tab]) && !in_array($tab, $servedTabs, true)) {
        exit('On n\'enseigne pas cela dans cette école.');
    }
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
        </a>';

    foreach ($servedTabs as $tab) {
        echo '
        <a href="warschool.php?targetId=' . $trainer->id . '&' . $tab . '">
            ' . $disciplineTabs[$tab] . '
        </a>';
    }

    /* Reassignment is no discipline: it undoes what the Pi bought,
     * wherever they were spent. Every school offers it, served counters
     * or not — it is deliberately outside $servedTabs. */
    echo '
        <a href="warschool.php?targetId=' . $trainer->id . '&reassignation">
            <button><span class="ra ra-regeneration"></span> Réassignation</button>
        </a>';

    echo '
    </div>';
}

$warschool = new WarSchool($trainer);

/*
 * Typography shared by the school's tabs. This body is the single way in,
 * full page as well as HUD panel: dress the tabs here rather than in each
 * view, and a new tab joins the list to get the same look.
 *
 * The rules stay inline rather than in a sheet: the HUD panel arrives by
 * AJAX, without the Ui wrapper that loads the sheets.
 */
$skillTabs = ['melee', 'distance', 'magic', 'spells', 'stealth', 'survival', 'reassignation'];
$onSkillTab = (bool) array_intersect($skillTabs, array_keys($_GET));

if ($onSkillTab) {
    echo '<style>'
        . '.ws-content h1{font-size:1.6em}'
        . '.ws-content h2{font-family:sans-serif;font-size:1.1em;font-weight:bold}'
        . '.ws-content h3{font-family:sans-serif;font-size:1.05em;font-weight:normal}'
        . '.ws-content .ws-info{font-family:sans-serif;font-size:1.05em;text-align:center;margin:6px 0}'
        . '</style>';
    echo '<div class="ws-content">';
}

if (isset($_GET['melee'])) {
    MeleeView::render($player);
}
elseif (isset($_GET['distance'])) {
    DistanceView::render($player);
}
elseif (isset($_GET['magic'])) {
    MagicView::render($player);
}
elseif (isset($_GET['spells'])) {
    SpellView::render($player);
}
elseif (isset($_GET['stealth'])) {
    StealthView::render($player);
}
elseif (isset($_GET['survival'])) {
    SurvivalView::render($player);
}
elseif (isset($_GET['reassignation'])) {
    ReassignationView::render($player);
}
else {
    /* L'école est un BÂTIMENT (la garde ne laisse passer que lui) :
     * son dialogue (buildings.dialog) et son visuel. */
    $details = (new \App\Service\BuildingService())->getDetails((int) $trainer->id);
    $dialog = (string) $details?->getDialog();

    $bg = 'img/dialogs/bg/' . $trainer->id . '.webp';
    if (!file_exists($bg)) {
        /* Sprite du type, sinon le même repli « initiales dans un
         * cadre » que la fiche (un type sans visuel résout à ''). */
        $bg = \App\Service\BuildingService::resolveAvatar((string) ($trainer->data->race ?? ''));
        if ($bg === '') {
            $bg = \Classes\View::structureInitialsAvatar((string) $trainer->data->name);
        }
    }

    $options = [
        'name'   => $trainer->data->name,
        'avatar' => $bg,
        'dialog' => $dialog,
        'text'   => 'C\'est un plaisir de te revoir. Besoin d\'un entraînement ?',
        'player' => $player,
        'target' => $trainer
    ];

    echo Ui::get_dialog($player, $options);
}

if ($onSkillTab) {
    echo '</div>';
}
