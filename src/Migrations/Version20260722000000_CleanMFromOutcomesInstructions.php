<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Update parameters for specific outcome instructions.
 */
final class Version20260722000000_CleanMFromOutcomesInstructions extends AbstractMigration
{
    private const UPDATES = [
        79 => '{"actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": ["pui",3]}',
        87 => '{"actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusDamagesTrait": ["pui",3], "distance":true}',
        89 => '{"actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 0}',
        90 => '{"actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 5}',
        100 => '{"actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 1}',
        172 => '{"actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 3}',
        173 => '{"actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 1}',
        174 => '{"actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 1, "drain":true}',
        175 => '{"actorDamagesTrait":"pui","targetDamagesTrait":"res","bonusDamagesTrait":1,"bonusDefenseTrait":null,"distance":false,"saut":false,"drain":false,"siphon":true,"autoCrit":false}',
        183 => '{"lossType":"carac","value":"pui","typeDivisor":1}',
    ];

    public function getDescription(): string
    {
        return 'Update parameters column in outcome_instructions for specific IDs';
    }

    public function up(Schema $schema): void
    {
        foreach (self::UPDATES as $id => $parameters) {
            $this->addSql(
                'UPDATE outcome_instructions SET parameters = ? WHERE id = ?',
                [$parameters, $id]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Note: Previous parameters values are not stored and cannot be automatically restored.
    }
}