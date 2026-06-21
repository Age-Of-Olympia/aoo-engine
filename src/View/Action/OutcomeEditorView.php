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
    public function addOutcomeControls(): string
    {
        return '<div class="wb-add">'
            . '<button type="submit" class="btn btn-sm btn-default" name="on_success" value="1" formaction="/admin/action-outcome-add.php" formnovalidate>+ Outcome succès</button>'
            . '<button type="submit" class="btn btn-sm btn-default" name="on_success" value="0" formaction="/admin/action-outcome-add.php" formnovalidate>+ Outcome échec</button>'
            . '</div>';
    }

    public function removeOutcomeButton(int $outcomeId): string
    {
        return '<button type="submit" class="wb-remove-btn" name="outcome_id" value="' . $outcomeId . '"'
            . ' formaction="/admin/action-outcome-remove.php" formnovalidate'
            . ' onclick="return confirm(\'Retirer cet outcome et ses instructions ?\');" title="Retirer">&times;</button>';
    }

    /**
     * @param array<int, string> $types available instruction types
     */
    public function addInstructionControls(int $outcomeId, array $types): string
    {
        $options = '';
        foreach ($types as $type) {
            $options .= '<option value="' . $this->esc($type) . '">' . $this->esc($type) . '</option>';
        }

        return '<div class="wb-add">'
            . '<select class="form-control" name="instruction_type_' . $outcomeId . '">' . $options . '</select>'
            . '<button type="submit" class="btn btn-sm btn-default" name="outcome_id" value="' . $outcomeId . '"'
            . ' formaction="/admin/action-instruction-add.php" formnovalidate>+ Instruction</button>'
            . '</div>';
    }

    public function removeInstructionButton(int $instructionId): string
    {
        return '<button type="submit" class="wb-remove-btn" name="instruction_id" value="' . $instructionId . '"'
            . ' formaction="/admin/action-instruction-remove.php" formnovalidate'
            . ' onclick="return confirm(\'Retirer cette instruction ?\');" title="Retirer">&times;</button>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
