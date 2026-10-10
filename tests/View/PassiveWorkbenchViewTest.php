<?php

namespace Tests\View;

use App\Entity\ActionPassive;
use App\View\Action\PassiveWorkbenchView;
use PHPUnit\Framework\TestCase;

class PassiveWorkbenchViewTest extends TestCase
{
    public function testListsAPassiveWithoutRace(): void
    {
        $passive = new ActionPassive();
        (new \ReflectionProperty(ActionPassive::class, 'id'))->setValue($passive, 16);
        $passive->setName('apprenti_i');
        $passive->setDisplayName('Apprenti I');
        $passive->setType('spell-slot');
        $passive->setLevel(1);

        $html = (new PassiveWorkbenchView())->render([$passive], null, '');

        $this->assertStringContainsString('spell-slot · niv.1</span>', $html);
    }
}
