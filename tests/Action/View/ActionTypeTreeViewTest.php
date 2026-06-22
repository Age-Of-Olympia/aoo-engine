<?php

namespace Tests\Action\View;

use App\Service\Action\ActionTypeNode;
use App\View\Action\ActionTypeTreeView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-view')]
class ActionTypeTreeViewTest extends TestCase
{
    private function sampleTree(): array
    {
        return [
            new ActionTypeNode('attack', 'Attack', true, [
                new ActionTypeNode('melee', 'Melee', false, []),
            ]),
            new ActionTypeNode('rest', 'Rest', false, []),
        ];
    }

    public function testRendersNestedListWithLinksToTheTypeParam(): void
    {
        $html = (new ActionTypeTreeView())->render($this->sampleTree(), '/admin/action-type-defaults.php', 'melee');

        $this->assertStringContainsString('<ul class="tt-tree">', $html);
        $this->assertStringContainsString('href="/admin/action-type-defaults.php?type=attack"', $html);
        $this->assertStringContainsString('href="/admin/action-type-defaults.php?type=melee"', $html);
        // melee nests under attack's children list.
        $this->assertStringContainsString('<ul class="tt-children">', $html);
    }

    public function testHighlightsTheActiveNode(): void
    {
        $html = (new ActionTypeTreeView())->render($this->sampleTree(), '/admin/x.php', 'melee');

        $this->assertMatchesRegularExpression('/tt-link--active"[^>]*type=melee/', $html);
        $this->assertStringNotContainsString('tt-link--active" href="/admin/x.php?type=attack', $html);
    }

    public function testAbstractTypesAndLeavesAreMarkedDistinctly(): void
    {
        $html = (new ActionTypeTreeView())->render($this->sampleTree(), '/admin/x.php', 'attack');

        $this->assertStringContainsString('tt-node--abstract', $html); // attack
        $this->assertStringContainsString('tt-node--leaf', $html);     // rest (no children)
        // attack has children, so it gets a real toggle, not the empty placeholder.
        $this->assertStringContainsString('class="tt-toggle" aria-expanded="true"', $html);
        $this->assertStringContainsString('tt-toggle--empty', $html);
    }

    public function testRendersOwnDefaultCountBadges(): void
    {
        $html = (new ActionTypeTreeView())->render(
            $this->sampleTree(),
            '/admin/x.php',
            'melee',
            ['attack' => 2, 'melee' => 0],
        );

        $this->assertMatchesRegularExpression('/tt-badge"[^>]*>2<\/span>/', $html);
        $this->assertStringContainsString('tt-badge tt-badge--zero', $html); // melee = 0
    }

    public function testEscapesLabels(): void
    {
        $html = (new ActionTypeTreeView())->render(
            [new ActionTypeNode('x', '<script>', false, [])],
            '/admin/x.php',
            'x',
        );

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
