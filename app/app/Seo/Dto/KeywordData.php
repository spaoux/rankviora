<?php

namespace App\Seo\Dto;

use App\Seo\SeoException;

final readonly class KeywordData
{
    public function __construct(public string $keyword, public ?int $volume, public ?float $cpc, public ?string $updatedAt) {}

    public static function fromArray(array $data): self
    {
        $keyword = $data['keyword'] ?? null;
        $info = $data['keyword_info'] ?? null;
        if (! is_string($keyword) || trim($keyword) === '' || mb_strlen($keyword) > 2000 || ! is_array($info)) {
            throw new SeoException('invalid_keyword');
        }
        $volume = $info['search_volume'] ?? null;
        $cpc = $info['cpc'] ?? null;
        if (($volume !== null && (! is_int($volume) || $volume < 0)) || ($cpc !== null && (! is_numeric($cpc) || ! is_finite((float) $cpc) || $cpc < 0))) {
            throw new SeoException('invalid_keyword');
        }
        $date = $info['last_updated_time'] ?? null;
        if ($date !== null) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2} /', $date) || strtotime($date) === false) {
                throw new SeoException('invalid_data_date');
            }
        }

        return new self($keyword, $volume, $cpc === null ? null : (float) $cpc, $date);
    }
}
