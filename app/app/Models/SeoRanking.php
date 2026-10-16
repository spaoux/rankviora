<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoRanking extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['estimated_traffic' => 'decimal:4'];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'seo_keyword_id');
    }
}
