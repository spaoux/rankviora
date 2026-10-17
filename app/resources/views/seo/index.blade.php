@extends('layouts.app')
@section('title','SEO Veri Motoru')
@section('content')
@if(config('seo.mode')==='sandbox')<div class="card p-5 mb-5"><strong>SANDBOX / TEST VERİSİ</strong><p class="subtitle">Sandbox sonuçları sabit örnek verilerdir, gerçek domain veya ülke/dil performansı değildir. Canlı bütçeye maliyet eklenmez. Tek Overview kontrolü terminalden çalıştırılır.</p></div>@endif
<p class="eyebrow">SEO DATA ENGINE</p><h1 class="page-title">SEO Veri Motoru</h1><p class="subtitle mb-6">Domain Overview · Organic Keywords · Keyword Gap</p>
<div class="card p-5 mb-6 text-sm"><strong>{{ \App\Models\SeoSnapshot::labelForMode(config('seo.mode')) }}</strong> · Türkiye / Türkçe · Google organik sonuçları. Trafik değerleri tahmindir; gerçek ziyaretçi ölçümü değildir.
@switch(config('seo.mode'))
    @case('mock')
        MOCK modunda dış ağ isteği yoktur; örnek veriler kullanılır ve gerçek ücret oluşmaz.
        @break
    @case('sandbox')
        SANDBOX modunda resmî sandbox.dataforseo.com host’una ağ isteği gönderilebilir; yanıtlar test verisidir, gerçek SEO verisi değildir ve ücret oluşmaz.
        @break
    @case('live')
        LIVE modunda, onay ve güvenlik kontrolleri izin verdiğinde ücretli DataForSEO API’sine ağ isteği gönderilebilir; gerçekleşen maliyet bütçeye kaydedilir.
        @break
    @default
        Çalışma modu doğrulanamadı.
@endswitch
</div>
<div class="grid md:grid-cols-2 gap-5">@forelse($projects as $project)<a class="card p-6 hover:border-teal-400" href="{{ route('seo.show',[$workspace,$project]) }}"><h2 class="text-lg font-semibold">{{ $project->name }}</h2><p class="subtitle">{{ $project->domain }} · {{ $project->seo_snapshots_count }} geçmiş snapshot</p><p class="text-teal-700 text-sm mt-5">Analizi aç →</p></a>@empty<div class="card p-6"><p>Henüz proje yok.</p><a class="btn inline-block mt-4" href="{{ route('workspace.show',[$workspace,'projects']) }}">Proje ekle</a></div>@endforelse</div>
@endsection
