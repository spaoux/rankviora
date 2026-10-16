<?php

namespace App\Seo\Dto;

use App\Seo\SeoException;

final readonly class RankingData
{
    public function __construct(public int $position, public string $url, public ?float $estimatedTraffic) {}

    public static function fromArray(array $data): self
    {
        $rank = $data['rank_group'] ?? null;
        $url = $data['url'] ?? null;
        $etv = $data['etv'] ?? null;
        if (($data['type'] ?? null) !== 'organic' || ! is_int($rank) || $rank < 1 || $rank > 1000 || ! is_string($url) || strlen($url) > 8192
            || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            || ($etv !== null && (! is_numeric($etv) || ! is_finite((float) $etv) || $etv < 0))) {
            throw new SeoException('invalid_ranking');
        }

        return new self($rank, $url, $etv === null ? null : (float) $etv);
    }
}
