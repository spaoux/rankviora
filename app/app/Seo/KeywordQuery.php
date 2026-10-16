<?php

namespace App\Seo;

use App\Models\SeoSnapshot;
use Illuminate\Database\Eloquent\Builder;

final class KeywordQuery
{
    public function build(SeoSnapshot $snapshot, array $filters, bool $gap = false): Builder
    {
        $query = $snapshot->keywords()->getQuery()->select('seo_keywords.*', 'own.position as own_position', 'own.url as own_url', 'own.estimated_traffic as own_traffic', 'rival.position as rival_position', 'rival.url as rival_url')
            ->leftJoin('seo_rankings as own', fn ($join) => $join->on('own.seo_keyword_id', '=', 'seo_keywords.id')->where('own.domain', $snapshot->domain))
            ->leftJoin('seo_rankings as rival', fn ($join) => $join->on('rival.seo_keyword_id', '=', 'seo_keywords.id')->where('rival.domain', $snapshot->competitor_domain ?? ''));
        if (! $gap) {
            $query->where('kind', 'organic');
        } elseif (($filters['kind'] ?? 'missing') === 'better') {
            $query->where('kind', 'shared')->where('competitor_better', true);
        } else {
            $query->where('kind', $filters['kind'] ?? 'missing');
        }
        if (($filters['q'] ?? '') !== '') {
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']);
            $query->whereRaw("LOWER(seo_keywords.keyword) LIKE LOWER(?) ESCAPE '!'", ['%'.$term.'%']);
        }
        if (isset($filters['min_volume'])) {
            $query->where('search_volume', '>=', $filters['min_volume']);
        }
        if (isset($filters['min_position'])) {
            $query->where('own.position', '>=', $filters['min_position']);
        }
        if (isset($filters['max_position'])) {
            $query->where('own.position', '<=', $filters['max_position']);
        }
        $column = ['keyword' => 'seo_keywords.keyword', 'position' => 'own.position', 'volume' => 'search_volume', 'cpc' => 'cpc', 'traffic' => 'own.estimated_traffic'][$filters['sort'] ?? 'position'];

        return $query->orderBy($column, $filters['direction'] ?? 'asc')->orderBy('seo_keywords.id');
    }
}
