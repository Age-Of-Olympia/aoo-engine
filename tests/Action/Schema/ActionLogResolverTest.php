<?php

namespace Tests\Action\Schema;

use App\Action\MeleeAction;
use App\Action\SpellAction;
use App\Entity\ActionTypeLog;
use App\Service\Action\ActionLogResolver;
use App\Service\Action\TypeConfigWarning;
use Classes\Player;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionLogResolverTest extends TestCase
{
    /**
     * @param array<int, ActionTypeLog> $rows what the repository returns for findBy
     */
    private function resolver(array $rows): ActionLogResolver
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($rows);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return new ActionLogResolver($em);
    }

    private function log(string $typeKey, ?string $actor, ?string $target): ActionTypeLog
    {
        return (new ActionTypeLog())->setTypeKey($typeKey)->setActorTemplate($actor)->setTargetTemplate($target);
    }

    private function spell(string $displayName): SpellAction
    {
        $action = new SpellAction();
        $action->setDisplayName($displayName);

        return $action;
    }

    private function player(string $name, string $race = 'humain', ?string $weapon = null): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->data = (object) ['name' => $name, 'race' => $race];
        $player->emplacements = (object) ['main1' => $weapon === null ? null : (object) ['data' => (object) ['name' => $weapon]]];

        return $player;
    }

    public function testRendersActorAndTargetTemplatesWithPlaceholders(): void
    {
        $action = $this->spell('Aiguillon');
        $rows = [$this->log('technique', '{actor} a lancé {action} sur {target}.', '{target} a été attaqué par {actor} avec {action}.')];

        $result = $this->resolver($rows)->resolve($action, $this->player('Dorna'), $this->player('Thyrias'));

        $this->assertSame('Dorna a lancé Aiguillon sur Thyrias.', $result['actor']);
        $this->assertSame('Thyrias a été attaqué par Dorna avec Aiguillon.', $result['target']);
    }

    public function testWeaponPlaceholderAddsTheMainHandClause(): void
    {
        $action = new MeleeAction();
        $rows = [$this->log('attack', '{actor} a attaqué {target}{weapon}.', '{target} a été attaqué par {actor}{weapon}.')];

        $result = $this->resolver($rows)->resolve($action, $this->player('Dorna', 'humain', 'Gladius'), $this->player('Thyrias'));

        $this->assertSame('Dorna a attaqué Thyrias avec Gladius.', $result['actor']);
        $this->assertSame('Thyrias a été attaqué par Dorna avec Gladius.', $result['target']);
    }

    public function testAnimalsFightWithoutAWeaponClause(): void
    {
        $action = new MeleeAction();
        $rows = [$this->log('attack', '{actor} a attaqué {target}{weapon}.', null)];

        $result = $this->resolver($rows)->resolve($action, $this->player('Loup', 'animal', 'Croc'), $this->player('Thyrias'));

        $this->assertSame('Loup a attaqué Thyrias.', $result['actor']);
        $this->assertSame('', $result['target']);
    }

    public function testTheClosestTypeInTheAncestryWins(): void
    {
        // SpellAction's ancestry is spell -> technique -> attack. With both a
        // technique and an attack row, technique (closer) must be chosen.
        $action = $this->spell('Dard');
        $rows = [
            $this->log('attack', 'ATTACK {actor}', null),
            $this->log('technique', 'TECH {actor}', null),
        ];

        $result = $this->resolver($rows)->resolve($action, $this->player('Dorna'), $this->player('Thyrias'));

        $this->assertSame('TECH Dorna', $result['actor']);
    }

    public function testATypeWithNoConfiguredTemplateProducesNoLogLineAndWarns(): void
    {
        $warnings = [];
        TypeConfigWarning::setSink(function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        });
        TypeConfigWarning::reset();

        $action = $this->spell('Dard');

        try {
            $result = $this->resolver([])->resolve($action, $this->player('Dorna'), $this->player('Thyrias'));
        } finally {
            TypeConfigWarning::setSink(null);
            TypeConfigWarning::reset();
        }

        $this->assertSame(['actor' => '', 'target' => ''], $result);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('no log config', $warnings[0]);
    }
}
