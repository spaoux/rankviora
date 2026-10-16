# Sprint 2 — SEO Data Engine teslim raporu

Tarih: 15.10.2026 (Europe/Istanbul). Branch: `feature/seo-data-engine`. Başlangıç çalışma ağacı temizdi. Sprint 1 migration’ları ve testleri korunmuştur. Commit, push, VPS bağlantısı veya deployment yapılmamıştır.

## Tamamlanan işlevler

- DataForSEO Labs Google adapter, provider interface, typed request/response/keyword/ranking/metrics DTO’ları. Domain Rank Overview, Ranked Keywords, Relevant Pages ve Domain Intersection sözleşmeleri resmî belgelere göre hazırlandı.
- Varsayılan Türkiye/Türkçe. Sabit HTTPS endpoint allowlist, redirect kapalı, TLS doğrulama açık; timeout ve connection timeout. Gerçek bağlantı varsayılan kapalı; local mock HTTP fake ile çalışır.
- Domain Overview: organik kelime, tahmini ETV, kümülatif ilk 3/10/100, güçlü sayfalar, sağlayıcı ve veri alınma tarihi.
- Organic Keywords: kelime, rank_group, hacim, CPC USD, tahmini trafik, URL; filtreleme, sıralama, pagination ve CSV export.
- Keyword Gap: sağlayıcı intersection=false ile rakipte olup bizde olmayan kelimeler, ortak kelimeler ve daha iyi rakip pozisyonları.
- Yeni snapshot/keyword/ranking/domain metrics/bütçe tabloları; mevcut API kullanım log’una ek denetim alanları ve uzlaştırma notu. Geçmiş snapshot’lar korunur.
- Workspace bazında aylık $10 varsayılan bütçe, integer micro-USD rezervasyon/maliyet defteri, bağımsız MOCK/live kayıtları, gerçekleşen her deneme için receipt.
- Workspace row lock, Redis cache/query lock, DB receipt üzerinden cache kurtarma ve dispatch/job replay koruması. Redis seo worker Compose’a eklendi.
- Belirsiz maliyette rezervasyon/blokaj; yalnızca doğrulanmış sıfır maliyetli rate-limit yanıtı için sınırlı backoff. Timeout ve 5xx otomatik tekrar edilmez. Etkileşimli maliyet uzlaştırma komutu denetim izi bırakır ve API çağırmaz.
- Güncel yetkiyi worker’da tekrar doğrulama, foreign workspace/proje/snapshot/rakip ID kontrolleri, güvenli domain normalizasyonu, parametreli ve allowlist sıralamalı sorgular, CSV formula injection koruması. MOCK verinin gerçek olarak yeniden etiketlenmesi engellenir.

## Doğrulama

- `docker compose exec -T app php artisan test --compact`: **49 başarılı test, 260 doğrulama, 0 başarısız test**. Sprint 1’in 16 testi ve 104 doğrulaması bu toplama dahildir. Test DB SQLite `:memory:`; yerel uygulama verisine dokunmaz.
- Başarı/auth/rate-limit/timeout/5xx/bozuk yanıt/maliyet tutarsızlığı/bütçe/cache hit-miss/query lock/ay değişimi/queue replay/üyelik iptali/tenant izolasyonu/gap/export/provenance ve uzlaştırma testleri geçti.
- İlk hata testleri fake callback’lerin Laravel tarafından biriktirilmesi nedeniyle yanlış başarı yanıtı kullanıyordu; her senaryoda HTTP factory yenilenerek düzeltildi ve tüm suite tekrar geçti.
- PostgreSQL’de iki yeni migration uygulandı. Sprint 1 migration dosyaları değişmedi.
- Vite/Tailwind asset derlemesi tamamlandı. PHP Pint ile formatlandı; `git diff --check` temiz.
- Gerçek Chrome kontrolü: giriş → workspace/proje/rakip → Redis queue → tamamlanan MOCK snapshot → Overview/Keywords/CSV/missing ve better gap/geçmiş/API kullanım ekranı. Beş fake provider receipt ve **$0 gerçek maliyet** doğrulandı. GEO, mobil görünüm ve çıkış çalıştı. Geçici test hesabı/workspace’i kaldırıldı; mevcut gerçek hesap korundu.
- Canlı API isteği veya gerçek credential kullanımı yok. GitHub Actions branch filtresi güncellendi; GitHub’da workflow henüz çalıştırılmadı.

## Yerel servisler ve port

Uygulama adresi bu makinede **http://localhost:18088**. Kaynaktaki varsayılan 8088 korunur. Windows `netsh interface ipv4 show excludedportrange protocol=tcp` çıktısında 8070–8169 aralığı ayrılmış olduğundan Docker Desktop 8088 publish işleminde hata verdi. Sistem ayarları değiştirilmedi; Git’e girmeyen kök `.env` içinde RANKVIORA_PORT ve APP_URL ile 18088 override edildi. Nginx yeniden oluşturuldu ve HTTP sağlık kontrolü başarılı oldu.

PHP-FPM, Nginx, PostgreSQL, Redis ve worker healthcheck’leri kullanılır; assets tek seferlik servisinin Exited (0) sonucu normaldir. PostgreSQL/Redis host portu yayınlanmaz. VPS ağı yerelde kullanılmaz. Production override dosyasında yeni worker için debug=false hazırlığı yapıldı; dosya çalıştırılmadı ve production değiştirilmedi.

## Kapsam sınırları ve sonraki adım

Her provider endpoint’ten en fazla ilk 1000 satır alınır; UI toplam/saklanan satır sayısını gösterir. Snapshot üzerinde pagination/export vardır; tüm provider sayfalarını otomatik indirme yoktur. Trafik yalnızca tahmini ETV’dir. Provider kaynak güncelleme tarihi yoksa veri alınma zamanından gerçek ölçüm tarihi üretilmez. MOCK ücretleri simülasyon bütçesidir; canlı maliyete katılmaz.

Canlı entegrasyon sözleşmesi fake ile doğrulandı; gerçek hesapla uçtan uca doğrulama yapılmadı. README’deki güvenli canlı test planı ayrıca kullanıcı onayı gerektirir. Canlı deneme öncesinde güncel tarife, request rezervasyon üst sınırı ve sağlayıcı hesabındaki sert maliyet limiti doğrulanmalıdır. Bekleyen/sonucu belirsiz maliyetler otomatik temizlenmez; doğrulanmış faturayla uzlaştırılır. Production hazırlığı bu sprintin deployment kapsamı değildir.

Son kontrolde rate-limit tekrarından önce üyelik yeniden doğrulaması da eklendi ve test edildi. Uygulama container’ı yeniden oluşturulduğunda Nginx’in eski PHP-FPM IP’sini tutmasından kaynaklanan 502 hatası Docker DNS resolver ile düzeltildi; Nginx yapılandırma testi, giriş ve sağlık endpoint’leri HTTP 200 ile doğrulandı. Beş sürekli servis sağlıklı; assets Exited (0).

## Değiştirilen ve eklenen dosyalar

- .github/workflows/tests.yml
- app/.env.example
- app/app/Console/Commands/ReconcileSeoCost.php
- app/app/Http/Controllers/SeoController.php
- app/app/Jobs/RunSeoSnapshot.php
- app/app/Models/ApiUsageLog.php
- app/app/Models/Project.php
- app/app/Models/SeoBudget.php
- app/app/Models/SeoDomainMetric.php
- app/app/Models/SeoKeyword.php
- app/app/Models/SeoRanking.php
- app/app/Models/SeoSnapshot.php
- app/app/Providers/AppServiceProvider.php
- app/app/Seo/BudgetLedger.php
- app/app/Seo/CsvCell.php
- app/app/Seo/DataForSeoProvider.php
- app/app/Seo/DomainName.php
- app/app/Seo/Dto/KeywordData.php
- app/app/Seo/Dto/OrganicMetrics.php
- app/app/Seo/Dto/ProviderResponse.php
- app/app/Seo/Dto/RankingData.php
- app/app/Seo/Dto/SeoRequest.php
- app/app/Seo/KeywordQuery.php
- app/app/Seo/MockResponses.php
- app/app/Seo/Money.php
- app/app/Seo/Operation.php
- app/app/Seo/Provider.php
- app/app/Seo/SeoException.php
- app/app/Seo/SeoGateway.php
- app/app/Seo/SnapshotDispatcher.php
- app/app/Seo/SnapshotRunner.php
- app/config/queue.php
- app/config/seo.php
- app/database/migrations/2026_10_15_000001_create_seo_engine_tables.php
- app/database/migrations/2026_10_15_000002_add_seo_reconciliation_audit.php
- app/resources/views/layouts/app.blade.php
- app/resources/views/seo/index.blade.php
- app/resources/views/seo/show.blade.php
- app/resources/views/seo/usage.blade.php
- app/resources/views/workspaces/show.blade.php
- app/routes/web.php
- app/tests/Feature/SeoEngineTest.php
- compose.production.yml
- compose.yml
- deploy/SPRINT2_ARCHITECTURE.md
- deploy/SPRINT2_REPORT.md
- docker/nginx/default.conf
- README.md
