<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Discrétion and the camouflages write a word into players.visible:
 * 'invisible' or the race shown on the map. The INT value of the player
 * instruction had turned it into 0; the word now lives in its own key.
 *
 * Safe before or after the code: the old code ignores the new key.
 */
final class Version20261011200000_VisibleAppearanceIsAWord extends AbstractMigration
{
    private const APPEARANCES = [
        'discretion' => 'invisible',
        'camouflage-olympien' => 'olympien',
        'camouflage-nain' => 'nain',
        'camouflage-elfe' => 'elfe',
        'camouflage-geant' => 'geant',
        'camouflage-hs' => 'hs',
    ];

    public function getDescription(): string
    {
        return 'Discrétion et camouflages : l\'apparence (invisible ou une race) dans sa propre clé, au lieu d\'un value forcé à 0';
    }

    public function up(Schema $schema): void
    {
        foreach (self::APPEARANCES as $action => $appearance) {
            $this->addSql(
                "UPDATE outcome_instructions i
                   JOIN action_outcomes o ON o.id = i.outcome_id
                   JOIN actions a ON a.id = o.action_id
                    SET i.parameters = JSON_REMOVE(JSON_SET(i.parameters, '$.appearance', ?), '$.value')
                  WHERE a.name = ? AND i.type = 'player'
                    AND JSON_VALID(i.parameters)
                    AND JSON_UNQUOTE(JSON_EXTRACT(i.parameters, '$.carac')) = 'visible'",
                [$appearance, $action]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE outcome_instructions
                SET parameters = JSON_SET(JSON_REMOVE(parameters, '$.appearance'), '$.value', 0)
              WHERE type = 'player'
                AND JSON_VALID(parameters)
                AND JSON_CONTAINS_PATH(parameters, 'one', '$.appearance')"
        );
    }
}
