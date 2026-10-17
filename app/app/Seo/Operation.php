<?php

namespace App\Seo;

enum Operation: string
{
    case Overview = 'domain_rank_overview';
    case Keywords = 'ranked_keywords';
    case Intersection = 'domain_intersection';
    case Pages = 'relevant_pages';

    public function url(string $mode = 'live'): string
    {
        $host = match ($mode) {
            'sandbox' => 'sandbox.dataforseo.com',
            'live', 'mock' => 'api.dataforseo.com',
            default => throw new SeoException('provider_disabled', false, 0),
        };

        return 'https://'.$host.'/v3/dataforseo_labs/google/'.$this->value.'/live';
    }
}
