<?php

namespace App\Seo;

final class Money
{
    public static function micros(mixed $value): int
    {
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < 0 || $value > 999999) {
            throw new SeoException('invalid_cost');
        }

        return (int) ceil(round($value * 1000000, 4));
    }

    public static function decimal(int $micros): string
    {
        return number_format($micros / 1000000, 6, '.', '');
    }
}
