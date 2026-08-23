<?php

namespace Tests\Various;

use App\Service\TriggerPaletteService;
use PHPUnit\Framework\TestCase;

/**
 * Un déclencheur n'est posable que si quelque chose s'en sert.
 *
 * Les palettes se construisaient en listant `img/triggers/*.png`. L'image
 * survit au retrait du code : `altar` (parti quand l'autel est devenu
 * bâtiment + actions, 199f394d), `enter` et `exit` (voyage inter-plans
 * mort-né, 066f7b6c) restaient proposés. Posés, ils ne faisaient pas rien :
 * `go.php` répondait « error trigger path » et le déplacement mourait là.
 *
 * Le piège est de l'autre côté : « se sert » ne veut pas dire « a un
 * gestionnaire ». `grow` n'en a jamais eu et reste bien vivant — c'est un
 * point de pousse relu par le cron des plantes. Le retirer de la palette
 * ôterait aux animateurs le seul moyen de semer.
 */
class TriggerPaletteServiceTest extends TestCase
{
    protected function setUp(): void
    {
        TriggerPaletteService::forget();
    }

    /** Les quatre du pas, plus le point de pousse. */
    public function testThePaletteIsWhatTheGameConsumes(): void
    {
        $this->assertSame(
            ['forbidden', 'grow', 'need', 'rez', 'tp'],
            TriggerPaletteService::playableNames()
        );
    }

    /** grow est posable sans être un déclencheur du pas : le cron le lit. */
    public function testGrowIsPlayableButNotSteppedOn(): void
    {
        $this->assertTrue(TriggerPaletteService::isKnown('grow'), 'le cron des plantes le lit');
        $this->assertFalse(TriggerPaletteService::isStepTrigger('grow'), 'rien ne se passe au pas');
        $this->assertContains('grow', TriggerPaletteService::playableNames());
    }

    /** Ceux dont le code est parti ne se proposent plus. */
    public function testARetiredTriggerIsNotPlayable(): void
    {
        foreach (['altar', 'enter', 'exit'] as $retired) {
            $this->assertFalse(
                TriggerPaletteService::isKnown($retired),
                $retired . ' : plus personne ne s\'en sert, hors palette'
            );
        }

        $this->assertTrue(TriggerPaletteService::isKnown('tp'));
    }

    /** Le filtre garde l'ordre et ne laisse passer que l'exécutable. */
    public function testFilterKeepsOnlyWhatTheGameRuns(): void
    {
        $this->assertSame(
            ['forbidden', 'tp', 'grow'],
            TriggerPaletteService::filterNames(['altar', 'forbidden', 'enter', 'tp', 'exit', 'grow'])
        );
    }

    /**
     * La garde de go.php a la même source : un nom sans fichier est ignoré,
     * il n'interrompt plus le déplacement.
     */
    public function testGoSkipsATriggerWithoutHandlerInsteadOfStopping(): void
    {
        $go = (string) file_get_contents(__DIR__ . '/../../go.php');

        $this->assertStringNotContainsString(
            "exit('error trigger path')",
            $go,
            'la sortie brutale doit avoir disparu'
        );
        $this->assertMatchesRegularExpression(
            '/if\(!file_exists\(\$path\)\)\{.*?continue;/s',
            $go,
            'un déclencheur sans gestionnaire se saute'
        );
    }
}
