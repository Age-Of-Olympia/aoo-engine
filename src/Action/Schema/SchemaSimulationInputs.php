<?php

namespace App\Action\Schema;

/**
 * Derives the simulator's editable caracs from a parameter schema + stored params,
 * so the form follows the schema instead of a hand-maintained per-instruction list.
 *
 * Every TRAIT / TRAIT_OR_INT field IS, by definition, a carac reference: when its
 * stored value resolves to a real carac the action reads it at runtime
 * ($actor->caracs->{trait}), so the simulator must expose it. A field set to a
 * fixed number reads nothing and is skipped. This is the default for any
 * schema-backed condition/outcome; only genuinely special cases (caracs read with
 * no backing param, or a roll that reads nothing) implement DeclaresSimulationInputsInterface.
 */
final class SchemaSimulationInputs
{
    /**
     * @param array<string, mixed> $params
     * @return list<SimulationField>
     */
    public static function derive(ParameterSchema $schema, array $params): array
    {
        $caracs = defined('CARACS') ? CARACS : [];
        $fields = [];

        foreach ($schema->fields() as $field) {
            if ($field->type !== FieldType::TRAIT && $field->type !== FieldType::TRAIT_OR_INT) {
                continue;
            }

            $value = $params[$field->key] ?? null;
            // A bonus can be a [trait, divisor] pair (e.g. ["m", 3] = caracs.m / 3);
            // the carac it reads is the first element.
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            // A roll trait can list alternatives ("cc/agi") — each part is a carac.
            // Guard against TRAIT_OR_INT fields whose array form isn't a carac at all
            // (ApplyStatus value ["rollDivisor", 3]) by keeping only real CARACS keys.
            foreach (explode('/', (string) $value) as $trait) {
                if ($trait !== '' && isset($caracs[$trait])) {
                    $label = ($field->side === SimulationField::SIDE_TARGET ? 'Cible' : 'Acteur') . ' — ' . $trait;
                    $fields[] = new SimulationField(SimulationField::KIND_TRAIT, $field->side, $trait, $label);
                }
            }
        }

        return $fields;
    }
}
