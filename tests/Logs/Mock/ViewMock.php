<?php
namespace Tests\Logs\Mock;

class ViewMock
{
    private static array $coordsAroundResult = [];
    private static int $coordsIdResult = 1;

    public static function setCoordsAroundResult(array $coords): void
    {
        self::$coordsAroundResult = $coords;
    }

    public static function setGetCoordsIdResult(int $id): void
    {
        self::$coordsIdResult = $id;
    }

    public static function get_coords_arround(object $coords, int $perception, $type, string $separator = '_'): array
    {
        return self::$coordsAroundResult;
    }

    public static function get_coords_id(object $coords): int
    {
        return self::$coordsIdResult;
    }

    public static function reset(): void
    {
        self::$coordsAroundResult = [];
        self::$coordsIdResult = 1;
    }
}