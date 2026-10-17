# DataForSEO Sandbox — güvenli yerel kontrol

Bu entegrasyon `mock`, `sandbox` ve `live` modlarını ayırır. Varsayılan **mock** değişmedi. Sandbox ücretsiz ve sabit örnek veri döndüren resmî geliştirme ortamıdır; domain veya ülke/dil performansı ölçmez. Kaynak: https://docs.dataforseo.com/v3/appendix/sandbox/

## Yerel environment

Bilgileri yalnızca Git tarafından yok sayılan **app/.env** dosyasına kendiniz yazın. `.env.example` veya kaynak dosyalarına gerçek değer koymayın. Mevcut APP_KEY, DB ve Redis ayarlarını koruyun.

```dotenv
APP_ENV=local
DATAFORSEO_MODE=sandbox
DATAFORSEO_SANDBOX_ENABLED=true
DATAFORSEO_LIVE_ENABLED=false
DATAFORSEO_LIVE_APPROVAL=
DATAFORSEO_LOGIN='yerel-api-login-degeriniz'
DATAFORSEO_PASSWORD='yerel-api-password-degeriniz'
```

Değerleri API Access ekranından yerelde kendiniz aktarın; sohbete, terminal argümanına, ekran görüntüsüne veya log’a eklemeyin. Dotenv kurallarına uygun tırnak kullanın. Login/password için varsayılan gerçek değer veya CLI seçeneği yoktur. Testler gerçek environment değerlerini kullanmadan önce boş değerlerle izole edilir; yalnızca sahte test kimlik bilgileri kullanılır.

Repository kökünden (WSL: `/mnt/d/codex/rankviora`):

```bash
docker compose exec app php artisan config:clear
docker compose restart worker
git check-ignore app/.env
```

`.env` ve kimlik bilgilerini ekrana basmayın. `config:show seo`, `env`, `printenv`, shell debug/trace veya ham HTTP debug çıktısı kullanmayın. Runtime Sandbox transport global HTTP kayıt/telemetri dinleyicilerini kullanmaz. Hata çıktısı yalnızca güvenli hata kodları içerir; ham sağlayıcı mesajı veya exception zinciri gösterilmez.

## Tek Domain Rank Overview kontrolü

Yerel projenin ID’sini proje URL’sinden, workspace sahibinin kullanıcı ID’sini yerel `users.id` kaydından belirleyin. Komut bu kullanıcının proje workspace’inde **owner** rolünü doğrular. Aşağıdaki `PROJE_ID` ve `KULLANICI_ID` alanlarını sayısal ID’lerle değiştirin. Credential yazmayın.

Önce dry-run — ağ isteği, snapshot veya maliyet kaydı oluşturmaz; credentials gerekmez:

```bash
docker compose exec app php artisan rankviora:seo-sandbox PROJE_ID KULLANICI_ID
```

Environment ayarlarını tamamladıktan sonra **kendiniz** tek Sandbox kontrolünü çalıştırabilirsiniz:

```bash
docker compose exec app php artisan rankviora:seo-sandbox PROJE_ID KULLANICI_ID --send
```

Yalnızca şu sabit adrese POST gönderilebilir:

```text
https://sandbox.dataforseo.com/v3/dataforseo_labs/google/domain_rank_overview/live
```

URL sonundaki `/live`, Sandbox host’undaki endpoint metodudur; ücretli `api.dataforseo.com` host’una geçiş değildir. Base URL environment ile değiştirilemez; redirect ve canlı fallback kapalıdır, TLS doğrulaması açıktır. Bir komut çalışması **en fazla bir HTTP isteği** üretir; otomatik retry yoktur. Geçerli cache/receipt varsa sıfır yeni istek yapılır. Request Türkiye/Türkçe gönderir; Sandbox sabit yanıtının farklı domain/ülke/dil içermesi gerçek ölçüm gibi yorumlanmaz. Response şeması/maliyet doğrulaması korunur; sadece Sandbox için fixture hedef eşleşmesi aranmaz. Ham echo alanları saklanmaz, Sandbox task ID’si hash ile temsil edilir.

Sonuç `SANDBOX / TEST VERİSİ` etiketli bir Overview snapshot’ıdır. Sayfalar, kelimeler ve gap toplanmaz. Web formu veya queue üzerinden çoklu Sandbox analizi başlatılamaz; bu ilk entegrasyon yalnızca terminal kontrolü içindir. Sonucu projenin SEO Veri Motoru ve snapshot geçmişinde görebilirsiniz. Komut metrik veya ham yanıt basmaz; güvenli sonuç durumu ve snapshot ID’si gösterir.

Sandbox kaydı `mode=sandbox`, `is_demo=true`, `cost=0`, `reserved_micros=0`, `seo_budget_id=null` taşır. Yanıttaki örnek maliyet yalnızca `reported_cost_micros` alanında tanısal amaçla tutulur. Canlı bütçe oluşturulmaz, tüketilmez veya bloke edilmez. Başarısız/timeout Sandbox isteği de canlı rezervasyon oluşturmaz. Cache anahtarları workspace/proje/mod bazında ayrıdır; MOCK/LIVE cache’iyle karışmaz.

401/403 için login/password ve API Access’i yerelde kontrol edin; komut güvenli `provider_auth` kodu döndürür. Eksik bilgi `sandbox_credentials_missing`, kapalı mod `sandbox_disabled` kodudur. Credential veya tam yanıtı paylaşmayın. Production ortamında komut ve adapter Sandbox çağrılarını reddeder.

## MOCK’a dönüş

`app/.env` içinde `DATAFORSEO_MODE=mock`, `DATAFORSEO_SANDBOX_ENABLED=false` yapın; canlı kilitleri kapalı tutun. Ardından `config:clear` ve `docker compose restart worker` komutlarını yeniden çalıştırın. Sandbox snapshot’ları geçmişte kendi etiketiyle kalır. LIVE etkinleştirme bu görev kapsamında değildir.

## Doğrulama sınırı

Geliştirme testlerinde yalnızca fake HTTP ve sahte credentials kullanıldı. Gerçek API Access bilgileri istenmedi, görüntülenmedi veya test edilmedi. Kimlik bilgili gerçek Sandbox isteği kullanıcı tarafından yukarıdaki akışla yapılacaktır. Ücretli çağrı, VPS bağlantısı, commit veya push yapılmadı.
