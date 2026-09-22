<?php
use App\Factory\PlayerFactory;
use App\Service\Counter\CounterAccessService;
use App\Service\Counter\CounterCatalog;
use Classes\Ui;
use Classes\WarSchool;

use App\View\WarSchool\SkillTreeView;
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

/* Chaque école n'enseigne que SES disciplines — celles que son dialogue
 * mentionne. Le menu suit, et la garde refuse ce que le menu ne propose
 * pas : l'URL directe ne sert à rien. */
$counters = new CounterAccessService();
$servedTabs = $counters->servedTabs((int) $trainer->id, CounterCatalog::WAR_SCHOOL);

$tab = null;
foreach (array_keys(CounterCatalog::tabs(CounterCatalog::WAR_SCHOOL)) as $candidate) {
    if (isset($_GET[$candidate])) {
        $tab = (string) $candidate;
        break;
    }
}

$accessError = $counters->check($player, $trainer, CounterCatalog::WAR_SCHOOL, $tab);
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
        </a>';

    foreach ($servedTabs as $servedTab) {
        echo '
        <a href="warschool.php?targetId=' . $trainer->id . '&' . $servedTab . '">
            ' . CounterCatalog::menuButton(CounterCatalog::WAR_SCHOOL, $servedTab) . '
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
 */
$skillTabs = ['melee', 'distance', 'magic', 'spells', 'stealth', 'survival', 'reassignation'];
$onSkillTab = (bool) array_intersect($skillTabs, array_keys($_GET));

if ($onSkillTab) {
    echo SkillTreeView::styles();
    echo '<div class="ws-content">';
}

$treeTab = array_intersect_key(SkillTreeView::GET_KEYS, $_GET);
if ($treeTab !== []) {
    (new SkillTreeView())->render($player, reset($treeTab));
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
