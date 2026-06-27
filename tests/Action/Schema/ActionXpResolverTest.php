<?php

namespace Tests\Action\Schema;

use App\Action\SpellAction;
use App\Entity\ActionTypeXp;
use App\Service\Action\ActionXpResolver;
use App\Service\Action\Xp\XpCalculatorRegistry;
use Classes\Player;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionXpResolverTest extends TestCase
{
    /**
     * @param array<int, ActionTypeXp> $rows
     */
    private function resolver(array $rows): ActionXpResolver
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($rows);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return new ActionXpResolver($em);
    }

    private function player(array $data): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->data = (object) $data;

        return $player;
    }

    public function testASpellInheritsTheAttackXpRule(): void
    {
        $config = (new ActionTypeXp())->setTypeKey('attack')->setMode(XpCalculatorRegistry::MODE_ATTACK)
            ->setParams(['base' => 5, 'min' => 2, 'reducedXp' => 1, 'diffCap' => 3, 'targetFail' => 2]);

        $actor = $this->player(['rank' => 5, 'faction' => '', 'secretFaction' => '', 'isInactive' => false]);
        $actor->method('get_upgrades')->willReturn((object) ['a' => 0]);
        $target = $this->player(['rank' => 4, 'faction' => '', 'secretFaction' => '', 'isInactive' => false]);

        // SpellAction's ancestry is spell -> technique -> attack; the attack row applies.
        $result = $this->resolver([$config])->calculate(new SpellAction(), true, $actor, $target);

        $this->assertSame(4, $result['actor']); // 5 - diff 1 - 0
    }

    public function testFixedRuleIsUsedDirectly(): void
    {
        $config = (new ActionTypeXp())->setTypeKey('attack')->setMode(XpCalculatorRegistry::MODE_FIXED)
            ->setParams(['actorSuccess' => 7, 'actorFail' => 0, 'targetSuccess' => 0, 'targetFail' => 0]);

        $result = $this->resolver([$config])->calculate(new SpellAction(), true, $this->player([]), $this->player([]));

        $this->assertSame(7, $result['actor']);
    }

    public function testATypeWithNoConfiguredRuleGrantsNoXp(): void
    {
        $result = $this->resolver([])->calculate(new SpellAction(), true, $this->player([]), $this->player([]));

        $this->assertSame(['actor' => 0, 'target' => 0], $result);
    }
}
