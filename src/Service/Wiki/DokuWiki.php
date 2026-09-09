<?php

namespace App\Service\Wiki;

/** DokuWiki table syntax: what a cell may contain. */
final class DokuWiki
{
    public static function cell(string $value): string
    {
        return trim(str_replace(['|', "\r", "\n"], ['∣', ' ', ' '], strip_tags($value)));
    }
}
