<?php

namespace Tests\Various;

use PHPUnit\Framework\TestCase;

/**
 * BoardChanges is the single path for "the board changed": a second path
 * (a stray unlink of a cached board, a direct board_views query) would be
 * skipped by whatever plugs into BoardChanges::notify() later (websockets).
 */
class BoardChangesGuardTest extends TestCase
{
    private const ROOTS = ['src', 'Classes', 'scripts', 'api', 'admin', 'config'];
    private const ALLOWED = ['src/Service/Map/BoardChanges.php'];

    public function testOnlyBoardChangesTouchesBoardsAndTheirAreas(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach ($this->phpFiles($root) as $relative) {
            if (in_array($relative, self::ALLOWED, true) || str_starts_with($relative, 'src/Migrations/')) {
                continue;
            }

            foreach (file($root . '/' . $relative) ?: [] as $n => $line) {
                $dropsABoard = preg_match('/\b(unlink|glob|cachePath)\s*\(/', $line)
                    && str_contains($line, '.svg')
                    && (str_contains($line, 'players') || str_contains($line, 'cachePath'));

                if ($dropsABoard || str_contains($line, 'board_views')) {
                    $offenders[] = $relative . ':' . ($n + 1) . ' ' . trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, 'go through App\Service\Map\BoardChanges');
    }

    /** @return list<string> paths relative to the project root */
    private function phpFiles(string $root): array
    {
        $files = glob($root . '/*.php') ?: [];
        foreach (self::ROOTS as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return array_map(fn (string $path): string => substr($path, strlen($root) + 1), $files);
    }
}
