<?php

namespace Tests\View;

use App\View\AnimatedLayersView;
use PHPUnit\Framework\TestCase;

/**
 * Animated elements as layers: which files animate, what a sliding tile
 * becomes once frozen, and the stacking of the layers.
 */
class AnimatedLayersViewTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/element_layers_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function file(string $name, string $content): string
    {
        file_put_contents($this->dir . '/' . $name, $content);

        return $this->dir . '/' . $name;
    }

    private function composed(string $name, string $anim): string
    {
        $params = htmlspecialchars(json_encode(['anim' => $anim, 'speed' => 2, 'shape' => 'round']));

        return $this->file($name, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="50" height="50" data-composer="' . $params . '">'
            . '<defs><mask id="m"><rect width="50" height="50" fill="#fff"/></mask></defs>'
            . '<g mask="url(#m)" opacity="1"><rect x="-50" y="-50" width="150" height="150" fill="#08f">'
            . '<animateTransform attributeName="transform" type="translate" values="0 0;0 50" dur="2s" repeatCount="indefinite"/></rect></g></svg>');
    }

    public function testDetectsAnimatedFiles(): void
    {
        $frame = "\x21\xF9\x04";
        $this->assertTrue(AnimatedLayersView::animates($this->file('a.gif', 'GIF89a' . $frame . 'x' . $frame . 'y')));
        $this->assertFalse(AnimatedLayersView::animates($this->file('b.gif', 'GIF89a' . $frame . 'x')));
        $this->assertTrue(AnimatedLayersView::animates($this->file('c.webp', 'RIFF....WEBPVP8XANIMANMF')));
        $this->assertFalse(AnimatedLayersView::animates($this->file('d.webp', 'RIFF....WEBPVP8 ')));
        $this->assertTrue(AnimatedLayersView::animates($this->composed('e.svg', 'drift_v')));
    }

    public function testSlidingTileIsFrozenAndMovedByCss(): void
    {
        $layers = new AnimatedLayersView();
        $layers->add($this->composed('lava.svg', 'drift_v'), 3, 1.0, 90, [0, 25], 100, 50, 0, '');
        $html = $layers->render();

        $texture = rawurldecode($html);
        $this->assertStringNotContainsString('<animateTransform', $texture, 'the texture is frozen');
        $this->assertStringNotContainsString('<g mask="url(#m)"', $texture, 'its shape moves to the layer mask');
        $this->assertStringContainsString('transform:rotate(90deg)', $html);
        $this->assertStringContainsString('0px 25px/50px 50px', $html, 'phase shift as background position');
        $this->assertStringContainsString('@keyframes layer-elements-0-0{0%{transform:translate(0px,0px)}100%{transform:translate(0px,50px)}}', $html);
        $this->assertStringContainsString('data-segments="1"', $html);
        $this->assertStringContainsString('animation-timing-function:steps(24)', $html, 'default: 12 positions a second over 2s');
    }

    public function testLayersStackLikeTheCells(): void
    {
        $lava = $this->composed('lava.svg', 'drift_v');
        $fire = $this->file('fire.gif', 'GIF89a');
        $layers = new AnimatedLayersView();
        // Rows: lava first, then fire; an elbow's clipped half added before its whole one
        $layers->add($lava, 3, 1.0, 0, [0, 0], 0, 0, 0, 'elem-half-ENW');
        $layers->add($lava, 3, 1.0, 90, [0, 0], 0, 0, 0, '');
        $layers->add($fire, 0, 0.3, 0, [0, 0], 0, 0, 0, '');
        $html = $layers->render();

        $whole = strpos($html, 'rotate(90deg)');
        $clipped = strpos($html, 'url(#elem-half-ENW)');
        $this->assertLessThan($clipped, $whole, 'the clipped half goes over the whole one');
        $this->assertLessThan(strpos($html, 'fire.gif'), $clipped, 'fire, a later row, goes over lava');
        $this->assertStringContainsString('opacity:0.3', $html);
    }

    public function testEmptyStillRendersTheGroup(): void
    {
        $this->assertSame('<g id="anim-layers-tiles" class="anim-layers"></g>', (new AnimatedLayersView('tiles'))->render());
    }
}
