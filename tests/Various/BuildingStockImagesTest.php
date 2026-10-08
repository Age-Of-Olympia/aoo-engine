<?php

namespace Tests\Various;

use App\Service\BuildingService;
use App\Service\RaceService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A building type can show several images: each placed copy keeps the one
 * it was given, and stock changes only move the copies they concern.
 */
#[Group('items-baseline')]
class BuildingStockImagesTest extends LegacyPlayerFixtureTestCase
{
    private const TYPE = 'gm_stock_images';
    private const DIR = 'img/avatars/' . self::TYPE . '/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->link->executeStatement(
            "INSERT INTO races
                (code, name, label, description, playable, hidden, kind, type_kind, structure_nature,
                 bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color, faction, plan, pv)
             VALUES (?, ?, 'Stock', '', 0, 1, 'structure', 'building', 'obstacle',
                     '', '#cd7f32', 1, 1, '#8b6d43', 'black', '', '', 10)",
            [strtoupper(self::TYPE), self::TYPE]
        );
        RaceService::clearCache();

        $dir = $_SERVER['DOCUMENT_ROOT'] . '/' . self::DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            $this->markTestSkipped('img/ non inscriptible.');
        }
        foreach (['1.png', '2.png', '3.png'] as $file) {
            $image = imagecreatetruecolor(50, 50);
            imagepng($image, $dir . $file);
            imagedestroy($image);
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($_SERVER['DOCUMENT_ROOT'] . '/' . self::DIR . '*') ?: []);
        @rmdir($_SERVER['DOCUMENT_ROOT'] . '/' . self::DIR);
        $this->link->executeStatement('DELETE FROM races WHERE name = ?', [self::TYPE]);
        RaceService::clearCache();

        parent::tearDown();
    }

    private function place(?string $image): int
    {
        [$x, $y] = $this->farTile();
        $id = (new BuildingService())->place(self::TYPE, $this->tile($x, $y, 'gaia'), image: $image);
        $this->trackEntityId($id);

        return $id;
    }

    private function imageOf(int $id): string
    {
        return (string) $this->link->fetchOne('SELECT avatar FROM players WHERE id = ?', [$id]);
    }

    public function testEachCopyKeepsItsImageAndOnlyTheReplacedOnesMove(): void
    {
        $chosen = $this->place(self::DIR . '2.png');
        $default = $this->place(null);
        $this->assertSame(self::DIR . '2.png', $this->imageOf($chosen), 'the brush image');
        $this->assertSame(self::DIR . '1.png', $this->imageOf($default), 'no brush: the first image');

        $buildings = new BuildingService();
        $buildings->refreshTypeSprites(self::TYPE);
        $this->assertSame(self::DIR . '2.png', $this->imageOf($chosen), 'a stock change leaves other copies alone');

        // Replace 2.png by 3.png: only its copies follow.
        unlink($_SERVER['DOCUMENT_ROOT'] . '/' . self::DIR . '2.png');
        $buildings->refreshTypeSprites(self::TYPE, [self::DIR . '2.png' => self::DIR . '3.png']);
        $this->assertSame(self::DIR . '3.png', $this->imageOf($chosen));
        $this->assertSame(self::DIR . '1.png', $this->imageOf($default));

        // Delete 1.png: its copies take the first image left.
        unlink($_SERVER['DOCUMENT_ROOT'] . '/' . self::DIR . '1.png');
        $buildings->refreshTypeSprites(self::TYPE);
        $this->assertSame(self::DIR . '3.png', $this->imageOf($default));
    }

    public function testARuinGetsItsOwnImageBackOnceRestored(): void
    {
        $id = $this->place(null);
        $buildings = new BuildingService();
        $buildings->setImage($id, self::DIR . '3.png');
        $this->assertSame(self::DIR . '3.png', $this->imageOf($id));

        $broken = $_SERVER['DOCUMENT_ROOT'] . '/img/walls/' . self::TYPE . '_broken.png';
        $image = imagecreatetruecolor(50, 50);
        imagepng($image, $broken);
        imagedestroy($image);

        try {
            $buildings->markDestroyed($id);
            $this->assertSame('img/walls/' . self::TYPE . '_broken.png', $this->imageOf($id));

            $buildings->restore($id);
            $this->assertSame(self::DIR . '3.png', $this->imageOf($id));
        } finally {
            @unlink($broken);
        }
    }

    public function testAnOpenDoorShowsItsOpenImageAndARuinItsBrokenOne(): void
    {
        $this->link->executeStatement(
            "UPDATE races SET structure_nature = 'porte', lockable = 1 WHERE name = ?",
            [self::TYPE]
        );
        RaceService::clearCache();

        $walls = $_SERVER['DOCUMENT_ROOT'] . '/img/walls/' . self::TYPE;
        foreach (['_open', '_broken'] as $suffix) {
            $image = imagecreatetruecolor(50, 50);
            imagepng($image, $walls . $suffix . '.png');
            imagedestroy($image);
        }

        try {
            $id = $this->place(null);
            $buildings = new BuildingService();
            $this->assertSame('img/walls/' . self::TYPE . '_open.png', $this->imageOf($id), 'a door is placed open');

            $buildings->setOpen($id, false);
            $this->assertSame(self::DIR . '1.png', $this->imageOf($id), 'closed: the base image');

            $buildings->setOpen($id, true);
            $buildings->markDestroyed($id);
            $this->assertSame('img/walls/' . self::TYPE . '_broken.png', $this->imageOf($id), 'damage wins over open');

            $buildings->restore($id);
            $this->assertSame('img/walls/' . self::TYPE . '_open.png', $this->imageOf($id));
        } finally {
            @unlink($walls . '_open.png');
            @unlink($walls . '_broken.png');
        }
    }

    public function testAnImageOutsideTheStockIsRefused(): void
    {
        $id = $this->place('img/avatars/nain/1.png');
        $this->assertSame(self::DIR . '1.png', $this->imageOf($id), 'placement falls back to the first image');

        $this->expectException(\InvalidArgumentException::class);
        (new BuildingService())->setImage($id, 'img/avatars/nain/1.png');
    }
}
