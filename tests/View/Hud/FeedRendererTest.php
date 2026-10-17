<?php

namespace Tests\View\Hud;

use App\View\Hud\FeedRenderer;
use PHPUnit\Framework\TestCase;

class FeedRendererTest extends TestCase
{
    /** A refused shot's detail carries the line-of-fire script: the feed must not run it again. */
    public function testDetailDropsTheResultScripts(): void
    {
        $hiddenText = '<div class="action-details">Le mur bloque la ligne de tir.'
            . '<script>(function(t){window.showLineOfFire(t.from,t.to);})({"from":[1,1]});</script></div>';

        $detail = (new \ReflectionMethod(FeedRenderer::class, 'renderDetail'))->invoke(null, true, $hiddenText);

        $this->assertStringContainsString('Le mur bloque la ligne de tir.', $detail);
        $this->assertStringNotContainsString('<script', $detail);
        $this->assertStringNotContainsString('showLineOfFire', $detail);
    }
}
