<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SeoBudget;
use App\Models\Workspace;
use App\Seo\CsvCell;
use App\Seo\KeywordQuery;
use App\Seo\SeoException;
use App\Seo\SnapshotDispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeoController extends Controller
{
    public function index(Workspace $workspace): View
    {
        Gate::authorize('view', $workspace);

        return view('seo.index', ['workspace' => $workspace, 'section' => 'engine', 'projects' => $workspace->projects()->withCount('seoSnapshots')->get()]);
    }

    private function project(Workspace $workspace, int $project): Project
    {
        Gate::authorize('view', $workspace);

        return $workspace->projects()->with('competitors')->findOrFail($project);
    }

    private function filters(Request $request): array
    {
        return $request->validate(['snapshot' => 'nullable|integer|min:1', 'tab' => 'nullable|in:overview,keywords,gap,history',
            'q' => 'nullable|string|max:100', 'kind' => 'nullable|in:missing,shared,better', 'sort' => 'nullable|in:keyword,position,volume,cpc,traffic',
            'direction' => 'nullable|in:asc,desc', 'min_volume' => 'nullable|integer|min:0|max:1000000000',
            'min_position' => 'nullable|integer|min:1|max:1000', 'max_position' => 'nullable|integer|min:1|max:1000',
            'per_page' => 'nullable|in:10,25,50,100']);
    }

    public function show(Request $request, Workspace $workspace, int $project, KeywordQuery $query): View
    {
        $project = $this->project($workspace, $project);
        $filters = array_filter($this->filters($request), fn ($value) => $value !== null);
        $snapshot = isset($filters['snapshot']) ? $project->seoSnapshots()->findOrFail($filters['snapshot']) : $project->seoSnapshots()->latest('id')->first();
        $tab = $filters['tab'] ?? 'overview';
        $keywords = $snapshot && $snapshot->status === 'completed' && in_array($tab, ['keywords', 'gap'], true) ? $query->build($snapshot, $filters, $tab === 'gap')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString() : null;
        $history = $project->seoSnapshots()->latest('id')->paginate(20, ['*'], 'history_page');
        $budgets = SeoBudget::where('workspace_id', $workspace->id)->where('month', now('UTC')->format('Y-m'))->get();

        return view('seo.show', compact('workspace', 'project', 'snapshot', 'tab', 'filters', 'keywords', 'history', 'budgets') + ['section' => 'engine']);
    }

    public function store(Request $request, Workspace $workspace, int $project, SnapshotDispatcher $dispatcher): RedirectResponse
    {
        $project = $this->project($workspace, $project);
        Gate::authorize('update', $workspace);
        $data = $request->validate(['competitor_id' => 'nullable|integer|min:1']);
        try {
            $snapshot = $dispatcher->dispatch($request->user(), $project, isset($data['competitor_id']) ? (int) $data['competitor_id'] : null);
        } catch (SeoException $error) {
            return back()->withErrors(['seo' => 'Analiz başlatılamadı: '.$error->reason]);
        }

        return redirect()->route('seo.show', [$workspace, $project, 'snapshot' => $snapshot->id])->with('success', 'SEO analizi kuyruğa alındı. Sayfayı yenileyerek sonucu takip edebilirsiniz.');
    }

    public function export(Request $request, Workspace $workspace, int $project, KeywordQuery $query): StreamedResponse
    {
        $project = $this->project($workspace, $project);
        $filters = array_filter($this->filters($request), fn ($value) => $value !== null);
        $snapshot = $project->seoSnapshots()->findOrFail($filters['snapshot'] ?? 0);
        abort_unless($snapshot->status === 'completed', 422);
        $rows = $query->build($snapshot, $filters, ($filters['tab'] ?? '') === 'gap');
        abort_if((clone $rows)->count() > 10000, 422, 'Export en fazla 10000 satır içerebilir.');

        return response()->streamDownload(function () use ($rows, $snapshot) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Anahtar kelime', 'Google pozisyonu', 'Arama hacmi', 'CPC (USD)', 'Tahmini trafik (ETV)', 'Sıralanan URL', 'Rakip pozisyonu', 'Kaynak', 'Snapshot'], ',', '"', '');
            foreach ($rows->cursor() as $row) {
                fputcsv($out, array_map([CsvCell::class, 'safe'], [$row->keyword, $row->own_position, $row->search_volume, $row->cpc, $row->own_traffic, $row->own_url ?? $row->rival_url, $row->rival_position, $snapshot->sourceLabel(), $snapshot->id]), ',', '"', '');
            }
            fclose($out);
        }, 'rankviora-'.$snapshot->id.'-'.$snapshot->mode.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
