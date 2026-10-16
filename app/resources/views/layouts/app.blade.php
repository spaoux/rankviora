<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'RankViora') · SEO & GEO Intelligence</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<div class="min-h-screen lg:flex">
    @auth
    <aside x-data="{ open: window.innerWidth >= 1024 }" @resize.window="open = window.innerWidth >= 1024" class="bg-slate-950 text-slate-300 p-6 lg:w-64 lg:fixed lg:inset-y-0 flex flex-col">
        <div class="flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="text-2xl font-bold tracking-tight text-white">Rank<span class="text-teal-400">Viora</span><span class="block text-xs font-normal tracking-widest text-slate-400 mt-2">SEO & GEO INTELLIGENCE</span></a>
        <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="workspace-menu" class="lg:hidden rounded-lg border border-slate-700 px-3 py-2 text-sm text-white">Menü</button>
        </div>
        <div id="workspace-menu" x-show="open" x-cloak class="flex flex-col lg:flex-1">
        <div class="mt-8 text-xs uppercase tracking-widest text-slate-500">Çalışma alanı</div>
        <nav class="mt-3 space-y-1" aria-label="Ana menü">
            <a class="nav-item" href="{{ route('home') }}">▦ Workspace’ler</a>
            @isset($workspace)
            <a class="nav-item {{ ($section ?? '') === 'engine' ? 'nav-active' : '' }}" href="{{ route('seo.index', $workspace) }}">SEO Veri Motoru</a>
            @foreach(['seo'=>'SEO Dashboard','geo'=>'GEO Dashboard','projects'=>'Projeler ve rakipler','history'=>'Analiz geçmişi','usage'=>'API kullanımı','team'=>'Workspace ve ekip'] as $key=>$label)
                <a class="nav-item {{ ($section ?? '') === $key ? 'nav-active' : '' }}" href="{{ route('workspace.show', [$workspace, $key]) }}">{{ $label }}</a>
            @endforeach
            @endisset
        </nav>
        <div class="mt-8 lg:mt-auto rounded-xl bg-slate-900 p-4 text-xs leading-6"><span class="text-teal-400 font-semibold">SPRINT 2 · SEO</span><br>DataForSEO: {{ strtoupper(config('seo.mode')) }}<br>Örnek metrikler MOCK/DEMO’dur.</div>
        <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="text-sm text-slate-400 hover:text-white">Güvenli çıkış →</button></form>
        </div>
    </aside>
    @endauth
    <main class="flex-1 min-w-0 {{ auth()->check() ? 'lg:ml-64' : '' }}">
        @auth
        <header class="bg-white border-b border-slate-200 px-6 md:px-10 py-5 flex justify-between items-center gap-4"><div class="text-sm text-slate-500">{{ $workspace->name ?? 'Çalışma alanlarım' }} <span class="text-slate-300 mx-2">/</span> Intelligence</div><span class="text-sm font-medium truncate">{{ auth()->user()->name }}</span></header>
        @endauth
        <div class="p-6 md:p-10 max-w-7xl mx-auto">
            @if(session('success'))<div role="status" class="mb-6 p-4 bg-teal-50 border border-teal-200 rounded-xl text-teal-800">{{ session('success') }}</div>@endif
            @if($errors->any())<div role="alert" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </main>
</div>
@livewireScripts
</body></html>
