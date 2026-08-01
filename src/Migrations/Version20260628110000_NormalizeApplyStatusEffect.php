<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Enum\FieldType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reshape applystatus instruction params so the effect is a normal field instead
 * of the first param key.
 *
 *   legacy: {"feu": true, "player": "target", "duration": 172800}
 *   new:    {"effect": "feu", "apply": true, "player": "target", "duration": 172800}
 *
 * This lets the schema model the effect as a FieldType::EFFECT field (so both the
 * action editor and the type-defaults editor render a dropdown). Covers BOTH
 * stores of instruction params: per-action (outcome_instructions) and type-level
 * (action_type_instructions). The runtime keeps a legacy fallback, so this is for
 * the editor + cleanliness. Idempotent: rows already carrying "effect" are skipped.
 */
final class Version20260628110000_NormalizeApplyStatusEffect extends AbstractMigration
{
    private const KNOWN_KEYS = ['effect', 'apply', 'duration', 'player', 'value', 'stackable'];

    /** Each store of instruction params: [table, discriminator column]. */
    private const SOURCES = [
        ['table' => 'outcome_instructions', 'typeColumn' => 'type'],
        ['table' => 'action_type_instructions', 'typeColumn' => 'instruction_type'],
    ];

    public function getDescription(): string
    {
        return 'Normalize applystatus params: effect becomes a field instead of the first key';
    }

    public function up(Schema $schema): void
    {
        $this->warnIf(false); // data-only migration; the connection calls below do the work

        foreach (self::SOURCES as $source) {
            foreach ($this->rows($source['table'], $source['typeColumn']) as $row) {
                $params = json_decode((string) $row['parameters'], true);
                if (!is_array($params) || array_key_exists('effect', $params)) {
                    continue;
                }

                $effect = null;
                $apply = true;
                foreach ($params as $key => $value) {
                    if (!in_array((string) $key, self::KNOWN_KEYS, true)) {
                        $effect = (string) $key;
                        $apply = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        unset($params[$key]);
                        break;
                    }
                }
                if ($effect === null) {
                    continue;
                }

                $this->writeParams($source['table'], (int) $row['id'], array_merge(['effect' => $effect, 'apply' => $apply], $params));
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::SOURCES as $source) {
            foreach ($this->rows($source['table'], $source['typeColumn']) as $row) {
                $params = json_decode((string) $row['parameters'], true);
                if (!is_array($params) || !array_key_exists('effect', $params)) {
                    continue;
                }

                $effect = (string) $params['effect'];
                $apply = filter_var($params['apply'] ?? true, FILTER_VALIDATE_BOOLEAN);
                unset($params['effect'], $params['apply']);

                $this->writeParams($source['table'], (int) $row['id'], array_merge([$effect => $apply], $params));
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $table, string $typeColumn): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT id, parameters FROM {$table} WHERE {$typeColumn} = 'applystatus'"
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function writeParams(string $table, int $id, array $params): void
    {
        $this->connection->update(
            $table,
            ['parameters' => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ['id' => $id]
        );
    }
}
