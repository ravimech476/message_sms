<?php

namespace App\Support;

class Str
{
    /**
     * Masks a portion of a string with a repeated character.
     *
     * @param  string  $string
     * @param  string  $character
     * @param  int  $index
     * @param  int|null  $length
     * @param  string  $encoding
     *
     * @return string
     */
    public static function mask(string $string): string
    {
        $length = strlen($string);
        $visibleCount = (int) round($length / 4);
        $hiddenCount = $length - ($visibleCount * 2);

        return substr($string, 0, $visibleCount) . str_repeat('*', $hiddenCount) . substr($string, ($visibleCount * -1), $visibleCount);
    }
}
