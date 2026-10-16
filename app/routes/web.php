<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', [WorkspaceController::class, 'index'])->name('home');
    Route::get('/workspaces/{workspace}/seo-engine', [SeoController::class, 'index'])->name('seo.index');
    Route::get('/workspaces/{workspace}/seo-engine/projects/{project}/export', [SeoController::class, 'export'])->name('seo.export');
    Route::get('/workspaces/{workspace}/seo-engine/projects/{project}', [SeoController::class, 'show'])->name('seo.show');
    Route::post('/workspaces/{workspace}/seo-engine/projects/{project}', [SeoController::class, 'store'])->middleware('throttle:6,1')->name('seo.store');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspace.store');
    Route::patch('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspace.update');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspace.destroy');
    Route::post('/workspaces/{workspace}/members', [WorkspaceController::class, 'member'])->name('workspace.member');
    Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'removeMember'])->name('workspace.remove-member');
    Route::get('/workspaces/{workspace}/{section?}', [WorkspaceController::class, 'show'])->name('workspace.show');
});
