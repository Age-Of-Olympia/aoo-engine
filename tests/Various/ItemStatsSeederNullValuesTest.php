<?php

namespace Tests\Various;

use App\Service\ItemStatsSeeder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * A null in an item JSON must not stop the seed.
 *
 * `is_numeric(null)` is false, so a null used to be cast to '' and handed to
 * an integer column: MySQL answers "Incorrect integer value: '' for column
 * grow_rate", the exception escapes, and the WHOLE seed fails on the first
 * such file — the admin page shows only "Échec". `torche.json` carries
 * exactly that (`"grow_rate": null`), so the button was unusable.
 *
 * A null means "not set": the column keeps its default.
 */
class ItemStatsSeederNullValuesTest extends TestCase
{
    private string $docroot = '';

    /** @var list<string> */
    private array $sown = [];

    protected function tearDown(): void
    {
        if ($this->sown !== [] && ($link = $this->linkOrNull()) !== null) {
            $link->executeStatement(
                'DELETE FROM items WHERE name IN (?)',
                [$this->sown],
                [\Doctrine\DBAL\ArrayParameterType::STRING]
            );
        }
        if ($this->docroot !== '' && is_dir($this->docroot)) {
            foreach (glob($this->docroot . '/datas/public/items/*.json') ?: [] as $file) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testANullScalarLeavesTheColumnAloneInsteadOfFailingTheSeed(): void
    {
        $link = $this->linkOrNull();
        if ($link === null) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        $name = 'zz_seed_null_' . bin2hex(random_bytes(4));
        $this->docroot = sys_get_temp_dir() . '/seed_' . $name;
        mkdir($this->docroot . '/datas/public/items', 0777, true);
        file_put_contents(
            $this->docroot . '/datas/public/items/' . $name . '.json',
            (string) json_encode(['name' => $name, 'price' => 7, 'grow_rate' => null, 'text' => 'graine'])
        );

        $link->insert('items', ['name' => $name, 'stats_in_db' => 0]);
        $this->sown[] = $name;

        $result = (new ItemStatsSeeder())->seed($link, $this->docroot);

        $this->assertGreaterThanOrEqual(1, $result['seeded'], 'the file was read');

        $row = $link->fetchAssociative(
            'SELECT stats_in_db, price, grow_rate FROM items WHERE name = ?',
            [$name]
        );

        $this->assertSame(1, (int) $row['stats_in_db'], 'the row is now DB-sourced');
        $this->assertSame(7, (int) $row['price'], 'the values beside the null are still written');
        $this->assertNull($row['grow_rate'], 'a null leaves the column at its default');
    }

    private function linkOrNull(): ?Connection
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
        } catch (\Throwable $e) {
            return null;
        }

        global $link;

        return $link instanceof Connection ? $link : null;
    }
}
