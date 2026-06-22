<?php

namespace App\Service\Action;

use App\Action\Schema\ParameterSchema;

/**
 * Merges posted typed + raw parameters over the existing ones, coerced to a
 * schema. Shared by the action editor (ActionSaveService) and the type-defaults
 * editor so both coerce/merge identically.
 */
final class ParameterMerger
{
    private ActionParameterValidator $validator;

    public function __construct(?ActionParameterValidator $validator = null)
    {
        $this->validator = $validator ?? new ActionParameterValidator();
    }

    /**
     * @param array<string, mixed>          $existing
     * @param array<string, mixed>|null     $typedPosted
     * @param array<int|string, mixed>|null $rawPosted
     * @return array<string, mixed>|null    null when nothing was posted (leave as-is)
     */
    public function merge(ParameterSchema $schema, array $existing, ?array $typedPosted, ?array $rawPosted): ?array
    {
        if ($typedPosted === null && $rawPosted === null) {
            return null;
        }

        $reserved = array_map(static fn ($field): string => $field->key, $schema->fields());

        $typed = (!$schema->isEmpty() && $typedPosted !== null)
            ? $this->validator->coerce($schema, $typedPosted)
            : [];

        // When the raw editor wasn't posted, preserve any pre-existing keys the
        // schema doesn't own so a typed-only save never drops them.
        $raw = $rawPosted !== null
            ? $this->validator->coerceRaw($rawPosted, $reserved)
            : $this->leftover($existing, $reserved);

        return array_merge($raw, $typed);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int, string>   $reserved
     * @return array<string, mixed>
     */
    private function leftover(array $params, array $reserved): array
    {
        return array_filter(
            $params,
            static fn ($key): bool => !in_array((string) $key, $reserved, true),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
