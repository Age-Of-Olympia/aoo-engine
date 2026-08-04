<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The lock becomes a GESTURE of the action engine: `fermer` and
 * `ouvrir`, one per direction, shown by their display conditions —
 * within reach (RequiresDistance 1), on a lockable thing the actor
 * controls, standing in the opposite state (RequiresLockControl).
 * Free of cost: a lock is furniture, not an effort.
 *
 * Granted to every current character, and to the registration races'
 * starter kits for the ones to come. Idempotent.
 */
final class Version20260805120000_TheLockBecomesAGesture extends AbstractMigration
{
    private const ACTIONS = [
        // name, display, produces-open, text
        ['fermer', 'Fermer', 0, 'Tourne la serrure : ce qui est fermé garde son contenu et ses portes.'],
        ['ouvrir', 'Ouvrir', 1, 'Tourne la serrure : ce qui est ouvert sert les siens.'],
    ];

    public function getDescription(): string
    {
        return 'Actions fermer/ouvrir — la serrure se tourne par le moteur d\'actions';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        foreach (self::ACTIONS as [$name, $display, $open, $text]) {
            if ($conn->fetchOne('SELECT id FROM actions WHERE name = ?', [$name]) !== false) {
                continue;
            }

            $conn->executeStatement(
                "INSERT INTO actions (name, icon, type, display_name, text, level)
                 VALUES (?, 'ra-key', 'buff', ?, ?, 1)",
                [$name, $display, $text]
            );

            $conn->executeStatement(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
                 SELECT 'TargetType', ?, id, 0, 1, 0 FROM actions WHERE name = ?",
                [json_encode(['allowed' => ['structure']]), $name]
            );
            $conn->executeStatement(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
                 SELECT 'RequiresDistance', ?, id, 1, 1, 1 FROM actions WHERE name = ?",
                [json_encode(['max' => 1]), $name]
            );
            $conn->executeStatement(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
                 SELECT 'RequiresLockControl', ?, id, 2, 1, 1 FROM actions WHERE name = ?",
                [json_encode(['open' => $open]), $name]
            );

            $conn->executeStatement(
                "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
                 SELECT 'target', 'serrure', 1, id FROM actions WHERE name = ?",
                [$name]
            );
            $conn->executeStatement(
                "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
                 SELECT 'turnlock', ?, 0, o.id
                 FROM action_outcomes o JOIN actions a ON a.id = o.action_id
                 WHERE a.name = ? AND o.name = 'serrure'",
                [json_encode(['open' => $open]), $name]
            );

            // Every current character carries the gesture...
            $conn->executeStatement(
                "INSERT IGNORE INTO players_actions (player_id, name, type)
                 SELECT id, ?, 'action' FROM players WHERE player_type IN ('real', 'tutorial')",
                [$name]
            );
            // ...and the registration races hand it to the ones to come.
            $conn->executeStatement(
                "INSERT INTO race_starter_actions (race_id, name, position)
                 SELECT r.id, ?, 99 FROM races r
                  WHERE r.playable = 1 AND r.hidden = 0
                    AND NOT EXISTS (SELECT 1 FROM race_starter_actions s WHERE s.race_id = r.id AND s.name = ?)",
                [$name, $name]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;

        foreach (self::ACTIONS as [$name]) {
            $id = $conn->fetchOne('SELECT id FROM actions WHERE name = ?', [$name]);
            if ($id === false) {
                continue;
            }
            $conn->executeStatement(
                'DELETE oi FROM outcome_instructions oi JOIN action_outcomes o ON o.id = oi.outcome_id WHERE o.action_id = ?',
                [$id]
            );
            $conn->executeStatement('DELETE FROM action_outcomes WHERE action_id = ?', [$id]);
            $conn->executeStatement('DELETE FROM action_conditions WHERE action_id = ?', [$id]);
            $conn->executeStatement('DELETE FROM players_actions WHERE name = ?', [$name]);
            $conn->executeStatement('DELETE FROM race_starter_actions WHERE name = ?', [$name]);
            $conn->executeStatement('DELETE FROM actions WHERE id = ?', [$id]);
        }
    }
}
