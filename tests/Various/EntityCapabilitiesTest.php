<?php

namespace Tests\Various;

use App\Entity\NonPlayerCharacter;
use App\Entity\RealPlayer;
use App\Entity\TutorialPlayer;
use App\Interface\ProgressesInterface;
use App\Interface\TakesTurnsInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Taking turns and progressing are CAPABILITIES, not a branch: only `Character`
 * holds them today, a playable building will hold them without ever becoming a
 * character (docs/design-playable-buildings.md). What matters here is that a
 * reader can ask through the CONTRACT, which is what lets the gates switch one
 * at a time later.
 */
#[Group('entities-baseline')]
class EntityCapabilitiesTest extends TestCase
{
    /** @return array<string, array{0: class-string}> */
    public static function characterClasses(): array
    {
        return [
            'joueur'   => [RealPlayer::class],
            'tutoriel' => [TutorialPlayer::class],
            'PNJ'      => [NonPlayerCharacter::class],
        ];
    }

    /**
     * @param class-string $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('characterClasses')]
    public function testEveryCharacterHoldsBothCapabilities(string $class): void
    {
        $entity = new $class();

        $this->assertInstanceOf(TakesTurnsInterface::class, $entity);
        $this->assertInstanceOf(ProgressesInterface::class, $entity);
    }

    /** The turn is read and written through the contract, without naming Character. */
    public function testATurnIsReadAndWrittenThroughTheContract(): void
    {
        $entity = new RealPlayer();

        $this->assertSame(0, $this->whenIsItsTurn($entity), 'une entité neuve joue tout de suite');

        $entity->setNextTurnTime(1_800_000_000)
            ->setLastActionTime(1_799_999_000)
            ->setNextTurnRescheduled(true);

        $this->assertSame(1_800_000_000, $this->whenIsItsTurn($entity));
        $this->assertSame(1_799_999_000, $entity->getLastActionTime());
        $this->assertTrue($entity->isNextTurnRescheduled());
    }

    /** Progression too: experience, a level, points left to spend. */
    public function testProgressionIsReadAndWrittenThroughTheContract(): void
    {
        $entity = new RealPlayer();

        $this->assertSame(0, $this->howFarAlong($entity));

        $entity->addXp(150);
        $entity->setRank(3)->setBonusPoints(2);

        $this->assertSame(150, $this->howFarAlong($entity));
        $this->assertSame(3, $entity->getRank());
        $this->assertSame(2, $entity->getBonusPoints());
    }

    /**
     * A structure holds neither capability TODAY — this case dates that state,
     * and is where to come when a playable building takes them on.
     *
     * Asked of the CLASS, not an instance: `assertNotInstanceOf` on a Building
     * is true by construction and static analysis says so before it runs.
     */
    public function testAStructureHoldsNeitherCapabilityYet(): void
    {
        $implemented = class_implements(\App\Entity\Building::class);

        $this->assertNotContains(TakesTurnsInterface::class, $implemented);
        $this->assertNotContains(ProgressesInterface::class, $implemented);
    }

    private function whenIsItsTurn(TakesTurnsInterface $entity): int
    {
        return $entity->getNextTurnTime();
    }

    private function howFarAlong(ProgressesInterface $entity): int
    {
        return $entity->getXp();
    }
}
