<?php

namespace Tests\Action\Schema;

use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\Form\ParameterFieldRenderer;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ParameterFieldRendererTest extends TestCase
{
    private ParameterFieldRenderer $renderer;
    private ActionSchemaCatalog $catalog;

    protected function setUp(): void
    {
        if (!defined('CARACS')) {
            define('CARACS', ['cc' => 'CC', 'f' => 'F', 'agi' => 'Agi', 'pm' => 'PM']);
        }
        $this->renderer = new ParameterFieldRenderer();
        $this->catalog = new ActionSchemaCatalog();
    }

    private function field(ParameterSchema $schema, string $key): ParameterField
    {
        $field = $schema->field($key);
        if ($field === null) {
            $this->fail("No field '$key' in schema");
        }

        return $field;
    }

    public function testTraitFieldRendersASelectOfTraits(): void
    {
        $field = $this->field($this->catalog->schemaForCondition('Compute'), 'actorRollType');

        $html = $this->renderer->render($field, 'params[actorRollType]', 'cc');

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('name="params[actorRollType]"', $html);
        $this->assertStringContainsString('<option value="cc" selected>CC</option>', $html);
        $this->assertStringContainsString('<option value="agi"', $html);
    }

    public function testBoolFieldRendersACheckboxThatReflectsTheValue(): void
    {
        $field = $this->field($this->catalog->schemaForCondition('Compute'), 'actorAdvantage');

        $this->assertStringContainsString('type="checkbox"', $this->renderer->render($field, 'p[adv]', true));
        $this->assertStringContainsString('checked', $this->renderer->render($field, 'p[adv]', true));
        $this->assertStringNotContainsString('checked', $this->renderer->render($field, 'p[adv]', false));
    }

    public function testIntFieldRendersANumberInput(): void
    {
        $field = $this->field($this->catalog->schemaForOutcomeInstruction('malus'), 'rollDivisor');

        $html = $this->renderer->render($field, 'p[rollDivisor]', 2);

        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('value="2"', $html);
    }

    public function testEnumFieldRendersASelectOfItsOptions(): void
    {
        $field = $this->field($this->catalog->schemaForOutcomeInstruction('malus'), 'to');

        $html = $this->renderer->render($field, 'p[to]', 'target');

        $this->assertStringContainsString('<option value="actor">Acteur</option>', $html);
        $this->assertStringContainsString('<option value="target" selected>Cible</option>', $html);
    }

    public function testTraitOrIntFieldReferencesTheTraitDatalist(): void
    {
        $field = $this->field($this->catalog->schemaForOutcomeInstruction('manaloss'), 'value');

        $html = $this->renderer->render($field, 'p[value]', 'pm');

        $this->assertStringContainsString('list="caracs-options"', $html);
        $this->assertStringContainsString('<datalist id="caracs-options">', $this->renderer->traitDatalist());
    }

    public function testFallsBackToTheFieldDefaultWhenNoValueGiven(): void
    {
        $field = $this->field($this->catalog->schemaForOutcomeInstruction('malus'), 'rollDivisor');

        $this->assertStringContainsString('value="1"', $this->renderer->render($field, 'p[rollDivisor]'));
    }
}
