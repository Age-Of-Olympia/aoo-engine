<?php

namespace Tests\View\Classement;

use App\View\Classement\RankingCache;
use PHPUnit\Framework\TestCase;

class RankingCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!defined('CACHED_CLASSEMENTS')) {
            define('CACHED_CLASSEMENTS', true);
        }
        $this->dir = sys_get_temp_dir() . '/ranking-cache-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testTheSecondCallIsServedFromTheFileUntilTheViewChanges(): void
    {
        $path = $this->dir . '/foi.html';
        $source = $this->dir . '/FoiView.php';
        touch($source, time() - 100);
        $renders = 0;
        $render = static function () use (&$renders): void {
            $renders++;
            echo 'classement';
        };

        ob_start();
        RankingCache::serve($path, $source, $render);
        RankingCache::serve($path, $source, $render);
        $out = ob_get_clean();

        $this->assertSame('classementclassement', $out);
        $this->assertSame(1, $renders, 'the second call reads the file');

        touch($source, time() + 100);
        ob_start();
        RankingCache::serve($path, $source, $render);
        ob_end_clean();

        $this->assertSame(2, $renders, 'a newer view invalidates the file');
    }
}
