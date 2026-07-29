<?php

namespace Tests\Action\Condition;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\RequiresFaithCondition;
use App\Action\Condition\RequiresGodAffiliationCondition;
use App\Action\Condition\TargetRaceCondition;
use App\Action\SearchAction;
use App\Entity\ActionCondition;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * What the engine learns in order to speak about gods and altars.
 *
 * Three rules the catalogue could not express: aim at a NAMED type, ask about
 * a god on either side of the gesture, and charge faith points. Nothing here
 * knows what an altar is — that stays in the data.
 */
#[Group('action-condition')]
class GodAndFaithConditionsTest extends TestCase
{
    /** @param array<string, mixed> $data */
    private function actor(array $data): Player
    {
        $actor = $this->createMock(Player::class);
        $actor->data = (object) $data;

        return $actor;
    }

    /** @param array<string, mixed> $params */
    private function condition(array $params): ActionCondition
    {
        $action = new SearchAction();
        $action->setName('gm_test');

        return (new ActionCondition())->setParameters($params)->setAction($action);
    }

    /**
     * The rule `prier` has always had, unchanged: with no parameters, the
     * answer and the wording are the ones that were there before.
     */
    public function testWithoutParametersItIsThePrayerRuleWordForWord(): void
    {
        $condition = new RequiresGodAffiliationCondition();

        $this->assertTrue($condition->check(
            $this->actor(['godId' => 7]), null, $this->condition([]), new ConditionObject()
        )->isSuccess());

        $refused = $condition->check(
            $this->actor(['godId' => 0]), null, $this->condition([]), new ConditionObject()
        );

        $this->assertFalse($refused->isSuccess());
        $this->assertSame(
            'Vos prières ne servent à rien, car vous ne vénérez aucun Dieu !',
            $refused->getConditionFailureMessages()[0]
        );
    }

    /** Consecrating asks the ALTAR to have no god yet. */
    public function testAskingTheTargetForNoGod(): void
    {
        $condition = new RequiresGodAffiliationCondition();
        $params = ['side' => 'target', 'state' => 'none'];

        $this->assertTrue($condition->check(
            $this->actor(['godId' => 7]), $this->actor(['godId' => 0]), $this->condition($params), new ConditionObject()
        )->isSuccess(), 'un autel nu se laisse consacrer');

        $this->assertFalse($condition->check(
            $this->actor(['godId' => 7]), $this->actor(['godId' => 9]), $this->condition($params), new ConditionObject()
        )->isSuccess(), 'un autel déjà consacré, non');
    }

    /**
     * Worshipping asks the altar for a god that is NOT already yours — and a
     * NAKED altar must fail: "different from mine" alone would have let one
     * through and set the worshipper's god to nobody.
     */
    public function testAskingTheTargetForAnotherGodRejectsANakedAltar(): void
    {
        $condition = new RequiresGodAffiliationCondition();
        $params = ['side' => 'target', 'state' => 'other'];

        $this->assertFalse($condition->check(
            $this->actor(['godId' => 7]), $this->actor(['godId' => 0]), $this->condition($params), new ConditionObject()
        )->isSuccess(), 'un autel sans Dieu ne se vénère pas');

        $this->assertFalse($condition->check(
            $this->actor(['godId' => 7]), $this->actor(['godId' => 7]), $this->condition($params), new ConditionObject()
        )->isSuccess(), 'ni celui de son propre Dieu');

        $this->assertTrue($condition->check(
            $this->actor(['godId' => 7]), $this->actor(['godId' => 9]), $this->condition($params), new ConditionObject()
        )->isSuccess());
    }

    /** A named type, where the category "structure" is not precise enough. */
    public function testAimingAtANamedType(): void
    {
        $condition = new TargetRaceCondition();
        $params = ['allowed' => ['altar']];

        $this->assertTrue($condition->check(
            $this->actor([]), $this->actor(['race' => 'altar']), $this->condition($params), new ConditionObject()
        )->isSuccess());

        $this->assertFalse($condition->check(
            $this->actor([]), $this->actor(['race' => 'palissade']), $this->condition($params), new ConditionObject()
        )->isSuccess());
    }

    /** The form yields text; a comma-separated list means the same thing. */
    public function testTheListIsReadFromTextToo(): void
    {
        $this->assertTrue((new TargetRaceCondition())->check(
            $this->actor([]),
            $this->actor(['race' => 'altar']),
            $this->condition(['allowed' => 'forge, altar']),
            new ConditionObject()
        )->isSuccess());
    }

    /** Naming nothing forbids nothing: a half-filled entry is not a wall. */
    public function testNamingNoTypeForbidsNothing(): void
    {
        $this->assertTrue((new TargetRaceCondition())->check(
            $this->actor([]), $this->actor(['race' => 'palissade']), $this->condition([]), new ConditionObject()
        )->isSuccess());
    }

    /** Faith is not a characteristic: it has its own price and its own purse. */
    public function testFaithIsCheckedAgainstWhatIsHeld(): void
    {
        $condition = new RequiresFaithCondition();
        $params = ['pf' => 50];

        $this->assertTrue($condition->check(
            $this->actor(['pf' => 50]), null, $this->condition($params), new ConditionObject()
        )->isSuccess(), 'juste assez suffit');

        $refused = $condition->check(
            $this->actor(['pf' => 49]), null, $this->condition($params), new ConditionObject()
        );

        $this->assertFalse($refused->isSuccess());
        $this->assertStringContainsString('50 points de foi', $refused->getConditionFailureMessages()[0]);
        $this->assertStringContainsString('49', $refused->getConditionFailureMessages()[0], 'dire ce qu\'on a');
    }

    /** And it is actually spent, through the only method that knows how. */
    public function testFaithIsSpentOnPayment(): void
    {
        $actor = $this->createMock(Player::class);
        $actor->data = (object) ['pf' => 80];
        $actor->expects($this->once())->method('put_pf')->with(-50);

        $messages = (new RequiresFaithCondition())
            ->applyCosts($actor, null, $this->condition(['pf' => 50]));

        $this->assertStringContainsString('50 points de foi', $messages[0]);
    }
}
