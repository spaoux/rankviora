<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        return view('workspaces.index', ['workspaces' => $request->user()->workspaces()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $workspace = DB::transaction(function () use ($request, $data) {
            $w = Workspace::create($data);
            $w->users()->attach($request->user()->id, ['role' => 'owner']);

            return $w;
        });

        return redirect()->route('workspace.show', [$workspace, 'seo']);
    }

    public function show(Request $request, Workspace $workspace, string $section = 'seo'): View
    {
        Gate::authorize('view', $workspace);
        abort_unless(in_array($section, ['seo', 'geo', 'projects', 'history', 'usage', 'team']), 404);

        return view('workspaces.show', compact('workspace', 'section'));
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('update', $workspace);
        $workspace->update($request->validate(['name' => 'required|string|max:100']));

        return back()->with('success', 'Workspace güncellendi.');
    }

    public function destroy(Workspace $workspace): RedirectResponse
    {
        Gate::authorize('delete', $workspace);
        $workspace->delete();

        return redirect()->route('home')->with('success', 'Workspace silindi.');
    }

    public function member(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('update', $workspace);
        $data = $request->validate(['email' => ['required', 'email', Rule::exists('users', 'email')]]);
        $user = User::where('email', $data['email'])->firstOrFail();
        if (! $workspace->users()->where('users.id', $user->id)->exists()) {
            $workspace->users()->attach($user->id, ['role' => 'member']);
        }

        return back()->with('success', 'Kullanıcı workspace’e eklendi.');
    }

    public function removeMember(Workspace $workspace, int $user): RedirectResponse
    {
        Gate::authorize('update', $workspace);
        abort_if($workspace->users()->where('users.id', $user)->wherePivot('role', 'owner')->exists(), 422, 'Workspace sahibi çıkarılamaz.');
        $workspace->users()->detach($user);

        return back()->with('success', 'Üyelik kaldırıldı.');
    }
}
