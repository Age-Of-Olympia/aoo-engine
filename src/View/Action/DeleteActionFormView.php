<?php

namespace App\View\Action;

/**
 * The "delete action" form for the workbench Configurer. Renders only the form
 * shell (hidden inputs + confirm) with a stable id; the submit button lives in
 * the shared WorkbenchFooterView, wired back via the HTML `form=` attribute.
 */
final class DeleteActionFormView
{
    public const FORM_ID = 'wb-action-delete-form';

    public function render(int $actionId, string $csrfTokenField): string
    {
        return '<form method="post" action="/admin/action-delete.php" id="' . self::FORM_ID . '" class="wb-delete-form"'
            . ' onsubmit="return confirm(\'Supprimer définitivement cette action et toutes ses conditions/outcomes ?\');">'
            . $csrfTokenField
            . '<input type="hidden" name="action_id" value="' . $actionId . '">'
            . '</form>';
    }
}
