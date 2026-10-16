<?php

namespace App\Seo;

use App\Models\SeoSnapshot;
use App\Models\User;
use App\Seo\Dto\KeywordData;
use App\Seo\Dto\OrganicMetrics;
use App\Seo\Dto\RankingData;
use App\Seo\Dto\SeoRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SnapshotRunner
{
    public function __construct(private SeoGateway $gateway) {}

    public function run(int $id): void
    {
        $snapshot = DB::transaction(function () use ($id) {
            $snapshot = SeoSnapshot::lockForUpdate()->findOrFail($id);
            if ($snapshot->status !== 'queued') {
                return null;
            }
            $snapshot->update(['status' => 'processing']);

            return $snapshot;
        });
        if (! $snapshot) {
            return;
        }
        try {
            $snapshot->load('project.workspace');
            $actor = User::find($snapshot->requested_by);
            if (! $actor || ! Gate::forUser($actor)->allows('update', $snapshot->project->workspace)) {
                throw new SeoException('access_revoked');
            }
            if (DomainName::normalize($snapshot->project->domain) !== $snapshot->domain) {
                throw new SeoException('domain_changed');
            }
            if ($snapshot->competitor_domain && ! $snapshot->project->competitors()->where('domain', $snapshot->competitor_domain)->exists()) {
                throw new SeoException('competitor_removed');
            }
            $limit = (int) config('seo.row_limit');
            $overview = $this->gateway->fetch($snapshot, new SeoRequest(Operation::Overview, $snapshot->domain));
            $overviewItems = $this->items($overview->result, 1);
            $metrics = OrganicMetrics::fromArray($overviewItems[0]['metrics']['organic'] ?? []);
            $keywords = $this->gateway->fetch($snapshot, new SeoRequest(Operation::Keywords, $snapshot->domain, limit: $limit));
            $pages = $this->gateway->fetch($snapshot, new SeoRequest(Operation::Pages, $snapshot->domain, limit: $limit));
            $pageData = [];
            foreach ($this->items($pages->result, $limit) as $page) {
                $url = $page['page_address'] ?? null;
                if (! is_string($url) || strlen($url) > 8192 || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)) {
                    throw new SeoException('invalid_page');
                }
                $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                if ($host !== $snapshot->domain && ! str_ends_with($host, '.'.$snapshot->domain)) {
                    throw new SeoException('page_domain_mismatch');
                }
                $pageMetrics = OrganicMetrics::fromArray($page['metrics']['organic'] ?? []);
                $pageData[] = ['url' => $url, 'estimated_traffic' => $pageMetrics->estimatedTraffic, 'organic_keywords' => $pageMetrics->count];
            }
            $groups = ['organic' => $keywords];
            if ($snapshot->competitor_domain) {
                // target1 is always the competitor, including missing-keyword requests.
                $groups['shared'] = $this->gateway->fetch($snapshot, new SeoRequest(Operation::Intersection, $snapshot->competitor_domain, $snapshot->domain, true, $limit));
                $groups['missing'] = $this->gateway->fetch($snapshot, new SeoRequest(Operation::Intersection, $snapshot->competitor_domain, $snapshot->domain, false, $limit));
            }
            if (! Gate::forUser($actor)->allows('update', $snapshot->project->workspace)) {
                throw new SeoException('access_revoked');
            }
            DB::transaction(function () use ($snapshot, $metrics, $pageData, $groups, $limit, $overview, $pages) {
                $coverage = ['overview' => ['stored' => count($overview->result['items'] ?? []), 'total' => $overview->result['total_count'], 'truncated' => false, 'retrieved_at' => $overview->retrievedAt],
                    'pages' => ['stored' => count($pageData), 'total' => $pages->result['total_count'], 'truncated' => $pages->result['total_count'] > count($pageData), 'retrieved_at' => $pages->retrievedAt]];
                foreach ($groups as $kind => $response) {
                    $result = $response->result;
                    $items = $this->items($result, $limit);
                    $coverage[$kind] = ['stored' => count($items), 'total' => $result['total_count'], 'truncated' => $result['total_count'] > count($items), 'retrieved_at' => $response->retrievedAt];
                    foreach ($items as $item) {
                        $this->persistKeyword($snapshot, $kind, $item);
                    }
                }
                $snapshot->metrics()->create(['organic_keywords' => $metrics->count, 'estimated_traffic' => $metrics->estimatedTraffic, 'top3' => $metrics->top3, 'top10' => $metrics->top10, 'top100' => $metrics->top100, 'top_pages' => $pageData]);
                $snapshot->update(['status' => 'completed', 'completed_at' => now(), 'coverage' => $coverage]);
            });
        } catch (SeoException $error) {
            $snapshot->update(['status' => 'failed', 'error_code' => $error->reason, 'completed_at' => now()]);
        } catch (\Throwable $error) {
            $snapshot->update(['status' => 'failed', 'error_code' => 'analysis_failed', 'completed_at' => now()]);
            // Do not persist provider exception messages or credentials in failed_jobs/logs.
        }
    }

    private function items(array $result, int $limit): array
    {
        if (! is_int($result['total_count'] ?? null) || $result['total_count'] < 0) {
            throw new SeoException('invalid_items');
        }
        $items = $result['items'] ?? null;
        if ($items === null && $result['total_count'] === 0) {
            return [];
        }
        if (! is_array($items) || ! array_is_list($items) || count($items) > $limit || count($items) > $result['total_count']) {
            throw new SeoException('invalid_items');
        }

        return $items;
    }

    private function persistKeyword(SeoSnapshot $snapshot, string $kind, array $item): void
    {
        $keyword = KeywordData::fromArray($item['keyword_data'] ?? []);
        $rankings = [];
        if ($kind === 'organic') {
            $rankings[$snapshot->domain] = RankingData::fromArray($item['ranked_serp_element']['serp_item'] ?? []);
        } else {
            $rankings[$snapshot->competitor_domain] = RankingData::fromArray($item['first_domain_serp_element'] ?? []);
            if ($kind === 'shared') {
                $rankings[$snapshot->domain] = RankingData::fromArray($item['second_domain_serp_element'] ?? []);
            }
            if ($kind === 'missing' && ($item['second_domain_serp_element'] ?? null) !== null) {
                throw new SeoException('invalid_gap');
            }
        }
        foreach ($rankings as $domain => $ranking) {
            $host = strtolower((string) parse_url($ranking->url, PHP_URL_HOST));
            if ($host !== $domain && ! str_ends_with($host, '.'.$domain)) {
                throw new SeoException('ranking_domain_mismatch');
            }
        }
        $row = $snapshot->keywords()->create(['keyword' => $keyword->keyword, 'kind' => $kind, 'search_volume' => $keyword->volume, 'cpc' => $keyword->cpc,
            'volume_updated_at' => $keyword->updatedAt, 'competitor_better' => $kind === 'shared' && $rankings[$snapshot->competitor_domain]->position < $rankings[$snapshot->domain]->position]);
        foreach ($rankings as $domain => $ranking) {
            $row->rankings()->create(['domain' => $domain, 'position' => $ranking->position, 'url' => $ranking->url, 'estimated_traffic' => $ranking->estimatedTraffic]);
        }
    }
}
