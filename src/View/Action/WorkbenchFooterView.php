<?php

namespace App\View\Action;

/**
 * The workbench's single footer action bar, shared by the action and passive
 * editors: the primary Enregistrer on the left, any extra controls (e.g. an
 * export link) next, and a right-aligned danger Supprimer. The save and delete
 * live in separate <form>s, so the buttons are wired to them with the HTML
 * `form=` attribute and sit together in one tidy bar instead of two stray rows.
 */
final class WorkbenchFooterView
{
    public function render(string $saveFormId, string $deleteFormId, string $deleteLabel, string $extra = ''): string
    {
        return '<div class="wb-footer">'
            . '<button type="submit" form="' . $this->esc($saveFormId) . '" class="btn btn-success">Enregistrer</button>'
            . $extra
            . '<button type="submit" form="' . $this->esc($deleteFormId) . '" class="btn btn-danger wb-footer-danger">'
            . $this->esc($deleteLabel) . '</button>'
            . '</div>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
