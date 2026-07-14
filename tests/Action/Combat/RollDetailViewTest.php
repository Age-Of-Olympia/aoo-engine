<?php

namespace Tests\Action\Combat;

use App\Action\Combat\AdvantageRoll;
use App\Action\Combat\RollDetail;
use App\Action\Combat\RollDetailView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-combat')]
class RollDetailViewTest extends TestCase
{
    private RollDetailView $view;

    protected function setUp(): void
    {
        $this->view = new RollDetailView();
    }

    public function testRenderActorWithEffectsAndBonus(): void
    {
        $detail = new RollDetail(name: 'Actor', rollSum: 10, bonus: 2, positiveEffect: 3, negativeEffect: 1, total: 14);

        $this->assertSame(
            'Jet Actor = <span style="text-decoration: underline;" flow="up" tooltip="Effets : 3 - 1 Bonus de compétence : 2 ">14</span>',
            $this->view->renderActor($detail)
        );
    }

    public function testRenderActorWithNoModifiers(): void
    {
        $detail = new RollDetail(name: 'Actor', rollSum: 10, total: 10);

        $this->assertSame(
            'Jet Actor = <span style="text-decoration: underline;" flow="up" tooltip="">10</span>',
            $this->view->renderActor($detail)
        );
    }

    public function testRenderActorWithDistanceMalus(): void
    {
        $detail = new RollDetail(name: 'Actor', rollSum: 10, distanceMalus: 3, total: 7);

        $this->assertSame(
            'Jet Actor = <span style="text-decoration: underline;" flow="up" tooltip=" - 3 (Distance), ">7</span>',
            $this->view->renderActor($detail)
        );
    }

    public function testRenderTargetWithEffectsBonusAndMalus(): void
    {
        $detail = new RollDetail(name: 'Target', rollSum: 6, bonus: 2, positiveEffect: 3, negativeEffect: 1, malus: 2, total: 8);

        $this->assertSame(
            'Jet Target = 6 + 4 (<span style="text-decoration: underline;" flow="up" tooltip="Effets : 3 - 1 Bonus de compétence : 2 ">Autre</span>) - 2 (Malus) = 8',
            $this->view->renderTarget($detail)
        );
    }

    public function testRenderTargetWithNoModifiers(): void
    {
        $detail = new RollDetail(name: 'Target', rollSum: 6, total: 6);

        $this->assertSame('Jet Target = 6', $this->view->renderTarget($detail));
    }

    public function testRenderActorPutsAdvantageInTotalTooltip(): void
    {
        $advantage = new AdvantageRoll([12], AdvantageRoll::MODE_ADVANTAGE, 12, 7);
        $detail = new RollDetail(name: 'Actor', rollSum: 12, total: 12, advantage: $advantage);

        $this->assertSame(
            'Jet Actor = <span style="text-decoration: underline;" flow="up" tooltip="Avantage : jets 12 et 7, 12 retenu">12</span>',
            $this->view->renderActor($detail)
        );
    }

    public function testRenderActorAppendsAdvantageAfterModifiers(): void
    {
        $advantage = new AdvantageRoll([10], AdvantageRoll::MODE_ADVANTAGE, 10, 4);
        $detail = new RollDetail(name: 'Actor', rollSum: 10, bonus: 2, total: 12, advantage: $advantage);

        $this->assertSame(
            'Jet Actor = <span style="text-decoration: underline;" flow="up" tooltip="Bonus de compétence : 2 Avantage : jets 10 et 4, 10 retenu">12</span>',
            $this->view->renderActor($detail)
        );
    }

    public function testRenderTargetWrapsRollSumInDisadvantageTooltip(): void
    {
        $disadvantage = new AdvantageRoll([4], AdvantageRoll::MODE_DISADVANTAGE, 4, 15);
        $detail = new RollDetail(name: 'Target', rollSum: 4, total: 4, advantage: $disadvantage);

        $this->assertSame(
            'Jet Target = <span style="text-decoration: underline;" flow="up" tooltip="Désavantage : jets 4 et 15, 4 retenu">4</span>',
            $this->view->renderTarget($detail)
        );
    }

    public function testRenderIgnoresUnmodifiedAdvantageRoll(): void
    {
        $unmodified = new AdvantageRoll([6], null, 6, null);
        $detail = new RollDetail(name: 'Target', rollSum: 6, total: 6, advantage: $unmodified);

        $this->assertSame('Jet Target = 6', $this->view->renderTarget($detail));
    }

    public function testRenderTargetWithEsquiveAndMalus(): void
    {
        $detail = new RollDetail(name: 'Target', rollSum: 6, malus: 1, esquive: 4, total: 9);

        $this->assertSame(
            'Jet Target = 6 + 4 (<span style="text-decoration: underline;" flow="up" tooltip="Esquive : 4 ">Autre</span>) - 1 (Malus) = 9',
            $this->view->renderTarget($detail)
        );
    }
}
