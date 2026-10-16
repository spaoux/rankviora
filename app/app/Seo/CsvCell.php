<?php

namespace App\Seo;

final class CsvCell
{
    public static function safe(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if (preg_match('/^[\x00-\x20\x{FEFF}\x{200B}]*[=+\-@]/u', $value) || preg_match('/^[\t\r\n]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
