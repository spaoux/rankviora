<?php

namespace App\Seo\Dto;

use App\Seo\SeoException;

final readonly class OrganicMetrics
{
    public function __construct(public int $count, public float $estimatedTraffic, public int $top3, public int $top10, public int $top100) {}

    public static function fromArray(array $data): self
    {
        $keys = ['count', 'pos_1', 'pos_2_3', 'pos_4_10', 'pos_11_20', 'pos_21_30', 'pos_31_40', 'pos_41_50', 'pos_51_60', 'pos_61_70', 'pos_71_80', 'pos_81_90', 'pos_91_100'];
        foreach ($keys as $key) {
            if (! is_int($data[$key] ?? null) || $data[$key] < 0) {
                throw new SeoException('invalid_metrics');
            }
        }
        if (! is_numeric($data['etv'] ?? null) || ! is_finite((float) $data['etv']) || $data['etv'] < 0) {
            throw new SeoException('invalid_metrics');
        }
        $top3 = $data['pos_1'] + $data['pos_2_3'];
        $top10 = $top3 + $data['pos_4_10'];
        $top100 = array_sum(array_intersect_key($data, array_flip(array_slice($keys, 1))));

        return new self($data['count'], (float) $data['etv'], $top3, $top10, $top100);
    }
}
