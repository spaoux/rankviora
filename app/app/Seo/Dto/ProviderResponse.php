<?php

namespace App\Seo\Dto;

use App\Seo\Money;
use App\Seo\SeoException;

final readonly class ProviderResponse
{
    public function __construct(public string $taskId, public int $costMicros, public array $result, public string $retrievedAt) {}

    public static function fromArray(array $body, SeoRequest $request): self
    {
        $cost = Money::micros($body['cost'] ?? null);
        $task = $body['tasks'][0] ?? null;
        if (($body['tasks_count'] ?? null) !== 1 || ! is_array($body['tasks'] ?? null) || count($body['tasks']) !== 1
            || ! is_array($task) || ! is_string($task['id'] ?? null) || strlen($task['id']) > 100
            || Money::micros($task['cost'] ?? null) !== $cost || ! is_int($body['status_code'] ?? null) || ! is_int($task['status_code'] ?? null)) {
            throw new SeoException('invalid_envelope');
        }
        $status = $body['status_code'] === 20000 ? $task['status_code'] : $body['status_code'];
        if ($status !== 20000) {
            $reason = match ($status) {
                40100 => 'provider_auth', 40202, 40209 => 'provider_rate_limit', 40200, 40203, 40210 => 'provider_budget', default => 'provider_error'
            };
            throw new SeoException($reason, $reason === 'provider_rate_limit' && $cost === 0, $cost, $status, $task['id']);
        }
        if (($body['tasks_error'] ?? null) !== 0 || ($task['result_count'] ?? null) !== 1 || ! is_array($task['result'] ?? null) || count($task['result']) !== 1 || ! is_array($task['result'][0] ?? null)) {
            throw new SeoException('invalid_result', false, $cost, $status, $task['id']);
        }
        foreach ($request->task() as $key => $value) {
            if (in_array($key, ['target', 'target1', 'target2', 'location_code', 'language_code', 'intersections'], true) && ($task['data'][$key] ?? null) !== $value) {
                throw new SeoException('response_target_mismatch', false, $cost, $status, $task['id']);
            }
        }
        $result = $task['result'][0];
        if (($result['location_code'] ?? null) !== $request->location || ($result['language_code'] ?? null) !== $request->language
            || ! is_int($result['total_count'] ?? null) || $result['total_count'] < 0
            || ! is_int($result['items_count'] ?? null) || $result['items_count'] < 0
            || (! is_array($result['items'] ?? null) && ! ($result['items_count'] === 0 && ($result['items'] ?? null) === null))
            || ($result['items_count'] !== count($result['items'] ?? [])) || $result['items_count'] > $request->limit
            || $result['items_count'] > $result['total_count']) {
            throw new SeoException('invalid_items', false, $cost, $status, $task['id']);
        }

        return new self($task['id'], $cost, $result, now()->toIso8601String());
    }
}
