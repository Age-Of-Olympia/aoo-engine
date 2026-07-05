<?php

namespace App\Simulation;

/**
 * Request-scoped recorder for the dice rolled during a single simulation run, so
 * the workbench can show exactly what was rolled. Off by default (zero overhead
 * on real play); the simulator turns it on around the one detailed sample run.
 */
final class DiceLog
{
    private static bool $recording = false;

    /** @var list<array{sides: int, faces: list<int>}> */
    private static array $rolls = [];

    public static function start(): void
    {
        self::$recording = true;
        self::$rolls = [];
    }

    /**
     * Stop recording and return what was rolled, in roll order.
     *
     * @return list<array{sides: int, faces: list<int>}>
     */
    public static function stop(): array
    {
        self::$recording = false;
        $rolls = self::$rolls;
        self::$rolls = [];

        return $rolls;
    }

    /**
     * @param list<int> $faces
     */
    public static function record(int $sides, array $faces): void
    {
        if (self::$recording) {
            self::$rolls[] = ['sides' => $sides, 'faces' => $faces];
        }
    }
}
