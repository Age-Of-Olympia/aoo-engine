<?php

namespace Tests\Action\WarSchool;

use App\Service\WarSchool\SkillPrerequisiteService;
use PHPUnit\Framework\TestCase;

/** The per-level numbers the tree view prints in its column heads. */
class SkillTreeGateTest extends TestCase
{
    public function testLevelHeadNumbersFollowTheHoldings(): void
    {
        $catalog = [
            'a' => ['category' => 'melee-off', 'level' => 1],
            'b' => ['category' => 'melee-off', 'level' => 1],
        ];
        $passives = [['name' => 'p', 'category' => 'survival', 'level' => 1]];
        $service = new SkillPrerequisiteService(['a' => 'sort'], $catalog, $passives, [2 => 1], 15);

        $this->assertSame(2, SkillPrerequisiteService::requiredPerLevel('melee'));
        $this->assertSame(1, SkillPrerequisiteService::requiredPerLevel('survival'));

        $this->assertSame(1, $service->treeCountAt('melee', 1));
        $this->assertSame(0, $service->treeCountAt('melee', 2));
        $this->assertSame(1, $service->treeCountAt('survival', 1));

        $this->assertTrue($service->isLevelOpen('melee-off', 1));
        $this->assertFalse($service->isLevelOpen('melee-off', 2), 'one of two at level 1');
        $this->assertTrue($service->isLevelOpen('survival', 2), 'secondary wants one');
        $this->assertTrue($service->isLevelOpen('spell-off', 2), 'a free slot at 2');
        $this->assertFalse($service->isLevelOpen('spell-off', 1), 'no slot at 1');
    }
}
