<?php

namespace Tests\Various;

use App\Entity\NonPlayerCharacter;
use App\Entity\RealPlayer;
use App\Entity\TutorialPlayer;
use App\Interface\ProgressesInterface;
use App\Interface\TakesTurnsInterface;
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
 * The contracts are markers; the state lives in the `turns` and `progression`
 * satellites, behind TurnService and ProgressionService.
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

    /**
     * No class maps the satellite columns: `turns`, `progression` and
     * `accounts` hold them, and the `players` columns are dropped.
     *
     * @param class-string $class
     */
    #[DataProvider('everyEntityClass')]
    public function testNoEntityMapsTheSatelliteColumns(string $class): void
    {
        $mapped = \App\Factory\EntityManagerFactory::getEntityManager()
            ->getClassMetadata($class)
            ->getColumnNames();

        $this->assertSame([], array_values(array_intersect([
            'psw', 'mail', 'plain_mail', 'email_bonus', 'lastLoginTime',
            'nextTurnTime', 'lastActionTime', 'nextTurnRescheduled', 'antiBerserkTime',
            'xp', 'rank', 'bonus_points', 'pi',
        ], $mapped)));
    }

    /** @return array<string, array{0: class-string}> */
    public static function everyEntityClass(): array
    {
        return self::characterClasses() + self::structuresThatDoNotPlay()
            + ['bâtiment' => [\App\Entity\Building::class]];
    }
}
