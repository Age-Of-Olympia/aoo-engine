<?php

namespace App\Service\Action;

use App\Enum\FieldType;
use App\Action\Schema\OptionCatalog;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use InvalidArgumentException;

final class ActionParameterValidator
{
    private OptionCatalog $catalog;

    public function __construct(?OptionCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? new OptionCatalog();
    }

    /**
     * Coerce and validate a posted parameter array against a schema, returning
     * a clean typed array (only declared keys, defaults applied).
     *
     * @param array<string, mixed> $posted
     * @return array<string, mixed>
     */
    public function coerce(ParameterSchema $schema, array $posted): array
    {
        $result = [];
        foreach ($schema->fields() as $field) {
            $result[$field->key] = $this->coerceField($field, $posted[$field->key] ?? null);
        }

        return $result;
    }

    /**
     * Coerce posted key→value rows from the raw editor into a parameter map, for
     * handlers whose shape doesn't fit the typed schema (RequiresTraitValue's flat
     * trait→int map, ApplyStatus's effect-as-first-key). Blank-keyed rows and
     * reserved (schema-owned) keys are dropped; values are JSON-decoded when valid
     * ("1"→int, "true"→bool, "[1,2]"→array) and kept as strings otherwise.
     *
     * @param array<int|string, mixed> $rows     each ['k' => string, 'v' => string]
     * @param array<int, string>       $reserved keys owned by typed fields, skipped here
     * @return array<string, mixed>
     */
    public function coerceRaw(array $rows, array $reserved = []): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['k'] ?? ''));
            if ($key === '' || in_array($key, $reserved, true)) {
                continue;
            }
            // A raw key can become an effect name echoed unescaped into outcome HTML
            // (e.g. ApplyStatus keys off the first param), so it must be inert: a
            // trait/effect identifier starts with a letter/underscore (a numeric key
            // would also be reindexed by array_merge, losing its lead position).
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                throw new InvalidArgumentException("Clé de paramètre invalide : {$key}.");
            }
            $result[$key] = $this->parseRawValue((string) ($row['v'] ?? ''));
        }

        return $result;
    }

    /**
     * ENUM à valeurs multiples : chaque valeur postée doit être une clé
     * des options du champ ; vide + requis refuse, vide simple retombe
     * sur le défaut du champ.
     *
     * @return array<int, string>
     */
    private function enumValues(ParameterField $field, mixed $raw): array
    {
        $values = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            is_array($raw) ? $raw : [$raw]
        ), static fn (string $value): bool => $value !== ''));

        foreach ($values as $value) {
            if (!array_key_exists($value, $field->options)) {
                throw new InvalidArgumentException("Valeur invalide pour « {$field->label} » : {$value}.");
            }
        }

        if ($values === []) {
            if ($field->required) {
                throw new InvalidArgumentException("Le champ « {$field->label} » est requis.");
            }

            return (array) $field->default;
        }

        return $values;
    }

    private function parseRawValue(string $value): mixed
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function coerceField(ParameterField $field, mixed $raw): mixed
    {
        if ($field->type === FieldType::BOOL) {
            return (bool) $raw;
        }

        if ($field->type->isCatalog()) {
            return $this->catalogValue($field, $raw);
        }

        if ($raw === null || $raw === '') {
            if ($field->required) {
                throw new InvalidArgumentException("Le champ « {$field->label} » est requis.");
            }

            return $field->default;
        }

        return match ($field->type) {
            FieldType::INT => (int) $raw,
            FieldType::STRING => (string) $raw,
            FieldType::ENUM => $field->multiple
                ? $this->enumValues($field, $raw)
                : $this->enumValue($field, (string) $raw),
            FieldType::TRAIT => $this->traitValue($field, (string) $raw),
            FieldType::TRAIT_OR_INT => $this->traitOrIntValue($field, (string) $raw),
            FieldType::LIST => $this->listValue($raw),
            default => (string) $raw,
        };
    }

    /**
     * Validate a catalog-backed field's value(s) against the real option set.
     * Multiple → a clean list of valid values; single → one valid value (or the
     * default when blank).
     *
     * @return string|array<int, string>|null
     */
    private function catalogValue(ParameterField $field, mixed $raw): string|array|null
    {
        $options = $this->catalog->optionsFor($field->type);

        if ($field->multiple) {
            $values = $this->listValue($raw);
            foreach ($values as $value) {
                if (!array_key_exists($value, $options)) {
                    throw new InvalidArgumentException("Valeur invalide pour « {$field->label} » : {$value}.");
                }
            }
            if ($values === [] && $field->required) {
                throw new InvalidArgumentException("Le champ « {$field->label} » est requis.");
            }

            return $values;
        }

        $value = is_array($raw) ? '' : trim((string) ($raw ?? ''));
        if ($value === '') {
            if ($field->required) {
                throw new InvalidArgumentException("Le champ « {$field->label} » est requis.");
            }

            return $field->default;
        }
        if (!array_key_exists($value, $options)) {
            throw new InvalidArgumentException("Valeur invalide pour « {$field->label} » : {$value}.");
        }

        return $value;
    }

    private function enumValue(ParameterField $field, string $value): string
    {
        if (!array_key_exists($value, $field->options)) {
            throw new InvalidArgumentException("Valeur invalide pour « {$field->label} » : {$value}.");
        }

        return $value;
    }

    private function traitValue(ParameterField $field, string $value): string
    {
        if (!$this->isTrait($value)) {
            throw new InvalidArgumentException("Trait inconnu pour « {$field->label} » : {$value}.");
        }

        return $value;
    }

    private function traitOrIntValue(ParameterField $field, string $value): int|string|array
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        // A dynamic value can be a JSON array: ["rollDivisor", n], ["remaining",
        // carac], or a list to pick from at random. The engine reads the array
        // shape directly; keep it intact rather than rejecting it.
        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // A single trait or a "/"-joined set of traits (e.g. "cc/agi" = max of cc and agi)
        foreach (explode('/', $value) as $part) {
            if (!$this->isTrait($part)) {
                throw new InvalidArgumentException("Trait ou entier attendu pour « {$field->label} » : {$value}.");
            }
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function listValue(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_filter(
            array_map(static fn($item): string => trim((string) $item), $items),
            static fn(string $item): bool => $item !== ''
        ));
    }

    private function isTrait(string $value): bool
    {
        return defined('CARACS') && array_key_exists($value, CARACS);
    }
}
