@extends('layouts.app')
@section('title', 'Giriş')
@section('content')
<div class="min-h-[80vh] grid lg:grid-cols-2 items-center gap-12">
    <div><div class="text-teal-700 font-semibold tracking-widest text-sm mb-5">RANKVIORA INTELLIGENCE</div><h1 class="text-4xl md:text-6xl font-bold tracking-tight text-slate-950 leading-tight">Aramada görünür olun.<br><span class="text-teal-600">Yapay zekâda yer alın.</span></h1><p class="mt-6 text-slate-500 text-lg max-w-lg">SEO ve GEO performansınızı, projelerinizi ve rekabetinizi tek çalışma alanından yönetin.</p><div class="mt-8 flex gap-3"><span class="badge">SEO</span><span class="badge">GEO</span><span class="badge">Workspace</span></div></div>
    <div class="card p-8 md:p-10 max-w-md w-full mx-auto"><h2 class="text-2xl font-semibold">Tekrar hoş geldiniz</h2><p class="text-sm text-slate-500 mt-2 mb-8">Çalışma alanınıza güvenli giriş yapın.</p>
        <form method="POST" action="{{ route('login') }}" class="space-y-5">@csrf
            <label class="block text-sm font-medium">E-posta<input class="input mt-2" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus></label>
            <label class="block text-sm font-medium">Parola<input class="input mt-2" name="password" type="password" autocomplete="current-password" required></label>
            <label class="flex items-center gap-2 text-sm text-slate-500"><input type="checkbox" name="remember" value="1"> Beni hatırla</label>
            <button class="btn w-full">Giriş yap →</button>
        </form><p class="text-xs text-slate-400 mt-6 leading-5">Hesaplar güvenli terminal kurulumu ile oluşturulur. İlk hesap için README’deki kurulum adımlarını izleyin.</p>
    </div>
</div>
@endsection
