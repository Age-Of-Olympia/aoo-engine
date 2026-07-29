<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Consecrating and worshipping, as catalogue rows.
 *
 * `venerer` replaces what `worship.php` did through a trigger; `consacrer` is
 * the capability lost in July, and the one that gives a naked altar a god.
 *
 * Dormant on arrival: nobody holds them, and no altar is an entity yet. They
 * exist first so the switchover has somewhere to land — an altar that became
 * an entity with no action on it would leave the screen mute.
 */
final class Version20260729200000_ConsacrerEtVenererActions extends AbstractMigration
{
    private const CONSACRER = 'consacrer';
    private const VENERER = 'venerer';

    public function getDescription(): string
    {
        return 'Actions consacrer and venerer enter the catalogue, dormant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO actions (name, icon, type, display_name, text, level)
             SELECT 'consacrer', 'ra-candle', 'buff', 'Consacrer',
                    'Place un autel nu sous la protection de votre Dieu. Coûte 50 points de foi.', 1
              FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM actions WHERE name = 'consacrer')"
        );

        $this->addSql(
            "INSERT INTO actions (name, icon, type, display_name, text, level)
             SELECT 'venerer', 'ra-prayer', 'buff', 'Vénérer',
                    'Placez-vous sous la protection du Dieu de cet autel.', 1
              FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM actions WHERE name = 'venerer')"
        );

        /* Order matters for what the player is told: aim first, then the
         * state of the altar, then what it costs. `display_context` decides
         * whether the button shows at all — a refusal one cannot act on is
         * noise on every other cell. */
        $this->conditions(self::CONSACRER, [
            ['TargetType', ['allowed' => ['structure']], 0, 1],
            ['TargetRace', ['allowed' => ['altar']], 1, 1],
            ['RequiresDistance', ['max' => 1], 2, 1],
            ['RequiresGodAffiliation', ['side' => 'target', 'state' => 'none'], 3, 1],
            ['RequiresGodAffiliation', [
                'side' => 'actor',
                'state' => 'any',
                'message' => 'Vous ne vénérez aucun Dieu : vous n\'avez personne à qui consacrer cet autel.',
            ], 4, 0],
            ['RequiresFaith', ['pf' => 50], 5, 0],
            ['RequiresTraitValue', ['a' => 1], 6, 0],
        ]);

        $this->conditions(self::VENERER, [
            ['TargetType', ['allowed' => ['structure']], 0, 1],
            ['TargetRace', ['allowed' => ['altar']], 1, 1],
            ['RequiresDistance', ['max' => 1], 2, 1],
            /* `other` demands the altar HAVE a god: a naked one is not
             * worshipped, it is consecrated. */
            ['RequiresGodAffiliation', ['side' => 'target', 'state' => 'other'], 3, 1],
        ]);

        $this->outcome(self::CONSACRER, 'consecration', 'target', 'setgod', [
            'from' => 'actor',
            'to' => 'target',
            'rename' => 'Autel de {dieu}',
        ]);

        $this->outcome(self::VENERER, 'veneration', 'self', 'setgod', [
            'from' => 'target',
            'to' => 'actor',
        ]);
    }

    /**
     * @param list<array{0: string, 1: array<string, mixed>, 2: int, 3: int}> $rows
     */
    private function conditions(string $action, array $rows): void
    {
        foreach ($rows as [$type, $params, $order, $displayContext]) {
            $this->addSql(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
                 SELECT ?, ?, id, ?, 1, ? FROM actions WHERE name = ?",
                [$type, json_encode($params), $order, $displayContext, $action]
            );
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function outcome(string $action, string $name, string $applyTo, string $instruction, array $params): void
    {
        $this->addSql(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT ?, ?, 1, id FROM actions WHERE name = ?",
            [$applyTo, $name, $action]
        );

        $this->addSql(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT ?, ?, 0, o.id
               FROM action_outcomes o JOIN actions a ON a.id = o.action_id
              WHERE a.name = ? AND o.name = ?",
            [$instruction, json_encode($params), $action, $name]
        );
    }

    public function down(Schema $schema): void
    {
        foreach ([self::CONSACRER, self::VENERER] as $action) {
            $this->addSql(
                "DELETE oi FROM outcome_instructions oi
                   JOIN action_outcomes o ON o.id = oi.outcome_id
                   JOIN actions a ON a.id = o.action_id
                  WHERE a.name = ?",
                [$action]
            );
            $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name = ?", [$action]);
            $this->addSql("DELETE FROM action_conditions WHERE action_id IN (SELECT id FROM actions WHERE name = ?)", [$action]);
            $this->addSql("DELETE FROM players_actions WHERE name = ?", [$action]);
            $this->addSql("DELETE FROM actions WHERE name = ?", [$action]);
        }
    }
}
