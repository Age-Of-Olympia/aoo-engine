<?php

namespace Tests\Various;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: the `player addlog` console command must not
 * TypeError on an unknown target name.
 *
 * `add_log()` in Classes/console-commands/playercmd.php was migrated
 * to `PlayerFactory::legacyByName()`, which returns `?Player` — but
 * the callsite unconditionally derefs `$target->get_data()` on the
 * next line. A support engineer typing
 *   > player addlog Orcrist MispelledTaget "note"
 * now crashes the console with a fatal TypeError instead of seeing
 * the legacy "player not found" warning.
 *
 * Contract: `add_log` returns an error string (prefix "<font" to
 * match the sibling error returns in playercmd.php) when the target
 * name does not resolve.
 */
class PlayerCmdAddLogNullNameTest extends TestCase
{
    #[Group('playercmd-addlog-null')]
    public function testAddLogReturnsErrorStringForUnknownTargetName(): void
    {
        $this->bootstrapOrSkip();

        // add_log is a free function defined in playercmd.php (not a
        // method on PlayerCmd). Load the file once so the function is
        // available in the global namespace.
        require_once __DIR__ . '/../../Classes/console-commands/playercmd.php';

        $senderStub = new \stdClass();
        $unknownName = 'no-such-target-' . bin2hex(random_bytes(6));
        $args = [
            0 => 'addlog',           // action
            1 => 'anyMat',           // caller mat — unused on the bail path
            2 => $unknownName,       // target — the one we want null-safe
            3 => 'some log message', // body
        ];

        /** @phpstan-ignore function.notFound (defined in playercmd.php, required above) */
        $result = add_log($args, $senderStub);

        $this->assertIsString(
            $result,
            'add_log must return an error string for an unknown target name, not TypeError/null'
        );
        $this->assertStringContainsString(
            '<font',
            $result,
            'error return shape should match the sibling <font color="red">...</font> errors in add_log'
        );
    }

    private function bootstrapOrSkip(): Connection
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy DB unreachable: ' . $e->getMessage());
        }

        return $link;
    }
}
