<?php

namespace Tests\Action\View;

use App\Action\MeleeAction;
use App\Action\RestAction;
use App\Service\Action\ActionTypeNode;
use App\View\Action\ActionTreeListView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-view')]
class ActionTreeListViewTest extends TestCase
{
    public function testNestsActionsUnderTheirTypeWithEditAndExportLinks(): void
    {
        $melee = $this->action(MeleeAction::class, 7, 'attaquer', 'Attaquer', 1);

        $html = (new ActionTreeListView())->render($this->tree(), ['melee' => [$melee]]);

        $this->assertStringContainsString('Attaquer', $html);
        $this->assertStringContainsString('<code>attaquer</code>', $html);
        $this->assertStringContainsString('niv.1', $html);
        $this->assertStringContainsString('href="/admin/action-workbench.php?id=7"', $html);
        $this->assertStringContainsString('/admin/action-export.php?id=7', $html); // single export
        $this->assertStringContainsString('/admin/action-export.php"', $html);      // export all
    }

    public function testShowsTheTotalCountAndPerTypeBadges(): void
    {
        $a = $this->action(MeleeAction::class, 1, 'a', 'A', 1);
        $b = $this->action(MeleeAction::class, 2, 'b', 'B', 1);
        $rest = $this->action(RestAction::class, 3, 'repos', 'Repos', 1);

        $html = (new ActionTreeListView())->render($this->tree(), ['melee' => [$a, $b], 'rest' => [$rest]]);

        $this->assertStringContainsString('3 action(s)', $html);
        $this->assertMatchesRegularExpression('/tt-badge">2</', $html); // melee count
    }

    public function testTypeNodesAreHeadersNotLinks(): void
    {
        $html = (new ActionTreeListView())->render($this->tree(), []);

        $this->assertStringContainsString('tt-link--label', $html);
        $this->assertStringNotContainsString('action-type-defaults.php?type=', $html);
    }

    /**
     * @return array<int, ActionTypeNode>
     */
    private function tree(): array
    {
        return [
            new ActionTypeNode('attack', 'Attack', true, [
                new ActionTypeNode('melee', 'Melee', false, []),
            ]),
            new ActionTypeNode('rest', 'Rest', false, []),
        ];
    }

    private function action(string $class, int $id, string $name, string $displayName, int $level): object
    {
        /** @var \App\Entity\Action $action */
        $action = new $class();
        $action->setId($id);
        $action->setName($name);
        $action->setDisplayName($displayName);
        $action->setLevel($level);

        return $action;
    }
}
