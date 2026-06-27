<?php

namespace Tests\Action\View;

use App\Action\MeleeAction;
use App\Action\Schema\OptionCatalog;
use App\Action\Schema\SimulationField;
use App\Service\Action\ActionTargeting;
use App\Service\Action\SimulationWeaponCatalog;
use App\View\Action\SimulationFormView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class SimulationFormViewTest extends TestCase
{
    private function view(): SimulationFormView
    {
        // Inject a DB-free weapon catalog so the test never touches the datas
        // files (which need config/functions.php's helpers).
        $items = ['gladius' => (object) ['type' => 'equipement', 'emplacement' => 'main1', 'subtype' => 'melee', 'name' => 'Gladius']];

        return new SimulationFormView(new OptionCatalog(), new SimulationWeaponCatalog($items));
    }

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

        $html = $this->view()->render($action, $fields, []);

        $this->assertStringContainsString('class="sim-panel"', $html);
        $this->assertStringContainsString('Équipement', $html);
        $this->assertStringContainsString('name="distance"', $html);
        $this->assertStringContainsString('name="actor_trait[cc]"', $html);
        $this->assertStringContainsString('name="actor_weapon"', $html);
        $this->assertStringContainsString('name="actor_equipment[tete]"', $html);
        $this->assertStringContainsString('addEffectRow', $html);
        // Rank + energie pickers for each fighter (drive the XP reward).
        $this->assertStringContainsString('name="actor_rank"', $html);
        $this->assertStringContainsString('name="target_rank"', $html);
        $this->assertStringContainsString('name="actor_energie"', $html);
        $this->assertStringContainsString('name="target_energie"', $html);
    }

    public function testActionPointsRemainingDefaultsToThree(): void
    {
        $action = new MeleeAction();
        $action->setName('melee');
        $action->setDisplayName('Attaquer');

        $html = $this->view()->render($action, [new SimulationField('remaining', 'actor', 'a', 'Acteur — a disponible')], []);

        $this->assertStringContainsString('name="actor_remaining[a]" value="3"', $html);
    }

    public function testEnergieDefaultsToTheRealMaxForTheActionPoints(): void
    {
        $action = new MeleeAction();
        $action->setName('melee');
        $action->setDisplayName('Attaquer');

        // No action points posted -> a defaults to 3 -> energie max 7 − 3 = 4.
        $html = $this->view()->render($action, [], []);
        $this->assertStringContainsString('name="actor_energie" value="4"', $html);

        // Posted 6 action points -> energie max 7 − 6 = 1.
        $posted = ['actor_remaining' => ['a' => 6]];
        $this->assertStringContainsString('name="actor_energie" value="1"', $this->view()->render($action, [], $posted));
    }

    public function testRepopulatesSubmittedValues(): void
    {
        $action = new MeleeAction();
        $action->setName('melee');
        $action->setDisplayName('Attaquer');

        $html = $this->view()->render(
            $action,
            [new SimulationField('trait', 'actor', 'cc', 'Acteur — cc')],
            ['actor_trait' => ['cc' => 17], 'runs' => 60],
        );

        $this->assertStringContainsString('value="17"', $html);
        $this->assertStringContainsString('value="60"', $html);
    }

    public function testDistanceIsAlwaysShownEvenWithoutADistanceCondition(): void
    {
        $action = new MeleeAction();
        $action->setName('melee');
        $action->setDisplayName('Attaquer');

        // No shared/distance field declared by the action's conditions.
        $html = $this->view()->render($action, [new SimulationField('trait', 'actor', 'cc', 'cc')], []);

        $this->assertStringContainsString('name="distance"', $html);
        $this->assertStringContainsString('Environnement', $html);
    }

    public function testSelfActionDefaultsDistanceToZeroAndDisablesTheTargetPanel(): void
    {
        $action = new MeleeAction();
        $action->setName('soin');
        $action->setDisplayName('Soin');

        $html = $this->view()->render($action, [], [], ActionTargeting::SELF);

        $this->assertStringContainsString('name="distance" value="0"', $html);
        $this->assertStringContainsString('sim-panel--disabled', $html);
    }

    public function testSelfActionKeepsTheTargetPanelEditableWhenItReadsTargetState(): void
    {
        $action = new MeleeAction();
        $action->setName('regeneration');
        $action->setDisplayName('Régénération');

        // A self-scoped heal that computes from the target's R carac: the field
        // is declared on the target side, so the Cible panel must stay editable.
        $html = $this->view()->render(
            $action,
            [new SimulationField('trait', 'target', 'r', 'Cible — r')],
            [],
            ActionTargeting::SELF,
        );

        $this->assertStringNotContainsString('sim-panel--disabled', $html);
        $this->assertStringContainsString('name="target_trait[r]"', $html);
        $this->assertStringContainsString('name="distance" value="1"', $html);
    }

    public function testTargetActionDefaultsDistanceToOne(): void
    {
        $action = new MeleeAction();
        $action->setName('melee');
        $action->setDisplayName('Attaquer');

        $html = $this->view()->render($action, [], [], ActionTargeting::TARGET);

        $this->assertStringContainsString('name="distance" value="1"', $html);
        $this->assertStringNotContainsString('sim-panel--disabled', $html);
    }

    public function testRunsAreCappedAtAHundred(): void
    {
        $action = new MeleeAction();
        $action->setName('melee');
        $action->setDisplayName('Attaquer');

        $html = $this->view()->render($action, [], ['runs' => 250]);

        $this->assertStringContainsString('name="runs" value="100"', $html);
        $this->assertStringContainsString('max="100"', $html);
    }
}
