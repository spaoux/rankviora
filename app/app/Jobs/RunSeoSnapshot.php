<?php

namespace App\Jobs;

use App\Models\SeoSnapshot;
use App\Seo\SnapshotRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class RunSeoSnapshot implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 900;

    public function __construct(public int $snapshotId)
    {
        $this->onQueue('seo');
    }

    public function uniqueId(): string
    {
        return 'seo-snapshot:'.$this->snapshotId;
    }

    public function middleware(): array
    {
        return [(new RateLimited('seo-jobs'))->releaseAfter(60)];
    }

    public function handle(SnapshotRunner $runner): void
    {
        $runner->run($this->snapshotId);
    }

    public function failed(?\Throwable $error): void
    {
        SeoSnapshot::whereKey($this->snapshotId)->whereIn('status', ['queued', 'processing'])->update(['status' => 'failed', 'error_code' => 'worker_failed', 'completed_at' => now()]);
    }
}
