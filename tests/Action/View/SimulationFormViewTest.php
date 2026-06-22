<?php

namespace Tests\Action\View;

use App\Action\MeleeAction;
use App\Action\Schema\OptionCatalog;
use App\Action\Schema\SimulationField;
use App\View\Action\SimulationFormView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class SimulationFormViewTest extends TestCase
{
    public function testRendersTheDerivedFieldsAndEffectWidget(): void
    {
        $action = new MeleeAction();
        $action->setName('melee');
        $action->setDisplayName('Attaquer');

        $fields = [
            new SimulationField('distance', 'shared', 'distance', 'Distance'),
            new SimulationField('trait', 'actor', 'cc', 'Acteur — cc'),
            new SimulationField('weapon', 'actor', 'weapon', 'Arme acteur', 'melee'),
        ];

        $html = (new SimulationFormView(new OptionCatalog()))->render($action, $fields, []);

        $this->assertStringContainsString('class="sim-panel"', $html);
        $this->assertStringContainsString('Équipement', $html);
        $this->assertStringContainsString('name="distance"', $html);
        $this->assertStringContainsString('name="actor_trait[cc]"', $html);
        $this->assertStringContainsString('name="actor_weapon"', $html);
        $this->assertStringContainsString('name="actor_equipment[tete]"', $html);
        $this->assertStringContainsString('addEffectRow', $html);
    }

    public function testRepopulatesSubmittedValues(): void
    {
        $action = new MeleeAction();
        $action->setName('melee');
        $action->setDisplayName('Attaquer');

        $html = (new SimulationFormView(new OptionCatalog()))->render(
            $action,
            [new SimulationField('trait', 'actor', 'cc', 'Acteur — cc')],
            ['actor_trait' => ['cc' => 17], 'runs' => 250],
        );

        $this->assertStringContainsString('value="17"', $html);
        $this->assertStringContainsString('value="250"', $html);
    }
}
