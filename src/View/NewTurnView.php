<?php

namespace App\View;

use App\Service\TurnProcessingService;
use Classes\Player;

/**
 * Page « Nouveau Tour » : PRÉSENTE le récapitulatif du tour traité par
 * TurnProcessingService — la vue ne mute plus rien. Le même récap est
 * journalisé en événement (type 'turn') par le service, relisible dans
 * les Évènements.
 */
class NewTurnView
{
    public static function renderNewTurn(Player $player): void
    {
        $recap = (new TurnProcessingService())->processIfDue($player);
        if ($recap === null) {
            return;
        }

        echo '<h1><font color="red">Nouveau Tour</font></h1>';

        echo '<div style="text-align: center;">';
        echo '<a href="index.php"><img class="box-shadow" src="img/ui/illustrations/sunset.webp" /></a>';
        echo '</div>';

        echo '<br />Prochain Tour le ' . date('d/m/Y à H:i', $recap->nextTurnTime) . '.';

        echo '
        <table border="1" align="center" class="marbre">';

        foreach ($recap->rows as [$tooltipKey, $label, $value]) {
            $tooltip = $tooltipKey !== null
                ? ' flow="right" tooltip="' . CARACS_TXT[$tooltipKey] . '"'
                : '';
            echo '<tr><td' . $tooltip . '>' . $label . '</td><td align="right">' . $value . '</td></tr>';
        }

        echo '</table>';

        if ($recap->wearRecap !== []) {
            echo '<div class="new-turn-wear">';
            foreach ($recap->wearRecap as $line) {
                echo '<div>' . $line . '</div>';
            }
            echo '</div>';
        }

        echo '<br /><a href="index.php"><button>Jouer</button></a>';

        if ($recap->showMailPrompt) {
            echo ' <a href="account.php?changeMail"><button>Renseigner mon mail (+20 XP)</button></a>';
        }

        self::renderAutoTutorialRedirect();

        exit();
    }

    /**
     * Redirection auto-tutoriel des tout nouveaux joueurs : la page
     * Nouveau Tour bloque le flux normal, la bascule doit partir d'ici.
     */
    private static function renderAutoTutorialRedirect(): void
    {
        if (empty($_SESSION['auto_start_tutorial']) || isset($_SESSION['in_tutorial'])) {
            return;
        }

        echo '<script>
        console.log("[NewTurn] Auto-starting tutorial after new turn...");
        $(document).ready(function() {
            /* Wait for tutorial scripts to load */
            var checkInterval = setInterval(function() {
                if (typeof window.initTutorial === "function") {
                    clearInterval(checkInterval);
                    console.log("[NewTurn] Tutorial scripts loaded, redirecting...");
                    /* Redirect to index.php with tutorial=start parameter */
                    window.location.href = "index.php?tutorial=start";
                }
            }, 200);

            /* Timeout after 5 seconds */
            setTimeout(function() {
                clearInterval(checkInterval);
                if (typeof window.initTutorial !== "function") {
                    console.error("[NewTurn] Tutorial scripts failed to load");
                    /* Just redirect to index anyway */
                    window.location.href = "index.php";
                }
            }, 5000);
        });
        </script>';
    }
}
