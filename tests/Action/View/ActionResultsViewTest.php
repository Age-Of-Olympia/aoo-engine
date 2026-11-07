<?php

namespace Tests\Action\View;

use App\Action\ActionResults;
use App\Action\OutcomeInstruction\OutcomeResult;
use App\View\ActionResultsView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * What the player reads after an action: the header follows the roll,
 * except when every outcome refused — then nothing the player asked for
 * happened. Each outcome shows what it did, or its refusal, whatever the
 * roll (a recipe short of ingredients used to render "Réussite !" with no
 * explanation).
 */
#[Group('action')]
class ActionResultsViewTest extends TestCase
{
    /** @param OutcomeResult[] $outcomes */
    private function render(bool $success, bool $blocked, array $outcomes): string
    {
        $results = new ActionResults($success, $blocked, [], $outcomes, [], [], []);

        return (new ActionResultsView($results))->getActionResults();
    }

    public function testASuccessfulOutcomeRendersAWin(): void
    {
        $html = $this->render(true, false, [new OutcomeResult(true, ['Vous avez créé palissade (1)'], [])]);

        $this->assertStringContainsString('Réussite !', $html);
        $this->assertStringContainsString('Vous avez créé palissade (1)', $html);
    }

    public function testARefusedOutcomeOnASuccessfulRollRendersItsRefusal(): void
    {
        $html = $this->render(true, false, [new OutcomeResult(false, [], ["Vous n'avez pas assez de bois"])]);

        $this->assertStringContainsString('Echec !', $html);
        $this->assertStringNotContainsString('Réussite', $html);
        $this->assertStringContainsString("Vous n'avez pas assez de bois", $html);
    }

    public function testAMixedResultKeepsTheWinAndShowsTheRefusal(): void
    {
        $html = $this->render(true, false, [
            new OutcomeResult(true, ['Coup porté'], []),
            new OutcomeResult(false, [], ['Le coffre a résisté']),
        ]);

        $this->assertStringContainsString('Réussite !', $html);
        $this->assertStringContainsString('Coup porté', $html);
        $this->assertStringContainsString('Le coffre a résisté', $html);
    }

    /** An outcome run on a miss (malus, breakage) tells what it did (#137). */
    public function testAFailedRollRendersWhatItsOutcomesDid(): void
    {
        $html = $this->render(false, false, [
            new OutcomeResult(true, ['Vous cassez votre épée'], []),
            new OutcomeResult(false, [], ["L'arme reste entre ses mains"]),
        ]);

        $this->assertStringContainsString('Echec !', $html);
        $this->assertStringContainsString('Vous cassez votre épée', $html);
        $this->assertStringContainsString("L'arme reste entre ses mains", $html);
    }

    public function testBlockedRendersActionImpossible(): void
    {
        $html = $this->render(false, true, []);

        $this->assertStringContainsString('Action Impossible.', $html);
    }
}
