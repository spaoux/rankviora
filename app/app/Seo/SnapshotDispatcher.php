<?php

namespace App\Seo;

use App\Jobs\RunSeoSnapshot;
use App\Models\Project;
use App\Models\SeoSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

final class SnapshotDispatcher
{
    public function dispatch(User $actor, Project $project, ?int $competitorId): SeoSnapshot
    {
        Gate::forUser($actor)->authorize('update', $project->workspace);
        $domain = DomainName::normalize($project->domain);
        $competitor = $competitorId === null ? null : DomainName::normalize($project->competitors()->findOrFail($competitorId)->domain);
        if ($competitor === $domain) {
            throw new SeoException('invalid_request');
        }
        $mode = config('seo.mode');
        if ($mode === 'sandbox') {
            throw new SeoException('sandbox_cli_only');
        }
        if ($mode !== 'mock' && ($mode !== 'live' || ! config('seo.live_enabled') || config('seo.live_approval') !== 'approved-after-safety-review')) {
            throw new SeoException('live_not_approved');
        }
        $lock = Cache::lock('seo:dispatch:'.$project->workspace_id.':'.$project->id.':'.$mode.':'.$domain.':'.$competitor, 10);
        if (! $lock->get()) {
            throw new SeoException('query_in_progress');
        }
        try {
            $existing = $project->seoSnapshots()->where('domain', $domain)->where('competitor_domain', $competitor)->where('mode', $mode)->whereIn('status', ['queued', 'processing'])->first();
            if ($existing) {
                return $existing;
            }
            $snapshot = $project->seoSnapshots()->create(['requested_by' => $actor->id, 'domain' => $domain, 'competitor_domain' => $competitor, 'mode' => $mode, 'is_demo' => $mode === 'mock']);
            try {
                RunSeoSnapshot::dispatch($snapshot->id)->afterCommit();
            } catch (\Throwable) {
                $snapshot->update(['status' => 'failed', 'error_code' => 'queue_unavailable', 'completed_at' => now()]);
                throw new SeoException('queue_unavailable');
            }

            return $snapshot;
        } finally {
            $lock->release();
        }
    }
}
