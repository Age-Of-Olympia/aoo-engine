<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un objet POSÉ dit ce qu'il obstrue, comme n'importe quel type.
 *
 * `races` porte `blocks_passage` et `blocks_projectiles` depuis toujours, et
 * les murs prouvent qu'ils se règlent un par un : dix d'entre eux laissent
 * passer la flèche. Le catalogue des objets n'avait ni l'un ni l'autre, si
 * bien qu'un exemplaire installé obstruait par accident de recherche — son
 * type n'étant dans aucune des deux listes de races.
 *
 * Un coffre est un OBJET. Installé, il occupe sa case selon ces deux réglages ;
 * JETÉ, il n'obstrue rien — et cela, la localisation le dit déjà : ce qui
 * traîne ne tient pas sa case. Les colonnes ne parlent donc que du posé.
 *
 * Semé sur ce que les mêmes types disent déjà côté `races`, le temps que les
 * coffres cessent d'exister des deux côtés : un coffre arrête le pas, pas la
 * flèche. Tout le reste — une épée, une planche — n'obstrue rien.
 */
final class Version20260803200000_AnItemSaysWhatItObstructs extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'items.blocks_passage / blocks_projectiles: an installed exemplar states what it obstructs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE items ADD COLUMN IF NOT EXISTS blocks_passage TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->addSql(
            'ALTER TABLE items ADD COLUMN IF NOT EXISTS blocks_projectiles TINYINT(1) NOT NULL DEFAULT 0'
        );

        /* Ce que le type de bâtiment homonyme dit déjà : un coffre barre le
         * pas et laisse passer la flèche. */
        $this->addSql(
            "UPDATE items i JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(i.name USING utf8mb4)
                SET i.blocks_passage = r.blocks_passage,
                    i.blocks_projectiles = r.blocks_projectiles
              WHERE r.kind = 'structure'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS blocks_passage');
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS blocks_projectiles');
    }
}
