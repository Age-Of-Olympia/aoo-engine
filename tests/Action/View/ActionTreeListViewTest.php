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
        $this->assertStringContainsString('tt-leaf-slug">attaquer</code>', $html);
        $this->assertStringContainsString('niv. 1', $html);
        $this->assertStringContainsString('href="/admin/action-workbench.php?id=7"', $html);
        $this->assertStringContainsString('/admin/action-export.php?type=action&id=7', $html); // single export
        $this->assertStringContainsString('/admin/action-export.php"', $html);      // export all
    }

    public function testPrunesTypeBranchesWithNoActions(): void
    {
        // Tree has attack→melee and rest; only melee has an action.
        $melee = $this->action(MeleeAction::class, 1, 'a', 'A', 1);

        $html = (new ActionTreeListView())->render($this->tree(), ['melee' => [$melee]]);

        $this->assertStringContainsString('>Melee<', $html);
        $this->assertStringContainsString('>Attack<', $html); // kept: has a populated descendant
        $this->assertStringNotContainsString('>Rest<', $html); // pruned: empty
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

    public function testLinksOwnerCountsToTheSkillOwnersPage(): void
    {
        $owned = $this->action(MeleeAction::class, 1, 'attaquer', 'Attaquer', 1);
        $orphan = $this->action(RestAction::class, 2, 'repos', 'Repos', 1);

        $html = (new ActionTreeListView())->render(
            $this->tree(),
            ['melee' => [$owned], 'rest' => [$orphan]],
            ['attaquer' => 3]
        );

        $this->assertStringContainsString('href="/admin/skill-owners.php?type=action&amp;name=attaquer"', $html);
        $this->assertStringContainsString('3 joueurs', $html);
        $this->assertStringContainsString('tt-chip--noowner">0 joueur<', $html);
    }

    public function testTypeNodesAreHeadersNotLinks(): void
    {
        $melee = $this->action(MeleeAction::class, 1, 'a', 'A', 1);

        $html = (new ActionTreeListView())->render($this->tree(), ['melee' => [$melee]]);

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
