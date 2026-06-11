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
 * runtime dispatches on, and guarantees the admin validator
 * (TutorialStepValidationService) accepts every advertised key and
 * rejects unknowns.
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
}
