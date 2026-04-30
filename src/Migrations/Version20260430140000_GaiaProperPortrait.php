<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Point Gaïa's avatar/portrait at the dieu/25 asset used in prod.
 *
 * Previous tutorial migrations seeded Gaïa with
 *   avatar  = img/avatars/ame/dieu.webp
 *   portrait = img/portraits/ame/1.jpeg
 *
 * Both are generic 'ame' (soul) assets — a plain ghost silhouette and a
 * cosmic-energy backdrop. Players see a "mannequin"-shaped icon next to
 * the actual training dummy and can't tell them apart on the map.
 *
 * Prod ships the goddess portrait under img/avatars/dieu/25.png +
 * img/portraits/dieu/1.jpeg. Dev image bundles don't include them
 * (acceptable per playtest feedback — the prod-bound rollout is what
 * matters here; dev will fall back to a broken-image icon until the
 * asset is mirrored locally).
 *
 * Updates the template Gaïa (id = -999999) and every per-session
 * instance copy made by TutorialMapInstance::copyNPCs(). Idempotent +
 * scoped on the previous placeholder paths so any hand-curated NPC
 * keeps its custom imagery.
 */
final class Version20260430140000_GaiaProperPortrait extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Point Gaïa NPC avatar/portrait at the dieu/25 asset used in prod";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE players
            SET avatar = 'img/avatars/dieu/25.png',
                portrait = 'img/portraits/dieu/1.jpeg'
            WHERE name = 'Gaïa'
              AND avatar = 'img/avatars/ame/dieu.webp'
              AND portrait = 'img/portraits/ame/1.jpeg'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE players
            SET avatar = 'img/avatars/ame/dieu.webp',
                portrait = 'img/portraits/ame/1.jpeg'
            WHERE name = 'Gaïa'
              AND avatar = 'img/avatars/dieu/25.png'
              AND portrait = 'img/portraits/dieu/1.jpeg'
        ");
    }
}
