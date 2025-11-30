<?php
use Classes\Ui;
use Classes\Str;
use App\View\InfosView;
use App\View\MainView;
use App\View\MenuView;
use App\View\NewTurnView;
use App\Tutorial\TutorialHelper;
use App\Tutorial\TutorialFeatureFlag;
use App\Tutorial\TutorialSessionManager;
use Classes\Player;
use Classes\Db;

if(isset($_GET['logout'])){

    ob_start();
}


define('NO_LOGIN', true);


require_once('config.php');


// Handle tutorial replay redirect BEFORE any output
// Must happen before new Ui() to allow header() redirect
if (isset($_GET['replay_tutorial']) && $_GET['replay_tutorial'] == '1' && !empty($_SESSION['playerId'])) {
    if (TutorialFeatureFlag::isEnabledForPlayer($_SESSION['playerId'])) {
        // Player wants to replay tutorial - set flag to auto-start
        $_SESSION['auto_start_tutorial'] = true;
        error_log("[index.php] Replay tutorial requested for player {$_SESSION['playerId']}, redirecting to clean URL");

        // Redirect to clean URL (without the parameter) to prevent loop
        header('Location: index.php');
        exit();
    }
}


$ui = new Ui($title="Index");


if(!empty($_SESSION['banned'])){

    echo '<h1>Vous avez été banni.</h1>';

    exit($_SESSION['banned']);
}


if(!isset($_SESSION['playerId']) || isset($_GET['menu'])){

    include('scripts/index.php');
}

elseif(isset($_GET['logout'])){

    unset($_SESSION['mainPlayerId']);
    unset($_SESSION['playerId']);
    unset($_SESSION['nonewturn']);
    session_destroy();

    ob_clean();

    header('location:index.php');
    exit();
}


ob_start();

// DEBUG: Show session state (remove this later)
if ($_SESSION['playerId'] == 7) {
    error_log("INDEX.PHP SESSION DEBUG:");
    error_log("  playerId: " . ($_SESSION['playerId'] ?? 'NOT SET'));
    error_log("  in_tutorial: " . ($_SESSION['in_tutorial'] ?? 'NOT SET'));
    error_log("  tutorial_player_id: " . ($_SESSION['tutorial_player_id'] ?? 'NOT SET'));
}

// Get active player ID (tutorial player if in tutorial mode, otherwise main player)
$playerId = TutorialHelper::getActivePlayerId();
error_log("  USING PLAYER: $playerId (tutorial mode: " . (TutorialHelper::isInTutorial() ? 'YES' : 'NO') . ")");

$player = new Player($playerId);
$player->get_data(false);

// Check if player is brand new (should auto-start tutorial instead of showing modal)
$isBrandNew = false;
error_log("[index.php] Checking if player {$player->id} is brand new for tutorial");
error_log("[index.php] Tutorial enabled: " . (TutorialFeatureFlag::isEnabledForPlayer($player->id) ? 'YES' : 'NO'));
error_log("[index.php] In tutorial: " . (TutorialHelper::isInTutorial() ? 'YES' : 'NO'));

// Calculate total tutorial XP/PI dynamically from database
// Note: XP and PI are the same - when you earn XP, you also earn PI
// XP is permanent total, PI can be spent on character improvements
$totalTutorialXP = 0;
$totalTutorialPI = 0;
if (TutorialFeatureFlag::isEnabledForPlayer($player->id)) {
    $db = new Db();
    /* Sum all XP rewards from active tutorial steps */
    $sql = "SELECT SUM(xp_reward) as total_xp FROM tutorial_steps WHERE version = '1.0.0' AND is_active = 1 AND xp_reward IS NOT NULL";
    $result = $db->exe($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $totalTutorialXP = (int)$row['total_xp'];
        $totalTutorialPI = $totalTutorialXP; /* PI = XP (but PI can be spent) */
    }
}

if (TutorialFeatureFlag::isEnabledForPlayer($player->id) && !TutorialHelper::isInTutorial()) {
    $db = new Db();
    $sessionManager = new TutorialSessionManager($db);
    $hasCompleted = $sessionManager->hasCompletedBefore($player->id);
    $activeSession = $sessionManager->getActiveSession($player->id);

    error_log("[index.php] hasCompleted: " . ($hasCompleted ? 'YES' : 'NO'));
    error_log("[index.php] activeSession: " . ($activeSession ? 'EXISTS' : 'NULL'));

    if (!$hasCompleted && $activeSession === null) {
        $isBrandNew = true;
        $_SESSION['auto_start_tutorial'] = true;
        error_log("[index.php] Player {$player->id} is BRAND NEW - setting auto_start_tutorial=true");

        // Show loading overlay for brand new players
        echo '<div id="tutorial-loading-overlay" style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        ">
            <div style="text-align: center; color: #fff;">
                <h2 style="margin-bottom: 20px;">Chargement du tutoriel...</h2>
                <div class="spinner" style="
                    border: 4px solid rgba(255,255,255,0.3);
                    border-top: 4px solid #fff;
                    border-radius: 50%;
                    width: 50px;
                    height: 50px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto;
                "></div>
            </div>
        </div>
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>';
    }
}

// Check if player is invisible and not admin - they need to complete or skip tutorial
// BUT don't show modal for brand new players (they'll auto-start tutorial)
// OR if tutorial is being auto-started (from replay/resume)
$isInvisible = $player->have_option('invisibleMode');
$isAdmin = $player->have_option('isAdmin');
$inTutorial = TutorialHelper::isInTutorial();
$autoStarting = isset($_SESSION['auto_start_tutorial']) && $_SESSION['auto_start_tutorial'];

/* Extract reward values for use in modals and JS */
$skipRewardXP = TUTORIAL_SKIP_REWARD['xp'];
$skipRewardPI = TUTORIAL_SKIP_REWARD['pi'];
$completionRewardXP = TUTORIAL_COMPLETION_REWARD['xp'];
$completionRewardPI = TUTORIAL_COMPLETION_REWARD['pi'];

/* Expose reward values and replay status to JavaScript for tutorial UI */
/* IMPORTANT: Check main player (not tutorial player) to determine if this is a replay */
$db = new Db();
$sessionManager = new TutorialSessionManager($db);
$mainPlayerId = $_SESSION['playerId']; /* Always use main player ID, not active player ID */
$hasCompletedTutorialBefore = $sessionManager->hasCompletedBefore($mainPlayerId);

echo '<script>
    window.TUTORIAL_SKIP_REWARD_XP = ' . $skipRewardXP . ';
    window.TUTORIAL_SKIP_REWARD_PI = ' . $skipRewardPI . ';
    window.TUTORIAL_TOTAL_XP = ' . $totalTutorialXP . ';
    window.TUTORIAL_TOTAL_PI = ' . $totalTutorialPI . ';
    window.TUTORIAL_IS_REPLAY = ' . ($hasCompletedTutorialBefore ? 'true' : 'false') . ';
</script>';

if ($isInvisible && !$isAdmin && !$inTutorial && !$isBrandNew && !$autoStarting) {
    // Player is invisible (registered but didn't complete tutorial) - show modal
    echo '<div id="invisible-player-modal" style="
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    ">
        <div style="
            background: #2a2a2a;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            text-align: center;
            color: #fff;
        ">
            <h2>Bienvenue !</h2>
            <p>Tu as commencé le tutoriel mais ne l\'as pas terminé.</p>
            <div style="margin-top: 20px; margin-bottom: 20px;">
                <p style="margin-bottom: 10px;"><strong>Que souhaites-tu faire ?</strong></p>
                <div style="text-align: left; margin: 0 auto; display: inline-block; max-width: 400px;">
                    <div style="margin-bottom: 15px; padding: 10px; background: rgba(76, 175, 80, 0.2); border-radius: 5px;">
                        <strong style="color: #4CAF50;">✓ Reprendre le tutoriel (recommandé)</strong>
                        <p style="margin: 5px 0 0 0; font-size: 14px;">Termine le tutoriel et gagne jusqu\'à <strong>' . $totalTutorialXP . ' XP/PI</strong></p>
                    </div>
                    <div style="margin-bottom: 15px; padding: 10px; background: rgba(244, 67, 54, 0.2); border-radius: 5px;">
                        <strong style="color: #f44336;">⊗ Passer le tutoriel</strong>
                        <p style="margin: 5px 0 0 0; font-size: 14px;">Commence le jeu immédiatement mais ne reçois que <strong>' . $skipRewardXP . ' XP/PI</strong> au lieu de ' . $totalTutorialXP . ' XP/PI</p>
                    </div>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button id="resume-tutorial-btn" style="
                    padding: 12px 24px;
                    margin: 10px;
                    font-size: 16px;
                    cursor: pointer;
                    background: #4CAF50;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    font-weight: bold;
                ">Reprendre le tutoriel</button>
                <button id="skip-tutorial-btn" style="
                    padding: 12px 24px;
                    margin: 10px;
                    font-size: 16px;
                    cursor: pointer;
                    background: #f44336;
                    color: white;
                    border: none;
                    border-radius: 5px;
                ">Passer le tutoriel</button>
            </div>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        // Resume tutorial button
        $("#resume-tutorial-btn").click(function() {
            window.location.href = "index.php?replay_tutorial=1";
        });

        /* Skip tutorial button */
        $("#skip-tutorial-btn").click(function() {
            var skipXP = <?php echo $skipRewardXP; ?>;
            var totalXP = <?php echo $totalTutorialXP; ?>;
            var message = "Es-tu sûr de vouloir passer le tutoriel ?\n\n" +
                         "Tu recevras seulement " + skipXP + " XP/PI\n" +
                         "au lieu de " + totalXP + " XP/PI du tutoriel complet.";

            if (confirm(message)) {
                $.post("api/tutorial/skip.php", {}, function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert("Erreur: " + (response.error || "Impossible de passer le tutoriel"));
                    }
                }, "json").fail(function() {
                    alert("Erreur de connexion au serveur");
                });
            }
        });

        // Block all clicks outside modal
        $("body").on("click", function(e) {
            if (!$(e.target).closest("#invisible-player-modal").length) {
                e.stopPropagation();
                e.preventDefault();
                return false;
            }
        });
    });
    </script>';

    // Don't render the rest of the page - just exit after modal
    exit();
}

// Note: Auto-start tutorial logic has been moved earlier (before modal check)
?>
<div id="new-turn"><?php NewTurnView::renderNewTurn($player) ?></div>

<div id="infos"><?php InfosView::renderInfos($player);?></div>

<div id="menu"><?php MenuView::renderMenu(); ?></div>

<?php
// Clear auto-start flag after menu is rendered (JavaScript will pick it up)
if (isset($_SESSION['auto_start_tutorial'])) {
    unset($_SESSION['auto_start_tutorial']);
}
?>

<?php MainView::render($player) ?>


<?php

echo '<div style="color: red;">';

if(!CACHED_INVENT) echo 'CACHED_INVENT = false<br />';
if(!CACHED_KILLS) echo 'CACHED_KILLS = false<br />';
if(!CACHED_CLASSEMENTS) echo 'CACHED_CLASSEMENTS = false<br />';
if(!CACHED_QUESTS) echo 'CACHED_QUESTS = false<br />';
if(AUTO_GROW) echo 'AUTO_GROW = true<br />';
if(FISHING) echo 'AUTO_GROW = true<br />';
if(ITEM_DROP > 10) echo 'ITEM_DROP = '. ITEM_DROP .'<br />';
if(DMG_CRIT > 10) echo 'DMG_CRIT = '. DMG_CRIT .'<br />';
if(AUTO_BREAK) echo 'AUTO_BREAK = true<br />';
if(AUTO_FAIL) echo 'AUTO_FAIL = true<br />';

echo '</div>';

echo Str::minify(ob_get_clean());
