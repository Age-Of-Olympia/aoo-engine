<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The atelier gets a counter: its dialog leads to the repair and recycle
 * tabs of merchant.php (the counters rule — the dialog carries the role).
 * Every piece of equipment now wears down from the same 200 PV, so a
 * repair bill reads the same for all of them.
 */
final class Version20260921120000_TheAtelierRepairsAndRecycles extends AbstractMigration
{
    private const DURABILITY = 200;

    public function getDescription(): string
    {
        return "Dialogue de l'atelier (réparer, recycler) et 200 PV pour tout l'équipement";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items ALTER durability_max SET DEFAULT ' . self::DURABILITY);
        $this->addSql(
            "UPDATE items SET durability_max = ? WHERE type = 'equipement' AND durability_max < ?",
            [self::DURABILITY, self::DURABILITY]
        );

        if ($this->connection->fetchOne("SELECT id FROM dialogs WHERE name = 'atelier'") === false) {
            $this->addSql(
                "INSERT INTO dialogs (name, npc_name, type, custom, dialog_data, is_active)
                 VALUES ('atelier', 'TARGET_NAME', 'building', '', ?, 1)",
                [json_encode($this->dialog(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
            );
        }

        // An atelier already standing without a dialog takes this one.
        $this->addSql(
            "UPDATE buildings b JOIN players p ON p.id = b.player_id
                SET b.dialog = 'atelier'
              WHERE p.race = 'atelier' AND b.dialog = ''"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE buildings SET dialog = '' WHERE dialog = 'atelier'");
        $this->addSql("DELETE FROM dialogs WHERE name = 'atelier'");
        $this->addSql('ALTER TABLE items ALTER durability_max SET DEFAULT 100');
    }

    /** @return array<int, array<string, mixed>> */
    private function dialog(): array
    {
        return [
            [
                'id' => 'bonjour',
                'text' => 'Bienvenue à l\'atelier, PLAYER_NAME. Un objet usé se remet à neuf ici ; un objet brisé, lui, ne vaut plus que ses matières.',
                'options' => [
                    ['go' => 'reparer', 'text' => 'Je voudrais faire <font color=\'#f39c12\'>réparer</font> un objet.'],
                    ['go' => 'recycler', 'text' => 'J\'ai un objet <font color=\'#c0392b\'>brisé</font> à recycler.'],
                ],
            ],
            [
                'id' => 'reparer',
                'text' => 'Posez-le sur l\'établi. Le prix suit l\'usure : au pire un quart de la recette, en matières et un peu de main-d\'œuvre — ou tout en or, si vous préférez que je fournisse.',
                'options' => [
                    ['url' => 'merchant.php?targetId=TARGET_ID&repair', 'text' => '[voir mes objets usés]'],
                    ['go' => 'bonjour', 'text' => '[Retour]'],
                ],
            ],
            [
                'id' => 'recycler',
                'text' => 'Brisé, on ne le répare plus. Je le démonte et vous rends un quart de ce qui a servi à le faire.',
                'options' => [
                    ['url' => 'merchant.php?targetId=TARGET_ID&recycle', 'text' => '[voir mes objets brisés]'],
                    ['go' => 'bonjour', 'text' => '[Retour]'],
                ],
            ],
        ];
    }
}
