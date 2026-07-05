<?php

namespace Tests\Action\View;

use App\Action\SearchAction;
use App\View\Action\ActionIconPalette;
use App\View\Action\ActionIconView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-view')]
class ActionIconViewTest extends TestCase
{
    public function testRendersTheBareIcon(): void
    {
        $this->assertSame('<i class="ra ra-sword"></i>', (new ActionIconView())->render('ra-sword'));
    }

    public function testRendersTheLegacySpanTagWithExtraClasses(): void
    {
        $this->assertSame(
            '<span class="ra ra-sword wb-item-icon"></span>',
            (new ActionIconView())->render('ra-sword', null, 'span', ['wb-item-icon']),
        );
    }

    public function testAppliesThePaletteColour(): void
    {
        $this->assertSame(
            '<i class="ra ra-sword" style="color:#2980b9"></i>',
            (new ActionIconView())->render('ra-sword', 'bleu'),
        );
    }

    public function testAnUnknownTokenAddsNoStyleAndIsNeverReflected(): void
    {
        // The token is admin-set and flows into player-facing HTML; anything not in
        // the palette must resolve to no colour, never echo back into the markup.
        $html = (new ActionIconView())->render('ra-sword', '"><script>alert(1)</script>');

        $this->assertSame('<i class="ra ra-sword"></i>', $html);
        $this->assertStringNotContainsString('script', $html);
    }

    public function testForActionReadsTheActionsIconAndColour(): void
    {
        $action = new SearchAction();
        $action->setIcon('ra-shield');
        $action->setIconColor('rouge');

        $this->assertSame(
            '<span class="ra ra-shield" style="color:#c0392b"></span>',
            (new ActionIconView())->forAction($action, 'span'),
        );
    }

    public function testPaletteValidatesTokens(): void
    {
        $this->assertTrue(ActionIconPalette::isValid(null));
        $this->assertTrue(ActionIconPalette::isValid(''));
        $this->assertTrue(ActionIconPalette::isValid('vert'));
        $this->assertFalse(ActionIconPalette::isValid('not-a-colour'));

        $this->assertSame('#27ae60', ActionIconPalette::hex('vert'));
        $this->assertNull(ActionIconPalette::hex(null));
        $this->assertNull(ActionIconPalette::hex('not-a-colour'));
    }
}
