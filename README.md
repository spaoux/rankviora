# rankviora
RankViora — SEO &amp; GEO Intelligence SaaS Platform

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
