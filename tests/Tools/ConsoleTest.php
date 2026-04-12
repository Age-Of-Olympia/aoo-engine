<?php
namespace Tests\Tools;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use Classes\Command;
class ConsoleTest extends TestCase
{

    #[Group('console-parsing')]
    public function testParseEnvVariable(): void
    {
        $result = Command::ParseInput('[1,"_",10]');
        $this->assertCount(10, $result);
        $this->assertEquals(5, $result[4]);
    }
}