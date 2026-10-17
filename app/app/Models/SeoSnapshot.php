<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SeoSnapshot extends Model
{
    public function sourceLabel(): string
    {
        return self::labelForMode($this->mode);
    }

    public static function labelForMode(string $mode): string
    {
        return match ($mode) {
            'mock' => 'MOCK / DEMO',
            'sandbox' => 'SANDBOX / TEST VERİSİ',
            'live' => 'DataForSEO',
            default => 'DOĞRULANMAMIŞ KAYNAK',
        };
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean', 'coverage' => 'array', 'completed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(SeoKeyword::class);
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(SeoDomainMetric::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(ApiUsageLog::class);
    }
}
