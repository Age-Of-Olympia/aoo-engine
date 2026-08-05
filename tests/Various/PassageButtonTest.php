<?php

namespace Tests\Various;

use App\View\Observe\PassageView;
use Classes\View;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Le bouton « Monter / Descendre » du panneau d'observation : visible
 * seulement sur SA propre case quand elle porte un déclencheur `tp` —
 * l'arrivée d'un escalier est sa case même, aucun pas n'y repasse, le
 * bouton rejoue le déplacement (go.php, distance 0) pour l'emprunter.
 */
class PassageButtonTest extends LegacyPlayerFixtureTestCase
{
    /** @var array<int, int> ids map_triggers semés, à nettoyer */
    private array $seededTriggers = [];

    protected function tearDown(): void
    {
        foreach ($this->seededTriggers as $id) {
            $this->link->executeStatement('DELETE FROM map_triggers WHERE id = ?', [$id]);
        }
        parent::tearDown();
    }

    private function seedTp(int $x, int $y, int $z, string $params): void
    {
        $coordsId = (int) View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => $z, 'plan' => 'gaia']);
        $this->link->executeStatement(
            "INSERT INTO map_triggers (name, coords_id, params) VALUES ('tp', ?, ?)",
            [$coordsId, $params]
        );
        $this->seededTriggers[] = (int) $this->link->lastInsertId();
    }

    private function renderAt(\Classes\Player $player, int $x, int $y, int $z): string
    {
        ob_start();
        PassageView::render($player, $x, $y, (object) ['x' => $x, 'y' => $y, 'z' => $z, 'plan' => 'gaia']);

        return (string) ob_get_clean();
    }

    public function testTheButtonShowsOnOwnTpTileWithTheTravelVerb(): void
    {
        [$x, $y] = $this->farTile();
        $this->seedTp($x, $y, 0, 'x,y,-1,gaia');

        $player = $this->createRealPlayer('PassagerHalls');
        $this->movePlayerTo((int) $player->id, $x, $y);
        $player->getCoords();

        $html = $this->renderAt($player, $x, $y, 0);

        $this->assertStringContainsString('take-passage', $html);
        $this->assertStringContainsString('Descendre', $html, 'destination plus basse : on descend');
        $this->assertStringContainsString('data-coords="' . $x . ',' . $y . '"', $html);
    }

    public function testSilentOffOwnTileOrWithoutTrigger(): void
    {
        [$x, $y] = $this->farTile();
        $this->seedTp($x, $y, 0, 'x,y,-1,gaia');

        $player = $this->createRealPlayer('BadaudHalls');
        $this->movePlayerTo((int) $player->id, $x + 1, $y);
        $player->getCoords();

        $this->assertSame('', $this->renderAt($player, $x, $y, 0), 'la case du tp n\'est pas la sienne : pas de bouton');
        $this->assertSame('', $this->renderAt($player, $x + 1, $y, 0), 'sa case sans déclencheur : pas de bouton');
    }
}
