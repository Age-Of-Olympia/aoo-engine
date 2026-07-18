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
    /**
     * OptionCatalog branché sur un catalogue d'effets stub (le vrai vit
     * en base, indisponible sous le bootstrap unitaire).
     */
    private function catalog(): OptionCatalog
    {
        $effectService = $this->createStub(\App\Service\EffectService::class);
        $effectService->method('getGameplayEffectNames')->willReturn(['maladresse', 'protection', 'adrenaline']);
        $effectService->method('getLabel')->willReturnCallback(
            static fn (string $name): string => ucfirst(str_replace('_', ' ', $name))
        );

        return new OptionCatalog(effectService: $effectService);
    }

    public function testEffectsAreDerivedFromTheEffectCatalog(): void
    {
        $effects = $this->catalog()->effects();

        $this->assertArrayHasKey('maladresse', $effects);
        $this->assertArrayHasKey('protection', $effects);
        $this->assertSame('Maladresse', $effects['maladresse']);
    }

    public function testPassivesDegradeToEmptyWhenTheDatabaseIsUnavailable(): void
    {
        // No DB under the unit bootstrap: the lookup must not throw.
        $this->assertSame([], (new OptionCatalog())->passives());
    }

    public function testRendererBuildsACatalogMultiSelectWithSelectedValues(): void
    {
        $field = new ParameterField('actorEffects', FieldType::EFFECT, 'Effets', multiple: true);
        $html = (new ParameterFieldRenderer($this->catalog()))->render($field, 'cond[1][actorEffects]', ['protection']);

        $this->assertStringContainsString('name="cond[1][actorEffects][]" multiple', $html);
        $this->assertStringContainsString('<option value="protection" selected>', $html);
        $this->assertStringContainsString('<option value="maladresse">', $html);
    }

    public function testValidatorRejectsAValueOutsideTheCatalog(): void
    {
        $validator = new ActionParameterValidator($this->catalog());
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
