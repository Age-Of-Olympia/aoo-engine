<?php

namespace App\Action\Schema\Form;

use App\Action\Schema\FieldType;
use App\Action\Schema\ParameterField;

final class ParameterFieldRenderer
{
    public function render(ParameterField $field, string $name, mixed $value = null): string
    {
        $value ??= $field->default;

        $control = match ($field->type) {
            FieldType::BOOL => $this->checkbox($name, (bool) $value),
            FieldType::INT => $this->input($name, 'number', $value),
            FieldType::STRING => $this->input($name, 'text', $value),
            FieldType::ENUM => $this->select($name, $field->options, $value),
            FieldType::TRAIT => $this->select($name, $this->traitOptions(), $value),
            FieldType::TRAIT_OR_INT => $this->input($name, 'text', $value, 'caracs-options'),
            FieldType::LIST => $this->listInput($name, $value),
        };

        return $this->wrap($field, $control);
    }

    /**
     * Shared datalist of trait keys; render once per page for TRAIT_OR_INT inputs.
     */
    public function traitDatalist(): string
    {
        $html = '<datalist id="caracs-options">';
        foreach ($this->traitOptions() as $key => $label) {
            $html .= '<option value="' . $this->escape((string) $key) . '">' . $this->escape($label) . '</option>';
        }

        return $html . '</datalist>';
    }

    private function wrap(ParameterField $field, string $control): string
    {
        $label = $this->escape($field->label) . ($field->required ? ' *' : '');
        $help = $field->help !== null
            ? '<small class="form-text text-muted">' . $this->escape($field->help) . '</small>'
            : '';

        return '<div class="form-group"><label>' . $label . '</label>' . $control . $help . '</div>';
    }

    private function input(string $name, string $type, mixed $value, ?string $list = null): string
    {
        $listAttr = $list !== null ? ' list="' . $this->escape($list) . '"' : '';

        return '<input class="form-control" type="' . $type . '" name="' . $this->escape($name)
            . '" value="' . $this->escape((string) $value) . '"' . $listAttr . '>';
    }

    private function checkbox(string $name, bool $checked): string
    {
        return '<input class="form-check-input" type="checkbox" name="' . $this->escape($name) . '" value="1"'
            . ($checked ? ' checked' : '') . '>';
    }

    /**
     * @param array<string, string> $options
     */
    private function select(string $name, array $options, mixed $value): string
    {
        $html = '<select class="form-control" name="' . $this->escape($name) . '">';
        foreach ($options as $optionValue => $optionLabel) {
            $selected = ((string) $optionValue === (string) $value) ? ' selected' : '';
            $html .= '<option value="' . $this->escape((string) $optionValue) . '"' . $selected . '>'
                . $this->escape($optionLabel) . '</option>';
        }

        return $html . '</select>';
    }

    private function listInput(string $name, mixed $value): string
    {
        $text = is_array($value) ? implode(', ', $value) : (string) ($value ?? '');

        return '<input class="form-control" type="text" name="' . $this->escape($name)
            . '" value="' . $this->escape($text) . '" placeholder="valeurs séparées par des virgules">';
    }

    /**
     * @return array<string, string>
     */
    private function traitOptions(): array
    {
        return defined('CARACS') ? CARACS : [];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
