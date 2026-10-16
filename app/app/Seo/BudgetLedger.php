<?php

namespace App\Seo;

use App\Models\ApiUsageLog;
use App\Models\SeoBudget;
use App\Models\SeoSnapshot;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class BudgetLedger
{
    public function reconcile(int $id, int $cost, string $note): void
    {
        if ($cost < 0 || trim($note) === '') {
            throw new SeoException('invalid_reconciliation');
        }
        DB::transaction(function () use ($id, $cost, $note) {
            $log = ApiUsageLog::findOrFail($id);
            Workspace::lockForUpdate()->findOrFail($log->workspace_id);
            $log = ApiUsageLog::lockForUpdate()->findOrFail($id);
            if (! in_array($log->status, ['pending', 'uncertain'], true)) {
                throw new SeoException('already_settled');
            }
            $budget = SeoBudget::findOrFail($log->seo_budget_id);
            $budget->held_micros -= $log->reserved_micros;
            $budget->spent_micros += $cost;
            $log->update(['status' => 'reconciled', 'reported_cost_micros' => $cost, 'cost' => Money::decimal($log->is_demo ? 0 : $cost), 'reconciliation_note' => $note]);
            $budget->blocked = $budget->spent_micros > $budget->limit_micros || ApiUsageLog::where('seo_budget_id', $budget->id)->whereIn('status', ['pending', 'uncertain'])->exists();
            $budget->save();
        });
    }

    public function reserve(SeoSnapshot $snapshot, string $operation, string $hash): ApiUsageLog
    {
        return DB::transaction(function () use ($snapshot, $operation, $hash) {
            $workspace = Workspace::lockForUpdate()->findOrFail($snapshot->project->workspace_id);
            $budget = SeoBudget::firstOrCreate(['workspace_id' => $workspace->id, 'mode' => $snapshot->mode, 'month' => now('UTC')->format('Y-m')], ['limit_micros' => config('seo.monthly_budget_micros')]);
            if ($budget->blocked || $workspace->apiUsageLogs()->where('mode', $snapshot->mode)->whereIn('status', ['pending', 'uncertain'])->exists()) {
                throw new SeoException('budget_review_required');
            }
            $ceiling = (int) config('seo.request_ceiling_micros');
            if ($ceiling <= 0 || $budget->spent_micros + $budget->held_micros + $ceiling > $budget->limit_micros) {
                throw new SeoException('budget_exceeded');
            }
            $budget->increment('held_micros', $ceiling);

            return $workspace->apiUsageLogs()->create([
                'project_id' => $snapshot->project_id, 'seo_snapshot_id' => $snapshot->id, 'seo_budget_id' => $budget->id,
                'user_id' => $snapshot->requested_by, 'provider' => 'dataforseo', 'operation' => $operation, 'mode' => $snapshot->mode,
                'request_hash' => $hash, 'status' => 'pending', 'is_demo' => $snapshot->is_demo, 'cost' => 0, 'units' => 1, 'reserved_micros' => $ceiling,
            ]);
        });
    }

    public function settle(ApiUsageLog $log, ?int $cost, string $status, ?int $providerStatus = null, ?string $taskId = null, ?string $error = null, ?array $payload = null): void
    {
        DB::transaction(function () use ($log, $cost, $status, $providerStatus, $taskId, $error, $payload) {
            Workspace::lockForUpdate()->findOrFail($log->workspace_id);
            $log = ApiUsageLog::lockForUpdate()->findOrFail($log->id);
            if ($log->status !== 'pending') {
                throw new SeoException('already_settled');
            }
            $budget = SeoBudget::findOrFail($log->seo_budget_id);
            if ($cost === null) {
                $budget->update(['blocked' => true]);
                $status = 'uncertain';
            } else {
                $budget->held_micros -= $log->reserved_micros;
                $budget->spent_micros += $cost;
                $budget->blocked = $budget->blocked || $cost > $log->reserved_micros || $budget->spent_micros > $budget->limit_micros;
                $budget->save();
            }
            $log->update(['status' => $status, 'reported_cost_micros' => $cost, 'cost' => Money::decimal($log->is_demo ? 0 : ($cost ?? 0)),
                'provider_status' => $providerStatus, 'task_id' => $taskId, 'error_code' => $error, 'response_payload' => $payload,
                'expires_at' => $payload === null ? null : now()->addSeconds(config('seo.cache_seconds'))]);
        });
    }
}
