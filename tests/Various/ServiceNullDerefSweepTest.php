<?php

namespace Tests\Various;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Service-layer follow-up to MR !437 (views+controllers null-deref sweep).
 * The Explore agent found five more critical sites where json()->decode()
 * results or other possibly-null values are dereffed without a guard.
 * Plus three cascading `$x->foo ?? fallback` sites my own MR !437 left
 * in api/tutorial/{complete,cancel,skip}.php — the `->foo` still throws
 * TypeError before `??` kicks in.
 */
class ServiceNullDerefSweepTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /**
     * Sites where the original unsafe `$x->foo` fragment must be gone
     * from the source (replaced by a null-safe form or hoisted into a
     * branch that itself null-checks).
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function removedDerefSites(): array
    {
        return [
            // api/tutorial/{complete,cancel,skip}.php — `$factionJson->respawnPlan ??`
            // still throws on null before `??` fires. Fix rewrites
            // `->respawnPlan` to `?->respawnPlan`, so the unsafe
            // fragment (without `?`) disappears.
            ['api/tutorial/complete.php', 'factionJson->respawnPlan', 'complete.php respawnPlan'],
            ['api/tutorial/cancel.php',   'factionJson->respawnPlan', 'cancel.php respawnPlan'],
            ['api/tutorial/skip.php',     'factionJson->respawnPlan', 'skip.php respawnPlan'],
        ];
    }

    #[DataProvider('removedDerefSites')]
    #[Group('service-null-deref-sweep')]
    public function testUnsafeNeedleIsRemoved(string $path, string $needle, string $label): void
    {
        $source = (string) file_get_contents(self::ROOT . '/' . $path);

        $this->assertStringNotContainsString(
            $needle,
            $source,
            "{$label} ({$path}): unsafe fragment `{$needle}` is still present. "
            . 'Replace with `$x?->foo` (null-safe navigation).'
        );
    }

    /**
     * Sites where the deref stays in the source (inside a loop body or
     * similar) but must now sit under an explicit null/empty check.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function guardedDerefSites(): array
    {
        return [
            // ForumCookieService decodes postJson and topJson; the
            // derefs remain in-source but each now sits under a
            // `if (!$postJson) return;` / `if (!$topJson) return;` guard.
            [
                'src/Service/ForumCookieService.php',
                '$postJson->top_id',
                '/if\s*\(\s*!\s*\$postJson\s*\)/',
                'ForumCookie.giveCookie top_id',
            ],
            [
                'src/Service/ForumCookieService.php',
                '$topJson->forum_id ==',
                '/if\s*\(\s*!\s*\$topJson\s*\)/',
                'ForumCookie.giveCookie forum_id',
            ],

            // ForumService.GetAllUnreadTopics iterates through decoded
            // JSON chains three levels deep (cat → forum → topic). Each
            // decode call must be null-checked.
            [
                'src/Service/ForumService.php',
                'foreach ($catJson->forums',
                '/if\s*\(\s*!\s*\$catJson\b|continue\s*;/',
                'ForumService cat->forums loop',
            ],
            [
                'src/Service/ForumService.php',
                'foreach ($forJson->topics',
                '/if\s*\(\s*!\s*\$forJson\b|continue\s*;/',
                'ForumService forum->topics loop',
            ],
            [
                'src/Service/ForumService.php',
                '$topJson->last->time',
                '/if\s*\(\s*!\s*\$topJson\b|continue\s*;/',
                'ForumService topic->last->time',
            ],

            // InventoryService.useItem reads $raceJson->spells via
            // in_array; $raceJson must be non-null first.
            [
                'src/Service/InventoryService.php',
                '$raceJson->spells',
                '/if\s*\(\s*!\s*\$raceJson\b|\$raceJson\s*&&/',
                'InventoryService raceJson->spells',
            ],

            // ResourceService.createExhaustArray iterates $planJson->biomes
            // unconditionally — the sibling createRegrowArray has the guard.
            [
                'src/Service/ResourceService.php',
                'foreach($planJson->biomes',
                '/if\s*\(\s*!?\s*isset\s*\(\s*\$planJson->biomes\s*\)|if\s*\(\s*!\s*\$planJson\b/',
                'ResourceService createExhaustArray loop',
            ],
        ];
    }

    #[DataProvider('guardedDerefSites')]
    #[Group('service-null-deref-sweep')]
    public function testDerefIsGuarded(string $path, string $derefNeedle, string $guardRegex, string $label): void
    {
        $source = (string) file_get_contents(self::ROOT . '/' . $path);

        $pos = strpos($source, $derefNeedle);
        $this->assertNotFalse(
            $pos,
            "{$label}: deref `{$derefNeedle}` not found — refactored? "
            . 'Move this row to removedDerefSites() or update the needle.'
        );

        $window = substr($source, max(0, $pos - 400), min(400, $pos));

        $this->assertMatchesRegularExpression(
            $guardRegex,
            $window,
            "{$label} ({$path}): deref `{$derefNeedle}` lacks the expected "
            . "guard matching `{$guardRegex}` in the ~10 preceding lines. "
            . 'Add an explicit null check.'
        );
    }
}
