<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoDomainMetric extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['top_pages' => 'array', 'estimated_traffic' => 'decimal:4'];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SeoSnapshot::class, 'seo_snapshot_id');
    }
}
