# DataForSEO Sandbox teslim raporu

Tarih: 17.10.2026, Europe/Istanbul. Başlangıç branch’i temiz `main` idi; geliştirme `feature/dataforseo-sandbox` üzerinde yapıldı. Commit/push/deployment veya VPS bağlantısı yapılmadı.

## Sonuç

- MOCK ve LIVE’dan ayrı, varsayılan kapalı SANDBOX modu eklendi. Varsayılan uygulama modu MOCK olarak kaldı; gerçek yerel environment dosyaları düzenlenmedi veya görüntülenmedi.
- Sabit `https://sandbox.dataforseo.com/v3/dataforseo_labs/google/domain_rank_overview/live` adresi, TLS doğrulaması ve redirect kapalı transport. Environment base URL değiştiremez; ücretli host’a fallback yoktur.
- Sandbox global HTTP request/telemetry dinleyicilerinden ayrı factory kullanır. Ham sağlayıcı mesajları ve transport exception zincirleri dışarı verilmez. Arbitrary echo alanları saklanmaz; task ID hash ile temsil edilir.
- Sandbox genel fixture hedefleri gerçek domain/ülke/dil sonucu sayılmaz. Snapshot, geçmiş ve CSV `SANDBOX / TEST VERİSİ` etiketlidir. Sandbox provenance için is_demo=true zorunludur.
- Gerçek maliyet ve rezervasyon sıfır; Sandbox kayıtları hiçbir bütçe satırına bağlanmaz. LIVE bütçesi tüketilmez veya bloke edilmez. Cache ve receipt’ler workspace/proje/mod bazında ayrıdır.
- `rankviora:seo-sandbox PROJE_ID KULLANICI_ID` dry-run komutu ve açık `--send` seçeneği eklendi. Workspace sahipliği ve local/testing ortamı zorunludur. Send en fazla bir Overview isteği yapar, cache varsa sıfırdır; retry yoktur. Çoklu web/queue Sandbox akışı reddedilir.
- Yeni migration veya dependency gerekmiyor. CI branch filtresi güncellendi; GitHub CI çalıştırılmadı.

## Doğrulama

- Tam Laravel paketi: **64 başarılı test, 349 doğrulama, 0 başarısız**.
- Sprint 2’nin mevcut 49 testi korunmuştur; 15 Sandbox testi eklenmiştir. Mevcut FoundationTest, SeoEngineTest ve UserSetupTest dosyaları değiştirilmemiştir.
- Test bootstrap’ı gerçek environment kimlik değerlerini boş değerlerle izole eder; yalnızca dummy test kimlik bilgileri ve `Http::fake` kullanılır. Test DB SQLite bellektir.
- Host/auth/TLS/redirect, disabled/missing credentials, production engeli, tek endpoint, generic fixture, cache ayrımı, gerçek bütçe izolasyonu, rate-limit tekrar etmeme, safe exception/console çıktısı, workspace yetkisi, web/worker engeli ve UI/CSV etiketleri geçti.
- İlk çalıştırmada eklenen Blade koşulunun bitiş yönergeleri üç ekran testinde syntax hatası verdi; ayrı satırlarla düzeltildi, ilgili test ve ardından tam paket başarıyla yeniden çalıştırıldı.
- Pint başarılı; git diff --check temiz. Yerel login HTTP 200. Container’lar başlangıçta sağlıklıydı; servis/environment etkinleştirmesi yapılmadı.
- Git ignore kontrolü `.env`, `app/.env`, `app/.env.sandbox` için başarılı. Gerçek kimlik bilgileri istenmedi veya görüntülenmedi; ücretli ya da gerçek kimlik bilgili Sandbox çağrısı yapılmadı.

## Kullanıcının yapacağı işlem

Yerel ayarlar, config temizliği, worker yeniden başlatma, dry-run ve isteğe bağlı tek Sandbox sorgusu [Sandbox kurulum belgesinde](DATAFORSEO_SANDBOX.md) açıklanmıştır. Kimlik bilgilerini yalnızca Git’e girmeyen `app/.env` dosyasına kendiniz girin. Gerçek API Access hesabıyla uçtan uca bağlantı bu teslimde doğrulanmadı; bu, kullanıcı yerel ayarlarını tamamladıktan sonraki testtir.

## Değiştirilen ve eklenen dosyalar

- .github/workflows/tests.yml
- app/.env.example
- app/app/Console/Commands/SandboxSeoOverview.php
- app/app/Http/Controllers/SeoController.php
- app/app/Models/SeoSnapshot.php
- app/app/Providers/AppServiceProvider.php
- app/app/Seo/BudgetLedger.php
- app/app/Seo/DataForSeoProvider.php
- app/app/Seo/Dto/ProviderResponse.php
- app/app/Seo/Operation.php
- app/app/Seo/SeoGateway.php
- app/app/Seo/SnapshotDispatcher.php
- app/app/Seo/SnapshotRunner.php
- app/config/seo.php
- app/resources/views/seo/index.blade.php
- app/resources/views/seo/show.blade.php
- app/resources/views/seo/usage.blade.php
- app/tests/Feature/SeoSandboxTest.php
- app/tests/TestCase.php
- deploy/DATAFORSEO_SANDBOX.md
- deploy/DATAFORSEO_SANDBOX_REPORT.md
- README.md
