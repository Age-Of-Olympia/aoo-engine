<?php

namespace App\View\Action;

use App\Action\Schema\Form\ParameterFieldRenderer;
use App\Action\Schema\Form\RawParamsEditor;
use App\Action\Schema\ParameterSchema;

/**
 * Renders one "type-scoped child config" form — the shared shape behind the
 * instructions editor and the preconditions editor: a section title with an
 * add-control, one block per row (typed schema fields + raw editor, plus an
 * optional per-row extra such as a blocking toggle), and a save button. Add /
 * remove reuse the save form's csrf_token + hidden fields via formaction.
 */
final class TypeChildFormView
{
    private ParameterFieldRenderer $renderer;
    private RawParamsEditor $rawEditor;

    public function __construct(?ParameterFieldRenderer $renderer = null, ?RawParamsEditor $rawEditor = null)
    {
        $this->renderer = $renderer ?? new ParameterFieldRenderer();
        $this->rawEditor = $rawEditor ?? new RawParamsEditor();
    }

    /**
     * @param array<int, string>                        $childOptions the addable child types
     * @param array<int, array{id: int, childType: string, schema: ParameterSchema, params: array<string, mixed>, typedPrefix: string, rawPrefix: string, headExtra: string}> $rows
     * @param array<string, string>                     $hidden       extra hidden inputs (name => value)
     */
    public function render(
        string $saveAction,
        string $addAction,
        string $removeAction,
        string $csrfTokenField,
        string $sectionTitle,
        string $childSelectName,
        array $childOptions,
        string $addLabel,
        string $removeIdName,
        string $removeConfirm,
        array $rows,
        string $emptyLabel,
        array $hidden = [],
    ): string {
        $blocks = '';
        foreach ($rows as $row) {
            $blocks .= '<div class="wb-block"><div class="wb-block-head">' . $this->esc($row['childType'])
                . $row['headExtra']
                . $this->removeButton($removeAction, $removeIdName, $row['id'], $removeConfirm)
                . '</div><div class="wb-block-body">'
                . $this->renderParams($row['schema'], $row['params'], $row['typedPrefix'], $row['rawPrefix'])
                . '</div></div>';
        }

        $options = '';
        foreach ($childOptions as $option) {
            $options .= '<option value="' . $this->esc($option) . '">' . $this->esc($option) . '</option>';
        }

        $hiddenInputs = '';
        foreach ($hidden as $name => $value) {
            $hiddenInputs .= '<input type="hidden" name="' . $this->esc($name) . '" value="' . $this->esc($value) . '">';
        }

        return '<form method="post" action="' . $this->esc($saveAction) . '" class="wb-form">'
            . $csrfTokenField
            . $hiddenInputs
            . '<div class="wb-section-title wb-section-title--row">' . $this->esc($sectionTitle)
            . '<div class="wb-add"><select class="form-control" name="' . $this->esc($childSelectName) . '">' . $options . '</select>'
            . '<button type="submit" class="btn btn-sm btn-default" formaction="' . $this->esc($addAction) . '" formnovalidate>' . $this->esc($addLabel) . '</button></div>'
            . '</div>'
            . ($rows === [] ? '<p class="wb-muted">' . $this->esc($emptyLabel) . '</p>' : '')
            . '<div class="wb-grid">' . $blocks . '</div>'
            . $this->renderer->traitDatalist()
            . '<div class="wb-form-actions"><button type="submit" class="btn btn-success">Enregistrer</button></div>'
            . '</form>';
    }

    private function removeButton(string $removeAction, string $idName, int $id, string $confirm): string
    {
        return '<button type="submit" class="wb-remove-btn" name="' . $this->esc($idName) . '" value="' . $id . '"'
            . ' formaction="' . $this->esc($removeAction) . '" formnovalidate'
            . ' onclick="return confirm(\'' . $this->esc($confirm) . '\');" title="Retirer">&times;</button>';
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
