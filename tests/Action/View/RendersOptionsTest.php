<?php

namespace Tests\Action\View;

use App\View\Action\RendersOptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-view')]
class RendersOptionsTest extends TestCase
{
    private object $view;

    protected function setUp(): void
    {
        $this->view = new class {
            use RendersOptions {
                option as public;
                options as public;
                optionsList as public;
                optionsMulti as public;
                optgroup as public;
            }
        };
    }

    public function testSingleOptionEscapesAndMarksSelected(): void
    {
        $this->assertSame('<option value="a&amp;b">L&lt;x&gt;</option>', $this->view->option('a&b', 'L<x>'));
        $this->assertSame('<option value="x" selected>X</option>', $this->view->option('x', 'X', true));
    }

    public function testOptionsMarksTheMatchingValueWithStringCompare(): void
    {
        $html = $this->view->options(['1' => 'One', '2' => 'Two'], 2);

        $this->assertSame('<option value="1">One</option><option value="2" selected>Two</option>', $html);
    }

    public function testOptionsWithNullSelectsNothing(): void
    {
        $this->assertStringNotContainsString('selected', $this->view->options(['a' => 'A', 'b' => 'B']));
    }

    public function testOptionsListUsesValueAsItsOwnLabel(): void
    {
        $html = $this->view->optionsList(['Plan', 'Dodge'], 'Dodge');

        $this->assertSame('<option value="Plan">Plan</option><option value="Dodge" selected>Dodge</option>', $html);
    }

    public function testOptionsMultiMarksEverySelectedValue(): void
    {
        $html = $this->view->optionsMulti(['cc' => 'CC', 'agi' => 'Agi', 'f' => 'F'], ['cc', 'f']);

        $this->assertSame(
            '<option value="cc" selected>CC</option><option value="agi">Agi</option><option value="f" selected>F</option>',
            $html
        );
    }

    public function testOptgroupWrapsAndEscapesItsLabel(): void
    {
        $html = $this->view->optgroup('A & B', ['x' => 'X'], 'x');

        $this->assertSame('<optgroup label="A &amp; B"><option value="x" selected>X</option></optgroup>', $html);
    }
}
