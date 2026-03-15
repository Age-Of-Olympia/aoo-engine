<?php
namespace Tests\Various;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use Classes\Player;

class VariousTest extends TestCase
{

    #[Group('playerxp-pi_limits')]
    public function testParseEnvVariable(): void
    {
        // Assert
        $this->assertEquals(-60, Player::CapPI(900,-60,10000));
        $this->assertEquals(-60, Player::CapPI(9999,-60,10000));
        $this->assertEquals(-60, Player::CapPI(10000,-60,10000));
        $this->assertEquals(-40, Player::CapPI(1020,-60,10000));
        $this->assertEquals(-50, Player::CapPI(1040,-90,10000));
        $this->assertEquals(0, Player::CapPI(1220,-60,10000));

        $this->assertEquals(60, Player::CapPI(900,60,10000));
        $this->assertEquals(20, Player::CapPI(9980,60,10000));
        $this->assertEquals(1, Player::CapPI(9999,60,10000));
        $this->assertEquals(0, Player::CapPI(10000,60,10000));
        $this->assertEquals(0, Player::CapPI(11000,60,10000));
    }
}