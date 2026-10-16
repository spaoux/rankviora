<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoKeyword extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['competitor_better' => 'boolean', 'volume_updated_at' => 'datetime', 'cpc' => 'decimal:6'];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SeoSnapshot::class, 'seo_snapshot_id');
    }

    public function rankings(): HasMany
    {
        return $this->hasMany(SeoRanking::class);
    }
}
