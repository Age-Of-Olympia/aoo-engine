<?php

namespace Tests\Tutorial;

use App\Tutorial\TutorialMapInstance;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression guard: TutorialMapInstance prefix consistency.
 *
 * createInstance / deleteInstance compute the per-session plan name as
 * `'tut_' . substr($sessionId, 0, 10)` (max 14 chars to leave room for
 * coord suffixes). The deleted `instanceExists()` method drifted to
 * `'tutorial_session_' . substr($sessionId, 0, 13)` and therefore
 * always returned false. The method had no callers; it was deleted.
 *
 * If a future author reintroduces an `instanceExists` (or any sibling
 * lookup), it must use the same `tut_` prefix as create/delete.
 */
class TutorialMapInstancePrefixTest extends TestCase
{
    private const SOURCE_PATH =
        __DIR__ . '/../../src/Tutorial/TutorialMapInstance.php';

    #[Group('tutorial-map-instance-prefix')]
    public function testInstanceExistsMethodIsRemoved(): void
    {
        $this->assertFalse(
            (new ReflectionClass(TutorialMapInstance::class))->hasMethod('instanceExists'),
            'TutorialMapInstance::instanceExists() was permanently broken — '
            . 'it used the "tutorial_session_" prefix while create/delete '
            . 'use "tut_". The method had no callers and was deleted; do '
            . 'not reintroduce it without using the same prefix as createInstance.'
        );
    }

    #[Group('tutorial-map-instance-prefix')]
    public function testWrongPrefixDoesNotReappear(): void
    {
        $source = (string) file_get_contents(self::SOURCE_PATH);

        $this->assertDoesNotMatchRegularExpression(
            "/'tutorial_session_'\\s*\\.\\s*substr\\s*\\(\\s*\\\$sessionId/i",
            $source,
            'TutorialMapInstance must not reintroduce the broken '
            . '"tutorial_session_" plan-name prefix — createInstance and '
            . 'deleteInstance use "tut_" + substr(sessionId, 0, 10).'
        );
    }
}
