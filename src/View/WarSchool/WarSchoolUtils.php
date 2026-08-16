<?php

namespace App\View\WarSchool;

/**
 * Shared bits of the war school views. A skill row takes its colour from
 * the second half of its category (`magic-off`, `magic-support`…), so a
 * new sub-category needs a case here or it renders in black.
 */
class WarSchoolUtils
{
    public static function getColor(?string $category): string
    {
        if (empty($category)) {
            return '#000000';
        }

        $parts = explode('-', $category);
        $subCategory = $parts[1] ?? '';

        switch ($subCategory) {
            case 'off':
                return '#c0392b';
            case 'support':
                return '#27ae60';
            case 'buff':
                return '#2980b9';
            case 'curse':
                return '#8e44ad';
            default:
                return '#000000';
        }
    }
}
