<?php

namespace App\Livewire;

use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProjectManager extends Component
{
    #[Locked]
    public int $workspaceId;

    public string $name = '';

    public string $domain = '';

    public string $competitorDomain = '';

    #[Locked]
    public ?int $editingId = null;

    private function workspace(): Workspace
    {
        $w = Workspace::findOrFail($this->workspaceId);
        Gate::authorize('view', $w);

        return $w;
    }

    private function writable(): Workspace
    {
        $w = $this->workspace();
        Gate::authorize('update', $w);

        return $w;
    }

    private function normalize(string $domain): string
    {
        return strtolower(rtrim(trim($domain), '.'));
    }

    private function domainRules(): array
    {
        return ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'];
    }

    public function save(): void
    {
        $w = $this->writable();
        $this->domain = $this->normalize($this->domain);
        $data = $this->validate(['name' => 'required|string|max:100', 'domain' => [...$this->domainRules(), Rule::unique('projects', 'domain')->where('workspace_id', $w->id)->ignore($this->editingId)]]);
        if ($this->editingId) {
            $w->projects()->findOrFail($this->editingId)->update($data);
        } else {
            $w->projects()->create($data);
        }
        $this->cancel();
        session()->flash('success', 'Proje kaydedildi.');
    }

    public function edit(int $id): void
    {
        $p = $this->writable()->projects()->findOrFail($id);
        $this->editingId = $p->id;
        $this->name = $p->name;
        $this->domain = $p->domain;
    }

    public function cancel(): void
    {
        $this->reset('name', 'domain', 'editingId');
        $this->resetValidation();
    }

    public function delete(int $id): void
    {
        $this->writable()->projects()->findOrFail($id)->delete();
    }

    public function addCompetitor(int $id): void
    {
        $p = $this->writable()->projects()->findOrFail($id);
        $this->competitorDomain = $this->normalize($this->competitorDomain);
        $this->validate(['competitorDomain' => [...$this->domainRules(), Rule::notIn([$p->domain]), Rule::unique('competitors', 'domain')->where('project_id', $p->id)]]);
        $p->competitors()->create(['domain' => $this->competitorDomain]);
        $this->reset('competitorDomain');
    }

    public function deleteCompetitor(int $projectId, int $id): void
    {
        $this->writable()->projects()->findOrFail($projectId)->competitors()->findOrFail($id)->delete();
    }

    public function demo(int $id, string $type): void
    {
        abort_unless(in_array($type, ['seo', 'geo']), 422);
        $p = $this->writable()->projects()->findOrFail($id);
        $p->analysisRuns()->create(['type' => $type, 'is_demo' => true, 'status' => 'completed', 'results' => ['score' => $type === 'seo' ? 78 : 64, 'source' => 'DEMO: sabit örnek veri; harici API çağrısı yapılmadı.']]);
        session()->flash('success', 'DEMO analizi geçmişe kaydedildi. Ücretli API kullanılmadı.');
    }

    public function render(): View
    {
        $w = $this->workspace();

        return view('livewire.project-manager', ['workspace' => $w, 'projects' => $w->projects()->with('competitors')->latest()->get()]);
    }
}
