<?php

namespace App\Seo\Dto;

use App\Seo\Money;
use App\Seo\SeoException;

final readonly class ProviderResponse
{
    public function __construct(public string $taskId, public int $costMicros, public array $result, public string $retrievedAt) {}

    public static function fromArray(array $body, SeoRequest $request, bool $sandbox = false): self
    {
        $cost = Money::micros($body['cost'] ?? null);
        $task = $body['tasks'][0] ?? null;
        if (($body['tasks_count'] ?? null) !== 1 || ! is_array($body['tasks'] ?? null) || count($body['tasks']) !== 1
            || ! is_array($task) || ! is_string($task['id'] ?? null) || strlen($task['id']) > 100
            || Money::micros($task['cost'] ?? null) !== $cost || ! is_int($body['status_code'] ?? null) || ! is_int($task['status_code'] ?? null)) {
            throw new SeoException('invalid_envelope');
        }
        $taskId = $sandbox ? 'sandbox-'.hash('sha256', $task['id']) : $task['id'];
        $status = $body['status_code'] === 20000 ? $task['status_code'] : $body['status_code'];
        if ($status !== 20000) {
            $reason = match ($status) {
                40100 => 'provider_auth', 40202, 40209 => 'provider_rate_limit', 40200, 40203, 40210 => 'provider_budget', default => 'provider_error'
            };
            throw new SeoException($reason, $reason === 'provider_rate_limit' && $cost === 0, $cost, $status, $taskId);
        }
        if (($body['tasks_error'] ?? null) !== 0 || ($task['result_count'] ?? null) !== 1 || ! is_array($task['result'] ?? null) || count($task['result']) !== 1 || ! is_array($task['result'][0] ?? null)) {
            throw new SeoException('invalid_result', false, $cost, $status, $taskId);
        }
        foreach ($request->task() as $key => $value) {
            if (! $sandbox && in_array($key, ['target', 'target1', 'target2', 'location_code', 'language_code', 'intersections'], true) && ($task['data'][$key] ?? null) !== $value) {
                throw new SeoException('response_target_mismatch', false, $cost, $status, $taskId);
            }
        }
        $result = $task['result'][0];
        if ((! $sandbox && (($result['location_code'] ?? null) !== $request->location || ($result['language_code'] ?? null) !== $request->language))
            || ! is_int($result['total_count'] ?? null) || $result['total_count'] < 0
            || ! is_int($result['items_count'] ?? null) || $result['items_count'] < 0
            || (! is_array($result['items'] ?? null) && ! ($result['items_count'] === 0 && ($result['items'] ?? null) === null))
            || ($result['items_count'] !== count($result['items'] ?? [])) || $result['items_count'] > $request->limit
            || $result['items_count'] > $result['total_count']) {
            throw new SeoException('invalid_items', false, $cost, $status, $taskId);
        }

        if ($sandbox) {
            // Sandbox returns generic fixtures, not measurements for the requested target/market.
            // Keep only validated Overview fields; never persist arbitrary echoed request data.
            $items = [];
            foreach ($result['items'] ?? [] as $item) {
                $organic = is_array($item) ? ($item['metrics']['organic'] ?? []) : [];
                OrganicMetrics::fromArray($organic);
                $items[] = ['metrics' => ['organic' => array_intersect_key($organic, array_flip([
                    'count', 'etv', 'pos_1', 'pos_2_3', 'pos_4_10', 'pos_11_20', 'pos_21_30', 'pos_31_40', 'pos_41_50', 'pos_51_60', 'pos_61_70', 'pos_71_80', 'pos_81_90', 'pos_91_100',
                ]))]];
            }
            $result = ['total_count' => $result['total_count'], 'items_count' => $result['items_count'], 'items' => $items];
        }

        return new self($taskId, $cost, $result, now()->toIso8601String());
    }
}
