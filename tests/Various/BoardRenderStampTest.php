<?php

namespace Tests\Various;

use App\Service\Map\BoardRenderStamp;
use PHPUnit\Framework\TestCase;

/**
 * A cached board older than the renderer is redrawn.
 *
 * The board is cached whole, per viewer, with no expiry — the file exists,
 * so it is served. Changing how the board is drawn therefore used to need a
 * console command after every deployment, and a player who did not move kept
 * a board drawn by code that no longer existed.
 */
class BoardRenderStampTest extends TestCase
{
    private string $cached;

    protected function setUp(): void
    {
        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
        }

        $this->cached = sys_get_temp_dir() . '/gm_board_' . getmypid() . '.svg';
        BoardRenderStamp::forget();
    }

    protected function tearDown(): void
    {
        @unlink($this->cached);
        BoardRenderStamp::forget();
    }

    private function cachedAt(int $when): void
    {
        file_put_contents($this->cached, '<svg/>');
        touch($this->cached, $when);
        clearstatcache(true, $this->cached);
    }

    /** The renderer's own sources are found, or nothing could ever be stale. */
    public function testTheRendererHasAKnownAge(): void
    {
        $this->assertGreaterThan(0, BoardRenderStamp::renderedAt());
    }

    public function testABoardOlderThanTheRendererIsStale(): void
    {
        $this->cachedAt(BoardRenderStamp::renderedAt() - 3600);

        $this->assertTrue(BoardRenderStamp::isStale($this->cached));
    }

    public function testABoardDrawnSinceIsKept(): void
    {
        $this->cachedAt(BoardRenderStamp::renderedAt() + 60);

        $this->assertFalse(BoardRenderStamp::isStale($this->cached));
    }

    /** No cache at all is the plain case, and still means "draw it". */
    public function testAMissingBoardIsStale(): void
    {
        $this->assertTrue(BoardRenderStamp::isStale($this->cached));
    }
}
