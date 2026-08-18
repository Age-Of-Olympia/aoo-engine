<?php

namespace Tests\Action\Compute;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\MeleePureComputeCondition;
use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;
use Tests\Action\Mock\ScriptedDice;

#[Group('action-combat')]
class MeleePureComputeConditionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('AUTO_FAIL')) {
            define('AUTO_FAIL', false);
        }
    }

    private function player(int $id, string $name): PlayerMock
    {
        $player = new PlayerMock($id, $name);
        $player->caracs->force = 1;

        return $player;
    }

    private function condition(): ActionCondition
    {
        $action = new MeleeAction();
        $action->setName('attaquer');

        $condition = new ActionCondition();
        $condition->setConditionType('MeleePure');
        $condition->setParameters(['actorRollType' => 'force', 'targetRollType' => 'force']);
        $condition->setAction($action);

        return $condition;
    }

    /**
     * Exécute la condition et retourne le résultat ainsi que l'état du ConditionObject
     * * @return array{result: \App\Action\Condition\ConditionResult, conditionObject: ConditionObject}
     */
    private function check(ScriptedDice $dice, ?PlayerMock $customActor = null): array
    {
        $condition = $this->condition();
        $conditionObject = new ConditionObject();
        $conditionObject->setAction($condition->getAction());

        $actor = $customActor ?? $this->player(1, 'Actor');
        $target = $this->player(2, 'Target');

        $result = (new MeleePureComputeCondition($dice))->check(
            $actor,
            $target,
            $condition,
            $conditionObject,
        );

        return [
            'result' => $result,
            'conditionObject' => $conditionObject
        ];
    }

    public function testActorWinsWhenItsInjectedRollBeatsTheTarget(): void
    {
        $checkData = $this->check(new ScriptedDice([[10], [5]]));
        $this->assertTrue($checkData['result']->isSuccess());
    }

    public function testActorLosesWhenItsInjectedRollIsLower(): void
    {
        $checkData = $this->check(new ScriptedDice([[5], [10]]));
        $this->assertFalse($checkData['result']->isSuccess());
    }

    /**
     * Vérifie qu'un effet d'équipement est correctement récupéré et poussé dans le ConditionObject
     */
    public function testEffectsAreCorrectlyAppliedToConditionObject(): void
    {
        // 1. On crée un acteur spécifique
        $actor = $this->player(1, 'Cradek');

        // 2. On simule le retour de getEquipedItemsEffects() sur ton PlayerMock
        // Note : Vérifie si ton PlayerMock permet de mocker directement cette méthode
        // ou si tu dois lui injecter un faux service. Ici on simule une structure stdClass
        $mockEffect = new \stdClass();
        $mockEffect->name = 'feu';
        $mockEffect->duration = 20;
        $mockEffect->on = 'target';
        $mockEffect->when = 'win';

        // Idéalement, si ton PlayerMock hérite bien de Player, on mock la méthode :
        $actor = $this->getMockBuilder(PlayerMock::class)
            ->setConstructorArgs([1, 'Cradek'])
            ->onlyMethods(['getEquipedItemsEffects'])
            ->getMock();

        $actor->caracs->force = 1;
        $actor->method('getEquipedItemsEffects')->willReturn([$mockEffect]);

        // 3. On lance le check avec un jet victorieux
        $checkData = $this->check(new ScriptedDice([[10], [5]]), $actor);

        // 4. On vérifie que le tableau d'effets du conditionObject contient bien notre effet 'feu'
        $attackEffects = $checkData['conditionObject']->getAttackEffects();

        $this->assertCount(1, $attackEffects);
        $this->assertEquals('feu', $attackEffects[0]->name);
    }

    /**
     * Vérifie que de multiples effets (comme feu et boue) coexistent sans s'écraser
     */
    public function testMultipleEffectsAreAllRetainedInConditionObject(): void
    {
        $actor = $this->getMockBuilder(PlayerMock::class)
            ->setConstructorArgs([1, 'Cradek'])
            ->onlyMethods(['getEquipedItemsEffects'])
            ->getMock();

        $actor->caracs->force = 1;

        // On prépare nos deux effets distincts
        $effectFeu = (object) ['name' => 'feu', 'duration' => 20, 'on' => 'target', 'when' => 'win'];
        $effectBoue = (object) ['name' => 'boue', 'duration' => 20, 'on' => 'target', 'when' => 'win'];

        // La méthode doit retourner les deux effets dans le tableau plat
        $actor->method('getEquipedItemsEffects')->willReturn([$effectFeu, $effectBoue]);

        $checkData = $this->check(new ScriptedDice([[10], [5]]), $actor);
        $attackEffects = $checkData['conditionObject']->getAttackEffects();

        // On valide qu'aucun effet n'a sauté (Taille = 2)
        $this->assertCount(2, $attackEffects);
        $this->assertEquals('feu', $attackEffects[0]->name);
        $this->assertEquals('boue', $attackEffects[1]->name);
    }
}