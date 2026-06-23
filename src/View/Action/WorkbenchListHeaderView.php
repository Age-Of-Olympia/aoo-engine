<?php

namespace App\View\Action;

/**
 * The workbench list-column header, shared by the action and passive panels: the
 * "+ Nouveau…" create control and the "export all" link side by side. Keeping it
 * one component means both panels stay laid out identically.
 */
final class WorkbenchListHeaderView
{
    private ExportButtonView $exportButton;

    public function __construct(?ExportButtonView $exportButton = null)
    {
        $this->exportButton = $exportButton ?? new ExportButtonView();
    }

    public function render(string $createHtml, string $objectType, string $exportLabel): string
    {
        return '<div class="wb-list-header">'
            . $createHtml
            . $this->exportButton->allOfType($objectType, $exportLabel)
            . '</div>';
    }
}
