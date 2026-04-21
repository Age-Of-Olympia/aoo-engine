<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: admin/tutorial-sessions-api.php's import_all_steps
 * action must route through TutorialStepSaveService so the same enum
 * whitelists, length caps, and CSS-selector checks that protect the
 * editor-save path also protect imports.
 */
class TutorialSessionsApiImportValidationTest extends TestCase
{
    private const API_PATH = __DIR__ . '/../../admin/tutorial-sessions-api.php';

    private function loadSource(): string
    {
        return (string) file_get_contents(self::API_PATH);
    }

    #[Group('tutorial-import-validation')]
    public function testApiImportsTutorialStepSaveService(): void
    {
        $this->assertStringContainsString(
            'TutorialStepSaveService',
            $this->loadSource(),
            'admin/tutorial-sessions-api.php must import TutorialStepSaveService '
            . 'so the import_all_steps path can validate via the same pipeline as '
            . 'admin/tutorial-step-save.php.'
        );
    }

    #[Group('tutorial-import-validation')]
    public function testImportActionInvokesSaveService(): void
    {
        $source = $this->loadSource();

        $importPos = strpos($source, "case 'import_all_steps':");
        $this->assertNotFalse($importPos, "import_all_steps case marker missing");

        $rest = substr($source, $importPos);
        $nextCasePos = strpos($rest, "\n        case '");
        $importBody = $nextCasePos !== false ? substr($rest, 0, $nextCasePos) : $rest;

        $this->assertStringContainsString(
            '->saveStep(',
            $importBody,
            'The import_all_steps case must call ->saveStep(...) on '
            . 'TutorialStepSaveService for each row, not hand-rolled '
            . 'INSERT/UPDATE that bypasses the validator.'
        );
    }

    #[Group('tutorial-import-validation')]
    public function testImportActionDoesNotHandRollTutorialStepsInsert(): void
    {
        $source = $this->loadSource();

        $importPos = strpos($source, "case 'import_all_steps':");
        $this->assertNotFalse($importPos);
        $rest = substr($source, $importPos);
        $nextCasePos = strpos($rest, "\n        case '");
        $importBody = $nextCasePos !== false ? substr($rest, 0, $nextCasePos) : $rest;

        $this->assertDoesNotMatchRegularExpression(
            '/INSERT\s+INTO\s+tutorial_steps\b/i',
            $importBody,
            'import_all_steps must not hand-roll INSERT INTO tutorial_steps — '
            . 'route through TutorialStepSaveService::saveStep so step_type / '
            . 'validation_type / tooltip_position / interaction_mode / '
            . 'context_key / preparation_key are whitelisted uniformly.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/UPDATE\s+tutorial_steps\b/i',
            $importBody,
            'import_all_steps must not hand-roll UPDATE tutorial_steps — '
            . 'route through TutorialStepSaveService::saveStep instead.'
        );
    }
}
