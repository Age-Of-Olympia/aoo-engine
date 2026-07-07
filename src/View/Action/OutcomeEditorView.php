<?php

namespace App\View\Action;

/**
 * Workbench controls for adding/removing an action's outcomes and their
 * instructions. Like ConditionEditorView these render INSIDE the Configurer save
 * form, so they are not <form>s: the buttons use formaction (+ formnovalidate)
 * to post to the endpoints while reusing the save form's csrf_token/action_id.
 * The add-instruction select is named per outcome (instruction_type_<id>) so
 * multiple outcomes' selects don't collide when the form is submitted.
 */
final class OutcomeEditorView
{
    use RendersOptions;

    public function addOutcomeControls(): string
    {
        return '<div class="wb-add">'
            . '<button type="submit" class="btn btn-sm btn-default" name="on_success" value="1" formaction="/admin/action-outcome-add.php" formnovalidate>+ Outcome succès</button>'
            . '<button type="submit" class="btn btn-sm btn-default" name="on_success" value="0" formaction="/admin/action-outcome-add.php" formnovalidate>+ Outcome échec</button>'
            . '</div>';
    }

    /**
     * The "applies to" picker for an outcome — the actor (sur soi) or the target
     * (sur la cible). Rendered inside the Configurer save form (name
     * outcome_self[<id>]) so it saves with everything else; ActionSaveService
     * persists it and it drives the action's derived targeting scope.
     */
    public function targetToggle(int $outcomeId, bool $applyToSelf): string
    {
        return '<select class="wb-outcome-target" name="outcome_self[' . $outcomeId . ']" title="Sur qui s\'applique cet outcome">'
            . $this->option('0', 'sur la cible', !$applyToSelf)
            . $this->option('1', 'sur soi', $applyToSelf)
            . '</select>';
    }

    public function removeOutcomeButton(int $outcomeId): string
    {
        return '<button type="submit" class="wb-remove-btn" name="outcome_id" value="' . $outcomeId . '"'
            . ' formaction="/admin/action-outcome-remove.php" formnovalidate'
            . ' onclick="var b=this; aooConfirm(\'Retirer cet outcome et ses instructions ?\').then(function(ok){ if(ok){ b.form.requestSubmit(b); } }); return false;" title="Retirer">&times;</button>';
    }

    /**
     * @param array<int, string> $types available instruction types
     */
    public function addInstructionControls(int $outcomeId, array $types): string
    {
        return '<div class="wb-add">'
            . '<select class="form-control" name="instruction_type_' . $outcomeId . '">' . $this->optionsList($types) . '</select>'
            . '<button type="submit" class="btn btn-sm btn-default" name="outcome_id" value="' . $outcomeId . '"'
            . ' formaction="/admin/action-instruction-add.php" formnovalidate>+ Instruction</button>'
            . '</div>';
    }

    public function removeInstructionButton(int $instructionId): string
    {
        return '<button type="submit" class="wb-remove-btn" name="instruction_id" value="' . $instructionId . '"'
            . ' formaction="/admin/action-instruction-remove.php" formnovalidate'
            . ' onclick="var b=this; aooConfirm(\'Retirer cette instruction ?\').then(function(ok){ if(ok){ b.form.requestSubmit(b); } }); return false;" title="Retirer">&times;</button>';
    }

}
