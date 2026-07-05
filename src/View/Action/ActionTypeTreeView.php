<?php

namespace App\View\Action;

use App\Service\Action\ActionTypeNode;

/**
 * Renders an action-type inheritance tree (see {@see \App\Service\Action\ActionTypeRegistry::tree()})
 * as a collapsible rail of links. Each node links to "$hrefBase?type=KEY";
 * $activeKey is highlighted; abstract grouping types (attack/technique) are
 * styled distinctly; $counts[KEY] shows a badge of that type's own defaults.
 *
 * Shared by the type-defaults page (and, later, the action list).
 */
final class ActionTypeTreeView
{
    use EscapesHtml;

    /**
     * @param array<int, ActionTypeNode> $nodes
     * @param array<string, int>         $counts type key => badge count
     */
    public function render(array $nodes, string $hrefBase, string $activeKey, array $counts = []): string
    {
        return '<ul class="tt-tree">' . $this->renderNodes($nodes, $hrefBase, $activeKey, $counts) . '</ul>';
    }

    /**
     * @param array<int, ActionTypeNode> $nodes
     * @param array<string, int>         $counts
     */
    private function renderNodes(array $nodes, string $hrefBase, string $activeKey, array $counts): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $hasChildren = $node->children !== [];
            $count = $counts[$node->key] ?? 0;

            $liClass = 'tt-node'
                . ($node->abstract ? ' tt-node--abstract' : '')
                . ($hasChildren ? '' : ' tt-node--leaf');

            $toggle = $hasChildren
                ? '<button type="button" class="tt-toggle" aria-expanded="true" aria-label="Replier/déplier">▾</button>'
                : '<span class="tt-toggle tt-toggle--empty"></span>';

            $link = '<a class="tt-link' . ($node->key === $activeKey ? ' tt-link--active' : '') . '"'
                . ' href="' . $this->esc($hrefBase) . '?type=' . urlencode($node->key) . '">'
                . $this->esc($node->label) . '</a>';

            $badge = '<span class="tt-badge' . ($count === 0 ? ' tt-badge--zero' : '') . '"'
                . ' title="' . $count . ' instruction(s) propre(s)">' . $count . '</span>';

            $html .= '<li class="' . $liClass . '"><div class="tt-row">' . $toggle . $link . $badge . '</div>';
            if ($hasChildren) {
                $html .= '<ul class="tt-children">'
                    . $this->renderNodes($node->children, $hrefBase, $activeKey, $counts)
                    . '</ul>';
            }
            $html .= '</li>';
        }

        return $html;
    }

}
