# Sprint 2 — SEO Data Engine

## Uygulama planı

1. Sabit HTTPS DataForSEO Labs Google uç noktaları için provider interface, request/response DTO ve sıkı doğrulama. Varsayılan ülke 2792 (Türkiye), dil tr. Local HTTP fake, açıkça MOCK etiketli; gerçek bağlantı iki ayrı environment kilidiyle kapalı.
2. Her provider denemesinden önce workspace satırı transaction/row lock ile kilitlenir; aylık bütçeden üst sınır rezervasyonu alınır. Maliyetler integer micro-USD olarak hesaplanır. İstek sonucu maliyeti ve task durumları doğrulanır. Timeout, bozuk JSON veya belirsiz maliyette rezervasyon tutulur ve workspace ücretli istekleri inceleme bekler. Ücretli POST, timeout/5xx sonrası körlemesine tekrar edilmez; yalnızca doğrulanmış sıfır maliyetli rate-limit yanıtları sınırlı backoff ile tekrarlanabilir.
3. Cache anahtarı workspace, proje, mod, operation, normalize domain, ülke/dil ve pagination içerir. Redis distributed lock aynı sorguyu birleştirir. Snapshot işleri Redis seo kuyruğundadır; yürütmede sahiplik/üyelik ve domain tekrar doğrulanır. Bitmiş snapshot değişmez; yeni analiz yeni kayıt açar.
4. Yeni migration: snapshot, keyword, ranking ve domain metrics tabloları; bütçe politikası ve API kullanım kaydına ilave denetim alanları. Sprint 1 migration’ları değişmez. Çocuk kayıtlar snapshot → project → workspace üzerinden yetkilendirilir.
5. SEO panelinde snapshot seçimi, organik kelime filtre/sıralama/sayfalama, bounded CSV export ve rakip gap görünümü. Ortak kelimelerde düşük rank_group daha iyidir. Eksik kelimeler target1=rakip, target2=proje, intersections=false isteğinden gelir; sayfalı örnekten “domain’de yok” sonucu türetilmez. İlk sürüm en fazla yapılandırılmış 1000 satırlık provider penceresini alır; toplam/saklanan sayıları ve kapsam sınırı UI’da belirtilir. Export mevcut snapshot’ın filtrelenmiş kayıtlarıdır; API çağırmaz.
6. Fake HTTP başarı/hata, envelope ve DTO şekli, bütçe/rezervasyon, cache/dedup, queue replay, üyelik iptali, tenant izolasyonu, gap ve CSV testleri. Sprint 1 testleri dahil tüm suite çalıştırılır; migration ve yerel HTTP doğrulanır.

## Güvenlik ve onay kapısı

Gerçek anahtar okunmaz/kullanılmaz, gerçek provider isteği çalıştırılmaz. `DATAFORSEO_MODE=mock` varsayılandır; mock transport eşleşmeyen tüm HTTP isteklerini engeller. İleride gerçek mod için hem `DATAFORSEO_LIVE_ENABLED=true` hem `DATAFORSEO_LIVE_APPROVAL` değeri ve kimlik bilgileri gereklidir. Bu ayarlar kullanıcı ayrıca onay vermeden değiştirilmez. Bütçe üst sınırı bir fiyat garantisi değildir; sağlayıcı kontrol paneli limitleri ve güncel tarife onaylı canlı smoke testten önce doğrulanmalıdır.

## Resmî sözleşme kaynakları

- https://docs.dataforseo.com/v3/dataforseo_labs-google-ranked_keywords-live/
- https://docs.dataforseo.com/v3/dataforseo_labs-google-domain_rank_overview-live/
- https://docs.dataforseo.com/v3/dataforseo_labs-google-domain_intersection-live/
- https://docs.dataforseo.com/v3/dataforseo_labs-google-relevant_pages-live/
- https://docs.dataforseo.com/v3/appendix/errors/
