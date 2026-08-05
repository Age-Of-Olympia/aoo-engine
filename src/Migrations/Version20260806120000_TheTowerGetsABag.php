<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Review decision: no differentiated ceilings — every people keeps the
 * flat 10. The one gap stays: the playable guard tower inherited the
 * structures' unlimited bag (capacity 0); driven, it carries like
 * everybody. Guarded on the previous default so an admin tuning
 * survives replays.
 */
final class Version20260806120000_TheTowerGetsABag extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'un sac de 10 pour tous — la tour de garde jouable comprise';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE races SET capacity = 10 WHERE name = 'tour_garde' AND capacity = 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE races SET capacity = 0 WHERE name = 'tour_garde'");
    }
}
