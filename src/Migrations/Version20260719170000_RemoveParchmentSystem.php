<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retire le système de parchemins : l'apprentissage des sorts passe par
 * les écoles de guerre, le « lire un parchemin_sort » (et le marchand de
 * sorts, jamais routé) partent. Le parchemin vierge (matière) suit — il
 * n'existait que pour y inscrire un sort.
 *
 * Purge par NOM (les ids diffèrent entre environnements) : lignes
 * filles d'abord (inventaires, banque, marché, échanges, instances,
 * recettes qui produisent OU consomment un parchemin), puis les objets.
 * L'option « acheter un Parchemin de sort » est retirée des dialogues.
 *
 * La colonne items.spell RESTE : elle porte les objets à sort intégré,
 * mécanisme distinct des parchemins.
 */
final class Version20260719170000_RemoveParchmentSystem extends AbstractMigration
{
    private const ITEM_NAMES = ['parchemin', 'parchemin_sort'];

    public function getDescription(): string
    {
        return 'Remove the parchment system (spell scrolls + blank parchment) — warschool replaces it';
    }

    public function up(Schema $schema): void
    {
        $ids = array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT id FROM items WHERE name IN (?, ?)',
            self::ITEM_NAMES
        ));

        if ($ids !== []) {
            $in = implode(',', $ids);

            foreach (['players_items', 'players_items_bank', 'players_items_exchanges',
                      'map_items', 'items_asks', 'items_bids'] as $table) {
                $this->addSql("DELETE FROM {$table} WHERE item_id IN ({$in})");
            }

            $this->addSql("DELETE l FROM players_items_instances l
                           JOIN item_instances i ON i.id = l.instance_id WHERE i.item_id IN ({$in})");
            $this->addSql("DELETE l FROM map_items_instances l
                           JOIN item_instances i ON i.id = l.instance_id WHERE i.item_id IN ({$in})");
            $this->addSql("DELETE FROM item_instances WHERE item_id IN ({$in})");

            // Recettes : celles qui produisent un parchemin (inscription)
            // comme celles qui en consomment (aucun autre débouché).
            $recipeIds = array_map('intval', $this->connection->fetchFirstColumn(
                "SELECT recipe_id FROM craft_recipes_results WHERE item_id IN ({$in})
                 UNION SELECT recipe_id FROM craft_recipes_ingredients WHERE item_id IN ({$in})"
            ));
            if ($recipeIds !== []) {
                $rin = implode(',', $recipeIds);
                foreach (['craft_recipes_ingredients', 'craft_recipes_results', 'race_recipes'] as $table) {
                    $this->addSql("DELETE FROM {$table} WHERE recipe_id IN ({$rin})");
                }
                $this->addSql("DELETE FROM craft_recipes WHERE id IN ({$rin})");
            }

            $this->addSql("DELETE FROM items WHERE id IN ({$in})");
        }

        // Dialogues : l'option qui pointait vers le marchand de sorts
        // (route &spells, jamais branchée) disparaît de chaque nœud.
        foreach ($this->connection->fetchAllAssociative(
            "SELECT id, dialog_data FROM dialogs WHERE dialog_data LIKE '%&spells%'"
        ) as $dialog) {
            $nodes = json_decode((string) $dialog['dialog_data']);
            if (!is_array($nodes)) {
                continue;
            }

            foreach ($nodes as $node) {
                if (!empty($node->options) && is_array($node->options)) {
                    $node->options = array_values(array_filter(
                        $node->options,
                        static fn (object $option): bool => !str_contains((string) ($option->url ?? ''), '&spells')
                    ));
                }
            }

            $this->addSql(
                'UPDATE dialogs SET dialog_data = ? WHERE id = ?',
                [json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $dialog['id']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->warnIf(true, 'RemoveParchmentSystem: pas de retour arrière (données supprimées).');
    }
}
