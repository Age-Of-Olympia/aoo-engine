<?php

namespace App\Action\Schema;

final class ParameterField
{
    /**
     * @param array<string, string> $options value => label, for ENUM fields
     * @param bool $multiple select many values (catalog/LIST fields) — stored as an array
     * @param 'actor'|'target' $side for TRAIT / TRAIT_OR_INT fields, whose fighter
     *        the carac is read from — drives which simulator panel it lands in.
     *        Defaults to the actor; only the few target-read traits set 'target'.
     */
    public function __construct(
        public readonly string $key,
        public readonly FieldType $type,
        public readonly string $label,
        public readonly mixed $default = null,
        public readonly array $options = [],
        public readonly ?string $help = null,
        public readonly bool $required = false,
        public readonly bool $multiple = false,
        public readonly string $side = 'actor',
    ) {
    }
}
