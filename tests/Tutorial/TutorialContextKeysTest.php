<?php

declare(strict_types=1);

namespace Tests\Tutorial;

use App\Service\TutorialStepValidationService;
use App\Tutorial\TutorialContextKeys;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterization test for TutorialContextKeys.
 *
 * Pins the exact context-change and next-preparation keys that the tutorial
 * runtime dispatches on, and guarantees:
 *
 *  1. the admin validator (TutorialStepValidationService) accepts every
 *     advertised key and rejects unknowns;
 *  2. the runtime handlers (AbstractStep::applyContextChanges and
 *     TutorialContext::prepareForNextStep) actually dispatch on every key
 *     the whitelist promises — so adding a key to the whitelist without
 *     wiring the runtime fails in CI instead of silently no-op'ing in prod.
 */
#[Group('tutorial')]
class TutorialContextKeysTest extends TestCase
{
    public function testContextChangeKeysArePinned(): void
    {
        self::assertSame(
            [
                'unlimited_mvt',
                'unlimited_actions',
                'consume_movements',
                'set_mvt_limit',
                'set_action_limit',
            ],
            TutorialContextKeys::contextChangeKeys()
        );
    }

    public function testNextPreparationKeysArePinned(): void
    {
        self::assertSame(
            [
                'restore_mvt',
                'restore_actions',
                'spawn_enemy',
                'spawn_item',
                'remove_enemy',
                'remove_item',
            ],
            TutorialContextKeys::nextPreparationKeys()
        );
    }

    public function testValidatorAcceptsEveryContextChangeKey(): void
    {
        $service = new TutorialStepValidationService();
        foreach (TutorialContextKeys::contextChangeKeys() as $key) {
            self::assertSame($key, $service->validateContextChangeKey($key));
        }
    }

    public function testValidatorAcceptsEveryPreparationKey(): void
    {
        $service = new TutorialStepValidationService();
        foreach (TutorialContextKeys::nextPreparationKeys() as $key) {
            self::assertSame($key, $service->validatePreparationKey($key));
        }
    }

    public function testValidatorTreatsEmptyAndWhitespaceAsNull(): void
    {
        $service = new TutorialStepValidationService();
        self::assertNull($service->validateContextChangeKey(''));
        self::assertNull($service->validateContextChangeKey(null));
        self::assertNull($service->validateContextChangeKey('   '));
        self::assertNull($service->validatePreparationKey(''));
        self::assertNull($service->validatePreparationKey(null));
        self::assertNull($service->validatePreparationKey('   '));
    }

    public function testValidatorRejectsUnknownContextChangeKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TutorialStepValidationService())->validateContextChangeKey('restore_movements');
    }

    public function testValidatorRejectsUnknownPreparationKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TutorialStepValidationService())->validatePreparationKey('spawn_npc');
    }

    /**
     * Guards against whitelist-runtime drift: every key we advertise must
     * appear as an `isset($changes['KEY'])` dispatch in AbstractStep.
     */
    public function testRuntimeDispatchesEveryContextChangeKey(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../src/Tutorial/Steps/AbstractStep.php');

        foreach (TutorialContextKeys::contextChangeKeys() as $key) {
            self::assertStringContainsString(
                "\$changes['" . $key . "']",
                $source,
                "AbstractStep::applyContextChanges() must dispatch on '$key' — add a handler or remove it from TutorialContextKeys::CONTEXT_CHANGES."
            );
        }
    }

    /**
     * Guards against whitelist-runtime drift: every key we advertise must
     * appear as an `isset($preparation['KEY'])` dispatch in TutorialContext.
     */
    public function testRuntimeDispatchesEveryPreparationKey(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../src/Tutorial/TutorialContext.php');

        foreach (TutorialContextKeys::nextPreparationKeys() as $key) {
            self::assertStringContainsString(
                "\$preparation['" . $key . "']",
                $source,
                "TutorialContext::prepareForNextStep() must dispatch on '$key' — add a handler or remove it from TutorialContextKeys::NEXT_PREPARATIONS."
            );
        }
    }
}
