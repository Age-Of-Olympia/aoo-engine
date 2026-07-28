<?php

namespace Tests\Various;

use App\View\Observe\SceneryPortraitView;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The portrait of a multi-cell object: its whole figure, not one corner.
 *
 * A nine-piece library was shown by its top-left ninth, since an entity's
 * `portrait` is a single image and such a figure has none.
 */
#[Group('items-golden-master')]
class SceneryPortraitViewTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_portrait';

    protected function tearDown(): void
    {
        $link = $this->link;

        $link->executeStatement(
            'DELETE m FROM map_foregrounds m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $link->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    /** Lay a piece on the map and give the entity the cell that holds it. */
    private function piece(int $entityId, string $name, int $x, int $y, int $index): void
    {
        $coordsId = $this->coordsId($x, $y);

        $this->link->executeStatement(
            'INSERT INTO map_foregrounds (name, coords_id) VALUES (?, ?)',
            [$name, $coordsId]
        );

        $this->link->executeStatement(
            'INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, 0, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE piece = VALUES(piece)',
            [$entityId, $coordsId, self::PLAN, $x, $y, $index, 'cover']
        );
    }

    public function testTheWholeFigureIsRecomposed(): void
    {
        $entity = $this->createRealPlayer('GmPortrait');

        $this->piece((int) $entity->id, 'gm_tour-00', 0, 1, 0);
        $this->piece((int) $entity->id, 'gm_tour-01', 0, 0, 1);

        $html = (new SceneryPortraitView($this->link))->compose((int) $entity->id);

        $this->assertNotNull($html);
        $this->assertStringContainsString('gm_tour-00.png', $html);
        $this->assertStringContainsString('gm_tour-01.png', $html, 'every piece, not just the first');
    }

    /**
     * The piece names come from the MAP, not from the family name: three
     * suffix conventions coexist on disk and guessing would miss two.
     */
    public function testPieceNamesComeFromTheMap(): void
    {
        $entity = $this->createRealPlayer('GmPortraitConv');

        $this->piece((int) $entity->id, 'gm_odd_07', 0, 1, 7);
        $this->piece((int) $entity->id, 'gm_odd_08', 0, 0, 8);

        $html = (new SceneryPortraitView($this->link))->compose((int) $entity->id);

        $this->assertNotNull($html);
        $this->assertStringContainsString('gm_odd_07.png', $html);
        $this->assertStringContainsString('gm_odd_08.png', $html);
    }

    /** The board grows upwards, the screen downwards. */
    public function testTheFigureIsNotDrawnUpsideDown(): void
    {
        $entity = $this->createRealPlayer('GmPortraitHaut');

        $this->piece((int) $entity->id, 'gm_haut-00', 0, 1, 0);
        $this->piece((int) $entity->id, 'gm_haut-01', 0, 0, 1);

        $html = (string) (new SceneryPortraitView($this->link))->compose((int) $entity->id);

        $top = static function (string $piece) use ($html): int {
            preg_match('/' . preg_quote($piece, '/') . '\.png"[^>]*top:(\d+)px/', $html, $m);

            return (int) ($m[1] ?? -1);
        };

        $this->assertSame(0, $top('gm_haut-00'), 'the higher piece sits at the top');
        $this->assertGreaterThan(0, $top('gm_haut-01'), 'and the lower one below it');
    }

    /** A single cell speaks for itself: nothing to recompose. */
    public function testASingleCellHasNoComposedPortrait(): void
    {
        $entity = $this->createRealPlayer('GmPortraitSeul');

        $this->piece((int) $entity->id, 'gm_seul', 0, 0, 0);

        $this->assertNull((new SceneryPortraitView($this->link))->compose((int) $entity->id));
    }
}
