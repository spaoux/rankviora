<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use App\Seo\DomainName;
use App\Seo\Dto\OrganicMetrics;
use App\Seo\Dto\SeoRequest;
use App\Seo\Operation;
use App\Seo\SeoException;
use App\Seo\SeoGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SandboxSeoOverview extends Command
{
    protected $signature = 'rankviora:seo-sandbox {project : Yerel proje ID} {user : Workspace sahibi kullanıcı ID} {--send : En fazla bir resmi Sandbox Overview isteği gönder}';

    protected $description = 'Ücretsiz SANDBOX Overview kontrolü; varsayılan dry-run, credential veya ham yanıt yazdırmaz';

    public function handle(SeoGateway $gateway): int
    {
        $snapshot = null;
        try {
            if (! in_array(app()->environment(), ['local', 'testing'], true) || config('seo.mode') !== 'sandbox' || ! config('seo.sandbox_enabled')) {
                throw new SeoException('sandbox_disabled');
            }
            $project = Project::with('workspace')->findOrFail($this->argument('project'));
            $user = User::findOrFail($this->argument('user'));
            Gate::forUser($user)->authorize('update', $project->workspace);
            $domain = DomainName::normalize($project->domain);
            if (! $this->option('send')) {
                $this->info('SANDBOX dry-run: ağ isteği gönderilmedi. Gerçek SEO verisi değildir; gerçek maliyet $0.');
                $this->line(Operation::Overview->url('sandbox'));

                return self::SUCCESS;
            }
            if (! config('seo.login') || ! config('seo.password')) {
                throw new SeoException('sandbox_credentials_missing');
            }
            $snapshot = $project->seoSnapshots()->create(['requested_by' => $user->id, 'domain' => $domain, 'mode' => 'sandbox', 'is_demo' => true, 'status' => 'processing']);
            $response = $gateway->fetch($snapshot, new SeoRequest(Operation::Overview, $domain, limit: 1));
            $metrics = OrganicMetrics::fromArray($response->result['items'][0]['metrics']['organic'] ?? []);
            DB::transaction(function () use ($snapshot, $response, $metrics) {
                $snapshot->metrics()->create(['organic_keywords' => $metrics->count, 'estimated_traffic' => $metrics->estimatedTraffic,
                    'top3' => $metrics->top3, 'top10' => $metrics->top10, 'top100' => $metrics->top100, 'top_pages' => []]);
                $snapshot->update(['status' => 'completed', 'completed_at' => now(), 'coverage' => ['overview' => [
                    'stored' => 1, 'total' => $response->result['total_count'], 'truncated' => false, 'retrieved_at' => $response->retrievedAt,
                    'sandbox_fixture' => true, 'overview_only' => true,
                ]]]);
            });
            $this->info('SANDBOX / TEST VERİSİ: Overview kontrolü tamamlandı. Gerçek API maliyeti $0; canlı bütçe kullanılmadı.');
            $this->line('Snapshot #'.$snapshot->id.'. Cache varsa yeni ağ isteği yapılmaz; otomatik retry yoktur.');

            return self::SUCCESS;
        } catch (SeoException $error) {
            $snapshot?->update(['status' => 'failed', 'error_code' => $error->reason, 'completed_at' => now()]);
            $this->error('SANDBOX kontrolü başarısız: '.$error->reason);
        } catch (\Throwable) {
            $snapshot?->update(['status' => 'failed', 'error_code' => 'sandbox_check_failed', 'completed_at' => now()]);
            $this->error('SANDBOX kontrolü başarısız: sandbox_check_failed. Proje ID ve workspace sahipliğini kontrol edin.');
        }

        return self::FAILURE;
    }
}
