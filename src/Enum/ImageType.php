<?php

namespace App\Enum;

enum ImageType: string
{
    case PORTRAIT = 'portrait';
    case AVATAR   = 'avatar';

    /**
     * Returns the width/height for this image type.
     */
    public function dimensions(): array
    {
        return match ($this) {
            // 330 : la totalité du stock (143 portraits) et l'affichage en
            // jeu (height=330) — l'ancien 320 était une coquille qui
            // écrasait les portraits envoyés par l'API ; la miniature 50×79
            // ne colle d'ailleurs qu'au ratio 210/330.
            self::PORTRAIT => [210, 330],
            self::AVATAR   => [50, 50],
        };
    }

    /**
     * Returns the smaller (mini) dimensions if relevant,
     * or null if there's no mini size.
     */
    public function miniDimensions(): ?array
    {
        return match ($this) {
            self::PORTRAIT => [50, 79],
            self::AVATAR   => null,
        };
    }

    /**
     * Returns the directory (relative to __DIR__) where images should go.
     */
    public function uploadDirectory(string $race, string $rootDir): string
    {
        return match ($this) {
            self::PORTRAIT => $rootDir . "/img/portraits/" . $race,
            self::AVATAR   => $rootDir . "/img/avatars/" . $race,
        };
    }

    /**
     * Builds the filename for this image type (no mini).
     */
    public function buildFilename(int $number): string
    {
        // e.g. "10.jpeg"
        return "{$number}.jpeg";
    }

    /**
     * Builds the mini version filename.
     */
    public function buildMiniFilename(int $number): string
    {
        // e.g. "10_mini.jpeg" (only relevant for PORTRAIT)
        return "{$number}_mini.jpeg";
    }
}
