<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisRun extends Model
{
    protected $fillable = ['type', 'status', 'is_demo', 'results'];

    protected function casts(): array
    {
        return ['results' => 'array', 'is_demo' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
