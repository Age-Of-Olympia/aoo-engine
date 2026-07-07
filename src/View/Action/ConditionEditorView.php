<?php

namespace App\View\Action;

/**
 * Workbench controls for adding/removing an action's conditions. These render
 * INSIDE the Configurer save form, so they are not <form> elements themselves:
 * the buttons use formaction to post to the add/remove endpoints while reusing
 * the save form's csrf_token and action_id hidden inputs. formnovalidate skips
 * the save form's field validation on these unrelated submits.
 */
final class ConditionEditorView
{
    use RendersOptions;

    /**
     * @param array<int, string> $types available condition types
     */
    public function addControls(array $types): string
    {
        return '<div class="wb-add">'
            . '<select class="form-control" name="condition_type">' . $this->optionsList($types) . '</select>'
            . '<button type="submit" class="btn btn-sm btn-default" formaction="/admin/action-condition-add.php" formnovalidate>+ Condition</button>'
            . '</div>';
    }

    public function removeButton(int $conditionId): string
    {
        return '<button type="submit" class="wb-remove-btn" name="condition_id" value="' . $conditionId . '"'
            . ' formaction="/admin/action-condition-remove.php" formnovalidate'
            . ' onclick="var b=this; aooConfirm(\'Retirer cette condition ?\').then(function(ok){ if(ok){ b.form.requestSubmit(b); } }); return false;" title="Retirer">&times;</button>';
    }

}
