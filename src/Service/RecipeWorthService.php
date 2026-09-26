<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Random\Randomizer;

/**
 * What an item is worth in raw resources, and how to hand out a sum in
 * those resources.
 *
 * The recipe is flattened down to raw resources (a sceptre in the recipe
 * counts as what the sceptre is made of), the worth is their summed
 * catalog price. A sum of gold converts back into whole resources of the
 * recipe, priciest first. Shared by the atelier's repair and recycling;
 * a building or a chest torn down settles the same way.
 */
final class RecipeWorthService
{
    /** @var array<string, array<string, array{count: float, price: int, race: string}>> flatten() per item, for this request */
    private array $flattened = [];

    public function __construct(private Connection $conn)
    {
    }

    /**
     * The recipe flattened to raw resources, priced from the catalog:
     * name => [count, price, race], for ONE item: a recipe that makes 6
     * arrows from 5 wood counts 5/6 wood per arrow. An ingredient with a
     * recipe of its own is replaced by what it is made of; gold stays a
     * line (it counts in the worth) but is never handed out as a resource.
     *
     * @param array<string, true> $seen the results already being expanded, against recipe cycles
     * @return array<string, array{count: float, price: int, race: string}>
     */
    public function flatten(string $itemName, array $seen = []): array
    {
        // Cycle guard aside, the result depends on the item only
        if ($seen === [] && isset($this->flattened[$itemName])) {
            return $this->flattened[$itemName];
        }

        $recipe = $this->expand($itemName, $seen);
        if ($seen === []) {
            $this->flattened[$itemName] = $recipe;
        }

        return $recipe;
    }

    /**
     * @param array<string, true> $seen
     * @return array<string, array{count: float, price: int, race: string}>
     */
    private function expand(string $itemName, array $seen): array
    {
        $seen[$itemName] = true;
        $recipes = new RecipeService();
        $source = $recipes->recipeForResult($itemName);
        if ($source === null) {
            return [];
        }
        $ingredients = [];
        foreach ($source->getRecipeIngredients() as $ingredient) {
            $ingredients[$ingredient->getItem()->getName()] = $ingredient->getCount() / $recipes->yieldOf($source, $itemName);
        }
        if ($ingredients === []) {
            return [];
        }

        $catalog = $this->conn->fetchAllAssociativeIndexed(
            'SELECT name, price, race FROM items WHERE name IN (?)',
            [array_keys($ingredients)],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $recipe = [];
        $add = static function (string $name, float $count, int $price, string $race) use (&$recipe): void {
            $recipe[$name] ??= ['count' => 0, 'price' => $price, 'race' => $race];
            $recipe[$name]['count'] += $count;
        };
        foreach ($ingredients as $name => $count) {
            $parts = isset($seen[$name]) ? [] : $this->flatten($name, $seen);
            if ($parts === []) {
                $add($name, $count, (int) ($catalog[$name]['price'] ?? 0), (string) ($catalog[$name]['race'] ?? ''));
                continue;
            }
            foreach ($parts as $part => $line) {
                $add($part, $line['count'] * $count, $line['price'], $line['race']);
            }
        }

        return $recipe;
    }

    /** @param array<string, array{count: float, price: int, race: string}> $recipe */
    public function worthOf(array $recipe): float
    {
        $worth = 0;
        foreach ($recipe as $line) {
            $worth += $line['price'] * $line['count'];
        }

        return $worth;
    }

    /**
     * Whole resources of the recipe worth $amount: while one is affordable,
     * draw among the priciest affordable ones and pay it. With one price
     * per tier that reads rare → racial → common, the change under the
     * cheapest resource is forgiven — unless nothing was drawn: a bill
     * smaller than every price costs one of the cheapest, never nothing.
     *
     * @param array<string, array{count: float, price: int, race: string}> $recipe
     * @param Randomizer|null $dice seeded by the caller when the draw must be replayable
     * @return array<string, int> name => count
     */
    public function resourcesWorth(int $amount, array $recipe, ?Randomizer $dice = null): array
    {
        $dice ??= new Randomizer();
        $prices = [];
        foreach ($recipe as $name => $line) {
            if ($name !== 'or' && $line['price'] > 0) {
                $prices[$name] = $line['price'];
            }
        }

        $out = [];
        $owed = $amount > 0;
        while ($prices !== []) {
            $affordable = array_filter($prices, static fn (int $price): bool => $price <= $amount);
            if ($affordable === []) {
                break;
            }
            $top = max($affordable);
            $name = $dice->pickArrayKeys(array_filter($affordable, static fn (int $price): bool => $price === $top), 1)[0];
            $out[$name] = ($out[$name] ?? 0) + 1;
            $amount -= $top;
        }

        if ($out === [] && $owed && $prices !== []) {
            $out[array_search(min($prices), $prices, true)] = 1;
        }

        return $out;
    }

    /**
     * One resource of the object's race (a base one for a common object):
     * drawn from the recipe when it holds one, else the cheapest the
     * catalog knows.
     *
     * @param array<string, array{count: float, price: int, race: string}> $recipe
     * @return array{name: string, price: int}|null
     */
    public function racialResource(string $race, array $recipe, ?Randomizer $dice = null): ?array
    {
        $dice ??= new Randomizer();
        $race = $race === '' ? 'common' : $race;

        $own = array_filter($recipe, static fn (array $line, string $name): bool => $name !== 'or' && $line['race'] === $race, ARRAY_FILTER_USE_BOTH);
        if ($own !== []) {
            $name = $dice->pickArrayKeys($own, 1)[0];

            return ['name' => $name, 'price' => $own[$name]['price']];
        }

        $row = $this->conn->fetchAssociative(
            "SELECT name, price FROM items WHERE race = ? AND type = 'matiere' AND is_deprecated = 0 ORDER BY price, name LIMIT 1",
            [$race]
        );

        return $row === false ? null : ['name' => (string) $row['name'], 'price' => (int) $row['price']];
    }

    /**
     * A share of a recipe, whole units only, rounded down: nothing is conjured.
     *
     * @param array<string, array{count: float, price: int, race: string}> $recipe
     * @return array<string, int>
     */
    public function shareOf(array $recipe, float $share): array
    {
        $out = [];
        foreach ($recipe as $name => $ingredient) {
            $n = (int) floor($ingredient['count'] * $share);
            if ($n > 0) {
                $out[$name] = $n;
            }
        }

        return $out;
    }
}
