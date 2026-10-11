<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * jet_brutal: its attack roll was blocking and had no order, so it ran
 * before the range, weapon, ammo and cost checks, and a miss stopped the
 * action before anything was paid (no ammo spent either). It now rolls
 * last and a miss is paid, like every other technique. The stale cost
 * column said 6 PM; the condition charges 8 (5 with Voie de l'eau).
 */
final class Version20261011220000_JetBrutalRollLast extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'jet_brutal : jet d\'attaque en dernier et non bloquant, colonne cost alignée sur 8 PM';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.blocking = 0, c.execution_order = 10
              WHERE a.name = 'jet_brutal' AND c.conditionType = 'DistanceCompute'"
        );
        $this->addSql(
            "UPDATE actions
                SET cost = '<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">8 PM</span>'
              WHERE name = 'jet_brutal'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.blocking = 1, c.execution_order = NULL
              WHERE a.name = 'jet_brutal' AND c.conditionType = 'DistanceCompute'"
        );
        $this->addSql(
            "UPDATE actions
                SET cost = '<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">6 PM</span>'
              WHERE name = 'jet_brutal'"
        );
    }
}
