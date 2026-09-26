<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The atelier's dialog still quoted the old repair bill: a share of the
 * recipe plus a labour fee in gold. The bill is now a share of the
 * object's value, paid in the recipe's resources plus one resource of the
 * object's race, and the labour fee is gone.
 *
 * A plain text swap, so an atelier dialog edited in the admin keeps its
 * own wording.
 */
final class Version20260922120000_TheAtelierQuotesTheValue extends AbstractMigration
{
    private const OLD = 'Le prix suit l\'usure : au pire un quart de la recette, en matières'
        . ' et un peu de main-d\'œuvre — ou tout en or, si vous préférez que je fournisse.';

    private const NEW = 'Le prix dépend de l\'usure : au plus un quart de la valeur de l\'objet,'
        . ' payé en matières de sa recette, plus une matière du peuple qui l\'a fait'
        . ' — ou tout en or, si vous préférez que je fournisse.';

    public function getDescription(): string
    {
        return "Dialogue de l'atelier : le prix de la réparation suit la valeur de l'objet";
    }

    public function up(Schema $schema): void
    {
        $this->swapQuote(self::OLD, self::NEW);
    }

    public function down(Schema $schema): void
    {
        $this->swapQuote(self::NEW, self::OLD);
    }

    private function swapQuote(string $from, string $to): void
    {
        $this->addSql(
            'UPDATE dialogs SET dialog_data = REPLACE(dialog_data, ?, ?)
              WHERE name = ? AND dialog_data LIKE ?',
            [$from, $to, 'atelier', '%' . $from . '%']
        );
    }
}
