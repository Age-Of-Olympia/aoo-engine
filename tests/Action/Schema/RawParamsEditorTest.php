<?php

namespace Tests\Action\Schema;

use App\Action\Schema\Form\RawParamsEditor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class RawParamsEditorTest extends TestCase
{
    private RawParamsEditor $editor;

    protected function setUp(): void
    {
        $this->editor = new RawParamsEditor();
    }

    public function testRendersARowPerLeftoverParameter(): void
    {
        $html = $this->editor->render('cond_raw[5]', ['a' => 1, 'pm' => 10]);

        $this->assertStringContainsString('name="cond_raw[5][0][k]"', $html);
        $this->assertStringContainsString('value="a"', $html);
        $this->assertStringContainsString('name="cond_raw[5][0][v]"', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('value="pm"', $html);
        $this->assertStringContainsString('value="10"', $html);
    }

    public function testSkipsReservedKeysAndKeepsTheDynamicOne(): void
    {
        $params = ['adrenaline' => true, 'duration' => 1, 'player' => 'actor'];

        $html = $this->editor->render('inst_raw[7]', $params, ['duration', 'player', 'value', 'stackable']);

        $this->assertStringContainsString('value="adrenaline"', $html);
        $this->assertStringContainsString('value="true"', $html);
        $this->assertStringNotContainsString('value="1"', $html);
    }

    public function testDisplaysArrayValuesAsJson(): void
    {
        $html = $this->editor->render('cond_raw[5]', ['imposture' => [1, 2]]);

        $this->assertStringContainsString('value="[1,2]"', $html);
    }

    public function testHidesEditorForFullyTypedBlockWithNoLeftover(): void
    {
        $html = $this->editor->render('inst_raw[7]', ['duration' => 1], ['duration'], false);

        $this->assertSame('', $html);
    }

    public function testShowsEmptyEditorWhenAllowed(): void
    {
        $html = $this->editor->render('cond_raw[5]', [], [], true);

        $this->assertStringContainsString('wb-raw-editor', $html);
        $this->assertStringContainsString('wb-raw-add', $html);
    }
}
