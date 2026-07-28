<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Deux ressources posées sans type le reçoivent.
 *
 * `glaise3` (2 poses sur `volcan_s2`) et `arbre7` (1 pose sur
 * `tertre_sauvage_s2`) sont récoltables sur la carte — `damages = -1` — mais
 * n'existent dans aucun catalogue. Un animateur les a posées sans créer le
 * type ; rien ne l'en empêchait, et rien ne l'a signalé.
 *
 * Elles prennent donc la configuration de leurs sœurs : `pv = -1`, comme
 * `arbre1` à `arbre6` — c'est-à-dire « récoltable », ce que la carte dit
 * déjà d'elles.
 *
 * # Ce que cette migration ne fait PAS
 *
 * Elle ne dit pas ce qu'elles RENDENT. Le rendement d'une ressource ne vient
 * pas de `resource_types` — qui ne porte qu'un nom et des points de vie —
 * mais des biomes du JSON de plan : `{"wall": "arbre1", "ressource": "bois",
 * "exhaust": 75, "regrow": 20}`. C'est cette dispersion que le cadrage
 * propose de refermer avec `race_harvest`, clé sur (plan, race_id).
 *
 * Le JSON des deux plans concernés n'a pas pu être consulté depuis
 * l'environnement de développement — `datas/` n'y contient que trois plans
 * sur deux cent sept. Si leurs biomes couvrent déjà `arbre` et `glaise`, il
 * n'y a rien de plus à faire ; sinon, ces trois cases se récolteront sans
 * rien rendre, et cela se règle dans le plan, pas ici.
 *
 * Idempotente : `INSERT IGNORE` sur une clé primaire de nom.
 */
final class Version20260728120000_OrphanResourcesGetTheirType extends AbstractMigration
{
    /** Les orphelines, et la sœur dont elles reprennent la configuration. */
    private const ORPHANS = [
        'glaise3' => 'glaise3',
        'arbre7'  => 'arbre1',
    ];

    public function getDescription(): string
    {
        return 'resource_types gains glaise3 and arbre7 — placed and gatherable, but described nowhere';
    }

    public function up(Schema $schema): void
    {
        foreach (self::ORPHANS as $orphan => $sibling) {
            /* La sœur donne les PV quand elle existe ; sinon -1, qui est ce
             * que la carte dit déjà de l'orpheline — et ce que portent toutes
             * les ressources récoltables. */
            $this->addSql(
                'INSERT IGNORE INTO resource_types (name, pv)
                 SELECT ' . $this->connection->quote($orphan) . ',
                        COALESCE((SELECT pv FROM resource_types WHERE name = '
                        . $this->connection->quote($sibling) . '), -1)'
            );
        }
    }

    public function down(Schema $schema): void
    {
        $names = implode(', ', array_map(
            fn (string $name): string => $this->connection->quote($name),
            array_keys(self::ORPHANS)
        ));

        $this->addSql("DELETE FROM resource_types WHERE name IN ({$names})");
    }
}
