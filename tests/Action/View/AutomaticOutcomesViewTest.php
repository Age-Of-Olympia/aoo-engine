<?php

namespace Tests\Action\View;

use App\Action\OutcomeInstruction\ApplyStatusOutcomeInstruction;
use App\View\Action\AutomaticOutcomesView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class AutomaticOutcomesViewTest extends TestCase
{
    public function testRendersTypeParamsAndAReadOnlyBadge(): void
    {
        $instruction = new ApplyStatusOutcomeInstruction();
        $instruction->setParameters(['adrenaline' => true, 'duration' => 3]);

        $html = (new AutomaticOutcomesView())->render([$instruction]);

        $this->assertStringContainsString('applystatus', $html);
        $this->assertStringContainsString('adrenaline', $html);
        $this->assertStringContainsString('true', $html);
        $this->assertStringContainsString('3', $html);
        $this->assertStringContainsString('hérité du type', $html);
    }

    public function testRendersNothingWhenThereAreNoAutomaticInstructions(): void
    {
        $this->assertSame('', (new AutomaticOutcomesView())->render([]));
    }
}
