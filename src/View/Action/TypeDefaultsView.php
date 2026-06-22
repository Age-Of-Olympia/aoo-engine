<?php

namespace App\View\Action;

use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\Form\ParameterFieldRenderer;
use App\Action\Schema\Form\RawParamsEditor;
use App\Action\Schema\ParameterSchema;
use App\Entity\ActionTypeInstruction;

/**
 * The "type defaults" editor body: a type picker plus the editable instructions
 * configured on the selected action type (the data-driven home of what used to
 * be code automatics, e.g. an attack's adrenaline). Mirrors the workbench
 * Configurer: typed schema fields + raw editor per instruction, add/remove via
 * formaction buttons that reuse the save form's csrf_token/type_key.
 */
final class TypeDefaultsView
{
    private ParameterFieldRenderer $renderer;
    private RawParamsEditor $rawEditor;
    private ActionSchemaCatalog $catalog;

    public function __construct(
        ?ParameterFieldRenderer $renderer = null,
        ?RawParamsEditor $rawEditor = null,
        ?ActionSchemaCatalog $catalog = null,
    ) {
        $this->renderer = $renderer ?? new ParameterFieldRenderer();
        $this->rawEditor = $rawEditor ?? new RawParamsEditor();
        $this->catalog = $catalog ?? new ActionSchemaCatalog();
    }

    /**
     * @param array<string, string>             $assignableTypes  type key => label
     * @param array<int, ActionTypeInstruction> $instructions
     * @param array<int, string>                $instructionTypes available instruction types
     */
    public function render(
        string $selectedType,
        array $assignableTypes,
        array $instructions,
        array $instructionTypes,
        string $csrfTokenField,
    ): string {
        $tabs = '';
        foreach ($assignableTypes as $key => $label) {
            $active = $key === $selectedType ? ' wb-item--active' : '';
            $tabs .= '<a class="wb-item' . $active . '" href="/admin/action-type-defaults.php?type=' . $this->esc($key) . '">'
                . $this->esc($label) . '</a>';
        }

        $blocks = '';
        foreach ($instructions as $instruction) {
            $type = $instruction->getInstructionType();
            $id = (int) $instruction->getId();
            $blocks .= '<div class="wb-block"><div class="wb-block-head">' . $this->esc($type)
                . $this->removeButton($id) . '</div><div class="wb-block-body">'
                . $this->renderParams(
                    $this->catalog->schemaForOutcomeInstruction($type),
                    $instruction->getParameters() ?? [],
                    'type_inst[' . $id . ']',
                    'type_inst_raw[' . $id . ']',
                )
                . '</div></div>';
        }

        $options = '';
        foreach ($instructionTypes as $instructionType) {
            $options .= '<option value="' . $this->esc($instructionType) . '">' . $this->esc($instructionType) . '</option>';
        }

        $form = '<form method="post" action="/admin/action-type-save.php" class="wb-form">'
            . $csrfTokenField
            . '<input type="hidden" name="type_key" value="' . $this->esc($selectedType) . '">'
            . '<div class="wb-section-title wb-section-title--row">Instructions du type « ' . $this->esc($selectedType) . ' »'
            . '<div class="wb-add"><select class="form-control" name="instruction_type">' . $options . '</select>'
            . '<button type="submit" class="btn btn-sm btn-default" formaction="/admin/action-type-instruction-add.php" formnovalidate>+ Instruction</button></div>'
            . '</div>'
            . ($instructions === [] ? '<p class="wb-muted">Aucune instruction pour ce type.</p>' : '')
            . '<div class="wb-grid">' . $blocks . '</div>'
            . $this->renderer->traitDatalist()
            . '<div class="wb-form-actions"><button type="submit" class="btn btn-success">Enregistrer</button></div>'
            . '</form>';

        return '<div class="wb"><div class="wb-col"><div class="wb-col-head">Types d\'action</div>'
            . '<div class="wb-col-body"><div class="wb-list">' . $tabs . '</div></div></div>'
            . '<div class="wb-col wb-col--wide"><div class="wb-col-body">' . $form . '</div></div></div>';
    }

    private function removeButton(int $id): string
    {
        return '<button type="submit" class="wb-remove-btn" name="instruction_id" value="' . $id . '"'
            . ' formaction="/admin/action-type-instruction-remove.php" formnovalidate'
            . ' onclick="return confirm(\'Retirer cette instruction du type ?\');" title="Retirer">&times;</button>';
    }

    /**
     * @param array<string, mixed> $params
     */
    private function renderParams(ParameterSchema $schema, array $params, string $typedPrefix, string $rawPrefix): string
    {
        $out = '';
        $reserved = [];
        foreach ($schema->fields() as $field) {
            $reserved[] = $field->key;
            $out .= $this->renderer->render($field, $typedPrefix . '[' . $field->key . ']', $params[$field->key] ?? null);
        }
        $out .= $this->rawEditor->render($rawPrefix, $params, $reserved, $schema->isEmpty());

        return $out;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
