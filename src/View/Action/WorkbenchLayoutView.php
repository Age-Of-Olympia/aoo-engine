<?php

namespace App\View\Action;

/**
 * The two-column workbench shell shared by the action and passive editors: a
 * foldable list column on the left (head with title + fold toggle, then the
 * list body) and an editor column on the right (head + body). Both pages render
 * through this so the template lives in one place instead of being duplicated.
 *
 * The variable parts are passed in: the list title/count and body, and the
 * editor head (e.g. tab buttons, or a title + selected-item name) and body.
 */
final class WorkbenchLayoutView
{
    public function render(
        string $listTitle,
        int $count,
        string $listBody,
        string $editorHead,
        string $editorBody,
        string $editorHeadClass = '',
    ): string {
        $headClass = 'wb-col-head' . ($editorHeadClass !== '' ? ' ' . $editorHeadClass : '');

        return '<div class="wb">'
            . '<div class="wb-col wb-col--list">'
            . '<div class="wb-col-head">'
            . '<span class="wb-col-head-title">' . $this->esc($listTitle) . ' <small>' . $count . '</small></span>'
            . '<button type="button" class="wb-fold-toggle" id="wb-fold" title="Replier / déplier la liste" aria-label="Replier / déplier la liste"></button>'
            . '</div>'
            . '<div class="wb-col-body">' . $listBody . '</div>'
            . '</div>'
            . '<div class="wb-col">'
            . '<div class="' . $headClass . '">' . $editorHead . '</div>'
            . '<div class="wb-col-body">' . $editorBody . '</div>'
            . '</div>'
            . '</div>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
