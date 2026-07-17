<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dialogue attaché à un bâtiment : buildings.dialog porte le code du
 * dialogue (clé naturelle dialogs.name, '' = aucun). Ce qui était
 * porté par des PNJ (marchand, école de guerre, dialogues de lore via
 * map_dialogs) peut désormais l'être par une entité bâtiment — le lien
 * vit sur l'ENTITÉ, pas sur la case, et suit donc le bâtiment dans
 * tout son cycle de vie (ruine = dialogue muet, restauration = il
 * reparle, suppression = lien disparu).
 *
 * Pas de FK : dialogs.name est la clé d'échange des bundles
 * export/import, même convention sans FK que races/factions.
 */
final class Version20260718120000_BuildingDialog extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'buildings.dialog : code de dialogue (dialogs.name) porté par le bâtiment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE buildings ADD dialog VARCHAR(100) NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buildings DROP COLUMN dialog');
    }
}
