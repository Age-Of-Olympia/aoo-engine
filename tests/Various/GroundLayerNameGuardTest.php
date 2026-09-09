<?php

namespace Tests\Various;

use App\Service\Map\GroundLayerService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GroundLayerNameGuardTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'sql injection' => ['walls; DROP TABLE players'],
            'space' => ['map walls'],
            'quote' => ["walls'"],
            'leading digit' => ['1walls'],
            'empty' => [''],
            'uppercase' => ['Walls'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsANonTokenTileNameBeforeTouchingTheDatabase(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Throws on the guard, before any Db access, so no database is needed.
        (new GroundLayerService())->hasAt($name, 1);
    }
}
