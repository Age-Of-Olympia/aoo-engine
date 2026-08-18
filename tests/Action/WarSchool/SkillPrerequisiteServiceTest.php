<?php

namespace Tests\Action\WarSchool;

use App\Service\WarSchool\SkillPrerequisiteService;
use PHPUnit\Framework\TestCase;

/**
 * The prerequisite engine on injected data — no database. Trees: primary
 * (melee, distance, magic) want 2 owned skills per level below, secondary
 * (survival, stealth) want 1, spells want a free slot at their level.
 */
class SkillPrerequisiteServiceTest extends TestCase
{
    private const MAX_COMP = 15;

    /**
     * @param array<string, string> $ownedActions
     * @param array<string, array{category: ?string, level: int}> $catalog
     * @param array<int, array{name: string, category: ?string, level: int}> $passives
     * @param array<int, int> $spellSlots
     */
    private function service(
        array $ownedActions = [],
        array $catalog = [],
        array $passives = [],
        array $spellSlots = [],
        int $maxComp = self::MAX_COMP
    ): SkillPrerequisiteService {
        return new SkillPrerequisiteService($ownedActions, $catalog, $passives, $spellSlots, $maxComp);
    }

    public function testLevelOneNeedsNothing(): void
    {
        $this->assertTrue($this->service()->isSkillUsable('melee-off', 1, null));
        $this->assertTrue($this->service()->isSkillUsable('survival', 1, null));
    }

    public function testPrimaryTreeWantsTwoSkillsPerLevelBelow(): void
    {
        $catalog = [
            'coup_bas' => ['category' => 'melee-off', 'level' => 1],
            'taillade' => ['category' => 'melee-off', 'level' => 1],
        ];

        $one = $this->service(['coup_bas' => 'sort'], $catalog);
        $this->assertFalse($one->isSkillUsable('melee-off', 2, null), 'one level-1 skill is not enough');

        $two = $this->service(['coup_bas' => 'sort', 'taillade' => 'sort'], $catalog);
        $this->assertTrue($two->isSkillUsable('melee-off', 2, null));
    }

    public function testEveryLevelBelowMustBeFilledNotJustTheLast(): void
    {
        // two level-2 skills but a hole at level 1 — level 3 stays locked
        $catalog = [
            'a' => ['category' => 'melee-off', 'level' => 2],
            'b' => ['category' => 'melee-off', 'level' => 2],
        ];
        $service = $this->service(['a' => 'sort', 'b' => 'sort'], $catalog);

        $this->assertFalse($service->isSkillUsable('melee-off', 3, null));
    }

    public function testSecondaryTreeWantsOneSkillPerLevelBelow(): void
    {
        $catalog = ['piegeage' => ['category' => 'survival', 'level' => 1]];

        $this->assertFalse($this->service([], $catalog)->isSkillUsable('survival', 2, null));
        $this->assertTrue($this->service(['piegeage' => 'sort'], $catalog)->isSkillUsable('survival', 2, null));
    }

    public function testAnotherTreesSkillsDoNotUnlockThisTree(): void
    {
        $catalog = [
            'a' => ['category' => 'distance-off', 'level' => 1],
            'b' => ['category' => 'distance-off', 'level' => 1],
        ];
        $service = $this->service(['a' => 'sort', 'b' => 'sort'], $catalog);

        $this->assertFalse($service->isSkillUsable('melee-off', 2, null));
        $this->assertTrue($service->isSkillUsable('distance-off', 2, null));
    }

    public function testPassivesCountTowardTheTreeGate(): void
    {
        $catalog = ['a' => ['category' => 'magic-off', 'level' => 1]];
        $passives = [['name' => 'p1', 'category' => 'magic', 'level' => 1]];

        $service = $this->service(['a' => 'sort'], $catalog, $passives);

        $this->assertTrue($service->isSkillUsable('magic-off', 2, null));
    }

    public function testUnknownOrEmptyCategoryCarriesNoGate(): void
    {
        $this->assertTrue($this->service()->isSkillUsable(null, 4, null));
        $this->assertTrue($this->service()->isSkillUsable('todo', 4, null));
    }

    public function testSpellWantsAFreeSlotAtItsLevel(): void
    {
        $catalog = ['eclair' => ['category' => 'spell-off', 'level' => 1]];

        $noSlot = $this->service([], [], [], []);
        $this->assertFalse($noSlot->isSkillUsable('spell-off', 1, null), 'no slot, no spell');

        $free = $this->service([], [], [], [1 => 1]);
        $this->assertTrue($free->isSkillUsable('spell-off', 1, null));

        $full = $this->service(['eclair' => 'sort'], $catalog, [], [1 => 1]);
        $this->assertFalse($full->isSkillUsable('spell-off', 1, null), 'the only slot is taken');
        $this->assertFalse($full->hasFreeSpellSlot(1));
        $this->assertSame(1, $full->spellCountAt(1));

        // the slot of a level serves that level only
        $this->assertFalse($free->isSkillUsable('spell-off', 2, null));
    }

    public function testNeedAndForbiddenReadTheOwnedNames(): void
    {
        $service = $this->service(['berserker' => 'sort'], [], [['name' => 'voie_air', 'category' => null, 'level' => 1]]);

        $this->assertTrue($service->isSkillUsable(null, 1, '{"need": ["berserker"]}'));
        $this->assertFalse($service->isSkillUsable(null, 1, '{"need": ["berserker", "absent"]}'));
        $this->assertFalse($service->isSkillUsable(null, 1, '{"forbidden": ["voie_air"]}'), 'passives count as owned');
        $this->assertTrue($service->isSkillUsable(null, 1, '{"forbidden": ["voie_terre"]}'));
        $this->assertTrue($service->isSkillUsable(null, 1, 'not json'), 'unreadable prerequisites do not block');
    }

    public function testCapCountsBoughtNonSpellSkillsAndPassives(): void
    {
        $catalog = [
            'taillade' => ['category' => 'melee-off', 'level' => 1],
            'eclair' => ['category' => 'spell-off', 'level' => 1],
        ];
        $owned = [
            'taillade' => 'sort',   // bought skill: counts
            'eclair' => 'sort',     // bought spell: slot-capped, not comp-capped
            'attaquer' => '',       // starter action: free
        ];
        $passives = [['name' => 'p1', 'category' => 'melee', 'level' => 1]];

        $service = $this->service($owned, $catalog, $passives);

        $this->assertSame(2, $service->capCount());
        $this->assertFalse($service->isFull());
        $this->assertTrue($this->service($owned, $catalog, $passives, [], 2)->isFull());
    }

    public function testOwnsCoversActionsAndPassives(): void
    {
        $service = $this->service(['taillade' => 'sort'], [], [['name' => 'p1', 'category' => null, 'level' => 1]]);

        $this->assertTrue($service->owns('taillade'));
        $this->assertTrue($service->owns('p1'));
        $this->assertFalse($service->owns('eclair'));
    }

    public function testTreeExtraction(): void
    {
        $this->assertSame('melee', SkillPrerequisiteService::tree('melee-off'));
        $this->assertSame('spell', SkillPrerequisiteService::tree('spell-curse'));
        $this->assertSame('survival', SkillPrerequisiteService::tree('survival'));
        $this->assertNull(SkillPrerequisiteService::tree('todo'));
        $this->assertNull(SkillPrerequisiteService::tree(null));
        $this->assertNull(SkillPrerequisiteService::tree(''));
    }
}
