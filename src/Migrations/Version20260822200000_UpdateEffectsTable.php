<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * « Pas de côté » n'esquive plus que les tirs ; « Leurre » devient « Dissipation ».
 */
final class Version20260822200000_UpdateEffectsTable extends AbstractMigration
{
    /** Rows are addressed by name: ids differ between databases. */
    private const CHANGES = [
        [
            'from' => 'pas_de_cote',
            'up'   => [
                'description' => 'Esquive le prochain tir en vous déplaçant sur une case adjacente.',
                'dodge_scope' => 'distance',
            ],
            'down' => [
                'description' => 'Esquive la prochaine attaque physique en vous déplaçant sur une case adjacente.',
                'dodge_scope' => 'physical',
            ],
        ],
        [
            'from' => 'leurre',
            'up'   => [
                'name'          => 'dissipation',
                'label'         => 'Dissipation',
                'description'   => 'Dissipe le prochain sort lancé sur vous.',
                'dodge_message' => '{defender} dissipe votre sort !',
            ],
            'down' => [
                'name'          => 'leurre',
                'label'         => 'Leurre',
                'description'   => 'Pare le prochain sort lancé sur vous.',
                'dodge_message' => '{defender} pare votre attaque grâce à un sort !',
            ],
        ],
    ];

    public function getDescription(): string
    {
        return 'Pas de côté ne pare que les tirs ; Leurre devient Dissipation.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::CHANGES as $change) {
            $this->update($change['from'], $change['up']);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::CHANGES as $change) {
            // A renamed row is found under its new name.
            $this->update($change['up']['name'] ?? $change['from'], $change['down']);
        }
    }

    /** @param array<string, string> $values */
    private function update(string $name, array $values): void
    {
        $set = implode(', ', array_map(fn ($col) => "`$col` = ?", array_keys($values)));
        $this->addSql("UPDATE effects SET $set WHERE name = ?", array_merge(array_values($values), [$name]));
    }
}
