<?php

namespace App\Seo;

use App\Models\ApiUsageLog;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Seo\Dto\ProviderResponse;
use App\Seo\Dto\SeoRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

final class SeoGateway
{
    public function __construct(private Provider $provider, private BudgetLedger $ledger) {}

    public function fetch(SeoSnapshot $snapshot, SeoRequest $request): ProviderResponse
    {
        $snapshot->loadMissing('project.workspace');
        $user = User::find($snapshot->requested_by);
        if (! $user || ! Gate::forUser($user)->allows('update', $snapshot->project->workspace)) {
            throw new SeoException('access_revoked');
        }
        if ($snapshot->mode !== config('seo.mode')) {
            throw new SeoException('mode_changed');
        }
        if ($snapshot->is_demo !== ($snapshot->mode === 'mock')) {
            throw new SeoException('invalid_data_provenance');
        }
        if (($request->operation === Operation::Intersection && ($request->target !== $snapshot->competitor_domain || $request->other !== $snapshot->domain))
            || ($request->operation !== Operation::Intersection && ($request->target !== $snapshot->domain || $request->other !== null))) {
            throw new SeoException('request_target_mismatch');
        }
        $key = 'seo:'.$snapshot->project->workspace_id.':'.$snapshot->project_id.':'.$snapshot->mode.':'.$request->fingerprint();
        $lock = Cache::lock($key.':lock', 180);
        if (! $lock->get()) {
            throw new SeoException('query_in_progress');
        }
        try {
            if ($cached = Cache::get($key)) {
                return new ProviderResponse(...$cached);
            }
            $hash = hash('sha256', $key);
            $receipt = ApiUsageLog::where('workspace_id', $snapshot->project->workspace_id)->where('project_id', $snapshot->project_id)
                ->where('mode', $snapshot->mode)->where('request_hash', $hash)->where('status', 'completed')->where('expires_at', '>', now())->latest('id')->first();
            if ($receipt && $receipt->response_payload !== null) {
                $cached = ['taskId' => $receipt->task_id, 'costMicros' => $receipt->reported_cost_micros, 'result' => $receipt->response_payload['result'] ?? $receipt->response_payload, 'retrievedAt' => $receipt->response_payload['retrieved_at'] ?? $receipt->created_at->toIso8601String()];
                Cache::put($key, $cached, max(1, (int) now()->diffInSeconds($receipt->expires_at, false)));

                return new ProviderResponse(...$cached);
            }
            for ($attempt = 0; $attempt <= (int) config('seo.safe_retries'); $attempt++) {
                if (! Gate::forUser($user)->allows('update', $snapshot->project->workspace)) {
                    throw new SeoException('access_revoked');
                }
                // The account-wide lock makes the rate check and increment atomic across workers/workspaces.
                $rateLock = Cache::lock('seo:account-rate-lock', 5);
                if (! $rateLock->get()) {
                    throw new SeoException('local_rate_limit');
                }
                try {
                    if (RateLimiter::tooManyAttempts('seo:account:'.$snapshot->mode, config('seo.rate_limit'))) {
                        throw new SeoException('local_rate_limit');
                    }
                    RateLimiter::hit('seo:account:'.$snapshot->mode, 60);
                } finally {
                    $rateLock->release();
                }
                $log = $this->ledger->reserve($snapshot, $request->operation->value, $hash);
                try {
                    $response = $this->provider->fetch($request);
                } catch (SeoException $error) {
                    $this->ledger->settle($log, $error->costMicros, 'failed', $error->providerStatus, $error->taskId, $error->reason);
                    if ($error->retryable && $error->costMicros === 0 && $attempt < config('seo.safe_retries')) {
                        if (! app()->runningUnitTests()) {
                            usleep(250000 * (2 ** $attempt));
                        }

                        continue;
                    }
                    throw $error;
                } catch (\Throwable) {
                    $this->ledger->settle($log, null, 'uncertain', error: 'transport_unknown');
                    throw new SeoException('transport_unknown');
                }
                $this->ledger->settle($log, $response->costMicros, 'completed', 20000, $response->taskId, payload: ['result' => $response->result, 'retrieved_at' => $response->retrievedAt]);
                Cache::put($key, ['taskId' => $response->taskId, 'costMicros' => $response->costMicros, 'result' => $response->result, 'retrievedAt' => $response->retrievedAt], config('seo.cache_seconds'));

                return $response;
            }
            throw new SeoException('retry_exhausted');
        } finally {
            $lock->release();
        }
    }
}
