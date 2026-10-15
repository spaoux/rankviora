@extends('layouts.app')
@section('title', 'Workspace’ler')
@section('content')
<div class="mb-8"><p class="eyebrow">ÇALIŞMA ALANLARINIZ</p><h1 class="page-title">İyi kararlar, tek bir yerden.</h1><p class="subtitle">Projelerinizi ve ekibinizi ayrı, güvenli workspace’lerde yönetin.</p></div>
<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
    @forelse($workspaces as $item)
    <a href="{{ route('workspace.show', [$item,'seo']) }}" class="card p-6 hover:border-teal-400 transition"><div class="text-teal-600 text-2xl mb-6">▦</div><h2 class="text-xl font-semibold">{{ $item->name }}</h2><p class="text-sm text-slate-500 mt-3">{{ $item->pivot->role === 'owner' ? 'Workspace sahibi' : 'Ekip üyesi' }}</p><div class="mt-6 text-teal-700 text-sm font-semibold">Workspace’i aç →</div></a>
    @empty<div class="card p-6"><h2 class="font-semibold">İlk workspace’inizi oluşturun</h2><p class="subtitle">Henüz bir çalışma alanınız bulunmuyor. Sağdaki form ile başlayabilirsiniz.</p></div>@endforelse
    <div class="card p-6"><h2 class="text-lg font-semibold mb-5">Yeni workspace</h2><form method="POST" action="{{ route('workspace.store') }}" class="space-y-4">@csrf<label class="block text-sm">Workspace adı<input name="name" class="input mt-2" placeholder="Örn. Marka ekibi" maxlength="100" required></label><button class="btn w-full">Workspace oluştur</button></form></div>
</div>
@endsection
