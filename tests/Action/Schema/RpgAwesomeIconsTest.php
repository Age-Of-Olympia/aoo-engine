<?php

namespace Tests\Action\Schema;

use App\Service\Action\RpgAwesomeIcons;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class RpgAwesomeIconsTest extends TestCase
{
    private function cssPath(): string
    {
        return dirname(__DIR__, 3) . '/css/rpg-awesome.min.css';
    }

    public function testParsesGlyphIconsFromTheShippedStylesheet(): void
    {
        $icons = (new RpgAwesomeIcons($this->cssPath()))->all();

        $this->assertContains('ra-crossed-swords', $icons);
        $this->assertContains('ra-health', $icons);
        $this->assertGreaterThan(100, count($icons));

        // Layout modifiers are not icons.
        $this->assertNotContains('ra-lg', $icons);
        $this->assertNotContains('ra-spin', $icons);

        // Sorted and unique.
        $sorted = $icons;
        sort($sorted);
        $this->assertSame($sorted, $icons);
        $this->assertSame(array_values(array_unique($icons)), $icons);
    }

    public function testMissingStylesheetYieldsNoIcons(): void
    {
        $this->assertSame([], (new RpgAwesomeIcons('/no/such/file.css'))->all());
    }
}
