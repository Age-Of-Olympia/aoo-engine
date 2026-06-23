<?php

namespace Tests\Action\Schema;

use App\View\Action\PassiveConditionEditorView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class PassiveConditionEditorViewTest extends TestCase
{
    private function editor(): PassiveConditionEditorView
    {
        return new PassiveConditionEditorView(
            ['tir' => ['arc' => 'Arc'], 'melee' => ['epee' => 'Épée']],
            ['spell-support' => 'Spell support', 'melee-off' => 'Melee off'],
        );
    }

    public function testNullConditionsSelectTheNoneMode(): void
    {
        $html = $this->editor()->render(null);

        $this->assertStringContainsString('<option value="none" selected>', $html);
        /* The weapon panel exists but is hidden until its mode is chosen. */
        $this->assertStringContainsString('data-cond-mode="weapon" hidden', $html);
    }

    public function testWeaponConditionPreChecksTheSelectedWeaponsAndRevealsThePanel(): void
    {
        $html = $this->editor()->render(['weapon' => ['arc', 'poing']]);

        $this->assertStringContainsString('<option value="weapon" selected>', $html);
        $this->assertStringContainsString('data-cond-mode="weapon"><', $html); /* visible: no hidden attr */
        $this->assertStringContainsString('value="arc" checked', $html);
        $this->assertStringContainsString('value="poing" checked', $html);
        $this->assertStringContainsString('value="epee">', $html); /* offered but unchecked */
        $this->assertStringContainsString('wb-cond-search', $html); /* long-list filter */
    }

    public function testCategoryConditionPreChecksTheSelectedCategories(): void
    {
        $html = $this->editor()->render(['category' => ['spell-support']]);

        $this->assertStringContainsString('<option value="category" selected>', $html);
        $this->assertStringContainsString('value="spell-support" checked', $html);
        $this->assertStringContainsString('value="melee-off">', $html);
    }

    public function testUnknownShapeFallsBackToTheRawJsonEditor(): void
    {
        $html = $this->editor()->render(['custom' => true]);

        $this->assertStringContainsString('<option value="raw" selected>', $html);
        $this->assertStringContainsString('name="passive[conditions]"', $html);
        $this->assertStringContainsString('&quot;custom&quot;', $html);
    }
}
