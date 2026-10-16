<?php

namespace Tests\Feature;

use App\Jobs\RunSeoSnapshot;
use App\Models\ApiUsageLog;
use App\Models\SeoBudget;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Models\Workspace;
use App\Seo\BudgetLedger;
use App\Seo\CsvCell;
use App\Seo\DataForSeoProvider;
use App\Seo\DomainName;
use App\Seo\Dto\KeywordData;
use App\Seo\Dto\ProviderResponse;
use App\Seo\Dto\SeoRequest;
use App\Seo\MockResponses;
use App\Seo\Operation;
use App\Seo\Provider;
use App\Seo\SeoException;
use App\Seo\SeoGateway;
use App\Seo\SnapshotDispatcher;
use App\Seo\SnapshotRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SeoEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['seo.mode' => 'mock', 'seo.live_enabled' => false, 'seo.login' => '', 'seo.password' => '', 'seo.live_approval' => '', 'seo.rate_limit' => 1000]);
        Http::preventStrayRequests();
        $this->fake(fn (Request $r) => Http::response(MockResponses::forRequest($r)));
        $this->app->bind(Provider::class, fn () => new DataForSeoProvider(Http::getFacadeRoot()));
    }

    private function fake(mixed $callback): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($callback);
    }

    private function tenant(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Ekip']);
        $workspace->users()->attach($user, ['role' => 'owner']);
        $project = $workspace->projects()->create(['name' => 'Proje', 'domain' => 'example.com']);

        return [$user, $workspace, $project];
    }

    private function snapshot(?array $tenant = null, bool $competitor = false): SeoSnapshot
    {
        [$user,$workspace,$project] = $tenant ?? $this->tenant();
        if ($competitor) {
            $project->competitors()->firstOrCreate(['domain' => 'rival.com']);
        }

        return $project->seoSnapshots()->create(['requested_by' => $user->id, 'domain' => $project->domain, 'competitor_domain' => $competitor ? 'rival.com' : null, 'mode' => config('seo.mode'), 'is_demo' => config('seo.mode') === 'mock']);
    }

    private function fetch(SeoSnapshot $snapshot, ?SeoRequest $request = null): ProviderResponse
    {
        return $this->app->make(SeoGateway::class)->fetch($snapshot, $request ?? new SeoRequest(Operation::Overview, $snapshot->domain));
    }

    private function failure(callable $callback, string $reason): void
    {
        try {
            $callback();
            $this->fail('Expected '.$reason);
        } catch (SeoException $error) {
            $this->assertSame($reason, $error->reason);
        }
    }

    private function errorBody(Request $request, int $code, float $cost = 0): array
    {
        $body = MockResponses::forRequest($request);
        $body['cost'] = $cost;
        $body['tasks_error'] = 1;
        $body['tasks'][0]['cost'] = $cost;
        $body['tasks'][0]['status_code'] = $code;
        $body['tasks'][0]['result'] = null;
        $body['tasks'][0]['result_count'] = 0;

        return $body;
    }

    public function test_success_defaults_dto_cost_and_fixed_outbound_endpoint(): void
    {
        $snapshot = $this->snapshot();
        $response = $this->fetch($snapshot);
        $this->assertSame(11000, $response->costMicros);
        $log = ApiUsageLog::firstOrFail();
        $this->assertSame('0.000000', $log->cost);
        $this->assertTrue($log->is_demo);
        $this->assertSame(11000, $log->reported_cost_micros);
        $this->assertSame(0, SeoBudget::first()->held_micros);
        Http::assertSent(fn (Request $r) => $r->url() === Operation::Overview->url() && $r->method() === 'POST' && $r[0]['location_code'] === 2792 && $r[0]['language_code'] === 'tr' && $r[0]['target'] === 'example.com' && ! $r->hasHeader('Authorization'));
    }

    public function test_live_cost_accounting_uses_only_fake_transport_and_dummy_credentials(): void
    {
        config(['seo.mode' => 'live', 'seo.live_enabled' => true, 'seo.live_approval' => 'approved-after-safety-review', 'seo.login' => 'fake-login', 'seo.password' => 'fake-password']);
        $this->fetch($this->snapshot());
        $this->assertSame('0.011000', ApiUsageLog::first()->cost);
        $this->assertFalse(ApiUsageLog::first()->is_demo);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('fake-login:fake-password')));
    }

    public function test_live_requires_separate_approval_and_sends_nothing(): void
    {
        config(['seo.mode' => 'live']);
        $snapshot = $this->snapshot();
        $this->failure(fn () => $this->fetch($snapshot), 'live_not_approved');
        Http::assertNothingSent();
        $this->assertSame(0, SeoBudget::first()->held_micros);
    }

    public function test_http_authorization_error_has_no_retry_or_charge(): void
    {
        $this->fake(['*' => Http::response([], 401)]);
        $this->failure(fn () => $this->fetch($this->snapshot()), 'provider_auth');
        Http::assertSentCount(1);
        $this->assertSame(0, SeoBudget::first()->spent_micros);
        $this->assertSame('failed', ApiUsageLog::first()->status);
    }

    public function test_api_authorization_error_inside_http_200(): void
    {
        $this->fake(fn (Request $r) => Http::response($this->errorBody($r, 40100)));
        $this->failure(fn () => $this->fetch($this->snapshot()), 'provider_auth');
        Http::assertSentCount(1);
        $this->assertSame(40100, ApiUsageLog::first()->provider_status);
    }

    public function test_zero_cost_rate_limit_is_retried_with_per_attempt_receipts(): void
    {
        $attempt = 0;
        $this->fake(function (Request $r) use (&$attempt) {
            return Http::response(++$attempt === 1 ? $this->errorBody($r, 40202) : MockResponses::forRequest($r));
        });
        $this->fetch($this->snapshot());
        Http::assertSentCount(2);
        $this->assertDatabaseCount('api_usage_logs', 2);
        $this->assertSame(11000, SeoBudget::first()->spent_micros);
        $this->assertSame(0, ApiUsageLog::first()->reported_cost_micros);
    }

    public function test_rate_limit_retry_is_bounded(): void
    {
        $this->fake(fn (Request $r) => Http::response($this->errorBody($r, 40202)));
        $this->failure(fn () => $this->fetch($this->snapshot()), 'provider_rate_limit');
        Http::assertSentCount(3);
        $this->assertSame(0, SeoBudget::first()->held_micros);
        $this->assertFalse(SeoBudget::first()->blocked);
    }

    public function test_nonzero_cost_rate_limit_is_never_retried(): void
    {
        $this->fake(fn (Request $r) => Http::response($this->errorBody($r, 40202, 0.002)));
        $this->failure(fn () => $this->fetch($this->snapshot()), 'provider_rate_limit');
        Http::assertSentCount(1);
        $this->assertSame(2000, SeoBudget::first()->spent_micros);
    }

    public function test_timeout_holds_budget_and_blocks_new_requests_without_replaying_post(): void
    {
        $this->fake(fn () => throw new ConnectionException('Sensitive transport message must not persist'));
        $snapshot = $this->snapshot();
        $this->failure(fn () => $this->fetch($snapshot), 'timeout_unknown_cost');
        $this->assertSame('uncertain', ApiUsageLog::first()->status);
        $this->assertNull(ApiUsageLog::first()->reported_cost_micros);
        $this->assertSame(config('seo.request_ceiling_micros'), SeoBudget::first()->held_micros);
        $this->failure(fn () => $this->fetch($snapshot, new SeoRequest(Operation::Pages, 'example.com')), 'budget_review_required');
        $this->assertDatabaseCount('api_usage_logs', 1);
    }

    public function test_unexpected_json_and_inconsistent_cost_fail_closed(): void
    {
        $this->fake(['*' => Http::response('invalid json', 200)]);
        $this->failure(fn () => $this->fetch($this->snapshot()), 'invalid_json');
        $this->assertTrue(SeoBudget::first()->blocked);
        $this->fake(function (Request $r) {
            $body = MockResponses::forRequest($r);
            $body['cost'] = 0.5;

            return Http::response($body);
        });
        $this->failure(fn () => $this->fetch($this->snapshot()), 'invalid_envelope');
        $this->assertSame(2, SeoBudget::where('blocked', true)->count());
    }

    public function test_invalid_result_shape_is_rejected_but_known_cost_is_recorded(): void
    {
        $this->fake(function (Request $r) {
            $body = MockResponses::forRequest($r);
            $body['tasks'][0]['result'][0]['items'] = 'bad';

            return Http::response($body);
        });
        $this->failure(fn () => $this->fetch($this->snapshot()), 'invalid_items');
        $this->assertSame(11000, SeoBudget::first()->spent_micros);
        $this->assertSame(0, SeoBudget::first()->held_micros);
    }

    public function test_budget_limit_prevents_http_request(): void
    {
        config(['seo.monthly_budget_micros' => 99999]);
        $this->failure(fn () => $this->fetch($this->snapshot()), 'budget_exceeded');
        Http::assertNothingSent();
        $this->assertDatabaseCount('api_usage_logs', 0);
    }

    public function test_cost_above_reserved_ceiling_records_actual_and_blocks_followup(): void
    {
        $this->fake(function (Request $r) {
            $body = MockResponses::forRequest($r);
            $body['cost'] = $body['tasks'][0]['cost'] = 0.2;

            return Http::response($body);
        });
        $snapshot = $this->snapshot();
        $this->fetch($snapshot);
        $this->assertSame(200000, SeoBudget::first()->spent_micros);
        $this->assertTrue(SeoBudget::first()->blocked);
        $this->failure(fn () => $this->fetch($snapshot, new SeoRequest(Operation::Pages, 'example.com')), 'budget_review_required');
        Http::assertSentCount(1);
    }

    public function test_cache_hit_durable_receipt_miss_and_workspace_keys(): void
    {
        $snapshot = $this->snapshot();
        $a = $this->fetch($snapshot);
        $b = $this->fetch($snapshot);
        Http::assertSentCount(1);
        $this->assertSame($a->retrievedAt, $b->retrievedAt);
        Cache::flush();
        $this->fetch($snapshot);
        Http::assertSentCount(1);
        $this->fetch($this->snapshot());
        Http::assertSentCount(2);
        $this->travel(86401)->seconds();
        $this->fetch($snapshot);
        Http::assertSentCount(3);
    }

    public function test_query_lock_prevents_duplicate_http(): void
    {
        $snapshot = $this->snapshot();
        $request = new SeoRequest(Operation::Overview, 'example.com');
        $lock = Cache::lock('seo:'.$snapshot->project->workspace_id.':'.$snapshot->project_id.':mock:'.$request->fingerprint().':lock', 180);
        $lock->get();
        try {
            $this->failure(fn () => $this->fetch($snapshot, $request), 'query_in_progress');
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_local_account_rate_limit_blocks_before_cost_reservation(): void
    {
        config(['seo.rate_limit' => 1]);
        $snapshot = $this->snapshot();
        $this->fetch($snapshot);
        $this->failure(fn () => $this->fetch($snapshot, new SeoRequest(Operation::Pages, 'example.com')), 'local_rate_limit');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('api_usage_logs', 1);
    }

    public function test_runner_integrates_all_endpoints_and_correct_gap_logic(): void
    {
        $snapshot = $this->snapshot(competitor: true);
        $this->app->make(SnapshotRunner::class)->run($snapshot->id);
        $snapshot->refresh();
        $this->assertSame('completed', $snapshot->status, $snapshot->error_code ?? '');
        Http::assertSentCount(5);
        $this->assertSame(248, $snapshot->metrics->organic_keywords);
        $this->assertSame(15, $snapshot->metrics->top3);
        $this->assertSame(40, $snapshot->metrics->top10);
        $this->assertSame(248, $snapshot->metrics->top100);
        $this->assertSame('seo analizi', $snapshot->keywords()->where('competitor_better', true)->first()->keyword);
        $missing = $snapshot->keywords()->where('kind', 'missing')->firstOrFail();
        $this->assertSame('rakip fırsatı', $missing->keyword);
        $this->assertSame(1, $missing->rankings()->count());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'domain_intersection') && $r[0]['target1'] === 'rival.com' && $r[0]['target2'] === 'example.com' && $r[0]['intersections'] === false);
        $this->app->make(SnapshotRunner::class)->run($snapshot->id);
        Http::assertSentCount(5);
        $this->assertDatabaseCount('seo_domain_metrics', 1);
        $this->assertSame('0.000000', ApiUsageLog::first()->cost);
    }

    public function test_membership_revocation_blocks_queue_before_http(): void
    {
        $tenant = $this->tenant();
        $snapshot = $this->snapshot($tenant);
        $tenant[1]->users()->detach($tenant[0]);
        $this->app->make(SnapshotRunner::class)->run($snapshot->id);
        $this->assertSame('access_revoked', $snapshot->fresh()->error_code);
        Http::assertNothingSent();
    }

    public function test_changed_domain_and_deleted_competitor_block_stale_jobs(): void
    {
        $snapshot = $this->snapshot();
        $snapshot->project->update(['domain' => 'changed.com']);
        $this->app->make(SnapshotRunner::class)->run($snapshot->id);
        $this->assertSame('domain_changed', $snapshot->fresh()->error_code);
        $other = $this->snapshot(competitor: true);
        $other->project->competitors()->delete();
        $this->app->make(SnapshotRunner::class)->run($other->id);
        $this->assertSame('competitor_removed', $other->fresh()->error_code);
        Http::assertNothingSent();
    }

    public function test_queue_dispatch_deduplicates_inflight_snapshots_and_has_safe_payload(): void
    {
        Queue::fake();
        [$user,$workspace,$project] = $this->tenant();
        $dispatcher = $this->app->make(SnapshotDispatcher::class);
        $a = $dispatcher->dispatch($user, $project, null);
        $b = $dispatcher->dispatch($user, $project, null);
        $this->assertSame($a->id, $b->id);
        Queue::assertPushed(RunSeoSnapshot::class, 1);
        Queue::assertPushed(RunSeoSnapshot::class, fn ($job) => $job->snapshotId === $a->id && $job->queue === 'seo' && $job->timeout < config('queue.connections.redis.retry_after'));
    }

    public function test_all_seo_routes_snapshot_and_competitor_ids_are_tenant_scoped(): void
    {
        Queue::fake();
        [$user,$workspace,$project] = $this->tenant();
        [$other,$foreign,$foreignProject] = $this->tenant();
        $snapshot = $this->snapshot([$other, $foreign, $foreignProject]);
        $this->actingAs($user);
        $base = '/workspaces/'.$workspace->id.'/seo-engine/projects/';
        $this->get('/workspaces/'.$foreign->id.'/seo-engine')->assertForbidden();
        $this->get($base.$foreignProject->id)->assertNotFound();
        $this->get($base.$project->id.'?snapshot='.$snapshot->id)->assertNotFound();
        $this->get($base.$project->id.'/export?snapshot='.$snapshot->id)->assertNotFound();
        $competitor = $foreignProject->competitors()->create(['domain' => 'rival.com']);
        $this->post($base.$project->id, ['competitor_id' => $competitor->id])->assertNotFound();
        $this->post('/workspaces/'.$foreign->id.'/seo-engine/projects/'.$foreignProject->id)->assertForbidden();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_member_reads_completed_snapshots_but_cannot_enqueue(): void
    {
        [$user,$workspace,$project] = $this->tenant();
        $snapshot = $this->snapshot([$user, $workspace, $project]);
        $this->app->make(SnapshotRunner::class)->run($snapshot->id);
        $member = User::factory()->create();
        $workspace->users()->attach($member, ['role' => 'member']);
        $base = '/workspaces/'.$workspace->id.'/seo-engine/projects/'.$project->id;
        $this->actingAs($member)->get($base)->assertOk()->assertSee('MOCK / DEMO')->assertSee('Tahmini organik trafik');
        $this->post($base)->assertForbidden();
    }

    public function test_keyword_filters_sort_pagination_and_csv_formula_protection(): void
    {
        [$user,$workspace,$project] = $this->tenant();
        $snapshot = $this->snapshot([$user, $workspace, $project]);
        $this->app->make(SnapshotRunner::class)->run($snapshot->id);
        $snapshot->keywords()->where('kind', 'organic')->first()->update(['keyword' => '=HYPERLINK("evil")']);
        $base = '/workspaces/'.$workspace->id.'/seo-engine/projects/'.$project->id;
        $this->actingAs($user)->get($base.'?tab=keywords&sort=position&direction=desc&per_page=10')->assertOk()->assertSee('CSV export');
        $this->get($base.'?tab=keywords&q=not-present')->assertOk()->assertSee('Bu filtrede sonuç yok.');
        $response = $this->get($base.'/export?snapshot='.$snapshot->id);
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('MOCK / DEMO', $csv);
        $filtered = $this->get($base.'/export?snapshot='.$snapshot->id.'&q=not-present')->streamedContent();
        $this->assertStringNotContainsString('HYPERLINK', $filtered);
        $this->get($base.'?tab=keywords&sort=malicious SQL')->assertSessionHasErrors('sort');
        foreach (['=SUM(1,2)', ' +cmd', '-cmd', '@cmd', "\t=cmd", "\xEF\xBB\xBF=cmd"] as $value) {
            $this->assertStringStartsWith("'", CsvCell::safe($value));
        }
        $this->assertSame('normal', CsvCell::safe('normal'));
    }

    public function test_domains_are_normalized_and_url_ip_private_targets_rejected(): void
    {
        $this->assertSame('example.com', DomainName::normalize(' EXAMPLE.COM. '));
        foreach (['https://example.com', '127.0.0.1', 'localhost', 'host.internal', 'host.local', 'example.com/path', 'example.com:80', 'example.com@evil.com', "example.com\nHost:evil.com"] as $domain) {
            $this->failure(fn () => new SeoRequest(Operation::Overview, $domain), 'invalid_domain');
        }
        Http::assertNothingSent();
    }

    public function test_unknown_http_error_is_not_retried_and_budget_stays_reserved(): void
    {
        $this->fake(['*' => Http::response('upstream failure', 500)]);
        $this->failure(fn () => $this->fetch($this->snapshot()), 'http_500');
        Http::assertSentCount(1);
        $this->assertSame('uncertain', ApiUsageLog::first()->status);
        $this->assertTrue(SeoBudget::first()->blocked);
    }

    public function test_provider_budget_status_is_recorded_without_retry(): void
    {
        $this->fake(fn (Request $r) => Http::response($this->errorBody($r, 40203)));
        $this->failure(fn () => $this->fetch($this->snapshot()), 'provider_budget');
        Http::assertSentCount(1);
        $this->assertSame(40203, ApiUsageLog::first()->provider_status);
    }

    public function test_response_target_mismatch_is_rejected_and_gateway_cannot_fetch_other_domains(): void
    {
        $snapshot = $this->snapshot();
        $this->failure(fn () => $this->fetch($snapshot, new SeoRequest(Operation::Overview, 'foreign.com')), 'request_target_mismatch');
        Http::assertNothingSent();
        $this->fake(function (Request $r) {
            $body = MockResponses::forRequest($r);
            $body['tasks'][0]['data']['target'] = 'foreign.com';

            return Http::response($body);
        });
        $this->failure(fn () => $this->fetch($snapshot), 'response_target_mismatch');
        $this->assertSame(11000, SeoBudget::first()->spent_micros);
    }

    public function test_reconciliation_is_audited_and_idempotent_and_does_not_send_http(): void
    {
        $this->fake(fn () => throw new ConnectionException);
        $this->failure(fn () => $this->fetch($this->snapshot()), 'timeout_unknown_cost');
        $log = ApiUsageLog::firstOrFail();
        $ledger = $this->app->make(BudgetLedger::class);
        $ledger->reconcile($log->id, 2000, 'Verified mock invoice reference 123');
        $this->assertSame('reconciled', $log->fresh()->status);
        $this->assertSame('Verified mock invoice reference 123', $log->fresh()->reconciliation_note);
        $this->assertSame(2000, SeoBudget::first()->spent_micros);
        $this->assertSame(0, SeoBudget::first()->held_micros);
        $this->assertFalse(SeoBudget::first()->blocked);
        $this->failure(fn () => $ledger->reconcile($log->id, 2000, 'Duplicate'), 'already_settled');
    }

    public function test_snapshot_history_retains_old_results_and_reuses_cached_data(): void
    {
        $tenant = $this->tenant();
        $first = $this->snapshot($tenant);
        $runner = $this->app->make(SnapshotRunner::class);
        $runner->run($first->id);
        $date = $first->fresh()->coverage['overview']['retrieved_at'];
        $this->travel(30)->minutes();
        $second = $this->snapshot($tenant);
        $runner->run($second->id);
        $this->assertSame('completed', $second->fresh()->status);
        $this->assertSame($date, $second->fresh()->coverage['overview']['retrieved_at']);
        $this->assertDatabaseCount('seo_snapshots', 2);
        $this->assertDatabaseCount('seo_domain_metrics', 2);
        Http::assertSentCount(3);
    }

    public function test_unknown_volume_and_cpc_are_preserved_as_null(): void
    {
        $data = MockResponses::keyword('belirsiz');
        $data['keyword_info']['search_volume'] = null;
        $data['keyword_info']['cpc'] = null;
        $dto = KeywordData::fromArray($data);
        $this->assertNull($dto->volume);
        $this->assertNull($dto->cpc);
    }

    public function test_pending_reservation_survives_month_rollover_and_other_workspaces_are_independent(): void
    {
        $snapshot = $this->snapshot();
        $this->app->make(BudgetLedger::class)->reserve($snapshot, 'domain_rank_overview', 'abandoned-worker');
        $this->travel(40)->days();
        $this->failure(fn () => $this->fetch($snapshot), 'budget_review_required');
        Http::assertNothingSent();
        $this->fetch($this->snapshot());
        Http::assertSentCount(1);
        $this->assertSame('pending', ApiUsageLog::first()->status);
    }

    public function test_mock_snapshot_cannot_be_relabelled_as_real(): void
    {
        $snapshot = $this->snapshot();
        $snapshot->update(['is_demo' => false]);
        $this->failure(fn () => $this->fetch($snapshot), 'invalid_data_provenance');
        Http::assertNothingSent();
        $this->assertDatabaseCount('api_usage_logs', 0);
    }

    public function test_membership_is_rechecked_before_rate_limit_retry(): void
    {
        $tenant = $this->tenant();
        $snapshot = $this->snapshot($tenant);
        $this->fake(function (Request $request) use ($tenant) {
            $tenant[1]->users()->detach($tenant[0]);

            return Http::response($this->errorBody($request, 40202));
        });
        $this->failure(fn () => $this->fetch($snapshot), 'access_revoked');
        Http::assertSentCount(1);
        $this->assertSame(0, SeoBudget::first()->held_micros);
    }
}
