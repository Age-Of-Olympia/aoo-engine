<?php

namespace Tests\Various;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Cross-file audit follow-up to MRs !435 / !436. `json()->decode(...)`
 * returns null/false when the requested key (race, faction, plan,
 * forum cache, item, etc.) is missing. Multiple callers in the views +
 * page controllers derefed the result unconditionally and fataled.
 *
 * Each case pins a single site: near the first deref of the decoded
 * value we must see either a null check (`if ($x`, `!empty(`, `=== null`,
 * `!== null`) OR the null-safe `?->` operator, OR the deref must be
 * gone entirely (replaced by a fallback literal).
 */
class JsonDecodeNullDerefTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /**
     * Sites where the fix rewrites the deref to a null-safe form
     * (`$x?->foo` or `$x->foo ?? fallback`), making the original
     * unsafe fragment disappear.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function rewrittenDerefSites(): array
    {
        return [
            ['src/View/FactionView.php',          '$raceJson->name',                    'FactionView player race'],
            ['pnjs.php',                          '$raceJson->name',                    'pnjs.php race name'],
            ['src/View/Forum/MissiveView.php',    '$raceJson->bgColor',                 'MissiveView dest cartouche bg'],
            ['src/View/InfosView.php',            '$lastPostJson->general->time',       'InfosView lastPost time'],
        ];
    }

    #[DataProvider('rewrittenDerefSites')]
    #[Group('json-decode-null-deref')]
    public function testRewrittenDerefNeedleIsRemoved(string $path, string $needle, string $label): void
    {
        $source = (string) file_get_contents(self::ROOT . '/' . $path);

        $this->assertStringNotContainsString(
            $needle,
            $source,
            "{$label} ({$path}): unsafe fragment `{$needle}` still present. "
            . 'Expected rewrite to `\$x?->foo` / `\$x->foo ?? fallback`.'
        );
    }

    /**
     * Sites where the fix hoists the deref inside a null-check
     * branch — the needle remains in the file but is now reachable
     * only when the decoded value is non-null. For these sites we
     * pin the presence of the guard rather than the absence of the
     * deref.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function guardedDerefSites(): array
    {
        return [
            // [path, deref needle (for locating the site), required guard regex, label]
            [
                'src/View/Forum/MissiveView.php',
                'Forum::add_dest($player, $raceJson->animateur',
                '/if\s*\(\s*\$raceJson\s*!==?\s*null\b/',
                'MissiveView.add_dest animateur',
            ],
            [
                'src/View/Merchant/SpellsView.php',
                'foreach ($raceJson->spells',
                '/if\s*\(\s*!\s*\$raceJson\s*\|\||\$raceJson\s*&&/',
                'SpellsView spells loop',
            ],
            [
                'register.php',
                '$raceJson->animateur]',
                '/if\s*\(\s*\$raceJson\s*&&/',
                'register.php animateur notify',
            ],
            [
                'src/View/Inventory/CraftView.php',
                '$return->data->mini',
                '/if\s*\(\s*!\s*\$return->data\s*\)/',
                'CraftView item->mini assign',
            ],
        ];
    }

    #[DataProvider('guardedDerefSites')]
    #[Group('json-decode-null-deref')]
    public function testDerefIsGuardedByExplicitCheck(
        string $path,
        string $derefNeedle,
        string $guardRegex,
        string $label
    ): void {
        $source = (string) file_get_contents(self::ROOT . '/' . $path);

        $derefPos = strpos($source, $derefNeedle);
        $this->assertNotFalse(
            $derefPos,
            "{$label}: deref site `{$derefNeedle}` not found — has it been refactored? "
            . 'Either update the needle or move this row to rewrittenDerefSites().'
        );

        // 400-char window immediately before the deref site.
        $window = substr($source, max(0, $derefPos - 400), min(400, $derefPos));

        $this->assertMatchesRegularExpression(
            $guardRegex,
            $window,
            "{$label} ({$path}): deref `{$derefNeedle}` is not preceded by the "
            . "expected guard matching `{$guardRegex}` within the previous "
            . '~10 lines. Add an explicit null check for the decoded value.'
        );
    }
}
