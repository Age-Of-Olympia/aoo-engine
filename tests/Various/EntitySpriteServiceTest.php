<?php

namespace Tests\Various;

use App\Service\BuildingService;
use App\Service\Map\CompositeSpriteService;
use App\Service\Map\EntitySpriteService;
use App\Service\Map\EntityTypeFootprintService;
use App\Service\RaceService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A type cut in pieces is drawn whole whatever its kind: the pieces are cut
 * into, looked for and stitched in the folder the kind keeps its pictures in.
 */
#[Group('items-baseline')]
class EntitySpriteServiceTest extends LegacyPlayerFixtureTestCase
{
    private const TYPE = 'gm_sprite_edifice';

    /** @var list<string> files this case wrote under img/ */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $file) {
            @unlink($file);
        }

        $this->link->executeStatement('DELETE FROM entity_type_footprints WHERE type_name = ?', [self::TYPE]);
        $this->link->executeStatement('DELETE FROM races WHERE name = ?', [self::TYPE]);
        RaceService::clearCache();
        EntitySpriteService::forget();

        parent::tearDown();
    }

    private function png(string $relative, int $w, int $h): void
    {
        $file = $_SERVER['DOCUMENT_ROOT'] . '/' . $relative;

        if (!is_dir(dirname($file)) && !@mkdir(dirname($file), 0775, true)) {
            $this->markTestSkipped('img/ non inscriptible.');
        }

        $image = imagecreatetruecolor($w, $h);
        imagepng($image, $file);
        imagedestroy($image);
        $this->written[] = $file;
    }

    private function seedBuildingType(): void
    {
        $this->link->executeStatement(
            "INSERT INTO races
                (code, name, label, description, playable, hidden, kind, type_kind, structure_nature,
                 bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color, faction, plan, pv)
             VALUES (?, ?, 'Édifice', '', 0, 1, 'structure', 'building', 'edifice',
                     '', '#cd7f32', 1, 1, '#8b6d43', 'black', '', '', 10)",
            [strtoupper(self::TYPE), self::TYPE]
        );
        RaceService::clearCache();

        (new EntityTypeFootprintService($this->link))
            ->declare(self::TYPE, 2, 2, [[0, 0], [1, 0], [0, -1], [1, -1]]);
    }

    public function testABuildingCutInWallPiecesIsStitchedLikeAFigure(): void
    {
        $this->seedBuildingType();
        $footprints = new EntityTypeFootprintService($this->link);

        /* The whole picture, cut along the shape — the admin's gesture. */
        $this->png('img/walls/' . self::TYPE . '.png', 100, 100);
        $pieces = (new CompositeSpriteService())->cutPieces(
            'walls',
            self::TYPE,
            $footprints->catalogue()[self::TYPE],
            $_SERVER['DOCUMENT_ROOT'] . '/img/walls/' . self::TYPE . '.png'
        );
        $this->assertSame([0, 1, 2, 3], array_keys($pieces));
        foreach ($pieces as $piece) {
            $this->written[] = $_SERVER['DOCUMENT_ROOT'] . '/' . $piece;
        }
        $this->written[] = $_SERVER['DOCUMENT_ROOT'] . '/img/walls/_composed/' . self::TYPE . '.png';
        EntitySpriteService::forget();

        $sprite = (new EntitySpriteService())->spriteOf(self::TYPE);

        $this->assertSame('img/walls/_composed/' . self::TYPE . '.png', $sprite);
        $this->assertSame([100, 100], array_slice((array) getimagesize($_SERVER['DOCUMENT_ROOT'] . '/' . $sprite), 0, 2));
        $this->assertSame($sprite, BuildingService::resolveAvatar(self::TYPE), 'le plateau et la palette lisent la même image');
    }

    /** A trade hall was scenery: its pieces are still in img/foregrounds on the servers. */
    public function testPiecesLeftInAnotherFolderStillDrawTheType(): void
    {
        $this->seedBuildingType();

        foreach ([0, 1, 2, 3] as $piece) {
            $this->png('img/foregrounds/' . self::TYPE . '_0' . $piece . '.png', 50, 50);
        }
        $this->written[] = $_SERVER['DOCUMENT_ROOT'] . '/img/foregrounds/_composed/' . self::TYPE . '.png';
        EntitySpriteService::forget();

        $this->assertSame(
            'img/foregrounds/_composed/' . self::TYPE . '.png',
            (new EntitySpriteService())->spriteOf(self::TYPE)
        );
    }

    public function testATypeWithoutPiecesHasNoStitchedSprite(): void
    {
        $this->assertNull((new EntitySpriteService())->spriteOf('gm_sprite_unknown'));
    }
}
