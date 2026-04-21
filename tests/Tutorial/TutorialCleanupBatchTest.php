<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Cleanup invariants left over from the debug-removal sweeps
 * (commits 553072e, 6c492b4, be643d6). These guard against the
 * stragglers re-appearing.
 */
class TutorialCleanupBatchTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function emptyElseFiles(): array
    {
        return [
            ['api/tutorial/cancel.php',   "} else {\n    }"],
            ['api/tutorial/complete.php', "} else {\n    }"],
            ['api/tutorial/skip.php',     "} else {\n    }"],
        ];
    }

    /**
     * @param string $path  Repo-relative source path
     * @param string $needle Empty-block fragment to scan for
     */
    #[Group('tutorial-cleanup-batch')]
    #[\PHPUnit\Framework\Attributes\DataProvider('emptyElseFiles')]
    public function testNoEmptyElseBlocks(string $path, string $needle): void
    {
        $source = (string) file_get_contents(self::ROOT . '/' . $path);

        $this->assertStringNotContainsString(
            $needle,
            $source,
            "{$path} still contains an empty `} else { }` block left over "
            . 'from the debug-cleanup sweep — drop it or restore its body.'
        );
    }

    #[Group('tutorial-cleanup-batch')]
    public function testCancelHasNoEmptyIfAfterObGetClean(): void
    {
        // The shape was:
        //   if (!empty($buffered)) {
        //   }
        // Dead block — drop it (the surrounding ob_start() / ob_get_clean()
        // dance still works without the inner check).
        $source = (string) file_get_contents(self::ROOT . '/api/tutorial/cancel.php');

        $this->assertStringNotContainsString(
            "if (!empty(\$buffered)) {\n        }",
            $source,
            'api/tutorial/cancel.php still contains an empty `if (!empty($buffered))` '
            . 'block from the debug-cleanup sweep — drop it.'
        );
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function debugLogFiles(): array
    {
        return [
            ['src/Tutorial/Steps/AbstractStep.php'],
            ['src/View/MenuView.php'],
        ];
    }

    /**
     * Files cleaned in commits 553072e/6c492b4 must stay debug-log free.
     */
    #[Group('tutorial-cleanup-batch')]
    #[\PHPUnit\Framework\Attributes\DataProvider('debugLogFiles')]
    public function testNoBracketedDebugErrorLogs(string $path): void
    {
        $source = (string) file_get_contents(self::ROOT . '/' . $path);

        // Match the `error_log("[ClassName] …")` shape the cleanup sweep
        // explicitly retired. Real error logs (e.g. exception handlers)
        // do not use the bracketed-class-tag prefix.
        $count = preg_match_all('/error_log\s*\(\s*"\[[A-Z][A-Za-z]+\]/', $source);

        $this->assertSame(
            0,
            $count,
            "{$path} contains {$count} `error_log(\"[ClassName] …\")` debug "
            . 'line(s) that the debug-cleanup sweep retired. Drop them.'
        );
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function cacheBusterFiles(): array
    {
        return [
            ['tiled.php',                'tiled.js'],
            ['src/View/MainView.php',    'view.js'],
        ];
    }

    /**
     * Bump-or-newer check: the v=YYYYMMDD parameter for the named JS
     * asset must be at or above the date the tutorial-refactoring
     * branch series is shipping with (2026-04-21). Catches a stale
     * cache-buster after the JS file was edited.
     */
    #[Group('tutorial-cleanup-batch')]
    #[\PHPUnit\Framework\Attributes\DataProvider('cacheBusterFiles')]
    public function testJsCacheBusterIsFresh(string $path, string $jsAsset): void
    {
        $source = (string) file_get_contents(self::ROOT . '/' . $path);

        $matched = preg_match(
            '/' . preg_quote($jsAsset, '/') . '\?v=(\d{8})/',
            $source,
            $m
        );

        $this->assertSame(1, $matched, "Could not find {$jsAsset}?v=YYYYMMDD in {$path}");
        $this->assertGreaterThanOrEqual(
            20260421,
            (int) $m[1],
            "{$jsAsset} cache-buster in {$path} is stale ({$m[1]}). Bump to "
            . 'today or later when the file changes — otherwise users keep '
            . 'the old cached copy.'
        );
    }

    #[Group('tutorial-cleanup-batch')]
    public function testStaleDisplayIdRawSqlIsRemoved(): void
    {
        // db/updates/20251125_add_display_id_system.sql added a column
        // that Version20251127000000 also creates. The two diverged on
        // the NULL/DEFAULT, so keeping both is a footgun. The migration
        // is the source of truth.
        $this->assertFileDoesNotExist(
            self::ROOT . '/db/updates/20251125_add_display_id_system.sql',
            'db/updates/20251125_add_display_id_system.sql is superseded by '
            . 'Version20251127000000_CreateCompleteTutorialSystem (which adds '
            . 'display_id with the canonical defaults). Delete the raw SQL '
            . 'so the two cannot drift.'
        );
    }
}
