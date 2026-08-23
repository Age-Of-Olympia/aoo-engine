<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mise à jour de l'esquive (ID 14) et transformation de Leurre en Dissipation (ID 16).
 */
final class Version20260822200000_UpdateEffectsTable extends AbstractMigration
{
    private const EFFECT_14_ID = 14;
    private const EFFECT_14_UP = [
        'description' => 'Esquive le prochain tir en vous déplaçant sur une case adjacente.',
        'dodge_scope' => 'distance',
    ];
    private const EFFECT_14_DOWN = [
        'description' => 'Esquive la prochaine attaque physique en vous déplaçant sur une case adjacente.',
        'dodge_scope' => 'physical',
    ];

    private const EFFECT_16_ID = 16;
    private const EFFECT_16_UP = [
        'name'          => 'dissipation',
        'label'         => 'Dissipation',
        'description'   => 'Dissipe le prochain sort lancé sur vous.',
        'dodge_message' => '{defender} dissipe votre sort !',
    ];
    private const EFFECT_16_DOWN = [
        'name'          => 'leurre',
        'label'         => 'Leurre',
        'description'   => 'Pare le prochain sort lancé sur vous.',
        'dodge_message' => '{defender} pare votre attaque grâce à un sort !',
    ];

    public function getDescription(): string
    {
        return 'Modification des effets 14 (esquive tir) et 16 (dissipation).';
    }

    public function up(Schema $schema): void
    {
        // 1. Mise à jour de l'ID 14
        $setClause14 = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys(self::EFFECT_14_UP)));
        $params14 = array_merge(array_values(self::EFFECT_14_UP), [self::EFFECT_14_ID]);
        $this->addSql("UPDATE effects SET {$setClause14} WHERE id = ?", $params14);

        // 2. Mise à jour de l'ID 16
        $setClause16 = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys(self::EFFECT_16_UP)));
        $params16 = array_merge(array_values(self::EFFECT_16_UP), [self::EFFECT_16_ID]);
        $this->addSql("UPDATE effects SET {$setClause16} WHERE id = ?", $params16);
    }

    public function down(Schema $schema): void
    {
        // 1. Rollback de l'ID 14
        $setClause14 = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys(self::EFFECT_14_DOWN)));
        $params14 = array_merge(array_values(self::EFFECT_14_DOWN), [self::EFFECT_14_ID]);
        $this->addSql("UPDATE effects SET {$setClause14} WHERE id = ?", $params14);

        // 2. Rollback de l'ID 16
        $setClause16 = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys(self::EFFECT_16_DOWN)));
        $params16 = array_merge(array_values(self::EFFECT_16_DOWN), [self::EFFECT_16_ID]);
        $this->addSql("UPDATE effects SET {$setClause16} WHERE id = ?", $params16);
    }
}