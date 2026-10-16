# rankviora
RankViora — SEO &amp; GEO Intelligence SaaS Platform

## Sprint 2 — SEO Data Engine

Aktif geliştirme branch’i `feature/seo-data-engine`. Sprint 1 giriş, workspace, proje/rakip ve GEO işlevleri korunur. **SEO Veri Motoru** menüsünden proje seçin; rakip seçerek **MOCK analizi başlat** düğmesini kullanın. Redis `seo` kuyruğundaki worker snapshot hazırlar. Sayfayı yenileyerek Domain Overview, Organic Keywords, Keyword Gap ve snapshot geçmişini açın. API kullanımında maliyet ve rezervasyon durumları görünür.

```bash
docker compose up -d --build
docker compose exec app php artisan migrate:status
docker compose exec app php artisan test
docker compose logs --tail=50 worker
```

Yeni worker servisi Compose ile otomatik başlar. PHP kodu veya environment değiştiğinde `docker compose restart worker` ile uzun yaşayan worker’ın kodunu yenileyin. Worker 600 saniyelik timeout kullanır; Redis `retry_after=660` saniyedir. Kuyruk yükü nedeniyle ertelenen işler sınırlı sayıda tekrar denenir; sonucu belirsiz ücretli POST işlemi tekrarlanmaz. Snapshot state geçişi DB satır kilidiyle yapılır, aynı iş tekrar teslim edilirse bitmiş/işlenen snapshot yeniden çağrı üretmez.

### Yerel port

Varsayılan **http://localhost:8088** korunur. 15.10.2026 yerel doğrulamasında Windows’un 8070–8169 port aralığını ayırdığı görüldü; bu makinenin Git’e eklenmeyen kök `.env` dosyasında `RANKVIORA_PORT=18088` ve `APP_URL=http://localhost:18088` kullanıldı. **Bu ortamın güncel adresi http://localhost:18088**. Varsayılan port Windows’ta kullanılabiliyorsa bu iki yerel override kaldırılabilir. Sistem port rezervasyonlarını değiştirmek gerekmez. CI normal 8088 portunu kullanır.

### SEO verilerinin kapsamı

- Ülke Türkiye (`2792`), dil Türkçe (`tr`); Google organik sonuçları. Domain Overview, Ranked Keywords, Relevant Pages ve Domain Intersection için resmî sözleşmelere göre request/response DTO’ları vardır.
- Trafik sağlayıcının **tahmini ETV** değeridir; gerçek ziyaretçi sayısı veya GSC/Analytics ölçümü değildir. İlk 3/10/100 dağılımı kümülatiftir. Bilinmeyen hacim/CPC null tutulur ve “—” gösterilir.
- Veri alınma zamanı, snapshot tamamlanma zamanından ayrıdır; cache okunduğunda orijinal alınma tarihi korunur. Kelime hacminin sağlayıcı güncelleme tarihi ayrıca saklanır. Sağlayıcının vermediği ölçüm tarihi uydurulmaz.
- Her endpoint’ten ilk en fazla **1000 satırlık pencere** alınır. UI toplam/saklanan satır sayısını ve kesilmiş kapsamı gösterir. Filtre, sıralama, sayfalama ve CSV bu saklanmış snapshot üzerinde çalışır; tüm provider veri kümesini indirmez ve yeni ücretli sorgu oluşturmaz. Otomatik tam provider pagination bu sprintte yoktur.
- Eksik kelimeler `target1=rakip`, `target2=proje`, `intersections=false` yanıtından gelir. Ortak kelimelerde `rank_group` karşılaştırılır; küçük pozisyon daha iyidir. İlk 1000 satırlık yerel listeyi birbirinden çıkararak “bizde yok” kararı verilmez.
- CSV filtreleri korur, en fazla 10000 satır çıkarır, MOCK kaynağını belirtir; `=`, `+`, `-`, `@`, baştaki kontrol karakterleri/BOM ile başlayan formül girişimleri apostrofla etkisizleştirilir. URL’ler veri olarak gösterilir; target domain’e HTTP isteği yapılmaz.
- Snapshot → proje → workspace ilişkisi üzerinden erişim kontrol edilir. Sadece sahip analiz başlatabilir; üyeler sonuçları/export’u okuyabilir. Worker güncel sahipliği ve bekleyen işin domain/rakip durumunu tekrar doğrular. Eski snapshot’lar yeni analizle değiştirilmez.

### Maliyet ve güvenli mock

`app/config/seo.php` varsayılanı **mock**’tur. Runtime mock transport `Http::fake` ile resmî yanıt şekillerinde örnek döner; eşleşmeyen ağ istekleri engellenir. Anahtar gerekmez. MOCK veriler gerçek domain performansı olarak gösterilmez. `app/.env.example` sadece boş credential alanları içerir; gerçek değerler Git’e girmez.

Varsayılan bütçe **workspace başına / UTC ayı başına $10**’dır. MOCK ve canlı defterler ayrıdır. Raporlanan mock bedel simülasyon bütçesine işlenir, `api_usage_logs.cost` gerçek bedeli **0** tutar. Bir provider denemesi öncesi varsayılan **$0.10** üst sınır rezerve edilir; bu tutar fiyat garantisi değildir ve canlı onaydan önce güncel tarife doğrulanmalıdır. Hesap genelindeki sağlayıcı harcama limiti ayrıca DataForSEO panelinden ayarlanmalıdır.

Transaction + workspace row lock rezervasyon/harcama yarışlarını engeller. Cache anahtarı workspace, proje, mod, endpoint, domain, ülke/dil ve pagination içerir; Redis distributed lock tekrarlı sorguyu birleştirir. Başarılı deneme DB’de süreli receipt bırakır; Redis cache kaybolursa geçerli receipt yeniden ücretlendirmeyi önler. Cache ömrü 24 saattir. Süresi bitince yeni analiz tekrar provider isteği oluşturabilir.

Maliyetler integer micro-USD ile hesaplanır. Üst/envelope ve task maliyeti/durumları doğrulanır. Yalnızca doğrulanmış **sıfır maliyetli** provider rate-limit yanıtı en fazla iki ek denemeyle exponential backoff kullanır. Timeout, HTTP 5xx veya bozuk/belirsiz yanıt tekrar edilmez; rezervasyon tutulur, maliyet “Bilinmiyor” gösterilir ve workspace aynı moddaki yeni ücretli sorgular için inceleme bekler. Worker çökmesiyle kalan `pending` kayıt da bloke eder; ay değişimi bu kaydı silmez.

Belirsiz bir kaydı yalnızca sağlayıcı hesap/fatura/task kaydıyla gerçek tutarı doğruladıktan sonra, sunucu terminalindeki güvenilir operatör şu komutla uzlaştırabilir:

```bash
docker compose exec app php artisan rankviora:seo-reconcile RECEIPT_ID
```

Komut gerçek tutarı ve referans notunu sorar, açık doğrulama ister, rezervasyonu uzlaştırır ve denetim notunu saklar. API çağırmaz. Tutarı bilinmeyen isteği sıfır varsayarak uzlaştırmayın. Bir kayıt ikinci kez uzlaştırılamaz. Bütçeyi aşan doğrulanmış harcama yeni sorguları engellemeye devam eder.

### Ayrı onay gerektiren canlı test planı

Gerçek bağlantı bu sprintte **etkinleştirilmedi**. Canlı moda geçiş için `DATAFORSEO_MODE=live`, `DATAFORSEO_LIVE_ENABLED=true`, `DATAFORSEO_LIVE_APPROVAL=approved-after-safety-review` ve credential alanlarının hepsi gereklidir; bunlar ayrıca kullanıcı onayı alınmadan ayarlanmaz.

Onaydan önce tüm fake testler geçmeli; güncel sağlayıcı tarifesi ve paneldeki sert maliyet limitleri doğrulanmalı; ayrı test workspace’i ve küçük bir sorgu limiti belirlenmelidir. Onay sonrasında tek bir domain için düşük satır limitli tek Overview isteğiyle başlanır. Gönderilen parametreler, task ID, gerçek fatura maliyeti, alınma zamanı ve receipt karşılaştırılır. Aynı sorgu cache’den tekrarlanarak ek ücret oluşmadığı kontrol edilir. Daha sonra diğer endpoint’ler ayrı bütçe ve onay kapsamı içinde denenir. Herhangi bir belirsiz maliyette test durur; anahtarlar log’a, fixture’a veya rapora yazılmaz. VPS veya production kullanımı bu planın parçası değildir.

Mimari ve resmî endpoint kaynakları: [Sprint 2 mimarisi](deploy/SPRINT2_ARCHITECTURE.md). Sonuç ve dosya listesi: [Sprint 2 raporu](deploy/SPRINT2_REPORT.md). Test kapsamı: `app/tests/Feature/SeoEngineTest.php`. GitHub Actions branch filtresi Sprint 2’yi kapsar; workflow’un GitHub’da çalışması commit/push onayından sonradır.

## Sprint 1 — çalışan yerel MVP

Laravel 13, PHP 8.5-FPM, Nginx, PostgreSQL 16, Redis 7, Blade, Livewire 4 ve Tailwind CSS 4 kullanılır. Yerel adres: **http://localhost:8088**. Compose proje adı `rankviora`.

### Kurulum (Windows PowerShell)

Docker Desktop açık ve WSL2 entegrasyonu etkin olmalıdır. Host’a PHP, Composer veya Node kurulması gerekmez.

```powershell
cd D:\codex\rankviora
.\deploy\setup.ps1
docker compose up -d --build
docker compose exec app php artisan rankviora:user
```

Docker CLI yalnızca WSL’de erişilebiliyorsa Ubuntu terminalinde:

```bash
cd /mnt/d/codex/rankviora
sh deploy/setup.sh
docker compose up -d --build
docker compose exec app php artisan rankviora:user
```

Kurulum script’i repository kökünde rastgele PostgreSQL parolası içeren `.env` üretir ve var olan dosyayı korur. Uygulama başlangıcında `app/.env` ve rastgele `APP_KEY` oluşturulur; migration’lar otomatik uygulanır. `.env` dosyaları Git tarafından yok sayılır. Composer bağımlılıkları ilk çalıştırmada kurulur; `assets` servisi kilit dosyasından npm bağımlılıklarını kurup Vite/Tailwind çıktılarını üretir. İlk çalıştırma birkaç dakika sürebilir.

`rankviora:user` etkileşimli terminalde ad, e-posta ve gizli parola/tekrarını ister. Parola en az 12 karakter, büyük ve küçük harf, sayı ve sembol içermelidir. Parola komut argümanına, Git’e veya log’a yazılmaz. Sabit yönetici/demo hesabı yoktur. Ek kullanıcılar aynı komutla oluşturulur. Web kayıt ve parola sıfırlama akışları Sprint 1 kapsamı dışındadır.

Giriş yaptıktan sonra workspace oluşturun, **Projeler ve rakipler** ekranında domain ekleyin (`ornek.com`; URL/protokol/yol kabul edilmez). Projeyi düzenleyebilir/silebilir, rakip ekleyebilir/silebilir ve SEO/GEO DEMO analizini geçmişe kaydedebilirsiniz. **Workspace ve ekip** ekranında mevcut kullanıcıları ekleyin.

### Erişim ve veriler

- Workspace’i oluşturan kullanıcı `owner` olur; proje, rakip, analiz ve ekip değişikliklerini yapabilir.
- `member` rolü panelleri, projeleri, geçmişi ve API kullanımını okuyabilir. Yazma ve üye yönetimi yetkisi yoktur.
- Her sayfa ve Livewire işlemi güncel üyeliği doğrular. Proje/rakip işlemleri workspace ilişkilerinden sorgulanır; başka workspace ID’lerine erişim engellenir.
- SEO/GEO skorları ve grafikler **DEMO** olarak işaretli sabit örneklerdir. Gerçek domain ölçümü yapılmaz.
- DEMO analizleri `analysis_runs` içinde tutulur. API çağrısı veya kullanım maliyeti üretmez. API kullanım ekranı yalnızca workspace’in kendi log’larını gösterir; ilk kurulumda sıfırdır.
- PostgreSQL ve Redis host portu yayınlamaz. Nginx yalnızca `127.0.0.1:8088` üzerinde dinler. PostgreSQL ve Redis verileri kalıcı volume’larda tutulur.

### Kontroller

```bash
docker compose ps -a
docker compose exec app php artisan migrate:status
docker compose exec app php artisan test
docker compose exec app composer check-platform-reqs
docker compose logs --tail=50 app nginx assets
```

PHPUnit testleri **SQLite `:memory:`** üzerinde çalışır. Test bootstrap’ı Docker’ın veritabanı ortam ayarlarını geçersiz kılar ve SQLite bellek bağlantısını zorunlu doğrular; yerel PostgreSQL verisini kullanmaz. Testler giriş/çıkış, giriş deneme sınırı, workspace/proje/rakip işlemleri, rol yetkileri, farklı workspace ID saldırıları, üyelik iptali ve geçmiş/kullanım izolasyonunu kapsar. GitHub Actions aynı kontrolleri temiz bir Compose ortamında çalıştırır; deployment içermez.

`http://localhost:8088/up` Laravel sağlık uç noktasıdır. `app`, `nginx`, `postgres` ve `redis` servislerinin `healthy` olması beklenir. `assets` tek seferlik derleyicidir; **Exited (0)** normal başarılı sonuçtur. UI değişikliklerini derlemek için `docker compose run --rm assets` çalıştırın. PHP bağımlılıkları değişirse `docker compose exec app composer install` çalıştırın.

Sprint 1 yerel doğrulamasında gerçek Chrome üzerinden giriş/çıkış, workspace oluşturma, Livewire proje/rakip ekleme, DEMO geçmişi, SEO/GEO ve API kullanımı akışı denendi. 390 px mobil ekranda yatay taşma kontrolü yapıldı. Geçici test hesabı ve verileri işlem sonunda kaldırıldı. İlk gerçek hesabınızı yukarıdaki gizli parola komutuyla oluşturun. GitHub Actions dosyası hazırdır; GitHub üzerinde henüz çalıştırılmadı.

### Dosya yapısı

```text
app/                       Laravel kaynak kodu, migration, Blade/Livewire, testler
docker/php/                PHP-FPM image ve başlangıç script’i
docker/nginx/              Nginx yapılandırması
compose.yml                Yerel servisler ve kalıcı volume’lar
compose.production.yml     İncelenmesi gereken production override taslağı
deploy/                    Yerel gizli veri kurulum script’leri
.github/workflows/         CI testleri
AGENTS.md                  Repository çalışma kuralları
```

### Production sınırı ve kalan işler

`compose.production.yml` yalnızca hazırlık taslağıdır; bu sprintte çalıştırılmaz. `n8n_default` external ağı yalnızca bu dosyada tanımlıdır; yerel Compose bu ağı aramaz veya oluşturmaz. Taslak production ortamında debug’ı kapatır ancak mevcut geliştirme bind mount’ları ve başlangıç migration akışı production için ayrıca gözden geçirilmelidir. TLS, immutable image, yedekleme/geri yükleme, worker/scheduler ve deploy/rollback süreci tamamlanmadan production’a uygun değildir.

Gerçek SEO/GEO sağlayıcıları, ücretli API entegrasyonları, zamanlanmış analiz, e-posta daveti, parola sıfırlama ve billing sonraki sprintlere bırakılmıştır. VPS bağlantısı ve production deployment yapılmamıştır. Commit/push için kullanıcı onayı gerekir.
