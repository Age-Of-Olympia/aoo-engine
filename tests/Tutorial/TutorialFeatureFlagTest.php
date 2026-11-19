<?php

namespace Tests\Tutorial;

use App\Module\Module;
use App\Module\Modules;
use App\Tutorial\TutorialFeatureFlag;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The tutorial module decides who gets the tutorial: everyone when it is
 * on, its testers otherwise, never an NPC.
 */
#[Group('tutorial-feature-flag')]
class TutorialFeatureFlagTest extends TestCase
{
    protected function tearDown(): void
    {
        Modules::reset();
    }

    public function testEveryoneGetsItWhenTheModuleIsOn(): void
    {
        Modules::force(Module::Tutorial, true);

        $this->assertTrue(TutorialFeatureFlag::isEnabledForPlayer(100));
    }

    public function testOnlyTheTestersGetItWhenTheModuleIsOff(): void
    {
        Modules::force(Module::Tutorial, false, [42, 99]);

        $this->assertTrue(TutorialFeatureFlag::isEnabledForPlayer(42));
        $this->assertTrue(TutorialFeatureFlag::isEnabledForPlayer(99));
        $this->assertFalse(TutorialFeatureFlag::isEnabledForPlayer(100));
    }

    public function testNpcsNeverGetIt(): void
    {
        Modules::force(Module::Tutorial, true, [-1, -1000023]);

        $this->assertFalse(TutorialFeatureFlag::isEnabledForPlayer(-1));
        $this->assertFalse(TutorialFeatureFlag::isEnabledForPlayer(-1000023));
    }
}
