<?php

use App\Factory\PlayerFactory;
use App\Service\FactionService;
use App\View\FactionView;
use Classes\Db;

/*
 * Corps de la page de faction (?faction=), partagé entre la page
 * complète (faction.php, enveloppe Ui) et le panneau glissant du HUD
 * (load_faction.php).
 */

$facJson = (new FactionService())->getFactionData($_GET['faction'] ?? '');

if(!$facJson){

    exit('error faction');
}


/* The page lives in the HUD side panel as well as full screen: each
 * section folds (native details) and its table scrolls sideways INSIDE
 * its frame instead of overflowing the panel or a mobile screen. */
echo '<div class="faction-page">
<style>
    /* Titles read from the left, like the rest of the game; only the
     * tables keep their centred marble. */
    .faction-page .faction-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .faction-page table.marbre { margin: 0 auto; }
    .faction-page details.faction-section { margin: 0.6em 0; }
    .faction-page details.faction-section summary { cursor: pointer; }
    .faction-page details.faction-section summary h2 { display: inline-block; margin: 0.2em 0; }
    .faction-page td img { max-height: 40px; }
    .faction-page .faction-ladder-names input { display: block; width: 100%; min-width: 9em; box-sizing: border-box; margin: 2px 0; }
    .faction-page .faction-ladder-actions { display: flex; flex-direction: column; gap: 4px; align-items: stretch; }
    .faction-page .faction-ladder-arrows { display: flex; gap: 4px; }
    .faction-page .faction-ladder-arrows button { flex: 1; }
    @media (max-width: 700px) {
        .faction-page table.marbre { font-size: 0.85em; }
        .faction-page td, .faction-page th { padding: 2px 4px; }
        .faction-page td img { max-height: 28px; }
    }
</style>';

/* The crest FOLLOWS the title, on its line — as a 5em block above it
 * ate a quarter of the screen before the first member. */
echo '<h1>'. htmlspecialchars((string) $facJson->name, ENT_QUOTES, 'UTF-8')
    .' <span class="ra '. $facJson->raFont .'"></span></h1>';

if (!empty($facJson->text)) {
    echo '<p style="max-width: 640px; margin: 0 0 1em;"><small>'
        . nl2br(htmlspecialchars((string) $facJson->text, ENT_QUOTES, 'UTF-8'))
        . '</small></p>';
}


$player = PlayerFactory::legacy($_SESSION['playerId']);
$player->get_data();


if(!empty($facJson->hidden) && !$player->have_option('isAdmin')){

    exit();
}


if(isset($facJson->secret)){

    if($player->data->secretFaction == $_GET['faction'] || $player->have_option('isAdmin')){
        $sql = 'SELECT players.id AS id,avatar,name,race,xp,secretFactionRole as factionRole,0 as factionRoleVariant,plan FROM players INNER JOIN coords ON coords_id = coords.id WHERE nextTurnTime > ? AND secretFaction = ? AND player_type = "real" ORDER BY factionRole DESC, name';

        $db = new Db();

        $timeLimit = time() - INACTIVE_TIME;

        $res = $db->exe($sql, array($timeLimit, $_GET['faction']));

        FactionView::renderFaction($player,$facJson,$res);

    }else{
        echo "<p>Cette faction est entourée d'un grand mystère, nul ne connait vraiment ses membres.</p>";
    }

}else{

    $sql = 'SELECT players.id AS id,avatar,name,race,xp,factionRole,factionRoleVariant,plan FROM players INNER JOIN coords ON coords_id = coords.id WHERE nextTurnTime > ? AND faction = ? AND player_type = "real" ORDER BY factionRole DESC, name';

    $db = new Db();

    $timeLimit = time() - INACTIVE_TIME;

    $res = $db->exe($sql, array($timeLimit, $_GET['faction']));

    /* Les gestes de gestion suivent le RANG du visiteur (faction_roles) :
     * la vue ne montre que ce que son drapeau permet, l'endpoint revérifie. */
    $manage = ($player->data->faction === ($_GET['faction'] ?? ''))
        ? (new FactionService())->roleOf((int) $player->id)
        : null;

    FactionView::renderFaction($player,$facJson,$res,$manage);
}

/* Les bâtiments de la faction — ses murs. Réservés à ses MEMBRES, par
 * la règle qui cache déjà le territoire des autres ; l'admin voit tout.
 * Le geste « prendre les commandes » n'appartient qu'aux membres — et
 * l'entité pilotée en ce moment porte le chemin du retour. */
if ($player->data->faction === ($_GET['faction'] ?? '') || $player->have_option('isAdmin')) {

    FactionView::renderBuildings(
        (new FactionService())->buildingsOf((string) $_GET['faction']),
        $player->data->faction === ($_GET['faction'] ?? ''),
        (int) $player->id
    );

    /* Its chests, listed like its walls: contents for the eyes the
     * rank allows, and the lock turnable from here. */
    FactionView::renderContainers(
        (new FactionService())->containersOf((string) $_GET['faction']),
        $player->data->faction === ($_GET['faction'] ?? ''),
        (int) $player->id
    );

    if ($player->data->faction === ($_GET['faction'] ?? '')) {
        /* The journal is the house's eyes — for the ranks it trusts
         * with them (showLogs). */
        if ((new FactionService())->mayManage((int) $player->id, 'showLogs')) {
            FactionView::renderJournal((new \App\Service\FactionLogService())->listOf((string) $_GET['faction']));
        }
        FactionView::renderAssetsScript((string) $_GET['faction']);
    }
}

echo '</div>';
?>
<script>
/* Each section remembers being folded or spread, per session. The
 * toggle event does not bubble: bound directly, re-bound at every
 * fragment load (off() before on()). */
(function(){
    var KEY = 'factionSections:' + <?php echo json_encode((string) ($_GET['faction'] ?? '')); ?>;
    var stored = {};
    try { stored = JSON.parse(sessionStorage.getItem(KEY) || '{}'); } catch(e) {}

    $('.faction-page details.faction-section').each(function(){
        var k = $(this).data('section');
        if(Object.prototype.hasOwnProperty.call(stored, k)){ this.open = !!stored[k]; }
    });

    $('.faction-page details.faction-section').off('toggle.factionSections')
        .on('toggle.factionSections', function(){
            stored[$(this).data('section')] = this.open;
            sessionStorage.setItem(KEY, JSON.stringify(stored));
        });
})();
</script>

