<div>
    @if(session('success'))<div role="status" class="mb-5 p-4 rounded-xl bg-teal-50 text-teal-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="mb-5 p-4 rounded-xl bg-red-50 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @can('update', $workspace)
    <form wire:submit="save" class="card p-6 grid md:grid-cols-3 gap-4 mb-6 items-end">
        <label class="text-sm font-medium">Proje adı<input class="input mt-2" wire:model="name" maxlength="100" required></label>
        <label class="text-sm font-medium">Domain<input class="input mt-2" wire:model="domain" placeholder="ornek.com" required><span class="text-xs text-slate-400">Protokol veya yol eklemeyin.</span></label>
        <div class="flex gap-2"><button class="btn" wire:loading.attr="disabled">{{ $editingId ? 'Değişiklikleri kaydet' : 'Proje ekle' }}</button>@if($editingId)<button type="button" class="btn-secondary" wire:click="cancel">Vazgeç</button>@endif</div>
    </form>
    @endcan
    <div class="space-y-5">
    @forelse($projects as $project)
        <article class="card p-6" wire:key="project-{{ $project->id }}"><div class="flex flex-wrap gap-4 justify-between"><div><h2 class="text-lg font-semibold">{{ $project->name }}</h2><p class="text-slate-500 text-sm mt-1">{{ $project->domain }}</p></div>@can('update', $workspace)<div class="flex gap-3"><button class="text-sm text-teal-700" wire:click="edit({{ $project->id }})">Düzenle</button><button class="text-sm text-red-600" wire:click="delete({{ $project->id }})" wire:confirm="Proje, rakipler ve analiz geçmişi silinsin mi?">Sil</button></div>@endcan</div>
        <div class="border-t border-slate-100 mt-5 pt-5"><h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-3">Rakip domain’ler</h3><div class="flex flex-wrap gap-2">@forelse($project->competitors as $competitor)<span class="badge">{{ $competitor->domain }} @can('update', $workspace)<button aria-label="{{ $competitor->domain }} rakibini kaldır" class="ml-2 text-red-500" wire:click="deleteCompetitor({{ $project->id }}, {{ $competitor->id }})" wire:confirm="Rakip silinsin mi?">×</button>@endcan</span>@empty<p class="text-sm text-slate-400">Henüz rakip eklenmedi.</p>@endforelse</div>
        @can('update', $workspace)<form wire:submit="addCompetitor({{ $project->id }})" class="flex gap-3 mt-4 max-w-lg"><label class="sr-only" for="competitor-{{ $project->id }}">Rakip domain</label><input id="competitor-{{ $project->id }}" class="input" wire:model="competitorDomain" placeholder="rakip.com" required><button class="btn-secondary whitespace-nowrap">Rakip ekle</button></form><div class="mt-5 flex flex-wrap gap-3"><button class="btn-secondary" wire:click="demo({{ $project->id }}, 'seo')" wire:loading.attr="disabled">SEO DEMO analizi</button><button class="btn-secondary" wire:click="demo({{ $project->id }}, 'geo')" wire:loading.attr="disabled">GEO DEMO analizi</button></div>@endcan</div></article>
    @empty<div class="card p-10 text-center"><h2 class="text-lg font-semibold">İlk projenizle başlayın</h2><p class="subtitle">Bir domain ekleyin, rakiplerinizi tanımlayın ve DEMO analizlerini deneyin.</p></div>@endforelse
    </div>
</div>
