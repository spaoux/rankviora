<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('domain');
            $t->string('competitor_domain')->nullable();
            $t->string('provider')->default('dataforseo');
            $t->string('mode');
            $t->boolean('is_demo');
            $t->unsignedInteger('location_code')->default(2792);
            $t->string('language_code', 10)->default('tr');
            $t->string('status')->default('queued');
            $t->string('error_code')->nullable();
            $t->json('coverage')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['project_id', 'mode', 'status']);
        });
        Schema::create('seo_domain_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seo_snapshot_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('organic_keywords');
            $t->decimal('estimated_traffic', 20, 4);
            $t->unsignedBigInteger('top3');
            $t->unsignedBigInteger('top10');
            $t->unsignedBigInteger('top100');
            $t->json('top_pages');
            $t->timestamps();
        });
        Schema::create('seo_keywords', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seo_snapshot_id')->constrained()->cascadeOnDelete();
            $t->text('keyword');
            $t->string('kind', 20);
            $t->boolean('competitor_better')->default(false);
            $t->unsignedBigInteger('search_volume')->nullable();
            $t->decimal('cpc', 16, 6)->nullable();
            $t->timestamp('volume_updated_at')->nullable();
            $t->timestamps();
            $t->index(['seo_snapshot_id', 'kind']);
        });
        Schema::create('seo_rankings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seo_keyword_id')->constrained()->cascadeOnDelete();
            $t->string('domain');
            $t->unsignedInteger('position');
            $t->text('url');
            $t->decimal('estimated_traffic', 20, 4)->nullable();
            $t->timestamps();
            $t->unique(['seo_keyword_id', 'domain']);
        });
        Schema::create('seo_budgets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('mode', 10);
            $t->string('month', 7);
            $t->unsignedBigInteger('limit_micros');
            $t->unsignedBigInteger('spent_micros')->default(0);
            $t->unsignedBigInteger('held_micros')->default(0);
            $t->boolean('blocked')->default(false);
            $t->timestamps();
            $t->unique(['workspace_id', 'mode', 'month']);
        });
        Schema::table('api_usage_logs', function (Blueprint $t) {
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('seo_snapshot_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('seo_budget_id')->nullable()->constrained()->nullOnDelete();
            $t->string('mode', 10)->nullable();
            $t->string('status')->nullable();
            $t->string('request_hash', 64)->nullable()->index();
            $t->string('task_id', 100)->nullable();
            $t->integer('provider_status')->nullable();
            $t->string('error_code')->nullable();
            $t->unsignedBigInteger('reserved_micros')->default(0);
            $t->unsignedBigInteger('reported_cost_micros')->nullable();
            $t->json('response_payload')->nullable();
            $t->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('api_usage_logs', function (Blueprint $t) {
            foreach (['project_id', 'seo_snapshot_id', 'seo_budget_id'] as $column) {
                $t->dropConstrainedForeignId($column);
            }
            $t->dropIndex(['request_hash']);
            $t->dropColumn(['mode', 'status', 'request_hash', 'task_id', 'provider_status', 'error_code', 'reserved_micros', 'reported_cost_micros', 'response_payload', 'expires_at']);
        });
        foreach (['seo_rankings', 'seo_keywords', 'seo_domain_metrics', 'seo_budgets', 'seo_snapshots'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
