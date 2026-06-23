<?php

namespace Tests\Action\View;

use App\View\Action\WorkbenchLayoutView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class WorkbenchLayoutViewTest extends TestCase
{
    public function testRendersTheSharedTwoColumnShell(): void
    {
        $html = (new WorkbenchLayoutView())->render('Passifs', 3, '<div id="list"></div>', '<span>Head</span>', '<div id="body"></div>');

        $this->assertStringContainsString('class="wb"', $html);
        $this->assertStringContainsString('wb-col wb-col--list', $html);
        $this->assertStringContainsString('Passifs <small>3</small>', $html);
        $this->assertStringContainsString('id="wb-fold"', $html);  // fold toggle
        $this->assertStringContainsString('<div id="list"></div>', $html);
        $this->assertStringContainsString('<div id="body"></div>', $html);
    }

    public function testEditorHeadClassIsAppliedWhenGiven(): void
    {
        $html = (new WorkbenchLayoutView())->render('Actions', 1, '', 'head', 'body', 'wb-tabs');

        $this->assertStringContainsString('class="wb-col-head wb-tabs"', $html);
    }
}
