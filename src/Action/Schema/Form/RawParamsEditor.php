<?php

namespace App\Action\Schema\Form;

/**
 * Editable key→value grid for parameters that don't fit the typed schema model:
 * RequiresTraitValue's flat trait→int map and ApplyStatus's effect-as-first-key.
 * Values are written as JSON so any shape (int, bool, string, array) round-trips
 * through ActionParameterValidator::coerceRaw().
 */
final class RawParamsEditor
{
    /**
     * @param array<string, mixed> $params   the entity's stored parameters
     * @param array<int, string>   $reserved keys owned by typed fields, skipped here
     * @param bool                 $allowEmpty render the (empty) grid even with no leftover
     *                             params — true for schema-less blocks, false so fully
     *                             typed blocks stay uncluttered
     */
    public function render(string $namePrefix, array $params, array $reserved = [], bool $allowEmpty = true): string
    {
        $leftover = [];
        foreach ($params as $key => $value) {
            if (!in_array((string) $key, $reserved, true)) {
                $leftover[(string) $key] = $value;
            }
        }

        if ($leftover === [] && !$allowEmpty) {
            return '';
        }

        $rows = '';
        $index = 0;
        foreach ($leftover as $key => $value) {
            $rows .= $this->row($namePrefix, (string) $index, (string) $key, $this->displayValue($value));
            $index++;
        }
        // A trailing blank row keeps the grid usable without JavaScript.
        $rows .= $this->row($namePrefix, 'b', '', '');

        return '<div class="wb-raw-editor" data-raw-prefix="' . $this->escape($namePrefix) . '">'
            . '<div class="wb-raw-title">Paramètres bruts <small>clé → valeur (JSON)</small></div>'
            . '<div class="wb-raw-rows">' . $rows . '</div>'
            . '<button type="button" class="wb-raw-add">+ Ajouter</button>'
            . '</div>';
    }

    private function row(string $prefix, string $index, string $key, string $value): string
    {
        $base = $this->escape($prefix) . '[' . $this->escape($index) . ']';

        return '<div class="wb-raw-row">'
            . '<input class="form-control wb-raw-k" name="' . $base . '[k]" value="' . $this->escape($key) . '" placeholder="clé" autocomplete="off">'
            . '<input class="form-control wb-raw-v" name="' . $base . '[v]" value="' . $this->escape($value) . '" placeholder="valeur" autocomplete="off">'
            . '<button type="button" class="wb-raw-del" title="Retirer">×</button>'
            . '</div>';
    }

    private function displayValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value === null) {
            return '';
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
