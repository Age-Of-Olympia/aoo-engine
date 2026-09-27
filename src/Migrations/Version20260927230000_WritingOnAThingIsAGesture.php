<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `ecrire`: write the inscription of a thing (a chest today). A gesture
 * (no XP) that costs 1 A, beside the thing, for its people, in the tile's
 * action panel; its button asks for the text (InscriptionText).
 *
 * Granted to every current character and to the registration races'
 * starter kits. Idempotent.
 */
final class Version20260927230000_WritingOnAThingIsAGesture extends AbstractMigration
{
    private const NAME = 'ecrire';

    public function getDescription(): string
    {
        return "Action ecrire — écrire l'inscription d'un objet (coffre), 1 A";
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;
        if ($conn->fetchOne('SELECT id FROM actions WHERE name = ?', [self::NAME]) !== false) {
            return;
        }

        $conn->executeStatement(
            "INSERT INTO actions (name, icon, type, display_name, text, level)
             VALUES (?, 'ra-quill-ink', 'gesture', 'Écrire', 'Écrit ou efface ce qui est inscrit sur l''objet.', 1)",
            [self::NAME]
        );

        $conditions = [
            // type, parameters, order, display_context
            ['TargetType', ['allowed' => ['structure']], 0, 0],
            ['RequiresDistance', ['max' => 1], 1, 1],
            ['RequiresInscriptionRight', [], 2, 1],
            ['InscriptionText', [], 3, 0],
            ['RequiresTraitValue', ['a' => 1], 4, 0],
        ];
        foreach ($conditions as [$type, $params, $order, $display]) {
            $conn->executeStatement(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
                 SELECT ?, ?, id, ?, 1, ? FROM actions WHERE name = ?",
                [$type, json_encode($params), $order, $display, self::NAME]
            );
        }

        $conn->executeStatement(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT 'target', 'inscription', 1, id FROM actions WHERE name = ?",
            [self::NAME]
        );
        $conn->executeStatement(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT 'inscribe', '{}', 0, o.id
             FROM action_outcomes o JOIN actions a ON a.id = o.action_id
             WHERE a.name = ? AND o.name = 'inscription'",
            [self::NAME]
        );

        $conn->executeStatement(
            "INSERT IGNORE INTO players_actions (player_id, name, type)
             SELECT id, ?, 'action' FROM players WHERE player_type IN ('real', 'tutorial')",
            [self::NAME]
        );
        $conn->executeStatement(
            "INSERT INTO race_starter_actions (race_id, name, position)
             SELECT r.id, ?, 99 FROM races r
              WHERE r.playable = 1 AND r.hidden = 0
                AND NOT EXISTS (SELECT 1 FROM race_starter_actions s WHERE s.race_id = r.id AND s.name = ?)",
            [self::NAME, self::NAME]
        );
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;
        $id = $conn->fetchOne('SELECT id FROM actions WHERE name = ?', [self::NAME]);
        if ($id === false) {
            return;
        }
        $conn->executeStatement(
            'DELETE oi FROM outcome_instructions oi JOIN action_outcomes o ON o.id = oi.outcome_id WHERE o.action_id = ?',
            [$id]
        );
        $conn->executeStatement('DELETE FROM action_outcomes WHERE action_id = ?', [$id]);
        $conn->executeStatement('DELETE FROM action_conditions WHERE action_id = ?', [$id]);
        $conn->executeStatement('DELETE FROM players_actions WHERE name = ?', [self::NAME]);
        $conn->executeStatement('DELETE FROM race_starter_actions WHERE name = ?', [self::NAME]);
        $conn->executeStatement('DELETE FROM actions WHERE id = ?', [$id]);
    }
}
