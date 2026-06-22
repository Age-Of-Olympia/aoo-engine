<?php

namespace App\View\Action;

use App\Service\Action\ActionTypeNode;

/**
 * Renders an action-type inheritance tree (see {@see \App\Service\Action\ActionTypeRegistry::tree()})
 * as a collapsible rail. Each node is a link to "$hrefBase?type=KEY" — or a plain
 * label when $hrefBase is '' (the action list, where nodes are headers, not a
 * selector). $activeKey is highlighted; abstract grouping types (attack/technique)
 * are styled distinctly; $counts[KEY] shows a badge; $nodeBody[KEY] is extra HTML
 * nested under that node (e.g. the action rows of that type), collapsible with it.
 *
 * Shared by the type-defaults rail and the action list.
 */
final class ActionTypeTreeView
{
    use EscapesHtml;

    /**
     * @param array<int, ActionTypeNode> $nodes
     * @param array<string, int>         $counts   type key => badge count
     * @param array<string, string>      $nodeBody type key => HTML nested under the node
     */
    public function render(array $nodes, string $hrefBase, string $activeKey, array $counts = [], array $nodeBody = []): string
    {
        return '<ul class="tt-tree">' . $this->renderNodes($nodes, $hrefBase, $activeKey, $counts, $nodeBody) . '</ul>';
    }

    /**
     * @param array<int, ActionTypeNode> $nodes
     * @param array<string, int>         $counts
     * @param array<string, string>      $nodeBody
     */
    private function renderNodes(array $nodes, string $hrefBase, string $activeKey, array $counts, array $nodeBody): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $hasChildren = $node->children !== [];
            $body = $nodeBody[$node->key] ?? '';
            $collapsible = $hasChildren || $body !== '';
            $count = $counts[$node->key] ?? 0;

            $liClass = 'tt-node'
                . ($node->abstract ? ' tt-node--abstract' : '')
                . ($collapsible ? '' : ' tt-node--leaf');

            $toggle = $collapsible
                ? '<button type="button" class="tt-toggle" aria-expanded="true" aria-label="Replier/déplier">▾</button>'
                : '<span class="tt-toggle tt-toggle--empty"></span>';

            $label = $hrefBase === ''
                ? '<span class="tt-link tt-link--label">' . $this->esc($node->label) . '</span>'
                : '<a class="tt-link' . ($node->key === $activeKey ? ' tt-link--active' : '') . '"'
                    . ' href="' . $this->esc($hrefBase) . '?type=' . urlencode($node->key) . '">'
                    . $this->esc($node->label) . '</a>';

            $badge = '<span class="tt-badge' . ($count === 0 ? ' tt-badge--zero' : '') . '">' . $count . '</span>';

            $html .= '<li class="' . $liClass . '"><div class="tt-row">' . $toggle . $label . $badge . '</div>';
            if ($hasChildren) {
                $html .= '<ul class="tt-children">'
                    . $this->renderNodes($node->children, $hrefBase, $activeKey, $counts, $nodeBody)
                    . '</ul>';
            }
            if ($body !== '') {
                $html .= '<ul class="tt-children tt-leaves">' . $body . '</ul>';
            }
            $html .= '</li>';
        }

        return $html;
    }

}
