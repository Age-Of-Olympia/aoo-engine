<?php

namespace Tests\Various;

use App\Service\RaceService;
use App\Service\TypeRenameService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\LegacyBootstrapTrait;

/**
 * Renaming a building type carries its name everywhere it is spelled out:
 * placed instance, construction item, legacy layer pieces, footprint, and
 * the image files named after it — but never a longer name sharing its
 * prefix. Temporary img/ tree; rows in the configured database.
 */
class TypeRenameServiceTest extends TestCase
{
    use LegacyBootstrapTrait;

    private const FROM = 'trs_test_tour';
    private const TO = 'trs_test_donjon';
    private const PLAN = 'plan_test_trs';
    private const INSTANCE = 990611;

    private ?Connection $conn = null;
    private string $root;

    protected function setUp(): void
    {
        $this->conn = $this->bootstrapLegacyOrSkip('entity_type_footprints');
        $this->root = sys_get_temp_dir() . '/type_rename_' . uniqid();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        if ($this->conn === null) {
            return;
        }
        $this->cleanup();
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function cleanup(): void
    {
        foreach ([self::FROM, self::TO] as $name) {
            $this->conn->executeStatement('DELETE FROM map_resources WHERE name LIKE ?', [$name . '%']);
            $this->conn->executeStatement('DELETE FROM items WHERE name = ?', [$name]);
            $this->conn->executeStatement('DELETE FROM entity_type_footprints WHERE type_name = ?', [$name]);
            $this->conn->executeStatement('DELETE FROM races WHERE name = ?', [$name]);
        }
        $this->conn->executeStatement('DELETE FROM players WHERE id = ?', [self::INSTANCE]);
        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
        RaceService::clearCache();
    }

    private function seedType(): void
    {
        $this->conn->executeStatement(
            "INSERT INTO races
                (code, name, label, description, playable, hidden, kind, type_kind, structure_nature,
                 bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color, faction, plan, pv)
             VALUES (?, ?, 'Tour', '', 0, 1, 'structure', 'building', 'obstacle',
                     '', '#cd7f32', 1, 1, '#8b6d43', 'black', '', '', 10)",
            [strtoupper(self::FROM), self::FROM]
        );
        RaceService::clearCache();
    }

    private function touch(string $relative): void
    {
        $path = $this->root . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, 'x');
    }

    public function testARenameFollowsTheNameIntoRowsAndFiles(): void
    {
        $this->seedType();
        $this->conn->insert('coords', ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::PLAN]);
        $coordsId = (int) $this->conn->lastInsertId();
        $this->conn->insert('players', [
            'id' => self::INSTANCE, 'player_type' => 'building', 'name' => 'Tour', 'race' => self::FROM,
            'coords_id' => $coordsId,
            'avatar' => 'img/walls/' . self::FROM . '_broken.png', 'portrait' => 'img/avatars/' . self::FROM . '/1.png',
        ]);
        $this->conn->insert('items', ['name' => self::FROM, 'requires_building' => self::FROM]);
        $this->conn->insert('map_resources', ['name' => self::FROM . '-01', 'coords_id' => $coordsId]);
        $this->conn->insert('entity_type_footprints', ['type_name' => self::FROM, 'w' => 2, 'h' => 1, 'offsets' => '{}']);

        foreach (['.png', '_broken.png', '_1.png', '_longer.png'] as $suffix) {
            $this->touch('img/walls/' . self::FROM . $suffix);
        }
        $this->touch('img/walls/_composed/' . self::FROM . '.png');
        $this->touch('img/items/' . self::FROM . '_mini.webp');
        $this->touch('img/avatars/' . self::FROM . '/1.png');

        $report = (new TypeRenameService(null, $this->root))->rename(self::FROM, self::TO);

        $this->assertSame(1, (int) $this->conn->fetchOne('SELECT COUNT(*) FROM races WHERE name = ? AND code = ?', [self::TO, strtoupper(self::TO)]));
        $this->assertSame(
            ['race' => self::TO, 'avatar' => 'img/walls/' . self::TO . '_broken.png', 'portrait' => 'img/avatars/' . self::TO . '/1.png'],
            $this->conn->fetchAssociative('SELECT race, avatar, portrait FROM players WHERE id = ?', [self::INSTANCE]),
            'the placed instance and its sprites follow'
        );
        $this->assertSame(self::TO, $this->conn->fetchOne('SELECT requires_building FROM items WHERE name = ?', [self::TO]), 'the construction item follows');
        $this->assertSame(1, (int) $this->conn->fetchOne('SELECT COUNT(*) FROM map_resources WHERE name = ?', [self::TO . '-01']), 'layer pieces follow');
        $this->assertSame(1, (int) $this->conn->fetchOne('SELECT COUNT(*) FROM entity_type_footprints WHERE type_name = ?', [self::TO]));

        foreach (['walls/' . self::TO . '.png', 'walls/' . self::TO . '_broken.png', 'walls/' . self::TO . '_1.png',
                  'walls/_composed/' . self::TO . '.png', 'items/' . self::TO . '_mini.webp', 'avatars/' . self::TO . '/1.png'] as $file) {
            $this->assertFileExists($this->root . '/img/' . $file);
        }
        $this->assertFileExists($this->root . '/img/walls/' . self::FROM . '_longer.png', 'a longer name sharing the prefix stays');
        $this->assertDirectoryDoesNotExist($this->root . '/img/avatars/' . self::FROM);
        $this->assertCount(6, $report['files']);
    }

    public function testRefusesAHardCodedNameAndATakenOneWithoutTouchingAnything(): void
    {
        $this->seedType();
        $this->touch('img/walls/' . self::FROM . '.png');
        $service = new TypeRenameService(null, $this->root);

        try {
            $service->rename(self::FROM, TypeRenameService::HARD_CODED_NAMES[0]);
            $this->fail('a name spelled out in PHP is refused');
        } catch (RuntimeException $e) {
            $this->assertSame(400, $e->getCode());
        }

        // A file already holding the target name stops the rename before any write.
        $this->touch('img/walls/' . self::TO . '.png');
        try {
            $service->rename(self::FROM, self::TO);
            $this->fail('an occupied file name is refused');
        } catch (RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }
        $this->assertSame(1, (int) $this->conn->fetchOne('SELECT COUNT(*) FROM races WHERE name = ?', [self::FROM]));
        $this->assertFileExists($this->root . '/img/walls/' . self::FROM . '.png');
    }
}
