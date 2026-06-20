<?php

namespace Tests\Action\Schema;

use App\Action\Schema\FieldType;
use App\Action\Schema\Form\ParameterFieldRenderer;
use App\Action\Schema\OptionCatalog;
use App\Action\Schema\ParameterField;
use App\Service\Action\ActionParameterValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class OptionCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('EFFECTS_RA_FONT')) {
            define('EFFECTS_RA_FONT', ['maladresse' => 'ra-x', 'protection' => 'ra-y', 'adrenaline' => 'ra-z']);
        }
    }

    public function testEffectsAreDerivedFromTheRealEffectConstant(): void
    {
        $effects = (new OptionCatalog())->effects();

        $this->assertArrayHasKey('maladresse', $effects);
        $this->assertArrayHasKey('protection', $effects);
        $this->assertSame('Maladresse', $effects['maladresse']);
    }

    public function testWeaponTypesAndEmplacementsAreEnumerated(): void
    {
        $catalog = new OptionCatalog();

        $this->assertSame(['melee', 'tir', 'jet', 'bouclier'], array_keys($catalog->weaponTypes()));
        $this->assertArrayHasKey('main1', $catalog->emplacements());
    }

    public function testPassivesDegradeToEmptyWhenTheDatabaseIsUnavailable(): void
    {
        // No DB under the unit bootstrap: the lookup must not throw.
        $this->assertSame([], (new OptionCatalog())->passives());
    }

    public function testRendererBuildsACatalogMultiSelectWithSelectedValues(): void
    {
        $field = new ParameterField('actorEffects', FieldType::EFFECT, 'Effets', multiple: true);
        $html = (new ParameterFieldRenderer(new OptionCatalog()))->render($field, 'cond[1][actorEffects]', ['protection']);

        $this->assertStringContainsString('name="cond[1][actorEffects][]" multiple', $html);
        $this->assertStringContainsString('<option value="protection" selected>', $html);
        $this->assertStringContainsString('<option value="maladresse">', $html);
    }

    public function testValidatorRejectsAValueOutsideTheCatalog(): void
    {
        $validator = new ActionParameterValidator(new OptionCatalog());
        $field = new ParameterField('actorEffect', FieldType::EFFECT, 'Effet');

        $this->expectException(InvalidArgumentException::class);
        $this->coerce($validator, $field, 'not_a_real_effect');
    }

    public function testValidatorCoercesAMultiCatalogFieldToACleanList(): void
    {
        $validator = new ActionParameterValidator(new OptionCatalog());
        $field = new ParameterField('type', FieldType::WEAPON_TYPE, 'Types', multiple: true);

        $this->assertSame(['melee', 'jet'], $this->coerce($validator, $field, ['melee', '', 'jet']));
    }

    private function coerce(ActionParameterValidator $validator, ParameterField $field, mixed $raw): mixed
    {
        return $validator->coerce(new \App\Action\Schema\ParameterSchema($field), [$field->key => $raw])[$field->key];
    }
}
