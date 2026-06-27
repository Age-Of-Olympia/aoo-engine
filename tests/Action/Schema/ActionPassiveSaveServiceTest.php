<?php

namespace Tests\Action\Schema;

use App\Entity\ActionPassive;
use App\Service\Action\ActionPassiveSaveService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionPassiveSaveServiceTest extends TestCase
{
    private function entityManagerFinding(?ActionPassive $passive): EntityManagerInterface&MockObject
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($passive);

        return $em;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function fields(array $overrides = []): array
    {
        return array_merge([
            'name' => 'griffes', 'displayName' => 'Griffes', 'type' => 'att', 'carac' => 'fixed',
            'value' => '2.5', 'level' => '3', 'race' => 'animal', 'category' => 'melee',
            'prerequisites' => '', 'text' => 'Inflige des dégâts', 'traits' => 'f, e', 'conditions' => '{"x":1}',
        ], $overrides);
    }

    public function testSavesScalarsTraitsAndConditions(): void
    {
        $passive = new ActionPassive();
        $em = $this->entityManagerFinding($passive);
        $em->expects($this->once())->method('flush');

        (new ActionPassiveSaveService($em))->saveFields(1, $this->fields());

        $this->assertSame('griffes', $passive->getName());
        $this->assertSame('Griffes', $passive->getDisplayName());
        $this->assertSame('att', $passive->getType());
        $this->assertSame(2.5, $passive->getValue());
        $this->assertSame(3, $passive->getLevel());
        $this->assertSame(['f', 'e'], $passive->getTraits());
        $this->assertSame(['x' => 1], $passive->getConditions());
    }

    public function testTraitsFromTheMultiSelectArriveAsAnArray(): void
    {
        $passive = new ActionPassive();
        $em = $this->entityManagerFinding($passive);

        // The multi-select posts passive[traits][] -> an array (with stray blanks).
        (new ActionPassiveSaveService($em))->saveFields(1, $this->fields(['traits' => ['cc', ' agi ', '']]));

        $this->assertSame(['cc', 'agi'], $passive->getTraits());
    }

    public function testDisplayNameFallsBackToName(): void
    {
        $passive = new ActionPassive();
        (new ActionPassiveSaveService($this->entityManagerFinding($passive)))
            ->saveFields(1, $this->fields(['displayName' => '   ']));

        $this->assertSame('griffes', $passive->getDisplayName());
    }

    public function testEmptyConditionsBecomeNull(): void
    {
        $passive = new ActionPassive();
        (new ActionPassiveSaveService($this->entityManagerFinding($passive)))
            ->saveFields(1, $this->fields(['conditions' => '']));

        $this->assertNull($passive->getConditions());
    }

    public function testRejectsAMissingPassive(): void
    {
        $em = $this->entityManagerFinding(null);
        $em->expects($this->never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);
        (new ActionPassiveSaveService($em))->saveFields(999, $this->fields());
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ActionPassiveSaveService($this->entityManagerFinding(new ActionPassive())))
            ->saveFields(1, $this->fields(['name' => '   ']));
    }

    public function testRejectsInvalidConditionsJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ActionPassiveSaveService($this->entityManagerFinding(new ActionPassive())))
            ->saveFields(1, $this->fields(['conditions' => '{not json']));
    }

    public function testWeaponModeBuildsAWeaponWhitelist(): void
    {
        $passive = new ActionPassive();
        (new ActionPassiveSaveService($this->entityManagerFinding($passive)))->saveFields(1, $this->fields([
            'conditions_mode' => 'weapon',
            'conditions_weapon' => ['arc', 'poing', ''],
            'conditions' => '{"x":1}',
        ]));

        $this->assertSame(['weapon' => ['arc', 'poing']], $passive->getConditions());
    }

    public function testCategoryModeBuildsACategoryWhitelist(): void
    {
        $passive = new ActionPassive();
        (new ActionPassiveSaveService($this->entityManagerFinding($passive)))->saveFields(1, $this->fields([
            'conditions_mode' => 'category',
            'conditions_category' => ['spell-support', 'melee-off'],
        ]));

        $this->assertSame(['category' => ['spell-support', 'melee-off']], $passive->getConditions());
    }

    public function testNoneModeClearsConditions(): void
    {
        $passive = new ActionPassive();
        (new ActionPassiveSaveService($this->entityManagerFinding($passive)))->saveFields(1, $this->fields([
            'conditions_mode' => 'none',
            'conditions' => '{"weapon":["arc"]}',
        ]));

        $this->assertNull($passive->getConditions());
    }

    public function testAnEmptyWeaponSelectionMeansNoCondition(): void
    {
        $passive = new ActionPassive();
        (new ActionPassiveSaveService($this->entityManagerFinding($passive)))->saveFields(1, $this->fields([
            'conditions_mode' => 'weapon',
            'conditions_weapon' => ['', '  '],
        ]));

        $this->assertNull($passive->getConditions());
    }

    public function testRawModeStillParsesTheJsonFallback(): void
    {
        $passive = new ActionPassive();
        (new ActionPassiveSaveService($this->entityManagerFinding($passive)))->saveFields(1, $this->fields([
            'conditions_mode' => 'raw',
            'conditions' => '{"weapon":["arc","arbalete"]}',
        ]));

        $this->assertSame(['weapon' => ['arc', 'arbalete']], $passive->getConditions());
    }
}
