<?php

namespace Tests\Various;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Every `use App\…` / `use Classes\…` names something that exists.
 *
 * A stale import is silent: PHP resolves it only when the class is touched, so
 * a page keeps loading until the one line that needs it runs — then a fatal,
 * in production, on a file no test opens. Moving a class between folders is
 * exactly what leaves them behind.
 */
#[Group('entities-baseline')]
class ImportsResolveTest extends TestCase
{
    /** Folders that hold no first-party code, matched on the RELATIVE path:
     *  an absolute one carries `/var/www/html` and skips the whole project. */
    private const SKIPPED = '#^(vendor|node_modules|tmp|var|\.git)/#';

    public function testEveryFirstPartyImportResolves(): void
    {
        $root = dirname(__DIR__, 2);
        $broken = [];
        $scanned = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $relative = substr($path, strlen($root) + 1);

            if (preg_match(self::SKIPPED, $relative)) {
                continue;
            }

            $scanned++;

            foreach (file($path) as $number => $line) {
                $name = self::importedName($line);

                if ($name === null || self::exists($name)) {
                    continue;
                }

                $broken[] = $relative . ':' . ($number + 1) . ' → ' . $name;
            }
        }

        // A filter that matches everything would make this pass on nothing.
        $this->assertGreaterThan(500, $scanned, 'le balayage doit voir le projet');
        $this->assertSame([], $broken, "imports morts :\n" . implode("\n", $broken));
    }

    /** The class an import names, or null when the line is not one. */
    private static function importedName(string $line): ?string
    {
        if (!preg_match('/^use\s+((?:App|Classes)\\\\[A-Za-z0-9_\\\\]+)/', trim($line), $matches)) {
            return null;
        }

        return $matches[1];
    }

    private static function exists(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name);
    }
}
