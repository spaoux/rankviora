<?php

namespace App\Seo;

enum Operation: string
{
    case Overview = 'domain_rank_overview';
    case Keywords = 'ranked_keywords';
    case Intersection = 'domain_intersection';
    case Pages = 'relevant_pages';

    public function url(): string
    {
        return 'https://api.dataforseo.com/v3/dataforseo_labs/google/'.$this->value.'/live';
    }
}
