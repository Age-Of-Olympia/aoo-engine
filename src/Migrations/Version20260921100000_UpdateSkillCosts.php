<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Manchette costs 4 PM instead of 2; discretion and pas-leger go back to
 * their old costs (no action point, 4 PM per imposture level).
 *
 * Actions are found by name: their ids differ between servers.
 */

final class Version20260921100000_UpdateSkillCosts extends AbstractMigration
{
    /** name => [condition parameters, displayed cost], after then before */
    private const UP = [
        'pas-leger' => [
            '{"imposture": [4,1]}',
            '<span style="color: #2980b9;">4x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>',
        ],
        'discretion' => [
            '{"imposture": [4,1]}',
            '<span style="color: #2980b9;">4x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>',
        ],
        'manchette' => [
            '{"a":1, "pm":4}',
            '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>',
        ],
    ];

    private const DOWN = [
        'pas-leger' => [
            '{ "a": 1, "imposture": [2,1] }',
            '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>',
        ],
        'discretion' => [
            '{ "a": 1, "imposture": [2,0.5] }',
            '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2x(<i class="ra ra-player-teleport"></i>+1) PM</span>, <span style="color: #27ae60;">1/2x(<i class="ra ra-player-teleport"></i>+1) Mvt</span>',
        ],
        'manchette' => [
            '{"a":1, "pm":2}',
            '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>',
        ],
    ];

    public function getDescription(): string
    {
        return 'Mise à jour des coûts et conditions pour quelques actions';
    }

    public function up(Schema $schema): void
    {
        $this->apply(self::UP);
    }

    public function down(Schema $schema): void
    {
        $this->apply(self::DOWN);
    }

    /** @param array<string, array{0: string, 1: string}> $actions */
    private function apply(array $actions): void
    {
        foreach ($actions as $name => [$parameters, $cost]) {
            $this->addSql(
                "UPDATE action_conditions ac JOIN actions a ON a.id = ac.action_id
                    SET ac.parameters = ?
                  WHERE a.name = ? AND ac.conditionType = 'RequiresTraitValue'",
                [$parameters, $name]
            );
            $this->addSql('UPDATE actions SET cost = ? WHERE name = ?', [$cost, $name]);
        }
    }
}