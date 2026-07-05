<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data-drive the "Plan" precondition's exemption list: add an `allowed` param
 * (default ["prier"]) to the existing Plan precondition rows, so the actions
 * allowed in les Enfers become editable instead of hardcoded in PlanCondition.
 *
 * Idempotent: rows already carrying `allowed` are left untouched. PlanCondition
 * still defaults to ["prier"] when the param is absent, so this is non-breaking.
 */
final class Version20260628120000_SeedPlanAllowedActions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add allowed=["prier"] to Plan preconditions (exemptions were hardcoded)';
    }

    public function up(Schema $schema): void
    {
        $this->warnIf(false);

        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, parameters FROM action_type_preconditions WHERE condition_type = 'Plan'"
        );
        foreach ($rows as $row) {
            $params = json_decode((string) $row['parameters'], true);
            $params = is_array($params) ? $params : [];
            if (array_key_exists('allowed', $params)) {
                continue;
            }

            $params['allowed'] = ['prier'];
            $this->connection->update(
                'action_type_preconditions',
                ['parameters' => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['id' => (int) $row['id']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, parameters FROM action_type_preconditions WHERE condition_type = 'Plan'"
        );
        foreach ($rows as $row) {
            $params = json_decode((string) $row['parameters'], true);
            if (!is_array($params) || !array_key_exists('allowed', $params)) {
                continue;
            }

            unset($params['allowed']);
            $this->connection->update(
                'action_type_preconditions',
                ['parameters' => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['id' => (int) $row['id']]
            );
        }
    }
}
