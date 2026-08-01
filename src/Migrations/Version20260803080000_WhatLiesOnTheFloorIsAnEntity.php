<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Service\Map\EntityLocationService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * What lies on the floor says so itself.
 *
 * `map_items_instances` was the third way of saying where an exemplar is, next
 * to `players_items_instances` for what a character holds and `unique_objects`
 * for what stands on the board. Its rows become what they always meant: an
 * entity on a cell, `dropped` — lying on the tile without being part of it.
 *
 * Every exemplar on the ground already has an entity: the backfill gave one to
 * everything a `unique_objects` row did not already wrap, and a wrapped one
 * cannot be on the floor at the same time — an exemplar has exactly ONE
 * location. Anything without one here would be a broken row, so the table only
 * goes once nothing is left behind.
 */
final class Version20260803080000_WhatLiesOnTheFloorIsAnEntity extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'map_items_instances retires: ground exemplars become entities dropped on their cell';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE players e
               JOIN item_instances i ON i.entity_id = e.id
               JOIN map_items_instances g ON g.instance_id = i.id
                SET e.coords_id = g.coords_id, e.holder_id = NULL, e.slot = ?',
            [EntityLocationService::SLOT_DROPPED]
        );

        /* Refuses to drop the table while a row has nowhere to go: a ground
         * exemplar with no entity is a broken row, not something to lose
         * quietly. */
        $stranded = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM map_items_instances g
               JOIN item_instances i ON i.id = g.instance_id
              WHERE i.entity_id IS NULL'
        );
        if ($stranded > 0) {
            throw new \RuntimeException(
                "{$stranded} exemplaire(s) au sol sans entité : rattraper avant de retirer la table."
            );
        }

        $this->addSql('DROP TABLE IF EXISTS map_items_instances');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS map_items_instances (
                instance_id INT(11) NOT NULL,
                coords_id INT(11) NOT NULL,
                PRIMARY KEY (instance_id),
                KEY coords_id (coords_id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->addSql(
            'INSERT INTO map_items_instances (instance_id, coords_id)
             SELECT i.id, e.coords_id
               FROM players e
               JOIN item_instances i ON i.entity_id = e.id
              WHERE e.slot = ?',
            [EntityLocationService::SLOT_DROPPED]
        );
        $this->addSql(
            "UPDATE players SET coords_id = NULL, slot = '' WHERE slot = ?",
            [EntityLocationService::SLOT_DROPPED]
        );
    }
}
