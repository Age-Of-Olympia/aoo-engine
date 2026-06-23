<?php

namespace Tests\Action\View;

use App\View\Action\WorkbenchFooterView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class WorkbenchFooterViewTest extends TestCase
{
    public function testWiresEachButtonToItsFormAndPushesDangerRight(): void
    {
        $html = (new WorkbenchFooterView())->render('save-form', 'delete-form', 'Supprimer', '<a>export</a>');

        $this->assertStringContainsString('class="wb-footer"', $html);
        $this->assertStringContainsString('form="save-form"', $html);   // save button -> save form
        $this->assertStringContainsString('form="delete-form"', $html); // delete button -> delete form
        $this->assertStringContainsString('wb-footer-danger', $html);   // danger pushed right
        $this->assertStringContainsString('<a>export</a>', $html);      // extra control kept
    }
}
