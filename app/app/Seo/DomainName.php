<?php

namespace App\Seo;

final class DomainName
{
    public static function normalize(string $value): string
    {
        $domain = strtolower(rtrim(trim($value), '.'));
        if (! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $domain)
            || preg_match('/\.(local|localhost|internal|test|invalid|example)$/D', $domain)
            || filter_var($domain, FILTER_VALIDATE_IP)) {
            throw new SeoException('invalid_domain');
        }

        return $domain;
    }
}
