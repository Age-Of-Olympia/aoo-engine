<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Service\ItemStatsSeeder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Items JSON→DB (docs/design-items-instances.md, même mouvement que
 * Version20260710120000_RacesFromJson) : les stats de jeu des objets
 * (emplacement, caracs, prix, texte…) quittent datas/*\/items/*.json
 * pour des colonnes de la table items + un fourre-tout `extra` SANS
 * PERTE pour toute clé non colonnisée.
 *
 * Le seed lit les JSON présents dans CET environnement ; en prod le
 * déploiement tourne sans datas/ (gitignoré), le vrai seed se rejoue
 * depuis la racine web via admin/item-seed.php (ItemStatsSeeder,
 * pattern race-seed). Les lignes déjà stats_in_db = 1 ne sont jamais
 * re-seedées (réglages admin préservés) ; les flags déjà en base
 * (cursed, spell…) ne sont jamais écrasés par leurs copies JSON
 * périmées.
 */
final class Version20260717180000_ItemsFromJson extends AbstractMigration
{
    private const INT_COLUMNS = [
        'price' => 1,
        'esquive' => 0, 'pr' => 0, 'pf' => 0, 'malus' => 0, 'spellMalus' => 0,
        'fixedF' => 0, 'mDamage' => 0, 'demolition' => 0, 'craftedByN' => 0,
        'lootChance' => 0,
        'a' => 0, 'mvt' => 0, 'p' => 0, 'pv' => 0, 'cc' => 0, 'ct' => 0,
        'f' => 0, 'e' => 0, 'agi' => 0, 'pm' => 0, 'fm' => 0, 'm' => 0,
        'r' => 0, 'rm' => 0, 'spd' => 0, 'ae' => 0,
    ];

    private const VARCHAR_COLUMNS = ['emplacement', 'type', 'subtype', 'race'];

    private const TEXT_COLUMNS = ['text', 'munitions', 'add_effects', 'forbid', 'extra'];

    public function getDescription(): string
    {
        return 'Item stats move from datas/*/items/*.json into items columns (+ lossless extra)';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('stats_in_db')) {
            $this->addSql('ALTER TABLE items ADD stats_in_db TINYINT(1) NOT NULL DEFAULT 0');
        }
        foreach (self::INT_COLUMNS as $col => $default) {
            if (!$this->columnExists($col)) {
                $this->addSql("ALTER TABLE items ADD `{$col}` INT NOT NULL DEFAULT {$default}");
            }
        }
        foreach (self::VARCHAR_COLUMNS as $col) {
            if (!$this->columnExists($col)) {
                $this->addSql("ALTER TABLE items ADD `{$col}` VARCHAR(50) NOT NULL DEFAULT ''");
            }
        }
        foreach (self::TEXT_COLUMNS as $col) {
            if (!$this->columnExists($col)) {
                $this->addSql("ALTER TABLE items ADD `{$col}` LONGTEXT DEFAULT NULL");
            }
        }
    }

    public function postUp(Schema $schema): void
    {
        $result = (new ItemStatsSeeder())->seed($this->connection, dirname(__DIR__, 2));
        $this->write(sprintf(
            'Items seed: %d recopiés, %d sans JSON dans cet environnement (re-seed prod : admin/item-seed.php), %d déjà en base.',
            $result['seeded'],
            $result['missing'],
            $result['kept']
        ));

        /* Rejeu depuis une base vierge : les colonnes ajoutées APRÈS cette
         * migration n'existent pas encore, le seeder les passe et la migration
         * qui les introduit les peuple. On l'annonce plutôt que de le taire —
         * c'est le signe que le catalogue a bougé depuis cette date. */
        if ($result['skipped'] !== []) {
            $this->write(sprintf(
                'Items seed: colonnes pas encore créées à cette étape, laissées à leur migration propriétaire : %s.',
                implode(', ', $result['skipped'])
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_merge(
            ['stats_in_db'],
            array_keys(self::INT_COLUMNS),
            self::VARCHAR_COLUMNS,
            self::TEXT_COLUMNS
        ) as $col) {
            $this->addSql("ALTER TABLE items DROP COLUMN `{$col}`");
        }
    }

    private function columnExists(string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = ?",
            [$column]
        );
    }
}
