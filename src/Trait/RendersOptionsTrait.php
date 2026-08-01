<?php

namespace App\Trait;

/**
 * Shared <option> / <optgroup> rendering for the workbench and simulator views,
 * which all rebuilt the same `<option value="…"[ selected]>…</option>` string by
 * hand. Selection is compared as strings so 0/'0' and int/string keys match.
 */
trait RendersOptionsTrait
{
    use EscapesHtmlTrait;

    /**
     * A single <option>.
     */
    private function option(int|string $value, int|string $label, bool $selected = false): string
    {
        return '<option value="' . $this->esc($value) . '"' . ($selected ? ' selected' : '') . '>'
            . $this->esc($label) . '</option>';
    }

    /**
     * <option> list from a value => label map, marking the one matching $selected.
     *
     * @param iterable<int|string, int|string> $map
     */
    private function options(iterable $map, int|string|null $selected = null): string
    {
        $current = $selected === null ? null : (string) $selected;
        $html = '';
        foreach ($map as $value => $label) {
            $html .= $this->option($value, $label, $current !== null && (string) $value === $current);
        }

        return $html;
    }

    /**
     * <option> list where each value is also its own label (a plain list of
     * tokens), marking the one matching $selected.
     *
     * @param iterable<int, int|string> $values
     */
    private function optionsList(iterable $values, int|string|null $selected = null): string
    {
        $current = $selected === null ? null : (string) $selected;
        $html = '';
        foreach ($values as $value) {
            $html .= $this->option($value, $value, $current !== null && (string) $value === $current);
        }

        return $html;
    }

    /**
     * <option> list for a multi-select: every value present in $selected (string
     * compare) is marked.
     *
     * @param iterable<int|string, int|string> $map
     * @param array<int, int|string> $selected
     */
    private function optionsMulti(iterable $map, array $selected): string
    {
        $selectedStrings = array_map('strval', $selected);
        $html = '';
        foreach ($map as $value => $label) {
            $html .= $this->option($value, $label, in_array((string) $value, $selectedStrings, true));
        }

        return $html;
    }

    /**
     * An <optgroup> wrapping a single-select option list.
     *
     * @param iterable<int|string, int|string> $map
     */
    private function optgroup(string $label, iterable $map, int|string|null $selected = null): string
    {
        return '<optgroup label="' . $this->esc($label) . '">' . $this->options($map, $selected) . '</optgroup>';
    }
}
