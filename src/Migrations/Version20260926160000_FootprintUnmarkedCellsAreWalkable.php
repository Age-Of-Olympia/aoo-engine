<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926160000_FootprintUnmarkedCellsAreWalkable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'shapes with marked cells: the unmarked ones become walkable (cover), as the Formes editor showed them';
    }

    public function up(Schema $schema): void
    {
        // Only shapes someone marked: an untouched one keeps its family default
        foreach ($this->connection->fetchAllAssociative(
            'SELECT type_name, offsets, roles FROM entity_type_footprints WHERE roles IS NOT NULL'
        ) as $row) {
            $roles = (array) json_decode((string) $row['roles'], true);
            $unmarked = array_values(array_diff(
                array_map('intval', array_keys((array) json_decode((string) $row['offsets'], true))),
                array_map('intval', array_keys($roles))
            ));

            if ($unmarked === []) {
                continue;
            }

            foreach ($unmarked as $piece) {
                $roles[$piece] = 'cover';
            }
            ksort($roles);

            $this->addSql(
                'UPDATE entity_type_footprints SET roles = ? WHERE type_name = ?',
                [json_encode($roles), $row['type_name']]
            );
            $this->addSql(
                "UPDATE entity_cells ec JOIN players p ON p.id = ec.player_id
                    SET ec.role = 'cover'
                  WHERE p.race = ? AND ec.piece IN (" . implode(',', $unmarked) . ')',
                [$row['type_name']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Irreversible on purpose: which cells were unmarked is not recorded
    }
}
