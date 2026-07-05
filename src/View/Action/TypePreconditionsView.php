<?php

namespace App\View\Action;

use App\Action\Schema\ActionSchemaCatalog;
use App\Entity\ActionTypePrecondition;

/**
 * Editor for the preconditions of one SCOPE — the global scope (empty type key,
 * applies to every action, e.g. the "Plan: enfers" rule) or one action type.
 * Delegates to the shared {@see TypeChildFormView}, adding a per-row "bloquant"
 * toggle and the hidden selected_type (so the save redirects back to the page the
 * user was on).
 */
final class TypePreconditionsView
{
    private TypeChildFormView $form;
    private ActionSchemaCatalog $catalog;

    public function __construct(?TypeChildFormView $form = null, ?ActionSchemaCatalog $catalog = null)
    {
        $this->form = $form ?? new TypeChildFormView();
        $this->catalog = $catalog ?? new ActionSchemaCatalog();
    }

    /**
     * @param array<int, ActionTypePrecondition> $preconditions
     * @param array<int, string>                 $conditionTypes available condition types
     */
    public function render(
        string $scopeKey,
        string $scopeLabel,
        array $preconditions,
        array $conditionTypes,
        string $selectedType,
        string $csrfTokenField,
    ): string {
        $rows = [];
        foreach ($preconditions as $precondition) {
            $id = (int) $precondition->getId();
            $rows[] = [
                'id' => $id,
                'childType' => $precondition->getConditionType(),
                'schema' => $this->catalog->schemaForCondition($precondition->getConditionType()),
                'params' => $precondition->getParameters() ?? [],
                'typedPrefix' => 'type_precond[' . $id . ']',
                'rawPrefix' => 'type_precond_raw[' . $id . ']',
                'headExtra' => '<label class="wb-precond-blocking"><input type="checkbox" name="type_precond_blocking[' . $id . ']" value="1"'
                    . ($precondition->isBlocking() ? ' checked' : '') . '> bloquant</label>',
            ];
        }

        return $this->form->render(
            saveAction: '/admin/action-type-precondition-save.php',
            addAction: '/admin/action-type-precondition-add.php',
            removeAction: '/admin/action-type-precondition-remove.php',
            csrfTokenField: $csrfTokenField,
            sectionTitle: $scopeLabel,
            childSelectName: 'condition_type',
            childOptions: $conditionTypes,
            addLabel: '+ Précondition',
            removeIdName: 'precondition_id',
            removeConfirm: 'Retirer cette précondition ?',
            rows: $rows,
            emptyLabel: 'Aucune précondition pour ' . $scopeLabel . '.',
            hidden: ['type_key' => $scopeKey, 'selected_type' => $selectedType],
        );
    }
}
