<?php
namespace Tests\Logs\Mock;

class ViewMock
{
    private static int $coordsIdResult = 1;

    public static function setGetCoordsIdResult(int $id): void
    {
        self::$coordsIdResult = $id;
    }

    public static function get_coords_id(object $coords): int
    {
        return self::$coordsIdResult;
    }

    public static function reset(): void
    {
        self::$coordsIdResult = 1;
    }
}