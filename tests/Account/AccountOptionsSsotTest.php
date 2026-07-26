<?php

namespace Tests\Account;

use App\View\AccountView;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * La liste des options du profil sert DEUX rôles à la fois : elle
 * décrit ce qu'on affiche, et elle fait office de liste blanche du
 * handler POST d'account.php (`if(!isset(OPTIONS[$_POST['option']]))
 * exit('error option')`).
 *
 * Conséquence : toute option proposée ailleurs dans l'interface mais
 * absente de cette liste est silencieusement intogglable — le bouton
 * existe, le clic ne fait rien. C'est arrivé pour de vrai à `newHud` et
 * `hideBoardCoords` : le refactor qui a centralisé la liste dans
 * AccountView a été suivi de la fusion d'une branche partie AVANT, dont
 * l'account.php a ressuscité une copie locale antérieure aux options
 * concernées. Deux listes, une seule consultée par le serveur.
 *
 * Ces tests interdisent la rechute : une seule liste, et elle couvre
 * tout ce que l'interface propose.
 */
#[Group('account-options')]
class AccountOptionsSsotTest extends LegacyPlayerFixtureTestCase
{
    /** @return array<string, string> */
    private function canonicalOptions(): array
    {
        $player = $this->createRealPlayer('SsotOpts');
        $player->get_data();

        return AccountView::buildOptions($player);
    }

    /**
     * Le popover « Affichage » du HUD bascule ses entrées via le POST
     * d'account.php : chacune doit donc être dans la liste blanche.
     */
    public function testEveryHudLayerOptionIsAcceptedByTheProfileWhitelist(): void
    {
        $source = file_get_contents(__DIR__ . '/../../src/View/Hud/HudLayoutView.php');
        $this->assertIsString($source);

        $block = substr(
            $source,
            (int) strpos($source, '$mapLayers = ['),
            (int) strpos($source, '];', (int) strpos($source, '$mapLayers = ['))
                - (int) strpos($source, '$mapLayers = [')
        );
        preg_match_all("/'([A-Za-z]+)'\s*=>/", $block, $m);
        $offered = $m[1];

        $this->assertNotEmpty($offered, 'les calques du HUD doivent être détectables');

        $accepted = $this->canonicalOptions();
        foreach ($offered as $option) {
            $this->assertArrayHasKey(
                $option,
                $accepted,
                "le HUD propose « {$option} » mais account.php refuserait la bascule"
            );
        }
    }

    /**
     * Idem côté client : js/hud.js applique à chaud une liste d'options
     * de plateau, toutes basculées par le même POST.
     */
    public function testEveryBoardOptionOfTheHudScriptIsAccepted(): void
    {
        $source = file_get_contents(__DIR__ . '/../../js/hud.js');
        $this->assertIsString($source);

        $this->assertSame(1, preg_match('/BOARD_OPTIONS\s*=\s*\[([^\]]*)\]/', $source, $m));
        preg_match_all("/'([A-Za-z]+)'/", $m[1], $names);

        $accepted = $this->canonicalOptions();
        foreach ($names[1] as $option) {
            $this->assertArrayHasKey(
                $option,
                $accepted,
                "js/hud.js bascule « {$option} » mais account.php refuserait la bascule"
            );
        }
    }

    /**
     * L'option qui donne accès à la nouvelle interface : c'est celle
     * qu'on a perdue, elle mérite son propre garde-fou.
     */
    public function testTheNewHudOptionCanBeToggled(): void
    {
        $this->assertArrayHasKey('newHud', $this->canonicalOptions());

        /* Membership seule ne prouve rien : AccountView a TOUJOURS eu
         * newHud pendant la panne. Ce qui manquait, c'est qu'account.php
         * consulte cette liste-là plutôt que sa copie. */
        $source = file_get_contents(__DIR__ . '/../../account.php');
        $this->assertIsString($source);
        $this->assertMatchesRegularExpression(
            '/\$options\s*=\s*AccountView::buildOptions\(\$player\);/',
            $source,
            'la liste blanche du POST doit être la liste canonique, pas une copie'
        );
    }

    /**
     * Le garde-fou anti-résurrection : account.php ne redéclare pas la
     * liste, il consomme celle d'AccountView. Un merge qui ramènerait
     * la copie locale échoue ici.
     */
    public function testAccountPageDoesNotKeepItsOwnCopyOfTheList(): void
    {
        $source = file_get_contents(__DIR__ . '/../../account.php');
        $this->assertIsString($source);

        $this->assertStringContainsString(
            'AccountView::buildOptions($player)',
            $source,
            'account.php doit consommer la liste canonique'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'raceHint'\s*=>/",
            $source,
            'account.php redéclare une liste d\'options : la duplication est de retour'
        );
    }

    /**
     * « DLA glissante » a été remplacée par le décalage de tour ; la
     * copie HUD avait gardé l'ancienne, invisible côté serveur.
     */
    public function testTheObsoleteSlidingDlaOptionIsGone(): void
    {
        $accepted = $this->canonicalOptions();

        $this->assertArrayNotHasKey('dlag', $accepted);
        $this->assertArrayHasKey('nextTurn', $accepted);
    }
}
