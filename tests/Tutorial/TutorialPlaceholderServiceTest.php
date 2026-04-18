<?php

namespace Tests\Tutorial;

use App\Tutorial\TutorialPlaceholderService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Foundation test for D4 Phase A. Two purposes:
 *
 *  1. Pin three behavioural contracts of TutorialPlaceholderService that
 *     do NOT depend on Classes\Player or filesystem race-JSON lookups:
 *       - text without placeholders is returned unchanged;
 *       - unknown placeholder names are kept in braces (preserves the
 *         legacy admin-panel "literal" use case);
 *       - the per-instance cache is consulted before the match block
 *         (means a pre-populated value short-circuits the DB/FS path).
 *
 *  2. Establish the "Player stub via reflection + private-property
 *     priming" pattern that Phase B + C tests will reuse to test
 *     placeholder branches that DO touch player data — without
 *     introducing a Tests\Player\Mock\TestDatabase dependency to the
 *     tutorial suite.
 */
class TutorialPlaceholderServiceTest extends TestCase
{
    /**
     * Build a TutorialPlaceholderService without invoking its
     * constructor (which requires a real Classes\Player). Returns the
     * service plus a reflection handle on its private cache so tests
     * can prime it.
     *
     * @return array{0: TutorialPlaceholderService, 1: ReflectionProperty}
     */
    private function makeServiceWithPrimedCache(): array
    {
        $service = (new ReflectionClass(TutorialPlaceholderService::class))
            ->newInstanceWithoutConstructor();
        $cacheProp = new ReflectionProperty($service, 'cachedValues');

        return [$service, $cacheProp];
    }

    #[Group('tutorial-placeholders')]
    public function testReturnsTextUnchangedWhenNoPlaceholders(): void
    {
        [$service, $cache] = $this->makeServiceWithPrimedCache();
        $cache->setValue($service, []);

        $this->assertSame(
            'Bienvenue dans le tutoriel.',
            $service->replacePlaceholders('Bienvenue dans le tutoriel.')
        );
    }

    #[Group('tutorial-placeholders')]
    public function testKeepsUnknownPlaceholderInBraces(): void
    {
        // Unknown placeholders fall through the match()'s default arm,
        // which returns the raw "{name}" so admin-authored text stays
        // legible rather than turning into empty strings.
        [$service, $cache] = $this->makeServiceWithPrimedCache();
        $cache->setValue($service, []);

        $this->assertSame(
            'Vous avez {totally_invented} jetons.',
            $service->replacePlaceholders('Vous avez {totally_invented} jetons.')
        );
    }

    #[Group('tutorial-placeholders')]
    public function testReplacesKnownPlaceholderFromCache(): void
    {
        // Pre-priming the cache short-circuits the data lookup branch
        // — useful for tests that want to assert placeholder-substitution
        // mechanics independently of player loading.
        [$service, $cache] = $this->makeServiceWithPrimedCache();
        $cache->setValue($service, ['max_mvt' => '4']);

        $this->assertSame(
            'Vous avez 4 mouvements par tour.',
            $service->replacePlaceholders('Vous avez {max_mvt} mouvements par tour.')
        );
    }

    #[Group('tutorial-placeholders')]
    public function testReplacesMultiplePlaceholdersInSingleString(): void
    {
        [$service, $cache] = $this->makeServiceWithPrimedCache();
        $cache->setValue($service, [
            'max_mvt'     => '5',
            'player_name' => 'Aragorn',
            'race'        => 'Elfe',
        ]);

        $this->assertSame(
            'Aragorn (Elfe) a 5 MVT.',
            $service->replacePlaceholders('{player_name} ({race}) a {max_mvt} MVT.')
        );
    }
}
