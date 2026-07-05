<?php

namespace Tests\Action\Combat;

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

    public function testRenderTargetWithEsquiveAndMalus(): void
    {
        $detail = new RollDetail(name: 'Target', rollSum: 6, malus: 1, esquive: 4, total: 9);

        $this->assertSame(
            'Jet Target = 6 + 4 (<span style="text-decoration: underline;" flow="up" tooltip="Esquive : 4 ">Autre</span>) - 1 (Malus) = 9',
            $this->view->renderTarget($detail)
        );
    }
}
