<?php

namespace Tests\Various;

use App\Service\ItemStatsSeeder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Le seeder d'objets n'écrit que des colonnes qui existent.
 *
 * SCALAR_KEYS dérive de Item::SPECIAL_KEYS et de Caracs::KEYS — des
 * constantes VIVANTES. La migration qui crée les colonnes, elle, fige sa
 * liste à sa date. Quand `grow_rate` a rejoint SPECIAL_KEYS six jours après
 * la migration des items, les deux ont divergé et plus aucune base fraîche
 * n'était constructible : le seed écrivait une colonne à naître.
 *
 * Le seeder filtre désormais à l'exécution, ce qui sauve le rejeu. Ce test
 * verrouille la CAUSE plutôt que le symptôme : sur une base à jour, toute
 * clé écrite doit avoir sa colonne. Ajouter une clé sans la migration qui
 * va avec fait échouer la suite au lieu de rester invisible jusqu'à la
 * prochaine installation neuve.
 */
class ItemStatsSeederColumnsTest extends TestCase
{
    public function testEveryWritableKeyHasItsColumn(): void
    {
        $columns = $this->itemColumnsOrSkip();

        $expected = ItemStatsSeeder::SCALAR_KEYS;
        foreach (ItemStatsSeeder::JSON_KEYS as $key) {
            $expected[] = $key === 'addEffects' ? 'add_effects' : $key;
        }
        $expected[] = 'extra';

        $missing = array_values(array_diff($expected, $columns));

        $this->assertSame(
            [],
            $missing,
            "Ces clés seraient écrites par ItemStatsSeeder sans avoir de colonne dans `items` : "
            . implode(', ', $missing)
            . ". Ajouter la migration correspondante, ou retirer la clé de la liste."
        );
    }

    /** @return list<string> */
    private function itemColumnsOrSkip(): array
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        $columns = array_map('strval', $link->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items'"
        ));

        if (!in_array('stats_in_db', $columns, true)) {
            $this->markTestSkipped('colonnes de stats absentes (migrations non jouées).');
        }

        return $columns;
    }
}
