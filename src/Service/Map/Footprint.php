<?php

namespace App\Service\Map;

use InvalidArgumentException;

/**
 * A scenery cut-out: which cells a figure occupies, and each piece's role.
 *
 * Offsets are relative to the first piece; construction normalises them so
 * callers can rely on it.
 */
final class Footprint
{
    /** @var array<int, array{0:int,1:int}> */
    private array $offsets;

    private int $w;
    private int $h;

    /** @var array<int, string> */
    private array $roles;

    /**
     * @param array<int, array{0:int,1:int}> $offsets
     * @param array<int, string> $roles
     */
    private function __construct(array $offsets, int $w, int $h, array $roles)
    {
        $this->offsets = $offsets;
        $this->w = $w;
        $this->h = $h;
        $this->roles = $roles;
    }

    /**
     * Box deduced from the pieces, so the figure always fills it.
     *
     * @param array<int, array{0:int,1:int}> $offsets
     * @param array<int, string> $roles
     *
     * @throws InvalidArgumentException on an empty figure
     */
    public static function fromOffsets(array $offsets, array $roles = []): self
    {
        $offsets = self::anchored($offsets);

        $xs = array_column($offsets, 0);
        $ys = array_column($offsets, 1);

        return new self(
            $offsets,
            max($xs) - min($xs) + 1,
            max($ys) - min($ys) + 1,
            self::cleanRoles($roles)
        );
    }

    /**
     * Box given from outside — a whole-object image, a declaration — so it may
     * exceed the pieces. That gap is what makes a figure holed.
     *
     * @param array<int, array{0:int,1:int}> $offsets
     * @param array<int, string> $roles
     *
     * @throws InvalidArgumentException on an empty figure, or a box too small for it
     */
    public static function boxed(int $w, int $h, array $offsets, array $roles = []): self
    {
        $offsets = self::anchored($offsets);

        if ($w < 1 || $h < 1) {
            throw new InvalidArgumentException('A cut-out box is at least one cell.');
        }

        if (count($offsets) > $w * $h) {
            throw new InvalidArgumentException(
                'A ' . $w . '×' . $h . ' box cannot hold ' . count($offsets) . ' pieces.'
            );
        }

        return new self($offsets, $w, $h, self::cleanRoles($roles));
    }

    /** @return array<int, array{0:int,1:int}> piece => offset from the first piece */
    public function offsets(): array
    {
        return $this->offsets;
    }

    /** @return array<int, string> only the pieces someone decided a role for */
    public function roles(): array
    {
        return $this->roles;
    }

    public function width(): int
    {
        return $this->w;
    }

    public function height(): int
    {
        return $this->h;
    }

    /** Occupied cells, not the box's. */
    public function cells(): int
    {
        return count($this->offsets);
    }

    public function isHoled(): bool
    {
        return $this->cells() < $this->w * $this->h;
    }

    public function isSingleCell(): bool
    {
        return $this->cells() < 2;
    }

    public function roleOf(int $piece, string $default): string
    {
        return $this->roles[$piece] ?? $default;
    }

    /**
     * Where every piece lands when $piece sits on (x, y).
     *
     * An unknown piece leaves the figure anchored on its first one.
     *
     * @return array<int, array{0:int,1:int}> piece => absolute position
     */
    public function cellsAround(int $piece, int $x, int $y): array
    {
        [$px, $py] = $this->offsets[$piece] ?? [0, 0];

        $cells = [];

        foreach ($this->offsets as $index => [$dx, $dy]) {
            $cells[$index] = [$x + $dx - $px, $y + $dy - $py];
        }

        return $cells;
    }

    /**
     * Serialisable form, for JSON payloads and templates.
     *
     * @return array{w:int,h:int,cells:int,holed:bool,offsets:array<int,array{0:int,1:int}>,roles:array<int,string>}
     */
    public function toArray(): array
    {
        return [
            'w'       => $this->w,
            'h'       => $this->h,
            'cells'   => $this->cells(),
            'holed'   => $this->isHoled(),
            'offsets' => $this->offsets,
            'roles'   => $this->roles,
        ];
    }

    /**
     * @param array<int, array{0:int,1:int}> $offsets
     * @return array<int, array{0:int,1:int}> shifted so the first piece is (0, 0)
     *
     * @throws InvalidArgumentException
     */
    private static function anchored(array $offsets): array
    {
        if ($offsets === []) {
            throw new InvalidArgumentException('A cut-out without any piece describes nothing.');
        }

        ksort($offsets);

        [$ax, $ay] = $offsets[array_key_first($offsets)];
        $anchored = [];

        foreach ($offsets as $piece => [$dx, $dy]) {
            $anchored[(int) $piece] = [$dx - $ax, $dy - $ay];
        }

        return $anchored;
    }

    /**
     * @param array<int, string> $roles
     * @return array<int, string>
     */
    private static function cleanRoles(array $roles): array
    {
        $clean = [];

        foreach ($roles as $piece => $role) {
            if ($role !== '') {
                $clean[(int) $piece] = $role;
            }
        }

        ksort($clean);

        return $clean;
    }
}
