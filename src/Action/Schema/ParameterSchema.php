<?php

namespace App\Action\Schema;

final class ParameterSchema
{
    /** @var array<int, ParameterField> */
    private array $fields;

    public function __construct(ParameterField ...$fields)
    {
        $this->fields = $fields;
    }

    /**
     * @return array<int, ParameterField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $key): ?ParameterField
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }
}
