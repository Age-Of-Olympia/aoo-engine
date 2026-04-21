<?php

namespace Tests\Various;

use Classes\Forum;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard: Forum::add_dest must not TypeError on an unknown
 * string dest name.
 *
 * Before the PlayerFactory::legacyByName migration, the legacy path
 * returned `Player|false`. The current path returns `?Player`. Forum::
 * add_dest unconditionally dereferences the lookup result via
 * `$dest->get_data(false)` immediately after the assignment, so any
 * caller passing a misspelled or previously-deleted recipient name
 * now hits a TypeError (Call to member function on null) instead of
 * the graceful error-string path.
 *
 * Contract: when `$dest` is a string that does not resolve to a real
 * player, `add_dest` returns an error string starting with "error "
 * (matches the other error returns from the same method).
 */
class ForumAddDestNullNameTest extends TestCase
{
    #[Group('forum-add-dest-null')]
    public function testAddDestReturnsErrorStringForUnknownName(): void
    {
        $this->bootstrapOrSkip();

        $senderStub = new \stdClass();
        $topJson = (object) ['name' => 'characterization-test-topic'];
        $unknownName = 'no-such-player-' . bin2hex(random_bytes(6));

        // Pre-populate destTbl so the method does NOT attempt to query
        // the forum missives table — it should bail out on the null
        // dest check well before that code path is reached.
        $result = Forum::add_dest($senderStub, $unknownName, $topJson, []);

        $this->assertIsString(
            $result,
            'add_dest must return an error string (not null/void/throw) for unknown recipient names'
        );
        $this->assertStringStartsWith(
            'error ',
            $result,
            'error string must match the sibling error-return shapes (error already in dest, error dest forbidden, …)'
        );
    }

    /**
     * Bootstrap legacy globals + legacy DB connection so the call chain
     * Forum::add_dest → PlayerFactory::legacyByName → Player::get_player_by_name
     * has a live $GLOBALS['link']. Same shape as PlayerFactoryTest.
     */
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
