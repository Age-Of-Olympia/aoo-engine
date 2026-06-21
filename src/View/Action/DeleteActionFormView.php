<?php

namespace App\View\Action;

/**
 * The "delete action" form (its own form + confirm) for the workbench Configurer.
 * Kept out of the page controller alongside NewActionFormView.
 */
final class DeleteActionFormView
{
    public function render(int $actionId, string $csrfTokenField): string
    {
        return '<form method="post" action="/admin/action-delete.php" class="wb-delete-form"'
            . ' onsubmit="return confirm(\'Supprimer définitivement cette action et toutes ses conditions/outcomes ?\');">'
            . $csrfTokenField
            . '<input type="hidden" name="action_id" value="' . $actionId . '">'
            . '<button type="submit" class="btn btn-danger btn-sm">Supprimer l\'action</button>'
            . '</form>';
    }
}
