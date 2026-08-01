<?php

namespace Tests\Action\ImportExport;

use App\Factory\EntityManagerFactory;
use App\Service\ImportExport\ItemExporter;
use App\Service\ImportExport\ItemImporter;
use App\Service\ImportExport\RecipeExporter;
use App\Service\ImportExport\RecipeImporter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Aller-retour import → export → réimport des bundles d'objets et de
 * recettes sur la base réelle, avec des lignes jetables créées par le
 * test lui-même (aucune dépendance au contenu de l'environnement).
 */
#[Group('import-export')]
class ItemRecipeBundleRoundTripTest extends TestCase
{
    private const ITEM = 'test_bundle_objet';
    private const INGREDIENT = 'test_bundle_ingredient';
    private const RECIPE = 'test_bundle_recette';

    private Connection $conn;

    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../../config/bootstrap.php';
            $this->conn = EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $recipeId = $this->conn->fetchOne('SELECT id FROM craft_recipes WHERE name = ?', [self::RECIPE]);
        if ($recipeId !== false) {
            foreach (['craft_recipes_ingredients', 'craft_recipes_results', 'race_recipes'] as $table) {
                $this->conn->executeStatement("DELETE FROM {$table} WHERE recipe_id = ?", [(int) $recipeId]);
            }
            $this->conn->executeStatement('DELETE FROM craft_recipes WHERE id = ?', [(int) $recipeId]);
        }
        $this->conn->executeStatement(
            'DELETE FROM items WHERE name IN (?, ?)',
            [self::ITEM, self::INGREDIENT]
        );
    }

    public function testItemImportCreatesThenExportRoundTrips(): void
    {
        $payload = [
            'name' => self::ITEM,
            'text' => 'Objet jetable de test bundle',
            'price' => 42,
            'type' => 'consommable',
            'f' => 1,
            'wear_triggers' => 'usage',
            'wear_rate' => 2,
            'munitions' => ['fleche'],
            'extra' => ['legacyKey' => 'gardée'],
        ];

        $report = (new ItemImporter($this->conn))->import([$payload]);

        $this->assertSame([self::ITEM], $report->created());
        $this->assertFalse($report->hasRejections());

        $exported = (new ItemExporter($this->conn))->exportOne(self::ITEM);

        $this->assertSame(self::ITEM, $exported['name']);
        $this->assertSame('Objet jetable de test bundle', $exported['text']);
        $this->assertEquals(42, $exported['price']);
        $this->assertSame('consommable', $exported['type']);
        $this->assertEquals(1, $exported['f']);
        $this->assertSame('usage', $exported['wear_triggers']);
        $this->assertEquals(2, $exported['wear_rate']);
        $this->assertSame(['fleche'], $exported['munitions']);
        $this->assertSame(['legacyKey' => 'gardée'], (array) $exported['extra']);
        // Un objet importé est toujours sourcé en base, même si le
        // payload ne portait pas la clé.
        $this->assertEquals(1, $exported['stats_in_db']);

        // Réimporter son propre export est un update sans rejet.
        $again = (new ItemImporter($this->conn))->import([$exported]);
        $this->assertSame([self::ITEM], $again->updated());
        $this->assertFalse($again->hasRejections());
    }

    public function testRecipeImportResolvesItemNamesAndExportRoundTrips(): void
    {
        (new ItemImporter($this->conn))->import([
            ['name' => self::ITEM, 'price' => 1],
            ['name' => self::INGREDIENT, 'price' => 1],
        ]);

        $payload = [
            'name' => self::RECIPE,
            'ingredients' => [['item' => self::INGREDIENT, 'count' => 3]],
            'results' => [['item' => self::ITEM, 'count' => 1]],
            'races' => [],
        ];

        $report = (new RecipeImporter($this->conn))->import([$payload]);

        $this->assertSame([self::RECIPE], $report->created());
        $this->assertFalse($report->hasRejections());

        $exported = (new RecipeExporter($this->conn))->exportOne(self::RECIPE);

        $this->assertSame(self::RECIPE, $exported['name']);
        $this->assertEquals([['item' => self::INGREDIENT, 'count' => 3]], $exported['ingredients']);
        $this->assertEquals([['item' => self::ITEM, 'count' => 1]], $exported['results']);
        $this->assertSame([], $exported['races']);

        $again = (new RecipeImporter($this->conn))->import([$exported]);
        $this->assertSame([self::RECIPE], $again->updated());
        $this->assertFalse($again->hasRejections());
    }

    public function testRecipeReferencingUnknownItemIsRejectedWithoutWriting(): void
    {
        $report = (new RecipeImporter($this->conn))->preview([[
            'name' => self::RECIPE,
            'ingredients' => [['item' => 'objet_qui_n_existe_pas', 'count' => 1]],
            'results' => [['item' => 'objet_qui_n_existe_pas', 'count' => 1]],
        ]]);

        $this->assertTrue($report->hasRejections());
        $this->assertFalse(
            (bool) $this->conn->fetchOne('SELECT id FROM craft_recipes WHERE name = ?', [self::RECIPE])
        );
    }
}
