<?php

namespace Tests\Various;

use PHPUnit\Framework\TestCase;

/**
 * A character's cell is written in one place, CharacterMoveService: biome
 * entry and exit hook in there, and a move written anywhere else would
 * cross a biome's edge unseen. Objects move through EntityLocationService.
 */
class CharacterMoveGuardTest extends TestCase
{
    /** The writers allowed, relative to the project root */
    private const WRITERS = ['src/Service/Map/CharacterMoveService.php', 'src/Service/Map/EntityLocationService.php'];

    /** Where the game's code lives; migrations rewrite tables, they move no one */
    private const SCANNED = ['src', 'Classes', 'api', 'admin', 'scripts'];

    /** An UPDATE of players SETTING coords_id to anything but NULL (which takes an object off the board), before its WHERE */
    private const WRITE = '/UPDATE\s+`?players`?\s+SET\b(?:(?!\bWHERE\b)[^;])*?\bcoords_id\s*=(?!\s*NULL\b)/is';

    public function testThePatternCatchesAnyValueButNull(): void
    {
        foreach ([
            "'UPDATE players SET coords_id = ? WHERE id = ?'",
            "'UPDATE players SET coords_id = ' . \$id . ' WHERE id = ?'",
            "\"UPDATE `players`\n SET name = ?,\n coords_id = :cell WHERE id = :id\"",
            "'UPDATE players SET coords_id = (SELECT id FROM coords LIMIT 1)'",
        ] as $write) {
            $this->assertMatchesRegularExpression(self::WRITE, $write);
        }
        foreach ([
            "'UPDATE players SET coords_id = NULL, holder_id = ? WHERE id = ? AND coords_id = ?'",
            "'UPDATE players SET name = ? WHERE coords_id = ?'",
        ] as $notAWrite) {
            $this->assertDoesNotMatchRegularExpression(self::WRITE, $notAWrite);
        }
    }

    public function testNothingButTheMoveServiceWritesACharacterCell(): void
    {
        $root = dirname(__DIR__, 2);
        $files = glob($root . '/*.php') ?: [];
        foreach (self::SCANNED as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() === 'php' && !str_contains($file->getPathname(), '/src/Migrations/')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $writers = [];
        foreach ($files as $file) {
            $relative = substr($file, strlen($root) + 1);
            if (!in_array($relative, self::WRITERS, true) && preg_match(self::WRITE, (string) file_get_contents($file))) {
                $writers[] = $relative;
            }
        }

        $this->assertSame([], $writers, 'move characters through CharacterMoveService::move()');
    }
}
