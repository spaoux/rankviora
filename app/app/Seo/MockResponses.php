<?php

namespace App\Seo;

use Illuminate\Http\Client\Request;

final class MockResponses
{
    public static function envelope(array $task, array $result, float $cost = 0.011): array
    {
        return ['status_code' => 20000, 'cost' => $cost, 'tasks_count' => 1, 'tasks_error' => 0, 'tasks' => [
            ['id' => 'mock-'.hash('sha256', json_encode($task)), 'status_code' => 20000, 'cost' => $cost, 'result_count' => 1, 'data' => $task, 'result' => [$result]],
        ]];
    }

    public static function organic(): array
    {
        return ['count' => 248, 'etv' => 1240.5, 'pos_1' => 3, 'pos_2_3' => 12, 'pos_4_10' => 25, 'pos_11_20' => 40, 'pos_21_30' => 28, 'pos_31_40' => 25, 'pos_41_50' => 25, 'pos_51_60' => 20, 'pos_61_70' => 20, 'pos_71_80' => 20, 'pos_81_90' => 15, 'pos_91_100' => 15];
    }

    public static function keyword(string $word): array
    {
        return ['keyword' => $word, 'keyword_info' => ['search_volume' => 1900, 'cpc' => 1.25, 'last_updated_time' => '2026-09-01 00:00:00 +00:00']];
    }

    public static function ranking(string $domain, int $rank): array
    {
        return ['type' => 'organic', 'rank_group' => $rank, 'url' => 'https://'.$domain.'/seo', 'etv' => 125.5];
    }

    public static function forRequest(Request $request): array
    {
        $task = $request->data()[0];
        $domain = $task['target'] ?? $task['target1'];
        $items = match (true) {
            str_contains($request->url(), '/domain_rank_overview/') => [['target' => $domain, 'metrics' => ['organic' => self::organic()]]],
            str_contains($request->url(), '/relevant_pages/') => [['page_address' => 'https://'.$domain.'/seo', 'metrics' => ['organic' => self::organic()]]],
            str_contains($request->url(), '/domain_intersection/') => ($task['intersections'] ? [
                ['keyword_data' => self::keyword('seo analizi'), 'first_domain_serp_element' => self::ranking($domain, 3), 'second_domain_serp_element' => self::ranking($task['target2'], 8)],
                ['keyword_data' => self::keyword('organik trafik'), 'first_domain_serp_element' => self::ranking($domain, 12), 'second_domain_serp_element' => self::ranking($task['target2'], 4)],
            ] : [['keyword_data' => self::keyword('rakip fırsatı'), 'first_domain_serp_element' => self::ranking($domain, 5), 'second_domain_serp_element' => null]]),
            default => [
                ['keyword_data' => self::keyword('seo analizi'), 'ranked_serp_element' => ['serp_item' => self::ranking($domain, 8)]],
                ['keyword_data' => self::keyword('organik trafik'), 'ranked_serp_element' => ['serp_item' => self::ranking($domain, 4)]],
            ],
        };

        return self::envelope($task, ['location_code' => 2792, 'language_code' => 'tr', 'total_count' => count($items), 'items_count' => count($items), 'items' => $items]);
    }
}
