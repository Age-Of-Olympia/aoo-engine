<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Visées 'self' et 'none' du TargetType (décision du 2026-07-19) :
 * construire n'a PAS de cible (il produit sur la carte), consommer ne
 * vise que son lanceur — leurs conditions TargetType, posées en
 * ['character'] par la migration des actions génériques, prennent leur
 * sorte de visée réelle. Idempotente.
 */
final class Version20260719230000_ActionTargetKinds extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Visées TargetType 'none' (construire) et 'self' (consommer)";
    }

    public function up(Schema $schema): void
    {
        foreach (['construire' => 'none', 'consommer' => 'self'] as $action => $kind) {
            $this->connection->executeStatement(
                "UPDATE action_conditions c
                 JOIN actions a ON a.id = c.action_id
                 SET c.parameters = ?
                 WHERE a.name = ? AND c.conditionType = 'TargetType'",
                [json_encode(['allowed' => [$kind]]), $action]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['construire', 'consommer'] as $action) {
            $this->connection->executeStatement(
                "UPDATE action_conditions c
                 JOIN actions a ON a.id = c.action_id
                 SET c.parameters = ?
                 WHERE a.name = ? AND c.conditionType = 'TargetType'",
                [json_encode(['allowed' => ['character']]), $action]
            );
        }
    }
}
