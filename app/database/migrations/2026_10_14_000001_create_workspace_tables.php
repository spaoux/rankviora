<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('workspace_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role')->default('member');
            $t->timestamps();
            $t->unique(['workspace_id', 'user_id']);
        });
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('domain');
            $t->timestamps();
            $t->unique(['workspace_id', 'domain']);
        });
        Schema::create('competitors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->string('domain');
            $t->timestamps();
            $t->unique(['project_id', 'domain']);
        });
        Schema::create('analysis_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->string('status')->default('completed');
            $t->boolean('is_demo')->default(true);
            $t->json('results');
            $t->timestamps();
        });
        Schema::create('api_usage_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('provider');
            $t->string('operation');
            $t->unsignedInteger('units')->default(0);
            $t->decimal('cost', 12, 6)->default(0);
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['api_usage_logs', 'analysis_runs', 'competitors', 'projects', 'workspace_user', 'workspaces'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
