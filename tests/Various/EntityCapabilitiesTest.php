<?php

namespace Tests\Various;

use App\Entity\NonPlayerCharacter;
use App\Entity\RealPlayer;
use App\Entity\TutorialPlayer;
use App\Interface\ProgressesInterface;
use App\Interface\TakesTurnsInterface;
use App\Service\ProgressionService;
use App\Service\TurnService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Taking turns and progressing are CAPABILITIES, not a branch: only `Character`
 * holds them today, a playable building will hold them without ever becoming a
 * character (docs/design-playable-buildings.md). What matters here is that a
 * reader can ask through the CONTRACT, which is what lets the gates switch one
 * at a time later.
 *
 * The contracts read; the services write. These cases go through both halves —
 * a service writes, the contract answers — because that agreement is what would
 * rot silently if a write ever went round the service.
 */
#[Group('entities-baseline')]
class EntityCapabilitiesTest extends LegacyPlayerFixtureTestCase
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
    #[DataProvider('characterClasses')]
    public function testEveryCharacterHoldsBothCapabilities(string $class): void
    {
        $implemented = class_implements($class);

        $this->assertContains(TakesTurnsInterface::class, $implemented);
        $this->assertContains(ProgressesInterface::class, $implemented);
    }

    /** The service writes the turn; the contract answers, without naming Character. */
    public function testATurnIsWrittenByTheServiceAndReadThroughTheContract(): void
    {
        $id = (int) $this->createRealPlayer('GmContratTour')->id;

        (new TurnService($this->link))->openTurn($id, 1_800_000_000, 1_800_005_400);

        $entity = $this->reloadCharacter($id);

        $this->assertSame(1_800_000_000, $this->whenIsItsTurn($entity));
        $this->assertSame(0, $entity->getLastActionTime(), 'a fresh turn has taken no action');
        $this->assertFalse($entity->isNextTurnRescheduled());
    }

    /** Progression too: experience, a level, points left to spend. */
    public function testProgressionIsWrittenByTheServiceAndReadThroughTheContract(): void
    {
        $id = (int) $this->createRealPlayer('GmContratXp')->id;

        $this->assertSame(0, $this->howFarAlong($this->reloadCharacter($id)));

        (new ProgressionService($this->link))->gain($id, 150, 150, 3);

        $entity = $this->reloadCharacter($id);

        $this->assertSame(150, $this->howFarAlong($entity));
        $this->assertSame(3, $entity->getRank());
        $this->assertSame(150, $entity->getPi());
    }

    /**
     * The contract offers no setter, so nothing can write the mirror column and
     * leave the satellite behind. The services are the writers.
     */
    #[DataProvider('writeMethodsThatMustNotExist')]
    public function testTheContractsOfferNoWrite(string $interface, string $method): void
    {
        $this->assertFalse(
            method_exists($interface, $method),
            "{$interface}::{$method}() would reach the mirror column alone"
        );
    }

    /** @return array<string, array{0: class-string, 1: string}> */
    public static function writeMethodsThatMustNotExist(): array
    {
        $cases = [];
        foreach (['setNextTurnTime', 'setLastActionTime', 'setNextTurnRescheduled'] as $method) {
            $cases[$method] = [TakesTurnsInterface::class, $method];
        }
        foreach (['setXp', 'addXp', 'setRank', 'setBonusPoints', 'setPi', 'addPi'] as $method) {
            $cases[$method] = [ProgressesInterface::class, $method];
        }

        return $cases;
    }

    /**
     * A building holds both, without being a character and without an account —
     * the point the whole strand was aiming at.
     *
     * Asked of the CLASS, not an instance: static analysis settles an
     * assertInstanceOf before it ever runs.
     */
    public function testABuildingHoldsBothCapabilitiesWithoutBeingACharacter(): void
    {
        $implemented = class_implements(\App\Entity\Building::class);

        $this->assertContains(TakesTurnsInterface::class, $implemented);
        $this->assertContains(ProgressesInterface::class, $implemented);
        $this->assertNotContains(\App\Entity\Character::class, class_parents(\App\Entity\Building::class));
    }

    /**
     * The other structures hold neither: a tree does not take turns and a
     * dropped sword earns nothing. Being a structure is not the capability —
     * holding it is.
     *
     * @param class-string $class
     */
    #[DataProvider('structuresThatDoNotPlay')]
    public function testTheOtherStructuresHoldNeitherCapability(string $class): void
    {
        $implemented = class_implements($class);

        $this->assertNotContains(TakesTurnsInterface::class, $implemented);
        $this->assertNotContains(ProgressesInterface::class, $implemented);
    }

    /** @return array<string, array{0: class-string}> */
    public static function structuresThatDoNotPlay(): array
    {
        return [
            'décor'     => [\App\Entity\Scenery::class],
            'ressource' => [\App\Entity\Resource::class],
            'plante'    => [\App\Entity\Plant::class],
            'exemplaire' => [\App\Entity\Exemplar::class],
        ];
    }

    private function reloadCharacter(int $id): RealPlayer
    {
        $em = \App\Factory\EntityManagerFactory::getEntityManager();
        $em->clear();

        return $em->find(RealPlayer::class, $id);
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
