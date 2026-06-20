<?php

namespace App\Action\Schema\Form;

use App\Action\Schema\FieldType;
use App\Action\Schema\OptionCatalog;
use App\Action\Schema\ParameterField;

final class ParameterFieldRenderer
{
    private OptionCatalog $catalog;

    public function __construct(?OptionCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? new OptionCatalog();
    }

    public function render(ParameterField $field, string $name, mixed $value = null): string
    {
        $value ??= $field->default;

        if ($field->type->isCatalog()) {
            $control = $this->catalogSelect($name, $this->catalog->optionsFor($field->type), $value, $field->multiple);

            return $this->wrap($field, $control);
        }

        $control = match ($field->type) {
            FieldType::BOOL => $this->checkbox($name, (bool) $value),
            FieldType::INT => $this->input($name, 'number', $value),
            FieldType::STRING => $this->input($name, 'text', $value),
            FieldType::ENUM => $this->select($name, $field->options, $value),
            FieldType::TRAIT => $this->select($name, $this->traitOptions(), $value),
            FieldType::TRAIT_OR_INT => $this->input($name, 'text', $value, 'caracs-options'),
            FieldType::LIST => $this->listInput($name, $value),
            default => $this->input($name, 'text', $value),
        };

        return $this->wrap($field, $control);
    }

    /**
     * Select backed by an OptionCatalog. Multiple → a <select multiple> posting
     * an array; single → a <select> with a blank first option.
     *
     * @param array<string, string> $options
     */
    private function catalogSelect(string $name, array $options, mixed $value, bool $multiple): string
    {
        if ($multiple) {
            $selected = is_array($value) ? array_map('strval', $value) : [];
            $html = '<select class="form-control" name="' . $this->escape($name) . '[]" multiple>';
            foreach ($options as $optionValue => $optionLabel) {
                $isSelected = in_array((string) $optionValue, $selected, true) ? ' selected' : '';
                $html .= '<option value="' . $this->escape((string) $optionValue) . '"' . $isSelected . '>'
                    . $this->escape($optionLabel) . '</option>';
            }

            return $html . '</select>';
        }

        return $this->select($name, ['' => '—'] + $options, $value);
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
