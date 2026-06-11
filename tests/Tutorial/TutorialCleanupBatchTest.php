<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class TutorialCleanupBatchTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    #[Group('tutorial-cleanup-batch')]
    public function testStaleDisplayIdRawSqlIsRemoved(): void
    {
        $this->assertFileDoesNotExist(
            self::ROOT . '/db/updates/20251125_add_display_id_system.sql',
            'db/updates/20251125_add_display_id_system.sql is superseded by '
            . 'Version20251127000000_CreateCompleteTutorialSystem (which adds '
            . 'display_id with the canonical defaults). Delete the raw SQL '
            . 'so the two cannot drift.'
        );
    }
}
