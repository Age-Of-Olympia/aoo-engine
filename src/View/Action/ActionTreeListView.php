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

        return '<div class="d-flex justify-content-between align-items-center mb-3">'
            . '<h1 class="mb-0">Actions</h1>' . $this->exportButton->all() . '</div>'
            . '<p class="text-muted mb-3">' . $total . ' action(s)</p>'
            . $this->tree->render($tree, '', '', $counts, $nodeBody);
    }

    private function row(Action $action): string
    {
        $id = (int) $action->getId();

        return '<li class="tt-leaf">'
            . '<span class="tt-leaf-name">' . $this->esc($action->getDisplayName()) . '</span>'
            . '<span class="tt-leaf-meta"><code>' . $this->esc($action->getName()) . '</code> · niv.' . (int) $action->getLevel()
            . ' · ' . $action->getConditions()->count() . 'c/' . $action->getOutcomes()->count() . 'o</span>'
            . '<a class="btn btn-sm btn-outline-primary" href="/admin/action-workbench.php?id=' . $id . '">Edit</a>'
            . $this->exportButton->single($id)
            . '</li>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
