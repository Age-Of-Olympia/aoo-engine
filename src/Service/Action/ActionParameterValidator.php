<?php

namespace App\Service\Action;

use App\Action\Schema\FieldType;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use InvalidArgumentException;

final class ActionParameterValidator
{
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

    private function coerceField(ParameterField $field, mixed $raw): mixed
    {
        if ($field->type === FieldType::BOOL) {
            return (bool) $raw;
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
            FieldType::ENUM => $this->enumValue($field, (string) $raw),
            FieldType::TRAIT => $this->traitValue($field, (string) $raw),
            FieldType::TRAIT_OR_INT => $this->traitOrIntValue($field, (string) $raw),
            FieldType::LIST => $this->listValue($raw),
        };
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

    private function traitOrIntValue(ParameterField $field, string $value): int|string
    {
        if (is_numeric($value)) {
            return (int) $value;
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
