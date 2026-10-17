<?php

namespace Tests\Feature;

use App\Models\ApiUsageLog;
use App\Models\SeoBudget;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Models\Workspace;
use App\Seo\DataForSeoProvider;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SeoSandboxTest extends TestCase
{
    use RefreshDatabase;

    private const DUMMY_LOGIN = 'sandbox-test@example.invalid';

    private const DUMMY_PASSWORD = 'dummy-only-not-a-real-password';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['seo.mode' => 'sandbox', 'seo.sandbox_enabled' => true, 'seo.login' => self::DUMMY_LOGIN, 'seo.password' => self::DUMMY_PASSWORD, 'seo.live_enabled' => false, 'seo.live_approval' => '']);
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
        $workspace = Workspace::create(['name' => 'Sandbox workspace']);
        $workspace->users()->attach($user, ['role' => 'owner']);
        $project = $workspace->projects()->create(['name' => 'Sandbox project', 'domain' => 'example.com']);

        return [$user, $workspace, $project];
    }

    private function snapshot(array $tenant): SeoSnapshot
    {
        return $tenant[2]->seoSnapshots()->create(['requested_by' => $tenant[0]->id, 'domain' => 'example.com', 'mode' => config('seo.mode'), 'is_demo' => true]);
    }

    private function fetch(SeoSnapshot $snapshot, Operation $operation = Operation::Overview): ProviderResponse
    {
        return $this->app->make(SeoGateway::class)->fetch($snapshot, new SeoRequest($operation, 'example.com', limit: 1));
    }

    private function fails(callable $callback, string $reason): void
    {
        try {
            $callback();
            $this->fail('Expected safe error code');
        } catch (SeoException $error) {
            $this->assertSame($reason, $error->reason);
            $this->assertNull($error->getPrevious());
        }
    }

    public function test_sandbox_host_auth_tls_no_redirect_and_no_live_fallback(): void
    {
        config(['seo.live_enabled' => true, 'seo.live_approval' => 'approved-after-safety-review', 'seo.base_url' => 'http://evil.invalid/']);
        $options = [];
        $this->fake(function (Request $request, array $httpOptions) use (&$options) {
            $options = $httpOptions;

            return Http::response(MockResponses::forRequest($request));
        });
        $this->fetch($this->snapshot($this->tenant()));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://sandbox.dataforseo.com/v3/dataforseo_labs/google/domain_rank_overview/live' && $r->hasHeader('Authorization', 'Basic '.base64_encode(self::DUMMY_LOGIN.':'.self::DUMMY_PASSWORD)));
        $this->assertFalse($options['allow_redirects']);
        $this->assertTrue($options['verify']);
        foreach (Operation::cases() as $operation) {
            $this->assertStringStartsWith('https://sandbox.dataforseo.com/v3/', $operation->url('sandbox'));
        }
    }

    public function test_sandbox_does_not_create_reserve_or_spend_real_budget_even_if_fixture_has_cost(): void
    {
        $tenant = $this->tenant();
        $budget = SeoBudget::create(['workspace_id' => $tenant[1]->id, 'mode' => 'live', 'month' => now()->format('Y-m'), 'limit_micros' => 0, 'spent_micros' => 0, 'held_micros' => 0, 'blocked' => true]);
        $this->fetch($this->snapshot($tenant));
        $this->assertDatabaseCount('seo_budgets', 1);
        $this->assertSame(0, $budget->fresh()->spent_micros);
        $this->assertTrue($budget->fresh()->blocked);
        $log = ApiUsageLog::firstOrFail();
        $this->assertTrue($log->is_demo);
        $this->assertSame('sandbox', $log->mode);
        $this->assertSame('0.000000', $log->cost);
        $this->assertSame(0, $log->reserved_micros);
        $this->assertNull($log->seo_budget_id);
        $this->assertSame(11000, $log->reported_cost_micros);
    }

    public function test_generic_sandbox_fixture_is_not_misrepresented_as_requested_domain_and_market(): void
    {
        $this->fake(function (Request $r) {
            $body = MockResponses::forRequest($r);
            $body['tasks'][0]['data'] = ['target' => 'fixture.com', 'language_code' => 'en', 'location_code' => 2840, 'login' => self::DUMMY_LOGIN];
            $body['tasks'][0]['result'][0]['target'] = 'fixture.com';
            $body['tasks'][0]['result'][0]['language_code'] = 'en';
            $body['tasks'][0]['result'][0]['location_code'] = 2840;
            $body['tasks'][0]['result'][0]['echoed_password'] = self::DUMMY_PASSWORD;

            return Http::response($body);
        });
        $snapshot = $this->snapshot($this->tenant());
        $response = $this->fetch($snapshot);
        $this->assertArrayNotHasKey('target', $response->result);
        $this->assertArrayNotHasKey('language_code', $response->result);
        $serialized = ApiUsageLog::first()->toJson();
        $this->assertStringNotContainsString(self::DUMMY_PASSWORD, $serialized);
        $this->assertStringNotContainsString(self::DUMMY_LOGIN, $serialized);
        $this->assertSame('SANDBOX / TEST VERİSİ', $snapshot->sourceLabel());
    }

    public function test_missing_credentials_and_disabled_sandbox_never_send_http(): void
    {
        $snapshot = $this->snapshot($this->tenant());
        config(['seo.login' => '', 'seo.password' => '']);
        $this->fails(fn () => $this->fetch($snapshot), 'sandbox_credentials_missing');
        Http::assertNothingSent();
        config(['seo.sandbox_enabled' => false]);
        $this->fails(fn () => $this->fetch($snapshot), 'sandbox_disabled');
        Http::assertNothingSent();
        $this->assertDatabaseCount('seo_budgets', 0);
    }

    public function test_sandbox_is_local_only_and_rejects_non_overview_operations(): void
    {
        $snapshot = $this->snapshot($this->tenant());
        $this->fails(fn () => $this->fetch($snapshot, Operation::Keywords), 'sandbox_overview_only');
        Http::assertNothingSent();
        $this->app->instance('env', 'production');
        $this->fails(fn () => $this->fetch($snapshot), 'sandbox_disabled');
        Http::assertNothingSent();
    }

    public function test_sandbox_rate_limit_error_does_not_automatically_retry(): void
    {
        $this->fake(function (Request $r) {
            $body = MockResponses::forRequest($r);
            $body['cost'] = $body['tasks'][0]['cost'] = 0;
            $body['tasks_error'] = 1;
            $body['tasks'][0]['status_code'] = 40202;

            return Http::response($body);
        });
        $this->fails(fn () => $this->fetch($this->snapshot($this->tenant())), 'provider_rate_limit');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('seo_budgets', 0);
    }

    public function test_sandbox_redirect_cannot_forward_auth_to_another_host(): void
    {
        $this->fake(['*' => Http::response('', 302, ['Location' => 'https://api.dataforseo.com/v3/'])]);
        $this->fails(fn () => $this->fetch($this->snapshot($this->tenant())), 'http_302');
        Http::assertSentCount(1);
        $this->assertSame('0.000000', ApiUsageLog::first()->cost);
        $this->assertDatabaseCount('seo_budgets', 0);
    }

    public function test_command_is_dry_run_without_send_and_needs_no_credentials(): void
    {
        [$user,$workspace,$project] = $this->tenant();
        config(['seo.login' => '', 'seo.password' => '']);
        $this->artisan('rankviora:seo-sandbox', ['project' => $project->id, 'user' => $user->id])->assertSuccessful();
        Http::assertNothingSent();
        $this->assertDatabaseCount('seo_snapshots', 0);
    }

    public function test_command_sends_only_one_overview_then_uses_cache_and_labels_ui_and_csv(): void
    {
        [$user,$workspace,$project] = $this->tenant();
        $args = ['project' => $project->id, 'user' => $user->id, '--send' => true];
        $this->artisan('rankviora:seo-sandbox', $args)->assertSuccessful();
        Http::assertSentCount(1);
        $this->artisan('rankviora:seo-sandbox', $args)->assertSuccessful();
        Http::assertSentCount(1);
        $snapshot = SeoSnapshot::latest('id')->firstOrFail();
        $this->assertTrue($snapshot->is_demo);
        $this->assertSame('completed', $snapshot->status);
        $this->assertTrue($snapshot->coverage['overview']['overview_only']);
        $this->assertDatabaseCount('api_usage_logs', 1);
        $this->assertDatabaseCount('seo_budgets', 0);
        $base = '/workspaces/'.$workspace->id.'/seo-engine/projects/'.$project->id;
        $this->actingAs($user)->get($base)->assertOk()->assertSee('SANDBOX / TEST VERİSİ')->assertSee('gerçek performansı değildir')->assertDontSee('MOCK analizi başlat');
        $this->get($base.'?tab=history')->assertOk()->assertSee('SANDBOX / TEST VERİSİ');
        $keyword = $snapshot->keywords()->create(['keyword' => 'sandbox fixture', 'kind' => 'organic']);
        $keyword->rankings()->create(['domain' => 'example.com', 'position' => 1, 'url' => 'https://example.com/']);
        $csv = $this->get($base.'/export?snapshot='.$snapshot->id)->assertOk()->streamedContent();
        $this->assertStringContainsString('SANDBOX / TEST VERİSİ', $csv);
    }

    public function test_secrets_never_enter_console_errors_or_usage_receipts(): void
    {
        [$user,$workspace,$project] = $this->tenant();
        Log::spy();
        $this->fake(fn () => throw new ConnectionException(self::DUMMY_LOGIN.' '.self::DUMMY_PASSWORD));
        $status = Artisan::call('rankviora:seo-sandbox', ['project' => $project->id, 'user' => $user->id, '--send' => true]);
        $this->assertSame(1, $status);
        $output = Artisan::output().ApiUsageLog::first()->toJson().SeoSnapshot::first()->toJson();
        $this->assertStringNotContainsString(self::DUMMY_LOGIN, $output);
        $this->assertStringNotContainsString(self::DUMMY_PASSWORD, $output);
        $this->assertStringContainsString('timeout_unknown_cost', $output);
        $this->assertDatabaseCount('seo_budgets', 0);
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_transport_exceptions_are_sanitized_without_previous_exception(): void
    {
        $this->fake(fn () => throw new \RuntimeException(self::DUMMY_PASSWORD));
        $this->fails(fn () => $this->fetch($this->snapshot($this->tenant())), 'transport_unknown');
        $this->assertStringNotContainsString(self::DUMMY_PASSWORD, ApiUsageLog::first()->toJson());
    }

    public function test_web_and_worker_cannot_launch_multi_endpoint_sandbox_analysis(): void
    {
        [$user,$workspace,$project] = $this->tenant();
        $this->fails(fn () => $this->app->make(SnapshotDispatcher::class)->dispatch($user, $project, null), 'sandbox_cli_only');
        $snapshot = $this->snapshot([$user, $workspace, $project]);
        $this->app->make(SnapshotRunner::class)->run($snapshot->id);
        $this->assertSame('sandbox_cli_only', $snapshot->fresh()->error_code);
        Http::assertNothingSent();
    }

    public function test_foreign_user_and_revoked_membership_cannot_query_sandbox(): void
    {
        $tenant = $this->tenant();
        $snapshot = $this->snapshot($tenant);
        $tenant[1]->users()->detach($tenant[0]);
        $this->fails(fn () => $this->fetch($snapshot), 'access_revoked');
        $foreign = User::factory()->create();
        $this->artisan('rankviora:seo-sandbox', ['project' => $tenant[2]->id, 'user' => $foreign->id, '--send' => true])->assertFailed();
        Http::assertNothingSent();
        $this->assertDatabaseCount('api_usage_logs', 0);
    }

    public function test_sandbox_and_mock_cache_are_separate(): void
    {
        $tenant = $this->tenant();
        $this->fetch($this->snapshot($tenant));
        config(['seo.mode' => 'mock']);
        $this->fetch($this->snapshot($tenant));
        Http::assertSentCount(2);
        $this->assertSame(['mock', 'sandbox'], ApiUsageLog::orderBy('mode')->pluck('mode')->all());
    }

    public function test_overview_command_refuses_mock_and_live_modes_even_with_send(): void
    {
        [$user,$workspace,$project] = $this->tenant();
        foreach (['mock', 'live'] as $mode) {
            config(['seo.mode' => $mode, 'seo.live_enabled' => true, 'seo.live_approval' => 'approved-after-safety-review']);
            $this->artisan('rankviora:seo-sandbox',['project' => $project->id, 'user' => $user->id, '--send' => true])->assertFailed();
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('seo_snapshots',0);
    }
}
