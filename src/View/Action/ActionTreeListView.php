<?php

namespace App\View\Action;

use App\Entity\Action;
use App\Service\Action\ActionTypeNode;

/**
 * The action list rendered as the type inheritance tree: each concrete type node
 * carries its actions as nested rows (name, level, condition/outcome counts, edit
 * + export). Type nodes are headers (not links); the badge is the action count.
 * Reuses {@see ActionTypeTreeView} for the tree structure and collapse.
 */
final class ActionTreeListView
{
    private ExportButtonView $exportButton;
    private ActionTypeTreeView $tree;

    public function __construct(?ExportButtonView $exportButton = null, ?ActionTypeTreeView $tree = null)
    {
        $this->exportButton = $exportButton ?? new ExportButtonView();
        $this->tree = $tree ?? new ActionTypeTreeView();
    }

    /**
     * @param array<int, ActionTypeNode>        $tree          the full type tree
     * @param array<string, array<int, Action>> $actionsByType concrete type key => its actions
     */
    public function render(array $tree, array $actionsByType): string
    {
        $total = 0;
        $counts = [];
        $nodeBody = [];
        foreach ($actionsByType as $typeKey => $actions) {
            $counts[$typeKey] = count($actions);
            $total += count($actions);
            $rows = '';
            foreach ($actions as $action) {
                $rows .= $this->row($action);
            }
            $nodeBody[(string) $typeKey] = $rows;
        }

        // Drop type branches that hold no actions anywhere beneath them, so the
        // list shows only populated families instead of the full empty taxonomy.
        $tree = $this->pruneEmpty($tree, $counts);

        return '<div class="d-flex justify-content-between align-items-center mb-3">'
            . '<h1 class="mb-0">Actions</h1>' . $this->exportButton->all() . '</div>'
            . '<p class="text-muted mb-3">' . $total . ' action(s)</p>'
            . $this->tree->render($tree, '', '', $counts, $nodeBody);
    }

    /**
     * @param array<int, ActionTypeNode> $nodes
     * @param array<string, int>         $counts
     * @return array<int, ActionTypeNode>
     */
    private function pruneEmpty(array $nodes, array $counts): array
    {
        $kept = [];
        foreach ($nodes as $node) {
            $children = $this->pruneEmpty($node->children, $counts);
            if (($counts[$node->key] ?? 0) > 0 || $children !== []) {
                $kept[] = new ActionTypeNode($node->key, $node->label, $node->abstract, $children);
            }
        }

        return $kept;
    }

    private function row(Action $action): string
    {
        $id = (int) $action->getId();
        $workbench = '/admin/action-workbench.php?id=' . $id;

        return '<li class="tt-leaf">'
            . '<a class="tt-leaf-main" href="' . $workbench . '" title="Éditer">'
            . '<span class="tt-leaf-name">' . $this->esc($action->getDisplayName()) . '</span>'
            . '<code class="tt-leaf-slug">' . $this->esc($action->getName()) . '</code>'
            . '</a>'
            . '<span class="tt-chip tt-chip--lvl">niv. ' . (int) $action->getLevel() . '</span>'
            . '<span class="tt-chip" title="conditions · outcomes">'
            . $action->getConditions()->count() . 'c · ' . $action->getOutcomes()->count() . 'o</span>'
            . '<span class="tt-leaf-actions">' . $this->exportButton->single($id, 'Exporter') . '</span>'
            . '</li>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
