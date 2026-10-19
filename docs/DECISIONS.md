# Karar defteri

Her kararın gerekçesi ve elenen alternatifi burada durur. Karar değişirse eski metin silinmez;
altına tarihli bir "Değişiklik" notu eklenir. Kaynak: Pergel Geliştirme Planı, 14 Ekim 2026
(onaylandı).

## K1 — Clean-room: Woodmart'tan kod alınmaz

Pergel sıfırdan yazılır. Woodmart'ın CSS, JS ve görselleri büyük ihtimalle Envato'nun split
lisansıyla korunuyor; türev bir temayı satmak riskli. Mimarisi de hedeflerle çelişiyor: aynı
eleman üç builder için üç kez yazılmış, her istekte 817 ayar alanı yükleniyor, script'lerin
146/161'i jQuery'ye bağlı. Düzeltmek yeniden yazmaktan pahalı.
**Elenen:** Woodmart fork'u, Woodmart child teması.

## K2 — Yerelde geliştirme, son kontrol staging'de

Yerelde her şey anında ve risksiz. ariumlab.com canlıda satış yapıyor. nginx/LiteSpeed, LSCache ve
Cloudflare katmanı yerelde birebir taklit edilemediği için yayından önce staging turu yapılır.
SSH (Hostinger) staging aşamasında, şifre yerine anahtarla kurulur.
**Elenen:** doğrudan canlıda geliştirme.

## K3 — Üç parça: `pergel` blok tema + `pergel-engine` eklenti + proje child teması

Tema yalnızca görünüş, işlev eklentide (Kadence, GeneratePress, Ollie aynı ayrımı yapıyor). Tema
değişse de içerik türleri, SEO verisi ve bloklar kaybolmaz. Child tema projeye özel tasarımı
dosyada, git'te tutar.
**Elenen:** her şeyi tek temada toplamak; hibrit Customizer teması.

## K4 — Blok tema (FSE), theme.json v3

WordPress'in yeni özellikleri blok tarafına geliyor (7.1 mobil/tablet stilleri, 7.0 navigation
overlay ve PHP-only bloklar, contentOnly pattern'ler). Blok işaretlemesi AI ile sayfa kurmaya en
uygun format. Yerel denemede TT25'ten hafif çıktı.
**Elenen:** klasik veya hibrit tema.

## K5 — Tasarım Site Editor'de, işlevler tek bir Pergel ayar ekranında

Renk, yazı tipi, boşluk ve şablon Global Styles'ta. Pergel ekranı yalnızca modüller, marka
bilgileri, SEO/AI ve performans ayarlarını içerir (yaklaşık 60 alan). Marka bilgileri Block
Bindings ile tek yerden bağlanır.
**Elenen:** Redux benzeri büyük ayar çerçevesi.

## K6 — Tema build'siz; yalnızca editör arayüzü gereken bloklar derlenir

Sunucuda Node gerekmez. Basit bloklar PHP-only. Karmaşık editör arayüzleri `@wordpress/scripts`
ile derlenir; derlenmiş dosyalar (`engine/build/`) repoya girer.
**Elenen:** tamamen build'siz yaklaşım; her şeyi derleyen ağır toolchain.
**Değişiklik (F3b, 15 Ekim 2026):** Şimdiye kadarki editör arayüzleri derleme gerektirmedi;
`engine/build/` yok. Gerekçe ve kural F3 kararlarında ("Blok editör betikleri derlemesiz").

## K7 — Asgari WordPress 7.1, PHP 8.1; destek: son iki major WP sürümü

7.1'in mobil/tablet stilleri temel ihtiyaç. WooCommerce Şubat 2027'de PHP 8.1'i asgari yapıyor.
CI PHP 8.1 ve 8.4'te, ayrıca WP'nin bir sonraki betasında koşar.
**Elenen:** WP 6.x ve PHP 7.4 desteği.

## K8 — SEO sözleşmesi: eklenti varsa devret, yoksa yedek katman

Bir SEO eklentisi (Rank Math, Yoast, AIOSEO, SEOPress, TSF) aktifse Pergel başlık, meta, JSON-LD
ve site haritası basmaz; kendi verisini eklentinin grafiğine ekler, breadcrumb'ı hizalar. Eklenti
yoksa çekirdek API'lerle eksiksiz bir yedek katman çalışır. İlk entegrasyon Rank Math.
**Elenen:** kendi SEO eklentimiz; hiç SEO çıktısı vermemek.

## K9 — AI SEO kanıta göre sıralanır

Önce sunucuda çizilen içerik ve tutarlı yapısal veri (GPTBot ve ClaudeBot JS çalıştırmıyor).
llms.txt ve markdown görünümü gibi kanıtı zayıf katmanlar isteğe bağlı.
**Elenen:** ölçülmemiş "AI SEO" özellikleri.

## K10 — WooCommerce blok şablonları; PHP şablon override'ı yok

Klasik override'lar her WooCommerce güncellemesinde "eskimiş şablon" uyarısı üretir. Blok
şablonları theme.json ile stillenir. Satış modları (satış, teklif, katalog, girişli fiyat) veri
düzeyinde uygulanır (`woocommerce_is_purchasable`, fiyat HTML'e ve Store API'ye yazılmaz).
**Elenen:** `woocommerce/` klasöründe PHP override'ları; fiyatı CSS ile gizlemek.

## K11 — Performans bütçesi CI'da zorunlu

Bütçeyi aşan değişiklik birleştirilmez. Bütçe `tests/budget/budget.json` dosyasındadır; ölçüm
`tools/budget/measure.mjs` ve `tools/budget/perf-probe.php` ile yapılır.
**Elenen:** arada bir elle Lighthouse çalıştırmak.

## K12 — Her site bir "proje paketinden" üretilir

Brief, marka token'ları, site haritası, içerik ve ürün CSV'si platformdan bağımsız dosyalarda
durur; kurulum komutları idempotenttir. Format: `docs/PROJECT-PACKAGE.md`.
**Elenen:** içeriği yalnızca veritabanında tutmak.

## K13 — İsimler

Tema `pergel`, eklenti `pergel-engine`, ariumlab child teması `ariumlab`. PHP namespace `Pergel\`,
önek `pergel_`, hook adları `pergel/...`. ariumweb'deki `pergel-core` ve `pergel-ariumlab` ayrı
bir projeye ait; o adlar kullanılmaz.
**Elenen:** `pergel-core`, `pergel-ariumlab`.

---

## Uygulamada yapılan sapmalar (F0, 14 Ekim 2026)

- **Autoload:** Plan "Composer classmap" diyordu. Engine kendi küçük PSR-4 `spl_autoload_register`
  yükleyicisini kullanır; `vendor/` dağıtıma girmez. Sebep: çalışma zamanı bağımlılığı yok, ek
  dosya yok, yalnızca gereken sınıf yüklenir (bütçe: istek başına ≤ 60 Pergel dosyası, F2'de ≤ 45). Üçüncü
  taraf bir çalışma zamanı kütüphanesi gerekirse classmap'e geçilir.
- **Yerel PHP:** Plan "XAMPP'in 8.2'si kalır, yalnızca CLI ve testler 8.4" diyordu. Uygulamada
  geliştirme sitesi de PHP 8.4'te çalışıyor: Apache yalnızca `pergel-tema` dizini için FastCGI ile
  `C:\devtools\php84`'e yönlendiriliyor. XAMPP'in global `php.ini`'si ve diğer siteler
  değişmedi; plandaki "php.ini'de 5 eklenti açılır" adımı bu yüzden gereksiz kaldı.
- **CI ortamı:** Plan wp-env (Docker) diyordu. F0'da CI, Ubuntu üzerinde PHP 8.4 + MariaDB 11.8
  servis konteyneri + WP-CLI + `php -S` ile kurulur (`tools/ci/`). Daha az hareketli parça,
  yerel kurulumla aynı adımlar. wp-env, Docker gerektiren testler (görsel regresyon, SEO matrisi)
  gelince yeniden değerlendirilir. CI kurulumu, yönetici sayfalarının api.wordpress.org'a
  güncelleme sorgusu atmasını bir must-use eklentiyle kapatır (F3c), böylece CI ağa bağlı kalmaz.
  setup-php, PHP 8.4'te JIT'i açık getirir. JIT, PHPUnit'i ve `wp-admin/post-new.php` isteğinde
  `php -S`'i çökertiyor: veritabanı "aborted connection" yazıyor, istek hiç bitmiyor. Bu yüzden
  PHPUnit ve blok editörü kontrolü JIT kapalı çalışır. Bütçe ölçümü JIT açık kalır. JIT
  kapalıyken de bir sayfa isteği ara sıra 30 saniyelik sınırı aşıp `php -S`'i sonlandırıyor
  (yerelde yeniden üretilemedi). Bu yüzden sunucu `tools/ci/serve.sh` ile çalışır ve ölürse
  yeniden başlar. Her istek `/tmp/pergel-requests.log`'a yazılır, böylece suçlu istek CI
  çıktısından bulunabilir.
- **Repo konumu:** `D:\dev\pergel` (önce `C:\dev\pergel`, kullanıcı isteğiyle taşındı).

## F1 kararları (15 Ekim 2026)

- **Token formatı:** W3C DTCG 2025.10'un bir alt kümesi, `tokens/*.json` (ayrıntı:
  `docs/TOKENS.md`). Plandaki proje paketi `tokens.json` dosyası da bu formatı kullanır.
  **Elenen:** Style Dictionary gibi bir Node aracı (derleme WordPress olmadan da PHP ile çalışmalı,
  child temalar `wp pergel tokens build` ile derleniyor) ve doğrudan `theme.json` düzenlemek
  (kontrast denetimi ve variation'lar arası tutarlılık kaybolur).
- **Ayrı JSON Schema yok:** Doğrulayıcı derleyicinin kendisidir. Tüm sorunları tek seferde
  dosya ve token yoluyla listeler, hiçbir dosyayı yarım yazmaz. Şema ile derleyici arasında
  iki kaynaklı kural ikiliği oluşmaz. Editör otomatik tamamlaması gerekirse şema sonradan
  derleyicinin sabitlerinden üretilir.
- **Üretilen `theme.json`:** Token'ların sahip olduğu alanlar her derlemede yeniden yazılır, geri
  kalanı korunur. Biçim sabittir (tab girinti, sonda satır sonu), böylece CI'daki
  `php tools/tokens.php --check` farkı bayt bayt yakalar.
- **Kontrast derleme hatasıdır:** WCAG AA 4.5:1 sağlamayan zorunlu renk çifti uyarı değil
  hatadır. ariumlab.com'un bugünkü `#767676` / `#F5F7F8` (4.23) gibi çiftleri bu yüzden yeni
  temaya taşınamaz.
- **PHPUnit 10.5:** Saf PHP birimleri (token derleyicisi ve sonraki modüller) için geliştirme
  bağımlılığı olarak eklendi. `composer check` artık lint + analiz + test çalıştırır.
  WordPress gerektiren testler F2'de ayrıca değerlendirilir.
- **Style variation'lar ariumlab.com kimliğine göre:** Variation'lar genel örnekler değil,
  ariumlab.com kurumsal kimliğinden (mavi `#1E64E6`, koyu `#191919`/`#242424`, açık gri zemin,
  küçük radius) türetilen yönlerdir. Ticari fontlar (Satoshi, Preospe) kullanılmaz; yerine OFL
  lisanslı, Türkçe karakterleri kapsayan değişken fontlar kendi sunucumuzdan sunulur.
- **Varsayılan font sistem yığınıdır:** Ana tema hiç web font indirmez. Web fontları yalnızca
  variation'lar ve child temalar getirir.

- **Şablonlar ve tek H1:** `index`, `home`, `front-page`, `archive`, `search`, `single`, `page`,
  `404`. Her sayfada tam olarak bir H1 bulunur ve bütçe bunu `h1 = [1, 1]` aralığıyla denetler.
  Bütçede bir sayı üst sınırdır, `[min, max]` aralıktır.
- **`front-page.html` yalnızca statik ön sayfada:** Şablon sayfanın içeriğini başlıksız gösterir;
  H1'i içeriğin ilk bölümü (hero) taşır. Ön sayfa yazıları listeliyorsa `front-page`
  `frontpage_template_hierarchy` filtresiyle hiyerarşiden çıkarılır ve `home.html` kullanılır.
  Aynı filtre Site Editor'ün ana sayfa için açtığı şablonu da düzeltir.
  **Elenen:** `front-page.html` hiç olmaması (statik ön sayfada sayfa başlığı H1 olarak görünür;
  kurumsal sitelerde istenmez) ve koşulsuz `front-page.html` (yazı listesi olan ön sayfayı bozar).
- **Şablon metinleri PHP pattern'lerde:** HTML şablonlardaki metinler çevrilemediği için
  `patterns/hidden-*.php` dosyalarına taşındı (`Inserter: no`). Şablonlar bunları
  `wp:pattern` ile çağırır. Pattern'lerde koşullu blok yapısı yoktur, yalnızca çevrilen metin
  ve ayar değerleri vardır; çünkü Site Editor pattern'i bir kez çözüp şablona gömebilir.
- **`.pot` dosyaları depoda:** `composer pot` ile üretilir (`--no-location`, satır numarası
  değişince fark oluşmasın diye). CI, tarih satırı dışında farkı hata sayar.
- **Erişilebilirlik bütçede:** `@axe-core/playwright` her bütçe sayfasında WCAG 2.0/2.1/2.2
  A ve AA kurallarını çalıştırır. Limit `axe = 0` ihlaldir; ihlaller rapora kural, etki ve ilk
  seçiciyle yazılır. Her sayfa üç görünümde taranır: masaüstü (1440 px), telefon (390 px) ve
  telefonda açılmış menü. Her ihlal hangi görünümde bulunduğunu taşır.
  **Elenen:** ayrı bir pa11y/Lighthouse işi (aynı tarayıcı oturumunu kullanmak daha hızlı).
- **Yatay taşma bütçede:** `overflowX`, telefon genişliğinde `scrollWidth − clientWidth` farkıdır
  ve limiti 0'dır.
- **CSS ve font bütçesi:** `cssKB` harici stil dosyaları ile satır içi `<style>` toplamıdır
  (blok temalarda çekirdek CSS'in çoğu satır içidir). İlk ölçüm 45–51 KB, limit 60 KB.
  `fontKB` varsayılan temada 0'dır (sistem fontu). Variation'ların limitleri aşağıdadır.
- **Navigation yedeği:** Menüsü olmayan Navigation bloğu çekirdekte `ul > ul > li` üretiyor (axe
  `list` ihlali). Tema, `block_core_navigation_render_fallback` filtresiyle Page List yedeğini üst
  düzey sayfalar için Navigation Link bloklarına çevirir (en çok 12). Menüsü olan siteler
  etkilenmez. Çekirdek düzeltilince filtre kaldırılır.
- **Üç style variation (ariumlab.com yönleri):** Hepsi `tokens/<slug>.json` dosyasından üretilir
  ve yedi kontrast çiftinin hepsini geçer. Font KB, Türkçe metin içeren sayfada ölçülmüştür.

| Variation | Zemin / metin / vurgu | Fontlar (gövde / başlık) | Biçim | Font KB |
| --- | --- | --- | --- | --: |
| Lab Precision | `#F5F7F8` / `#242424` / `#1E64E6` | Figtree / Lexend Exa | 5 px buton, 12 px kart, büyük harf buton ve menü | 110,9 |
| Graphite Engineering | `#191919` / `#EEF1F5` / `#4C8DFF` | Geist / Space Grotesk | Koyu, 4 px buton, 10 px kart | 85,1 |
| Clinical Air | `#FFFFFF` / `#0F2A5C` / `#00787A` | Manrope / Manrope | Hap buton, 16 px kart | 39,0 |

- **Lab Precision** bugünkü siteye en yakın olanıdır. Satoshi'nin yerine Figtree, Preospe'nin yerine
  geniş ve teknik görünümlü Lexend Exa kullanılır. İkincil metin `#5E6672` olduğu için bugünkü
  `#767676` / `#F5F7F8` çiftinin 4,23:1'lik sorunu kalkar (5,40:1).
- **Graphite**'ta marka mavisi `#1E64E6` koyu zeminde yaklaşık 3:1 kalır. Bu yüzden vurgu rengi
  `#4C8DFF`, buton metni koyudur.
- **Clinical Air**'deki teal `#00A3A3` beyaz üzerinde 3,10:1'de kalır. Bu yüzden vurgu rengi koyu
  teal `#00787A`'dır (5,29:1).
- **Font seçimi Türkçe kapsam ve boyuta göre:** ğĞşŞıİçÇöÖüÜâÂîÎûÛ glifleri fontTools ile
  denetlendi. ₺ işareti Figtree ve Geist'te yok; tarayıcı onu sistem fontundan gösterir.
  İtalik dosyaları paketlenmez; italik metni (ör. alıntı) tarayıcı eğerek üretir.
  **Elenen:** Unbounded (latin-ext 116 KB, kırpılınca bile 66 KB) ve Inter (latin-ext 83 KB). Türkçe
  sayfada font yükünü 150–195 KB'a çıkarıyorlardı.
- **Font bütçesi Türkçe metinle ölçülür:** Fixture'daki örnek yazıya ve ön sayfaya Türkçe bir
  başlık ve paragraf eklendi. Böylece İngilizce kurulan CI sitesi de latin-ext dosyalarını indirir
  ve yerel Türkçe siteyle aynı sonucu verir. Limitler ölçümün yaklaşık %10 üstündedir: Lab
  Precision 120, Graphite 95, Clinical Air 45 KB.
- **Variation'lar bütçede veritabanına yazmadan ölçülür:** Geliştirme probe'u
  `X-Pergel-Variation: <slug>` başlığıyla gelen istekte `styles/<slug>.json` dosyasını
  `wp_theme_json_data_theme` filtresiyle uygular. Bütçe sayfası `"variation"` ve sayfaya özel
  `"limits"` alabilir. Her variation için ön sayfa ve yazı sayfası ölçülür (axe dahil).
  **Elenen:** ölçüm sırasında global styles'ı kaydetmek (veritabanına yazar, paralel ölçümü
  bozar). Site Editor'de seçip kaydetme yolu ayrıca elle denendi: ön yüz doğru font ve renkleri
  yükledi.
- **Variation'a özel stil kuralları:** Token'la ifade edilemeyen kurallar (Lab Precision'daki
  büyük harf buton ve menü gibi) token dosyasındaki `$extensions."com.ariumlab.pergel".styles`
  alanına yazılır ve variation'a olduğu gibi kopyalanır.
- **Lab Precision 1600 px geniş alan:** ariumlab.com 1600 px konteyner kullanır; bu variation'ın
  `layout.wide` değeri 1600 px'tir (ana tema 1240 px kalır).
- **Header ve footer'lar pattern:** `parts/header.html` ve `parts/footer.html` yalnızca birer
  `wp:pattern` çağrısıdır (`pergel/header-classic`, `pergel/footer-simple`). Diğer pattern'ler
  `Block Types: core/template-part/header|footer` taşıdığı için Site Editor'de parça seçilince
  Ayarlar › Tasarım panelinde ilk sırada listelenir; tıklayınca parça o tasarıma geçer (elle
  denendi). Metinler PHP'de çevrilir. Dış grup tam genişlik, iç satır
  `align: wide` olduğu için variation'ın geniş alanını izler.

| Header | İçerik | Footer | İçerik |
| --- | --- | --- | --- |
| `classic` | Logo + başlık, menü | `simple` | Başlık + slogan, menü |
| `cta` | Classic + "Get in touch" butonu | `columns` | Marka, Sayfalar, Son yazılar, Konular, alt satır |
| `centered` | Ortalanmış başlık ve menü, alt çizgi | `cta` | Vurgu renkli çağrı bandı, altında başlık + menü |
| `search` | Classic + yalnız ikonlu arama | `centered` | Ortalanmış logo, başlık, slogan, menü |
| `topbar` | Üstte koyu şerit (slogan + iletişim), altında classic | `inverse` | Koyu zeminde marka, Sayfalar, Konular |
| `inverse` | Koyu zemin, çerçeveli buton | `search` | Marka + arama formu, alt satırda menü |

- **Telefonda gizleme çekirdek özelliğiyle:** CTA butonu ve üst şerit telefonda
  `metadata.blockVisibility.viewport.mobile: false` ile gizlenir (WP 7.0+, 480 px kırılımı).
  Ek CSS yazılmaz.
- **Site başlığı rengi `inherit`:** `core/site-title` bağlantısı grubun metin rengini alır; böylece
  koyu zeminli header ve footer'da ayrı ayar gerekmez.
- **Kök padding `0px`:** `styles.spacing.padding` üst/alt değeri birimsiz `0` iken çekirdeğin
  mobil menü kuralı `clamp(1rem, 0, 20rem)` geçersiz sayılıyor ve açılan menü kenara yapışıyordu.
- **Liste blokları arası boşluk:** Page List, Categories, Latest Posts ve Archives öğeleri arasına
  `0.5em` eklenir (theme.json blok `css`). Küçük puntolu footer listeleri axe `target-size`
  (24 px) kuralını bu olmadan geçmiyordu.
- **Yüzey kontrastı:** Footer'lar `surface` zemin kullandığı için derleyici `muted/surface` ve
  `accent/surface` çiftlerini de denetler (toplam yedi çift).
- **Header/footer'lar bütçede veritabanına yazmadan ölçülür:** Probe, `X-Pergel-Header: <ad>` ve
  `X-Pergel-Footer: <ad>` başlıklarıyla parçadaki `pergel/header-*` / `pergel/footer-*` pattern
  çağrısını `render_block_data` filtresiyle değiştirir. Altı header ve altı footer, beş sayfada
  eşleştirilerek ölçülür; biri Graphite ile.
- **Pattern önbelleği:** WordPress tema pattern dosyalarının listesini bir site transient'inde
  tutar ve anahtar tema yolunun hash'idir. Windows'ta web sunucusu ile WP-CLI yolu farklı yazdığı
  için (`C:\` / `C:/`) CLI'dan temizlemek web tarafını etkilemiyordu. Fixture bu transient'lerin
  hepsini siler.
- **Ertelenen:** Mega menü F3'e kaldı. Yapışkan (sticky) header yapılmadı: şablon parçasının
  sarmalayıcı `<header>` öğesi yalnızca kendi yüksekliği kadar olduğu için `position: sticky`
  içerideki grupta çalışmıyor; F3'te parça düzeyinde ele alınacak.

### Değişiklik (15 Ekim 2026)

F5'teki "organik ürünler provası" plandaki bir örnekti; kullanıcı bu provaya gerek olmadığını
söyledi. F5'in prova hedefi henüz belirlenmedi. Bitti tanımı (paketten tek seferde ve tekrar
üretilebilen bir site) aynı kalır.

## F2 kararları (15 Ekim 2026)

- **Tek şema:** `Settings\Schema` her alanı bir kez tanımlar; varsayılanlar, temizleme ve REST
  JSON Schema buradan üretilir. Saf PHP'dir, WordPress olmadan birim testlenir. Alan etiketleri
  ve hata metinleri şemada değil ayar ekranındadır; şema yalnızca alan yolu ve hata kodu döndürür.
- **Birleştirerek yazma:** Girdide olmayan grup ve alanlar kayıtlı değerini korur, bilinmeyen
  anahtarlar atılır, geçersiz bir değer de kayıtlı değerinde kalır ve
  `pergel/settings/invalid` eylemiyle bildirilir. Form, REST, CLI ve doğrudan `update_option`
  aynı yoldan geçer (`sanitize_option_pergel_settings` filtresi her zaman bağlıdır).
- **Uzunluk sınırı:** Metin alanlarında karakter, URL / e-posta / telefonda bayt olarak ölçülür.
  En kötü durum (her alan en uzun hâlinde, metinler 4 baytlık karakterle) birim testte 20 KB
  autoload sınırına karşı denetlenir; sonraki gruplar da aynı teste otomatik girer.
- **Boş değer geçerlidir:** REST şemasında e-posta ve URL alanları `anyOf` ile boş dizeyi de kabul
  eder; boş alan "ayarlanmamış" demektir.
- **Modül anahtarları ayarın içinde:** `modules.brand|seo|performance`. Anahtarı olmayan
  modüller (settings, cli, `pergel/modules` ile eklenenler) her zaman yüklenir.
- **Etkinleştirmede varsayılanlar yazılır:** Seçenek yoksa `get_option` her istekte bir sorgu
  yapar. Eklenti etkinleştirilirken varsayılanlar autoload ile kaydedilir (`Settings::install`).
- **WordPress testleri wp-phpunit ile değil:** Çekirdek test kütüphanesi ayrı bir test veritabanı
  ister ve PHPUnit 10'u desteklemez. `tests/integration/` kurulu bir siteyi (`PERGEL_WP_DIR`)
  `wp-load.php` ile yükler; testler değiştirdiklerini geri koyar. CI'da kurulumdan sonra
  `composer test:integration` çalışır; yerelde `PERGEL_WP_DIR=C:/xampp/htdocs/pergel-tema`.
- **Ayar ekranı build'siz PHP formu:** Ekran şemadan üretilir; React/DataForm ve derleme adımı
  yok. Üst düzey "Pergel" menüsü (eklenti listesinde "Settings" bağlantısı da var), grup başına
  bir sekme. Kayıt `options.php` + `register_setting` ile; nonce ve yetki çekirdekte.
- **Sekme başına kayıt:** Form yalnızca açık sekmenin alanlarını gönderir, şema eksik
  anahtarları eski değerle birleştirir. Böylece bir sekmeyi kaydetmek diğerini silmez.
- **Hata alanın yanında:** Geçersiz alan kaydedilmez, eski değer kalır; hata üstte bildirim ve
  alanın altında metin olarak gösterilir. Tarayıcı doğrulaması (`type="email"`, `url`) ilk
  savunmadır, sunucu şeması asıl kuraldır. Hata rengi `#b32d2e` (gri zeminde 4,5:1 üstü).
- **Medya seçici çekirdekten:** Görsel alanları `wp.media` kullanır; build'siz küçük bir
  script yalnızca ayar sayfasında yüklenir.
- **Marka bilgisi tek kaynak, Block Bindings ile:** Telefon, e-posta, adres gibi bilgiler
  pattern'lere yazılmaz; bloklar `pergel/brand` kaynağına bağlanır. Değer boşsa blok kayıtlı
  metnini gösterir; modül kapalıysa da aynı metin görünür, sayfa bozulmaz.
- **Editörde salt okunur:** Bağlı blokta değer editörde değiştirilemez (`canUserEditValue`
  false); değişiklik ayar ekranından yapılır. Böylece bir sayfada yanlışlıkla marka bilgisi
  değişmez.
- **Ad yoksa site başlığı, ana logo Site Logo:** Ayarda ayrı bir ana logo alanı yok; Site Editor'deki
  Site Logo kullanılır. Ayarda yalnızca koyu zemin logosu var.
- **SEO eklentisine tam devir (K8):** Eklenti aktifse Pergel başlık, açıklama, canonical,
  Open Graph, JSON-LD ve site haritası filtresi eklemez; panel ve meta kaydı da yüklenmez.
  Tek katkı eklentinin `Organization` düğümüne marka bilgisi eklemektir.
- **Zenginleştir, ezme:** Eklentinin doldurduğu bir özellik (ad, logo) korunur; Pergel yalnızca
  boş olanları doldurur ve `sameAs` listelerini birleştirir. Kişi (`Person`) düğümlerine ve
  diğer türlere dokunulmaz. Organization verisi marka modülü kapalı olsa da marka ayarlarından
  gelir (veri ayarda, modül anahtarı yalnızca Block Bindings'i yönetir).
- **Taşıma yok:** Pergel'in yazı başına SEO alanları bir eklenti kurulduğunda eklentiye
  aktarılmaz; eklentinin içe aktarma araçları veya F5'teki proje paketi bunu üstlenir. Veri
  silinmez, eklenti kaldırılınca yedek katman aynı alanları yeniden kullanır.
- **Yedek katman çekirdekle çakışmaz:** Tekil sayfada canonical'ı çekirdek basar, Pergel yalnızca
  liste sayfalarına ekler; robots `wp_robots` filtresinden, başlık `pre_get_document_title`'dan
  geçer. Böylece her etiket tam bir kez basılır.
- **Yazı başına küçük panel:** Başlık, açıklama ve "arama motorlarından gizle". Gizli yazı
  `noindex, follow` alır ve site haritasından çıkar. Ücretsiz eklentilerin sunduğu odak kelime,
  okunabilirlik gibi özellikler eklenmez.
- **Yazar site haritası varsayılan kapalı:** Kurumsal sitelerde yazar sayfaları blog listesini
  tekrarlar; ayardan açılabilir.
- **SEO matrisi bir şirket sitesini taklit eder:** Geçici bir Site Logo ve marka telefonu / sosyal
  profil girilir; Rank Math'te sihirbazın "Skip" seçeneği ve "şirket" türü, SEOPress'te bilgi
  grafiği türü "Organization" yapılır. Bunlar olmadan Yoast (logosuz) ve SEOPress hiç
  `Organization` basmaz, Rank Math sitenin bir kişi olduğunu varsayar. Lisans veya hesap
  gerekmez. Kişi sitelerinde (`Person`) Pergel bilinçli olarak hiçbir şey eklemez.
- **Rank Math kurulmadan önce de devredilir:** Rank Math, sihirbaz tamamlanana (veya atlanana)
  kadar ön yüzde çıktı vermez. Pergel yine de devreder: iki katmanın birlikte basma riski, birkaç
  dakikalık eksik etiketten daha kötüdür ve Rank Math yöneticiyi sihirbaza yönlendirir.
- **İç içe publisher da zenginleşir, referans değil:** The SEO Framework iç sayfalarda
  `Organization`'ı `WebSite.publisher` içinde yazar; bu düğüm de doldurulur. Yalnızca `@id`
  taşıyan referanslara (Rank Math) dokunulmaz, yoksa ikinci bir `Organization` doğardı.
- **"Tek site haritası" içerikle ölçülür:** Aynı haritayı iki adreste sunmak (TSF
  `sitemap_index.xml` ve `sitemap.xml`) tek harita sayılır; AIOSEO'nun robots.txt'teki RSS
  haritası XML haritası değildir.
- **Performans anahtarları varsayılan açık:** Emoji, oEmbed keşif bağlantıları, ziyaretçiye
  Dashicons ve font önyükleme ayrı ayrı kapatılabilir; modül anahtarı hepsini birden kapatır.
  Dashicons yalnızca kuyruktan çıkarılır, kaydı silinmez: ona bağımlı bir eklenti stili bozulmaz.
- **Yalnızca temel Latin fontu önyüklenir:** Variation'lar her fontu latin ve latin-ext olarak
  böler. Her sayfada kesin kullanılan latin dosyasıdır; latin-ext'i önyüklemek İngilizce bir
  sayfada boşa indirme olur. Türkçe metindeki ş/ğ/İ latin-ext'i normal yoldan indirir (bütçe
  Türkçe fixture'la ölçer, `fontKB` sınırları bunu içerir). Çoğunlukla Türkçe bir site
  `pergel/performance/font_preloads` filtresiyle latin-ext dosyalarını ekleyebilir. Önyükleme
  gövde ve başlık fontlarıyla sınırlıdır (en fazla iki dosya); mono gibi alt öğe fontları
  önyüklenmez.
- **Bütçe sıkılaştırıldı (F2):** Pergel dosyası 60 → 45, sorgu 75 → 50, HTML 100 → 90 KB, istek
  30 → 20, JS 30 → 25 KB. Ölçülen en yüksek değerler: 23 dosya, 25 sorgu, 73,6 KB HTML, 7 istek,
  16 KB JS; F3 blokları için pay bırakıldı. Yeni sınırlar: `jsonld ≤ 1`, `emoji = 0`,
  `fontPreloads ≤ 2`, `unusedPreloads = 0`.
- **CLI ayar yazımı ya hep ya hiç:** Ekran geçerli alanları kaydedip hatalıları eski değerinde
  tutar (formda kullanıcı hatayı yanında görür). Betik ve taşıma işlerinde yarım uygulanmış bir
  dosya fark edilmeden kalır; bu yüzden `wp pergel settings set/import` tek bir sorunda hiçbir şey
  yazmaz ve 1 ile çıkar. Bilinmeyen anahtarlar da hata sayılır (yazım hatası sessizce kaybolmasın).
  Dışa aktarılan görsel alanları ek kimliğidir; başka sitede anlamsız olduğu için `import` onları
  uyarıyla sıfırlar. CLI testi PHPUnit içinde sahte WP_CLI yerine gerçek WP-CLI ile koşar
  (`tools/ci/cli-smoke.sh`): argüman ayrıştırma, `--format` ve çıkış kodları da denenmiş olur.
- **PHPUnit CI'da JIT kapalı koşar:** setup-php `opcache.enable_cli` ile PHP 8.4 JIT'ini açıyor
  (`opcache.jit=1235`). Bu ayarla PHPUnit 10.5 çöküyor (segfault) ya da takılıyor; Pergel kodu
  içermeyen yalın bir data-provider testi de aynı şekilde çöküyor, `tracing` modu ise geçiyor.
  Sorun JIT ile PHPUnit arasında, Pergel'de değil. Bu yüzden yalnızca entegrasyon adımı
  `-d opcache.jit=disable` ile koşar; WP-CLI, PHP sunucusu ve bütçe ölçümü JIT açıkken çalışmaya
  devam eder ve denendiği kadarıyla sorunsuzdur. İşlerde 20 dakikalık süre sınırı var; takılan
  bir adım CI'ı saatlerce meşgul etmez.
- **SVG logo Safe SVG eklentisiyle (15 Ekim 2026):** WordPress SVG yüklemeyi kapalı tutar, çünkü
  bir SVG dosyası script taşıyabilir. Yükleme ve temizlik wp.org'daki ücretsiz Safe SVG
  eklentisine bırakıldı; Pergel kendi temizleyicisini taşımaz (bakımı ağır, güvenlik açığı
  doğurma riski yüksek). `Pergel\Core\Svg` yalnızca bir ekin SVG olup olmadığını ve sitede SVG
  yüklemenin açık olup olmadığını söyler. Sosyal ağlar SVG göstermediği için `og:image` ve
  `twitter:image` SVG öne çıkan görseli atlayıp varsayılan paylaşım görseline düşer (o da SVG
  ise görsel basılmaz); JSON-LD'de SVG kalır, Google kabul ediyor. Varsayılan paylaşım görseli
  alanı SVG almaz: ortam penceresi yalnızca JPG/PNG/GIF/WebP listeler, ekran SVG'yi hata olarak
  gösterir, `wp pergel settings set` reddeder, `import` uyarıyla sıfırlar. SVG yükleme kapalıyken
  Marka sekmesindeki ters logo alanı Safe SVG'yi önerir (eklenti kurma yetkisi olana bağlantıyla).

## F3 kararları (15 Ekim 2026)

- **F3 üç PR'da:** F3a altyapı, header ve breadcrumb; F3b bloklar ve blok uzantıları; F3c
  pattern kütüphanesi, görsel testler ve kurumsal starter. Her biri CI yeşilken birleşir.
- **Dönen video hero F3'ten çıkarıldı:** Tema bittikten sonra, Pergel üzerine kurulan ilk
  projede gerçek videolarla yapılacak (kullanıcı kararı). F3'ün bitti tanımı değişmedi.
- **Bloklar manifest'ten kaydedilir:** `engine/blocks/manifest.php` tüm `block.json`'ları tek PHP
  dizisinde tutar (`tools/blocks.php` üretir, CI denetler). Çekirdek blokları
  `wp_register_block_types_from_metadata_collection` ile bu dosyadan kaydeder; blok sayısı artsa
  da istek başına dosya okuma ve JSON çözme maliyeti sabit kalır. Blocks modülünün açma/kapama
  anahtarı yok: kapatılsaydı Pergel bloklarıyla kurulan pattern'ler ve sayfalar bozulurdu.
- **Logo yüksekliğe göre boyutlanır:** Pattern'lerdeki 40/48px sabit genişlik yatay yazı logosunu
  ~40×5px'e düşürüyordu. Genişlik kaldırıldı; logo 40px yükseklik ve 240px üst sınırla çizilir
  (token'lardan, variation başına değiştirilebilir). Kullanıcının editörde verdiği genişlik geçerli kalır.
- **Header davranışı Template Part özniteliğidir:** Sticky/akıllı/overlay seçenekleri şablondaki
  header örneğinde saklanır, global ayar değildir. Böylece yalnızca anasayfa overlay, diğer
  sayfalar sticky olabilir ve ayar Site Editor'de, header'ın yanında durur. CSS ve küçük script
  yalnızca seçenek açık olan sayfalarda yüklenir.
- **Görünen breadcrumb = BreadcrumbList (K8):** Breadcrumb bloğu yolu aktif SEO eklentisinin
  schema'ya giren listesinden okur, eklenti yoksa Pergel'in yolundan. Adı ve bağlantısı olan,
  gizlenmemiş adımlar kalır; bu, eklentilerin BreadcrumbList kuralıyla aynıdır. SEOPress ücretsiz
  sürümü yol üretmediği için Pergel'in yolu ve blokta basılan bir BreadcrumbList kullanılır.
  Rank Math'in blog sayfasında bağlantısız bıraktığı son adıma sayfa adresi verilir; böylece o
  sayfada da yol ve schema "Ana sayfa > Blog" olur.
- **F3b tek PR, üç commit:** içerik blokları, etkileşimli bloklar (carousel, mega menü, form),
  blok uzantıları (kullanıcı kararı).
- **Blok editör betikleri derlemesiz:** WP 7.0'ın `autoRegister` ile ürettiği ayar alanları blok
  contentOnly modundayken (kilitli pattern'ler) gizlenir, yalnızca metin/sayı/boolean
  özniteliklerini kapsar ve etiketleri çevrilmez. Bu yüzden içerik tutan bloklar içeriği çekirdek
  bloklarda (InnerBlocks şablonu) tutar ve editör tarafını elle yazılmış, derlemesiz bir
  `index.js` kaydeder (`wp.element.createElement`, ortak `pergel-blocks-editor` yardımcısı).
  Gerekirse ileride derlemeye geçmek K6 içinde kalır; şimdilik Node yalnızca test aracıdır.
- **Karşılaştırma tablosu blok stilidir:** Çekirdek tablo zaten doğru semantiği, başlık satırını
  ve düzenleme araçlarını veriyor; ayrı blok aynı şeyi ikinci kez yazardı.
- **İçindekiler id'leri tekil sayfadaki bütün başlıklara verilir:** Blok şablonda içerikten önce
  de sonra da durabilir; id'ler yalnızca blok varken verilseydi sıraya bağlı kalırdı. Ayrıca her
  bölüm bloksuz da bağlantılanabilir. HTML çapası olan başlığa dokunulmaz.
- **Harita tıklayınca yüklenir:** Gizlilik (sayfa açılışında Google'a istek ve çerez yok) ve
  performans (iframe ~1 MB). Bağlantı JavaScript olmadan da Google Maps'i açar.
- **Boş harita marka adresini kullanır:** Desenlerdeki harita bloklarına örnek adres yazılmaz;
  site sahibi adresi bir kez marka ayarlarına girer, bütün haritalar onu gösterir. Bloğa adres
  yazılırsa o geçerlidir (ikinci şube gibi).
- **Zaman çizelgesi kendi genişliğine bakar:** İki yanlı düzen ekran genişliğine (media query)
  bağlıyken dar bir sütundaki çizelge de iki yana açılıyordu. Artık container query ile blok
  40rem'den darsa tek sütundur. Kapsayıcı dikey flex içinde içeriğe göre büzülmesin diye
  `align-self`/`justify-self: stretch` taşır.
- **Görsel referanslar yalnızca CI'da:** Yazı tipi çizimi işletim sistemine göre değiştiği için
  ekran görüntüsü referansları Linux CI'da üretilip repoya girer; yerelde yalnızca axe ve taşma
  denetimi çalışır. Testler için sayfa oluşturulmaz (menü yedeği, bütçe ve veritabanı etkilenirdi);
  pattern'ler `X-Pergel-Pattern` başlığıyla mevcut örnek sayfaya çizilir. Yalnızca 8 tam sayfanın
  ekran görüntüsü alınır; bölümleri onlar ve axe kapsar.
- **İletişim/teklif formu gönderimleri:** yöneticiye e-posta ve panelde gizli bir yazı türünde
  kayıt; e-posta kaybolsa da talep kaybolmaz (kullanıcı kararı). Yalnızca yerelde, `pre_wp_mail`
  ile yakalanarak test edilir.
- **Etkileşim çekirdeğin Interactivity API'siyle:** Carousel, mega menü ve form aynı çekirdek
  çalışma zamanını paylaşır (~15 KB, bir kez); jQuery ya da ayrı kütüphane yoktur. Sunucu tam
  HTML'i bastığı için script'siz de çalışırlar.
- **Form belirteci nonce değil, imzalı belirteçtir:** Nonce oturuma ve 12–24 saate bağlıdır;
  LSCache/Cloudflare'de önbelleğe alınmış sayfada süresi geçer ve form bozulur. HMAC belirteci
  önbellekte de geçerli kalır, blok ayarlarını (tür, isteğe bağlı alanlar, sayfa) sunucuya
  değiştirilemez biçimde taşır. Spam'e karşı tuzak alanı, 3 saniye kuralı, bağlantı başına sınır
  ve isteğe bağlı Turnstile vardır.
- **Turnstile anahtarları sabitlerde:** Gizli anahtar veritabanına, ayar dışa aktarımına ve
  proje paketlerine girmesin diye `wp-config.php` sabitlerinden okunur. Anahtar yoksa Turnstile
  yoktur, form yine çalışır.
- **IP adresi saklanmaz:** Sınır için yalnızca özetlenmiş hali 10 dakikalık bir transient
  anahtarında kullanılır. Mesajla birlikte ad, e-posta, mesaj ve isteğe bağlı alanlar saklanır.
- **Script'siz form admin-post'a gider:** Aynı denetimlerden geçer ve sonuç koduyla sayfaya döner;
  JavaScript kapalı ya da yüklenememiş bir ziyaretçinin talebi de kaybolmaz.
- **Carousel kendiliğinden dönmez:** Otomatik oynatma okunurluğu ve erişilebilirliği bozar
  (WCAG 2.2.2), dönüşüm getirmez. Slaytlar ziyaretçinin denetimindedir.
- **Mega menü paneli bir template part alanıdır:** Panel içeriği (sütunlar, listeler, görsel,
  CTA) header gibi Site Editor'de bloklarla kurulur ve birden çok menü aynı parçayı kullanabilir;
  menü bloğunun içine iç blok olarak gömülseydi Navigation bloğunun sınırlı izinli blok listesine
  takılırdı. Footer'da panel yukarı açılır. Esc yalnızca paneli kapatır, mobil menüyü değil.
  İçeriği boş parça hiçbir şey basmaz.
- **Çekirdeğin page-list iç içe liste sorunu bizim değil:** Navigation'da Page List başka
  öğelerle yan yana konunca çekirdek onu `<ul>` içine sarar ve axe `list` hatası verir. Pergel
  header'ları buna düşmez; menüye elle öğe eklenecekse Page List yerine bağlantı öğeleri
  kullanılır.
- **Sayfa görüntülemesinde editör dosyaları kaydedilmez:** Blok sayısı arttıkça her istekte
  okunan `index.asset.php` dosyaları bütçeyi (Pergel dosyası ≤ 45) aşıyordu (49–54). Editör
  betikleri yalnızca yönetim, WP-CLI ve REST isteklerinde kaydedilir; ön yüz 31–37 dosyada kaldı.
- **Geliştirme notu, pattern önbelleği:** WordPress tema pattern dosyalarının listesini site
  transient'inde (`wp_theme_files_patterns-<hash>`) tutar; yeni bir `patterns/*.php` dosyası bu
  silinene kadar görünmez ve CLI ile Apache ayrı anahtar kullanabilir. Yerelde bu transient'ler
  silinir; canlıda tema sürümünü artırmak önbelleği geçersiz kılar.
- **Cihaza göre gizleme ve yapışkan konum çekirdekten:** Plan üç blok uzantısı yazmaktı. WP 7.0/7.1
  blok görünürlüğünü cihaz başına (`blockVisibility.viewport`), `appearanceTools` da Group için
  sticky konumu zaten getiriyor. Aynısını yazmak editörde iki ayrı "gizle" ayarı ve iki CSS
  yolu demekti; Pergel çekirdeğinkini açar ve sınırlarını verir, kendi kodu yalnızca
  animasyondur.
- **Cihaz sınırları 600 ve 1024 piksel:** 600px çekirdek Navigation'ın mobil menüye geçtiği
  sınırla aynıdır, böylece "mobilde gizle" ile hamburger menü aynı genişlikte devreye girer.
  1024px yatay tutulan tabletleri tablet sayar; çekirdeğin 782px'i onları masaüstüne atıyordu.
- **Kaydırma animasyonu yalnızca CSS:** IntersectionObserver betiği her sayfaya JS ve kaydırma
  sırasında iş ekler, betik gelmeden içeriği gizleyip sonra göstermek de LCP ve CLS riskidir.
  `view()` zaman çizelgesi tarayıcıda, ana iş parçacığı dışında akar; desteklemeyen tarayıcı
  içeriği hemen gösterir. Animasyon bir süsleme olduğu için bu kayıp kabul edildi.
- **Animasyon blok tamamen görününce biter:** Aralık `entry 0%–90%`. `entry 0%–100%` blok tam
  girdiğinde opaklığı 0,98'de bırakıyor ve sayfanın son bloğu hiç tamamlanmıyordu.
- **Ekleyicide yalnızca Pergel kütüphanesi:** Çekirdeğin hazır pattern'leri ve uzak Pattern
  Directory kapatıldı. Çekirdek pattern'leri Pergel token'larını ve kurallarını bilmez (sabit
  renk, H1, dış görsel); uzak dizin de editörden wordpress.org'a istek atar. Eklenti ve site
  pattern'leri açık kaldı.
- **Çekirdek kategoriler + `pergel-*`:** Kullanıcı ekleyicide tanıdık kategorileri görür; yalnızca
  çekirdekte karşılığı olmayan altı kategori (Numbers, Process, Clients, Pricing, FAQ, Full pages)
  eklendi. Kullanılmayan tek `pergel` kategorisi kaldırıldı.
- **Bölüm başına tek kök blok:** WordPress 7.1 tek köklü pattern'i bölüm olarak ekleyip yalnızca
  içerik modunda açar; müşteri metni ve görseli değiştirir, yerleşimi kazara bozmaz. Bu yüzden
  kural CI'da zorunlu; tam sayfalar bölümlere `core/pattern` referansıyla bağlanır, aynı bölüm iki
  yerde kopyalanmaz.
- **Hero H1'i için başlıksız sayfa şablonu:** Hero kendi H1'ini taşır; normal sayfa şablonu da
  başlığı H1 bastığı için iki H1 olurdu. `page-landing` şablonu başlığı basmaz. Hero dışındaki
  pattern'lerde H1 yasak.
- **Yer tutucular kendi ürettiğimiz nötr SVG'ler** (kullanıcı kararı): Lisans riski sıfır, toplam
  birkaç KB, her variation'da nötr görünür. Gerçek fotoğraflar ilk projede gelir.
- **Editör kontrolü sıkı doğrulama ile:** Çekirdeğin ayrıştırıcısı eksik `wp-block-heading` gibi
  farkları deprecation ile sessizce düzeltir; ama şablonda `wp:pattern` ile çağrılan pattern ön
  yüzde dosyadaki işaretlemeyle basılır. Bu
  yüzden her blok `validateBlock` ile bugünkü `save` çıktısına karşı denetlenir.
- **Ayırıcı rengi `line`, `border` değil:** Renk preset'inin metin sınıfı `.has-{slug}-color`'dır;
  WordPress kenarlık rengi verilen her bloğa `.has-border-color` ekler. `border` adlı renk bu
  yüzden kenarlıklı her kartın yazısını ayırıcı rengine boyuyordu. Renk `line` oldu; derleyici
  çekirdeğin genel sınıflarıyla çakışan `text`, `link` ve `border` adlarını reddeder.
- **Pattern'ler yalnızca var olan preset'lere başvurur:** `tools/patterns.php --check`
  `var:preset|…`, `var(--wp--preset--…)` ve `backgroundColor`/`textColor`/`borderColor`/
  `overlayColor`/`fontSize`/`fontFamily` adlarını `theme.json`'daki preset'lerle karşılaştırır;
  yeniden adlandırılan bir preset tarayıcıda sessizce boş kalır.
- **Izgara içeren bölümler akış düzeninde:** Dikey flex yığını (`stack`) içinde
  `container-type: inline-size` taşıyan bir çocuk (zaman çizelgesi, kart ızgarası) genişliğini
  içeriğinden alamıyor, dar sütunda yüksekliği şişiyordu. Izgara veya zaman çizelgesi saran
  yığınlar varsayılan akış düzenine (`flow`, aralık blok boşluğuyla) geçti; flex yığın yalnızca
  metin ve buton gruplarında kaldı.
- **Zaman çizelgesi öğeleri `box-sizing: border-box`:** İki yanlı düzende çift öğeler dolgu
  yüzünden 8 piksel taşıyor, tek öğelerin noktası çizginin yanına düşüyordu.
- **Sabit rakamlar sayılmaz:** Kuruluş yılı ("2009") ve "0 gün kapalı" gibi rakamlarda sayaç
  süresi 0'dır; yıl binlik ayırıcıyla sayılmaz, sıfır da boşuna canlandırılmaz.
- **Sütun içindeki ızgara `wide` hizası taşımaz:** Hizalama yalnızca bölüm kökündeki ızgarada
  anlamlıdır; sütun içinde `align` özniteliği tamamen kaldırılır (boş değerle bırakılan
  `"align":null` blok doğrulamasında hata verir).
- **Koyu bölümde tarih rengi açıkça verilir:** `theme.json` yazı tarihini `muted` yapar; koyu
  zeminde bu okunmuyordu. Tarihler koyu tonlarda `base`, açık tonlarda `muted` rengini taşır.
- **Starter'lar eklenti zip'ine girmez:** `starters/` geliştirme ve demo içindir (`export-ignore`);
  zip ile kurulan sitede paket klasör yoluyla kurulur. Starter içeriği pattern'leri kopyalar,
  sayfa editörde ekleyiciden kurulmuş gibi açılır; pattern sonradan değişse de müşterinin sayfası
  değişmez.
- **Starter'ın yaptığı her şey meta ile işaretli:** Tekrar kurulum aynı sayfaları bulur (kopya
  yapmaz), kaldırma yalnızca onları siler. Okuma ayarları silmeden önce okunur, çünkü WordPress
  ön sayfa silinince ayarı kendisi sıfırlar ve rapor değişikliği göstermezdi.
- **Starter içeriği kses'siz kaydedilir:** WP-CLI'da oturum açmış kullanıcı yoktur, bu yüzden
  içerik `unfiltered_html` yetkisi olmayan kullanıcının HTML süzgecinden geçerdi. İçerik temanın
  kendi pattern dosyalarından gelir, şablonlar kadar güvenilirdir; süzgeç yalnızca
  `wp_insert_post` çağrısı boyunca kapatılır.
- **Starter sayfalarında farklı hero'lar:** About, Pricing ve Portfolio aynı hero'yu, Services ve
  Team de bir başkasını paylaşıyordu; kurulan sitede beş sayfa iki H1 tekrar ediyordu. Her tam
  sayfa artık kendi hero'sunu kullanır ve ton sırası korunur.

## F4 kararları (15 Ekim 2026)

- **F4 üç PR'da:** F4a WooCommerce modülü, blok şablonları, satış modları ve ürün CTA'sı; F4b
  hızlı arama, filtre stilleri ve P2 özellikleri; F4c mağaza starter'ı, ödeme e2e ve ölçek testi
  (kullanıcı kararı).
- **P2 özelliklerinin dördü de F4b'de:** karşılaştırma ve istek listesi, swatch ve yapışkan sepete
  ekle, tahmini teslimat ve stok bildirimi, beden rehberi ve birlikte al. Her biri ayrı açılır,
  varsayılan kapalıdır; sayfa görüntülemede veritabanına yazmaz (kullanıcı kararı).
- **Divit F4'te sahte sağlayıcıyla:** Teklif sağlayıcı arayüzü ve bir sahte sağlayıcı yazılır;
  gerçek Divit entegrasyonu staging'de denenir (kullanıcı kararı).
- **Mağaza ayrı yerel sitede:** `localhost/pergel-shop` kendi veritabanıyla WooCommerce
  çalıştırır. `pergel-tema` WooCommerce'siz kalır; bütçesi ve testleri mağaza olmayan siteyi ölçer.
- **Woo modülü yalnızca WooCommerce etkinken yüklenir:** Modül kaydı `class_exists`
  denetimiyle yapılır; WooCommerce yoksa modülün hiçbir sınıfı açılmaz ve "Mağaza" sekmesi ile
  modül anahtarı ayar ekranında gösterilmez. Gizli alanlar kayıtlı değerlerini korur.
- **HPOS ve blok sepet/ödeme uyumu bildirilir:** Motor `before_woocommerce_init` üzerinde iki
  özellik için de uyumlu olduğunu bildirir; WooCommerce uyumsuzluk uyarısı göstermez.
- **WooCommerce'in klasik dosyaları yalnızca klasik işaretlemede:** WooCommerce 11.2 klasik
  CSS'lerini (`woocommerce-general`, `-layout`, `-smallscreen`, `-blocktheme`) ve jQuery
  betiklerini her sayfada yükler. Bunları yalnızca klasik işaretleme kullanır: hesap sayfaları,
  WooCommerce kısa kodları ve klasik şablon basan bloklar. Temanın mağaza şablonları kendi
  dosyasını getiren bloklardan oluştuğu için bu dosyalar mağaza sayfalarında da yüklenmez
  (`pergel/woo/classic_files` süzgeciyle değiştirilebilir). Yerel ölçümde ön sayfa 15 yerine 5
  JS/CSS dosyası, 83 yerine 24 KB yükledi ve jQuery kalktı. Ayar varsayılan açık, kapatılabilir.
- **Sepet parçaları (cart fragments) kapalı:** Her sayfada sunucudan sepeti isteyen eski betik
  kaldırılır; mini sepet bloğu buna ihtiyaç duymaz. Ayar varsayılan açık.
- **Sipariş kaynağı izleme dokunulmadı:** `sourcebuster` ve `wc-order-attribution` her sayfada
  kalır; bu WooCommerce'in kendi özellik ayarıdır (WooCommerce → Ayarlar → Gelişmiş → Özellikler)
  ve kapatma kararı site sahibinindir.
- **Entegrasyon testleri iki sitede:** Test takımı WooCommerce'siz sitede mağaza testlerini
  atlar. Yerelde `PERGEL_WP_DIR=…/pergel-shop` ile de çalıştırılır; CI'daki WooCommerce'li iş
  F4c'de gelir.
- **Mağaza şablonları blok şablonu, ön yüzde jQuery yok (K10):** Tema mağaza, ürün, kategori,
  etiket, öznitelik, ürün araması, sepet, ödeme ve sipariş onayı için kendi blok şablonlarını
  getirir. Ürün sayfası galeri ve `add-to-cart-with-options` bloklarını, açılır-kapanır ürün
  ayrıntılarını (açıklama, özellikler, yorumlar) ve blok yorum formunu kullanır; klasik
  galeri/sepete ekle şablonu ve onların jQuery betikleri yüklenmez. Basit ve varyasyonlu ürünle
  tam satın alma akışı yerelde denendi.
- **Mini sepet Block Hooks ile:** `woocommerce/mini-cart` Pergel header pattern'lerinin menüsünden
  sonra eklenir; header'larda mağaza bloğu yazılı durmaz, WooCommerce'siz sitede hiç görünmez.
  Ayar (`woo.mini_cart`) varsayılan açık. Sepet ve ödeme sayfalarında eklenmez: orada blok eski
  işaretlemesini basar ve betiği Store API adresini bilmeden istek atıp 404 alır. Sayı oturumdan
  gelir; sepet parçalarına gerek yoktur.
- **Ürün yolunda mağaza sayfası ve kategori:** `pergel/breadcrumb` ve BreadcrumbList ürün ve
  ürün kategorisi sayfalarında Ana sayfa → Mağaza → üst kategoriler → kategori → ürün yolunu
  izler (`pergel/seo/crumbs` süzgeci, `Woo\Trail`). Kategori seçimi WooCommerce'in kendi
  breadcrumb süzgecini (`woocommerce_breadcrumb_product_terms_args`) kullanır.
- **WooCommerce bloklarına tema renkleri:** Sıralama, indirim etiketi ve sepet/ödeme form alanları
  için blok başına küçük CSS dosyaları yalnızca blok sayfada varsa yüklenir. WooCommerce bu
  parçalarda beyaz zemin ve gri yazıyı sabit verdiği için koyu varyasyonda (graphite) okunmuyordu;
  artık varyasyon token'larını izler.
- **İlgili ürünler `section` değil `div`:** WooCommerce'in Product Collection bloğu bildirim
  kutusunu düzenli ifadeyle "ilk `<div`"in içine ekler; `tagName: section` iken bu ilk ürünün
  görseli oluyor ve kartlar kayıyordu. Pattern `pergel/hidden-product-related` sarmalayıcı
  olarak `div` kullanır.
- **Gizli mağaza pattern'lerinde WooCommerce blokları:** Pattern kuralları WooCommerce
  bloklarına yalnızca `pergel/hidden-product-*` pattern'lerinde izin verir; bunları yalnızca
  mağaza şablonları kullanır. Ekleyicide görünen pattern'ler çekirdek ve Pergel bloklarıyla kalır.
- **WooCommerce işaretlemesinde erişilebilirlik düzeltmeleri:** Mağaza sayfalarında axe üç sorun
  buldu, üçü de düzeltildi (`Woo\Markup`, `woocommerce-mini-cart.css`):
  kapalı mini sepet çekmecesi yalnızca saydamlaştığı için klavye içindeki bağlantılara
  giriyordu, solma bitince `visibility: hidden` olur; varyasyon seçicisi `role="list"` olduğu
  hâlde öznitelikleri liste öğesi değildi, `role="listitem"` alır; varyasyon seçilmeden
  sepete ekle düğmesi yalnızca soluk görünüyordu, aynı duruma bağlı `aria-disabled` alır.
  Ödeme sayfasındaki `aria-label`'lı iskeletler yüklenirken geçicidir, yüklenince kalkar.
  Mağaza, kategori, iki ürün, arama, boş ve dolu sepet ile ödeme sayfaları üç varyasyonda ve
  varsayılan stilde, 1280 ve 360 px'te axe temiz.
- **Satış modları üç seviyede, en katı kazanır:** Mağaza, kategori ve ürün `sale`, `login`,
  `quote` ya da `catalog` seçebilir. Ürünün kendi modu kazanır; yoksa kategorilerinden en katısı
  (birden çok kategorideki ürün, fiyat gizleyen bir kategorideyse fiyatı gizlenir), yoksa
  mağazanınki. Kategori modu alt kategorilere geçer. Mod bilgisi tek bir otomatik yüklenen
  seçenekte indekslenir; sayfa görüntülemesinde veritabanına yazılmaz (`Woo\Modes`).
- **Fiyat yalnızca gizlenmez, kaldırılır:** CSS ile gizlemek fiyatı HTML'de, Store API'de ve
  şemada bırakır. Satılmayan üründe fiyat getter'lardan boş döner, `price_html` boş, ürün
  satın alınamaz, yapılandırılmış veride ve SEO eklentilerinin graph'ında `offers` yoktur
  (`Woo\Selling`). Rank Math'te ürün düğümü satış modunda `offers` taşır, teklif modunda
  taşımaz; Yoast'ın ücretsiz sürümü ürün şeması basmaz, süzgeç Yoast WooCommerce SEO için
  güvenlik ağıdır.
- **Fiyat geri okunamasın:** Fiyat gizleyen listede fiyat filtresi, fiyat sıralaması ve Store
  API fiyat aralığı kapanır; aksi hâlde aralık daraltılarak fiyat bulunabilirdi. F6b'de
  genişledi: kullanımdaki herhangi bir mod (ürünün kendi modu ya da ikinci bir kategori dahil)
  ziyaretçiden fiyat gizliyorsa fiyat araçları bütün listelerde kapanır. Satan bir listede de
  gizli ürün bulunabiliyordu; gizli ürünleri sorgudan dışlamak daha ince ama mod çözümünü SQL'e
  taşımayı gerektiriyor, 1.0 için güvenli taraf seçildi.
- **Modlar ön yüzde, Store API'de ve REST'te uygulanır:** Yönetim ekranları, `edit_products`
  yetkili kullanıcının REST ve AJAX istekleri, cron ve WP-CLI fiyatları görür; sipariş, rapor ve
  içe aktarma bozulmaz. F6b'ye kadar REST'te yalnızca Store API'de uygulanıyordu ve
  `pergel/v1/shop-list` gizli fiyatları herkese gösteriyordu.
- **Mini sepeti WooCommerce'ten devralmak:** WooCommerce mini sepeti kendi kanca kaydıyla her
  header pattern'ine ekliyordu; `woo.mini_cart` kapalıyken ya da hiçbir şey satılmazken de
  görünüyordu. Pergel header'larında karar `Woo\MiniCart`'ta (öncelik 20) verilir.
- **Gizli üründe sepet formu yok:** Form blokları boş döner. Teklif ve giriş CTA'sı, değişken
  ürün için kendi varyasyon seçimiyle birlikte F4a 4. adımdaki `pergel/product-cta` bloğunda.
- **Dar sepette satır düzeni:** WooCommerce'in sabit 132 px ürün sütunu ve tek satırlık tasarruf
  rozeti Türkçe metinle sepeti 360 px'te yana taşırıyordu; `woocommerce-forms.css` ürün
  sütununu esnek yapar.
- **Satış modlarının sınırları (kabul edilen):**
  - Karışık katalogda satış modundaki listeler ve Store API gizli ürünleri saklanan fiyatlarına
    göre sıralar ve süzer; koleksiyon fiyat aralığı onları da içerir. Store API'deki liste
    kararı yalnızca mağaza modunu izler.
  - Editörde sıralaması fiyat olarak ayarlanmış bir Product Collection bloğu değiştirilmez.
  - Store API gizli ürün için fiyatı `"0"` verir; gizli değişken ürünün Store API
    `stock_availability` değeri "stokta yok" der.
  - Merchant Center'a daha önce gönderilmiş ürünler orada kalır; oradan kaldırılmalıdır.
  - Hızlı ve toplu düzenlemede mod alanı yok.
  - Yönetim etiketleri İngilizce yazılır; Türkçesi paketteki `tr_TR` çevirisinden gelir.
  - Rank Math kurulum sihirbazı tamamlanmadan şema basmaz.
- **Yerel mağaza "yakında" kipindeydi:** `pergel-shop`'ta WooCommerce'in `woocommerce_coming_soon`
  seçeneği açıktı; oturumsuz ziyaretçi mağaza sayfaları yerine "yakında" sayfasını görüyordu.
  Kapatıldı; mağaza ölçümleri ve axe taramaları oturumsuz gerçek sayfalarda yapılır.
- **Bilinen ve kabul edilen:** WooCommerce'in bazı metinleri ("Related products" gibi) yerel
  sitede Türkçe çeviri paketi kurulmadığı için İngilizce görünür. Tema ve eklenti metinleri
  paketteki `tr_TR` çevirisiyle Türkçedir (aşağıda "Türkçe çeviri").
- **Ürün sorusu bloğu (F4a 4. adım):**
  - Katalog modu yalnızca iletişim bağlantılarını gösterir; teklif formu yalnızca `quote`
    modunda. Katalog "fiyat yok, satış yok" demektir, talep toplamak değil.
  - `generate_lead` yalnızca ürün sorusunda değil, tüm Pergel formlarında ve iletişim
    bağlantılarında gönderilir; Analytics kurulu değilse hiçbir şey olmaz.
  - Divit, F7'de `pergel/woo/quote_providers` süzgeciyle bir `QuoteProvider` olarak bağlanır.
    F4'te arayüz ve testlerdeki sahte sağlayıcı var; Divit'in API'si tahmin edilmedi.
  - Sağlayıcı çağrıları eşzamanlı: mesaj önce saklanıp e-postalandığı için kaybolmaz; başarısız
    aktarım mesajın üstünde görünür ve elle izlenir. Kuyruk, gerçek bir sağlayıcıyla gerekirse
    eklenir.
  - Blok WooCommerce yoksa hiç kaydedilmez; mağazasız sitenin editöründe ve bütçesinde yok.
  - Editör ayarları `index.js`'te çevrilebilir ToggleControl'lerle; çekirdeğin otomatik ayar
    paneli etiketleri çevirmediği için kullanılmadı.
  - Konu alanları genel Forms modülünde süzgeçlerle; Forms WooCommerce'i bilmez.
- **Ürün yorumu formu dar ekranda:** WooCommerce'in `cols="45"` yorum kutusu 360 px'te sağdan
  kesiliyordu (sayfa kaymıyordu, axe'ın taşma denetimi buldu). Alanlar form genişliğini alır.
- **Mağaza pattern'lerinin editör denetimi:** `woocommerce/product-reviews` WooCommerce'te
  yalnızca Site Editor'de kayıtlı, yazı editöründe değil. `editor-check.mjs` gizli mağaza
  pattern'lerini (`pergel/hidden-product-*`) Site Editor'de, ürün şablonunda denetler;
  WooCommerce yoksa onları atlar ve adlarını yazar. WooCommerce'li CI işi F4c'de gelir; o zamana
  kadar yerel mağaza sitesinde çalıştırılır.

- **F4b arama (16 Ekim 2026, kullanıcı kararları):** Arama ayrı bir `search` modülüdür ve
  kendi anahtarıyla açılıp kapanır. Yazı, sayfa ve herkese açık tüm türler indekslenir; ürünler
  SKU, kategori, etiket ve öznitelikleriyle girer. Öneriler çekirdek Search bloğuna eklenir;
  pattern'ler değişmez, JS yoksa düz form olarak çalışır. `?s=` araması ve ürün araması aynı
  indeksi kullanır ve `LIKE '%x%'` kalkar. Önerilerde küçük görsel, fiyat (yalnızca `sale`
  modunda), kategori eşleşmeleri ve "tüm sonuçlar" bağlantısı gösterilir.
  - Eşleşme kelimenin başından yapılır. Türkçe ek kökleme yok: "masalar" sorgusu "masa"yı bulmaz.
    Türkçe harfler ve aksanlar her iki tarafta da katlanır (Işık = isik).
  - Bir arama en fazla 1000 sonuç döndürür. İlk kurulum bitene kadar WordPress'in kendi araması
    çalışır. Kurulum cron'da 20 saniyelik partilerle yapılır; `wp pergel search reindex` hemen
    kurar.
  - Hız sınırı (dakikada 120) yalnızca kalıcı object cache varken uygulanır, çünkü sayaç
    olmadan her tuş vuruşunda DB'ye yazmak gerekirdi. Yanıt herkese aynıdır, çerez gönderilmez
    ve 5 dakika önbelleğe alınabilir.
  - Görünüm betiği Interactivity API değil, düz bir script modülüdür: çekirdek Search bloğu
    formda kendi `core/search` store'unu çalıştırdığı için ikisi çakışırdı.
  - Modül kapalıyken kaydedilen içerik indekse girmez; modül yeniden açılınca indeks yeniden
    kurulur (`Core\Plugin::switched` → `enabled()`).
  - Eklenti kaldırıldığında tablo silinmez, çünkü motorda henüz `uninstall.php` yok. Bu iş
    F6'ya, diğer seçeneklerle birlikte yapılmak üzere bırakıldı (F6c'de yapıldı, `Core\Uninstall`).
- **F4b ürün filtreleri (16 Ekim 2026, kullanıcı kararları):** Mağaza şablonunda filtreler
  solda bir kenar çubuğudur (16rem); 782 px altında WooCommerce'in kendi çekmecesine ("Ürünleri
  filtrele" düğmesi) geçer. Filtreler gizli `pergel/hidden-product-filters` pattern'inde:
  seçili filtreler ve "Clear filters", fiyat, kategori, stok durumu ve mağazanın ilk üç
  özniteliği için birer öznitelik filtresi (öznitelik filtresi ID ister; Site Editor'den
  değiştirilir). Puan filtresi konmadı.
  - Stiller `woocommerce-product-filters.css`'te, `.wc-block-product-filters` kapsamında;
    `:where()` kullanılmadı, çünkü WooCommerce'in alt blok stilleri temanınkinden sonra
    basılıyor ve sıfır özgüllük kaybederdi. Renkler WooCommerce'in custom property'lerine
    preset token'larıyla verilir; editörde seçilen renk yine kazanır.
  - WooCommerce kapalı çekmeceyi yalnızca ekran dışına kaydırıyordu; klavye içine girebiliyordu.
    Kapalı çekmece kayma bittikten sonra `visibility: hidden` olur, geniş ekranda görünür kalır.
    Çekmece açılınca WooCommerce odağı içine taşımıyor; bu WooCommerce'in davranışı, değiştirilmedi.
  - Fiyat filtresi fiyat gizleyen modlarda zaten basılmaz (`Woo\Selling`).
  - Mağaza sayfalarında çekirdeğin satır içi stil sınırı WooCommerce stilleriyle dolduğu için
    filtre stili `<head>`'de ayrı bir `<link>` olarak gelir (+1 istek, ~5 KB). Filtre blokları
    WooCommerce'in beş script modülünü ekler.
  - Tema pattern'leri `wp_theme_files_patterns-*` site transient'inde önbelleğe alınır;
    anahtar web ve CLI'da farklı yol biçiminden üretildiği için yeni bir pattern
    `wp transient delete` ile değil, ilgili satırlar silinerek görünür olur (yalnızca
    `WP_DEVELOPMENT_MODE` `theme` değilken; canlıda tema güncellemesi sürümü değiştirdiği için
    önbellek yenilenir).
- **Tasarım hızlı düzeltmeleri (16 Ekim 2026, kullanıcı kararı "önce hızlı düzeltmeler"):** Stil
  seçimi (D1) beklerken, her stilde geçerli olan üç düzeltme yapıldı.
  - Görseli olmayan ürün, temanın `placeholders/product.svg` görselini gösterir: şeffaf zemin
    üzerinde ince bir çerçeve, arkada `surface` rengi. WooCommerce'in gri görseli koyu stilde
    açık bir kutu olarak görünüyordu. Filtre `woocommerce_placeholder_img_src`. Mağaza sahibi
    ayarlarda kendi görselini seçtiyse ona dokunulmaz; dosya adı `woocommerce-placeholder`
    ile başlayan varsayılan görsel temanınkiyle değiştirilir. Tek filtre hem ürün görseli
    bloğunu hem ürün galerisini kapsar.
  - Ürün kartı: başlık accent bağlantı rengi yerine `contrast` renktedir (600, altı çizgisiz;
    üzerine gelince çizgili). Karttaki düğme çerçevelidir, üzerine gelince dolar; dolu düğme
    ürün sayfasında kalır. WooCommerce düğme çerçevesini daha özgül bir seçiciyle kapattığı için
    çerçeve iç gölgeyle (`inset box-shadow`) çizilir. Kart görseli kare ve `contain` ölçeklidir,
    `surface` zemin üzerindedir; laboratuvar ürünlerinin fotoğrafları kırpılmaz. İndirim rozeti
    büyük harfli çerçeveli kutu yerine küçük, dolu (`contrast` zemin) bir etikettir.
  - Yedek menü (menü atanmamış Navigation bloğu) WooCommerce'in sepet, ödeme ve hesap
    sayfalarını listelemez; bunlara header ikonlarından ulaşılır. Header ve footer aynı yedeği
    kullanır.
- **Türkçe çeviri (16 Ekim 2026):** F6'daki "çeviriler" maddesinin Türkçe kısmı öne alındı.
  - Tema ve eklenti `tr_TR` çevirisini kendi içinde taşır: `theme/languages/` ve
    `engine/languages/` altında `.po` (kaynak), `.mo`, `.l10n.php` ve editör script'leri için
    `.json`. WordPress.org dil paketi beklenmez; ariumlab.com'un dili Türkçe.
  - Temanın dosyaları alan adı olmadan adlanır (`tr_TR.po`, `tr_TR.mo`, `tr_TR.l10n.php`):
    WordPress temanın kendi klasöründe `{locale}.mo` arar. Eklentininkiler
    `pergel-engine-tr_TR.*`.
  - `php tools/i18n.php` (`composer i18n`) `.pot`'u `.po`'ya birleştirir ve derler. `.po` kaynak
    satırı tutmaz; kod taşınınca değişmez. Derlenmiş dosyalara tarih olarak `.po`'nun
    `PO-Revision-Date` değeri yazılır ve JSON'lardan WP-CLI sürümünü taşıyan `generator`
    çıkarılır; aynı girdi her makinede aynı dosyaları üretir.
  - CI `php tools/i18n.php --check` çalıştırır: çevrilmemiş, `fuzzy` ya da yer tutucusu
    (`%s`, `%1$s`) tutmayan metin veya güncel olmayan derlenmiş dosya varsa kırmızıdır. Yeni
    metin ekleyen değişiklik Türkçesini de getirir.
  - Terimler: "Hero" → "Manşet", "Sticky" → "Sabit", call to action → "eylem çağrısı",
    renk adları Zemin/Yüzey/Soluk/Çizgi/Vurgu. Stil varyasyonu adları (marka adları gibi)
    çevrilmez. Örnek içerikteki fiyatlar ₺, kişi ve adresler Türkçedir.
  - Bütçedeki `pergel_files` sayımı `languages/` altındaki dosyaları saymaz. Ölçü, sınıfların
    yalnızca gerektiğinde yüklendiğini denetler; derlenmiş çeviriler kod değil veridir ve
    yalnızca Türkçe sitede yüklenir (yerelde blog 44 + 2 = 46 > 45, CI'nin İngilizce sitesinde
    0). Limit 45 olarak kaldı.
  - Bilinen: eklentide "Remove" hem Performans bölümü başlığı hem düğme olarak aynı metin;
    ikisi de "Kaldır". Gerekirse bağlam (`_x`) eklenir.

- **F4b P2 planı (16 Ekim 2026, kullanıcı kararları):** P2 dört ayrı PR'da, bu sırayla:
  (1) karşılaştırma ve istek listesi, (2) swatch ve yapışkan sepete ekle, (3) tahmini teslimat
  ve stok bildirimi, (4) beden rehberi ve birlikte al. Her `woo.*` anahtarı varsayılan kapalıdır
  ve yalnızca açıkken kaydedilir; kapalıyken hiçbir dosya yüklenmez. Sayfa görüntülemede
  veritabanına yazılmaz, `Modes::hidden()` gözetilir, derleme adımı yoktur. Stok bildirimi
  çift onaylı (double opt-in) olacak.
- **P2-1 istek listesi ve karşılaştırma (16 Ekim 2026):**
  - Ayarlar: `woo.wishlist`, `woo.wishlist_url`, `woo.compare`, `woo.compare_url` (Mağaza
    sekmesi, "Wishlist and compare" bölümü). Liste sayfası sitenin normal bir sayfasıdır;
    adres girilince header'larda bağlantı ve sayı görünür.
  - Ziyaretçinin listeleri tarayıcıda (localStorage) tutulur: önbelleğe alınan sayfa herkese
    aynıdır, sunucuda oturum ya da çerez açılmaz. Oturum açmış müşterinin istek listesi hesabında
    (`_pergel_wishlist` user meta) tutulur ve cihazlar arası izler; girişten önce tarayıcıda
    biriken liste girişte hesaba birleştirilir. Karşılaştırma her zaman tarayıcıdadır, en fazla
    4 ürün; istek listesi en fazla 100.
  - Düğmeler ürün kartına ve ürün sayfasına block hooks ile kendiliğinden gelir; elle de
    yerleştirilebilir (`pergel/product-actions`). WooCommerce'in sepet formu şablon parçası
    kendi ürün düğmesini taşıdığı için o parçaya eklenmez (çift düğme oluyordu). Düğmeler
    script çalışana kadar `hidden`'dır; script'siz ziyaretçi işlemeyen bir düğme görmez.
  - Liste sayfası (`pergel/shop-list`) ürünleri REST'ten ister (`GET pergel/v1/shop-list`);
    ziyaretçi isteği çerezsiz gider, yanıt herkese aynıdır. Müşterinin istek listesi sunucuda
    çizilir. Silinen ya da gizlenen ürünler listeden düşer.
  - Karşılaştırma tablosu: fiyat, puan, stok durumu, SKU, ağırlık, boyutlar ve görünür
    öznitelikler; hiçbir üründe değeri olmayan satır basılmaz. Fiyatı gizli üründe fiyat
    satırı boştur. "Sepete ekle" yalnızca basit, satın alınabilir, stoktaki üründe; diğerlerinde
    "Ürünü gör".
  - Simgeler kendi satır içi SVG'lerimiz; her blok kendi `style.css`'ini taşır.
  - Sayfa şablonu (`page.html`) `post-content`'i `align: full` yapar; önceden içerikteki
    geniş/tam bloklar 720 px'lik sütunda kalıyordu. Paragraflar yine 720 px'tir.
    `single.html` aynı durumda; görsel taban çizgilerini değiştireceği için ayrı bir işe kaldı.
- **P2-2 swatch ve yapışkan sepete ekle (16 Ekim 2026):**
  - Ayarlar: `woo.swatches` ve `woo.sticky_cart` (Mağaza sekmesi, "Product page" bölümü),
    ikisi de varsayılan kapalı.
  - Swatch verisi WooCommerce'in kendi altyapısıdır: "Color / image" öznitelik türü ve
    `woocommerce/add-to-cart-with-options-variation-selector` bloğu. Kendi veri modelimiz,
    term meta'mız ya da script'imiz yok; Pergel yalnızca CSS ekler (`engine/assets/swatches.css`)
    ve bu CSS seçenek bloğu sayfada çizildiğinde satır içi yüklenir. 44 px dokunma alanı,
    seçili ve odaktaki seçenekte halka, stokta olmayanın üstü çizili, seçilen rengin adı
    örneklerin altında; forced-colors kuralları dahil. WooCommerce'in "Color swatches for
    attributes" özellik bayrağını açmayız; mağaza sahibinin kararıdır, yardım metni yerini
    gösterir.
  - Çubuk (`pergel/sticky-add-to-cart`) sepet formundan sonra block hooks ile gelir; yalnızca
    basit ve değişken, satın alınabilir (satış modları dahil) ve stoktaki üründe çizilir.
    Kendi sepet isteği yoktur: düğmesi formun gerçek düğmesine basar, böylece adet, seçenekler,
    AJAX ve mini sepet WooCommerce'te kalır. Form gönderilemiyorsa (seçenek eksik) ziyaretçiyi
    forma kaydırıp seçilmemiş ilk seçeneğe odaklar. Fiyat ve düğme metni formu izler.
  - Çubuk yalnızca düğme ekranın üstünde kaldığında görünür: IntersectionObserver alanı
    ekranın çok altına uzatılır (`rootMargin` alt 100000 px), böylece End tuşu ya da bağlantı
    gibi ekranı hiç kesmeyen atlamalarda da doğru çalışır. Odak çubuktayken gizlenmez; sayfa
    çubuk yüksekliği kadar alt boşluk ve `scroll-padding` alır, odak çubuğun altında kalmaz.
  - Script'siz ziyaretçi çubuğu görmez (`hidden`, script kaldırır).
  - Bilinen: seçenek bloğundaki axe `aria-required-children` uyarısı WooCommerce'in kendi
    işaretlemesidir; ayarlar kapalıyken de çıkar.
- **P2-3 tahmini teslimat ve stok bildirimi (16 Ekim 2026, kullanıcı kararları):**
  - Ayarlar: `woo.delivery` (+ `delivery_cutoff`, `delivery_handling`, `delivery_min`,
    `delivery_max`, `delivery_days`, `delivery_holidays`) ve `woo.stock_alert`, Mağaza
    sekmesinde, ikisi de varsayılan kapalı. Şemaya iki tür eklendi: `time` (`SS:DD`) ve
    `dates` (satır başına `YYYY-AA-GG` ya da `başlangıç/bitiş`, en fazla 40).
  - Teslimat verisi genel ayar + kargo sınıfıdır: sınıf kendi teslimat süresini ("3" ya da
    "2-4" iş günü) WooCommerce → Ayarlar → Kargo → Sınıflar'da taşır (term meta
    `pergel_delivery_days`), boşsa genel ayar geçer. Ürün başına alan yoktur.
  - Tarih aralığı ziyaretçinin tarayıcısında, sitenin saat dilimiyle hesaplanır; sunucu
    yalnızca kuralları basar. Böylece önbelleğe alınmış sayfa ertesi gün de doğru tarihi
    gösterir. Son sipariş saati yalnızca hazırlama süresi 0 iken "bugün kargoda" satırı verir.
    Tarihler `Intl.DateTimeFormat` ile sitenin dilinde yazılır.
  - Sanal, tükenmiş, satılmayan (satış modları dahil) ürün ve seçenekte tahmin gösterilmez;
    değişken üründe bu seçenekler seçilince script tahmini gizler.
  - Stok bildirimi çift onaylıdır: oturum açmamış ziyaretçi adresini e-postadaki bağlantıyla
    onaylar, onaylanmayan adres 7 gün sonra silinir. Oturum açmış müşteri kendi hesap adresini
    girerse onaysız kaydedilir; başka bir adres girerse yine onay e-postası gider.
  - Adresler kendi tablomuzda tutulur (`{prefix}pergel_stock_alerts`): ürün başına adres
    benzersizdir, durumlar `pending` / `active` / `sent`. Post meta ya da option kullanılmadı;
    çok sayıda adreste ürün kaydını şişirmesin ve ürün başına sorgulanabilsin diye.
  - E-postalar WooCommerce'in e-posta sistemindedir (`WC_Email`): "Back-in-stock alert
    confirmation" ve "Back in stock", WooCommerce → Ayarlar → E-postalar'da konu, başlık ve ek
    metinleriyle düzenlenir, önizlemesi örnek ürünle çizilir. Düğme rengi WooCommerce'in e-posta
    ana rengidir.
  - Her adres bir kez e-posta alır; gönderimden 30 gün sonra satır silinir. Gönderim Action
    Scheduler'la arka planda, 50'şer adres hâlinde yapılır; ürün sayfası isteği e-posta
    göndermez.
  - Kötüye kullanım: formun imzalı belirteci (ürün + zaman, HMAC), en az 3 saniye bekleme, bal
    küpü alanı ve iletişim formuyla ortak IP sınırı (`Forms\Submit::within_limit`). Aynı adres
    ve ürüne saatte en fazla bir onay e-postası gider; form tekrar gönderilse de.
  - Kişisel veri: WordPress'in dışa aktarma ve silme araçlarına bağlıdır, gizlilik politikası
    kılavuzuna metin ekler. Onay kutusu zorunludur; gizlilik sayfası varsa bağlantısını taşır.
  - Yönetim: WooCommerce → Stok bildirimleri listesi (durum süzgeci, en çok beklenen ürünler,
    tek tek silme; `manage_woocommerce`).
  - Bilinen sınırlar:
    - Teslimat süresinde yalnızca ana ürünün kargo sınıfı sayılır; varyasyonun kendi sınıfı
      dikkate alınmaz.
    - E-posta bağlantı tarayıcıları (kurumsal güvenlik yazılımları) onay bağlantısını GET ile
      açıp adresi kendiliğinden onaylayabilir. Bağlantı yalnızca ilgili ürün için tek e-posta
      doğurduğundan kabul edildi.
    - Bildirim formunda Turnstile yok; korumayı belirteç, bekleme, bal küpü ve IP sınırı
      sağlar.
    - Boş ya da geçersiz alanı tarayıcının kendi doğrulaması durdurur; sunucu hataları (alan
      yanında, `aria-invalid`) script'siz gönderimde ve tarayıcı doğrulamasını aşan istekte
      görünür. İletişim formu da aynı yoldadır.
    - Bir seçenek stoğa girince ana ürünü bekleyenler de kuyruğa alınır; WooCommerce ana ürünü
      isteğin sonunda eşitlediği için bu kuyruk ana ürün eşitlendikten sonra işler.
    - Eklenti silinince tablo kalır; arama indeksiyle aynı şekilde `uninstall.php` işi F6'dadır
      (F6c'de yapıldı).
    - E2E testlerinde Playwright'ın `page.clock`'u Interactivity API'yi durduruyor; tarih
      testleri yalnızca `Date`'i `addInitScript` ile sabitler.
- **P2-4 beden rehberi ve birlikte sık alınanlar (16 Ekim 2026, kullanıcı kararları):**
  - Ayarlar: `woo.size_guide` ve `woo.bought_together`, Mağaza sekmesinde, ikisi de varsayılan
    kapalı.
  - Beden rehberi eşitlenmiş bir desendir (`wp_block`): tablo, görsel ve metin blok
    düzenleyicide hazırlanır, bir rehber birçok ürüne hizmet eder. Ürün kategorisi rehberini
    seçer (term meta `pergel_size_guide`), alt kategoriler ve ürünler onu izler; ürün kendi
    rehberini ya da "rehber yok"u seçebilir (post meta `pergel_size_guide`, `none`). En yakın
    kategori kazanır; eşit uzaklıkta düşük kimlikli kategori. Varyasyon ana ürünü izler.
  - Gösterim: sepet formunun üstünde "Size guide" düğmesi, rehber `<dialog>` içinde açılır.
    Düğme invoker command'la (`commandfor` / `command="show-modal"`) açar, `closedby="any"`
    Esc ve dışarı tıklamayla kapatır, odak düğmeye döner; güncel tarayıcıda script gerekmez.
    Küçük `view.js` yalnızca bu özellikleri tanımayan tarayıcılar içindir. Rehberi olmayan
    üründe blok hiçbir şey çizmez ve dosya yüklemez.
  - Birlikte sık alınanlar: ürün ve en fazla 3 çapraz satış ürünü (Ürün verisi → Bağlantılı
    ürünler) onay kutuları, canlı toplam ve tek düğmeyle. Paket indirimi yok; her ürün kendi
    fiyatındadır. Yalnızca basit, satın alınabilir, stoktaki, en az 1 adet satılan çapraz
    satışlar onay kutusu alır; seçenekli ya da başka yolla satılanlar (teklif/katalog modu,
    gruplu ürün…) kendi sayfasına bağlantı alır.
  - Ana ürün seçenekliyse onay kutusu kapalı başlar, yukarıdaki formda seçenekler
    tamamlanınca açılır ve ilk kez seçilir; fiyat aralığının yerine seçilen varyasyonun fiyatı
    yazılır.
  - Ekleme Store API'nin toplu isteğiyle (`wc/store/v1/batch`) yapılır; nonce sayfadan değil bir
    Store API yanıtından alınır, böylece önbelleğe alınmış sayfa da çalışır. Sonra mini sepet
    yenilenir. Script'siz form ürün sayfasına gönderilir, `wp_loaded`'da ürünleri ekleyip
    sepete yönlendirir.
  - Fiyat HTML'i için `Woo\Price::html()` eklendi: `wp_kses_post()` WooCommerce'in `<bdi>`
    etiketini ve `translate="no"` özniteliğini siliyordu. `StickyCart` ve `ListView` de buna
    geçti.
  - Bilinen sınırlar:
    - Rehberin script'siz açılması invoker command'ı tanıyan tarayıcı ister; eski tarayıcıda
      düğme yalnızca script'le çalışır.
    - Script'siz gönderim nonce taşımaz (WooCommerce'in kendi `add-to-cart` bağlantıları
      gibi); yalnızca ziyaretçinin kendi sepetine, izin verilen ürünlerden ekler.
    - Mini sepetin yenilenmesi WooCommerce'in iç olayına dayanır:
      `wc-blocks_store_sync_required` (`type: 'from_@wordpress/data'`). WooCommerce bunu
      değiştirirse sepet sayfası yine doğrudur, rozet bir sonraki sayfada güncellenir.
    - 100'den fazla varyasyonu olan ana ürün pakete katılmaz (varyasyonlar sayfaya basılır).
    - Seçenekli çapraz satışlar onay kutusu almaz, bağlantı alır.
- **F4c kararları (16 Ekim 2026):**
  - Üç PR'da: F4c-1 mağaza pattern'leri ve `starters/shop`; F4c-2 CI'da WooCommerce'li iş
    (entegrasyon testleri, mağaza pattern'lerinin editör denetimi, ödeme e2e) ve mağaza bütçesi;
    F4c-3 sahte ürün üreticisiyle ölçek testi (kullanıcı kararı).
  - Mağaza starter'ı ~24 ürün ve 5 kategoriyle gelir: basit, seçenekli (renk/beden), gruplu ve
    harici ürünler, indirimli, öne çıkan ve stokta olmayan örneklerle (kullanıcı kararı).
  - Ürün ve kategori görselleri depoya girmez; kurulumda GD ile WebP olarak üretilir (WebP
    yoksa PNG), etkin stilin renkleriyle düz bir şekil taşır (kullanıcı kararı). Plan baş harf
    de diyordu; temada yalnızca woff2 yazı tipi var ve GD onu okuyamıyor, sistem yazı tipi de
    her sunucuda yok. Harf yerine şekil anahtardan seçilir (daire, kare, kemer, karo, halka,
    hap), böylece aynı ürün her kurulumda aynı resmi alır.
  - CI'daki mağaza işi WooCommerce'in son sürümü ve 11.2.1 ile çalışır (kullanıcı kararı).
  - Ödeme e2e yalnızca WooCommerce'in çevrimdışı yöntemleriyle (havale, çek, kapıda ödeme);
    blok ve klasik ödeme, misafir ve üye, teklif/katalog modunda engellenen ödeme ve axe.
  - Ölçek testi: CI'da 5.000 ürün; yerelde bir kez 50.000 ürün ve 10.000 yazı, sonuçlar buraya
    yazılır.
- **F4c-1 uygulama notları (16 Ekim 2026):**
  - Kategori görselleri için yeni `pergel/term-image` bloğu: çekirdekte Terms Query içinde
    terimin görselini basan blok yok. WooCommerce'in kategori görseli term meta'sı
    (`thumbnail_id`) okunur, başka taksonomiler süzgeçle bağlanır; WooCommerce gerektirmez.
  - Mağaza bölümleri ayrı `pergel-shop` kategorisinde, yalnızca WooCommerce etkinken; böylece
    mağazasız sitenin ekleyicisinde çalışmayan bölüm görünmez. Pattern kurallarına `shop-*`
    adı ile bu kategorinin birlikte gelmesi eklendi.
  - Ürün kartının başlığı ve düğmesi metin rengini zeminden alır (`color: inherit`): koyu
    zeminli "indirimdekiler" bölümünde başlıklar ve düğme görünmüyordu.
  - `hidden-product-filters` pattern'indeki düğme genişliği WP 7.1'in biçimine çevrildi;
    editör denetimi eski biçimi geçersiz blok sayıyordu.
  - Mağaza starter'ı WooCommerce'in kendi nesneleriyle (`WC_Product_*`) kurulur, REST veya
    içe aktarma dosyası kullanılmaz. Öznitelik zaten varsa (ör. sitede `pa_color`) yeniden
    kullanılır ve kaldırmada silinmez; yalnızca starter'ın yarattığı öznitelikler
    (`pergel_starter_attributes`) silinir.
  - Kurulum bir çağrıda ~20 saniye sürer (36 görsel ve 40 varyasyon); `--dry-run` hiçbir
    görsel çizmez.
  - Yerel not: WP-CLI ile web sunucusu tema kökünü farklı yazdığından (`C:/…` ve `C:\…`)
    pattern önbelleğinin anahtarı farklıdır; CLI'dan temizlemek web'deki önbelleği silmez.
- **F4c-2 uygulama notları (17 Ekim 2026; kullanıcı uzaktayken alınan kararlar):**
  - CI'da yeni `shop` işi, WooCommerce `latest` ve `11.2.1` matrisiyle (`fail-fast: false`):
    `setup-wp.sh` `WOOCOMMERCE` doluysa eklentiyi kurar ve `tools/ci/shop.php` ile mağazayı
    ayarlar (TRY, Türkiye/İstanbul, "coming soon" kapalı, ücretsiz kargo, kapıda ödeme ve
    havale, çekirdek sayfalar, klasik ödeme kısa kodlu `pergel-classic-checkout` sayfası).
    Sonra entegrasyon testleri, `wp pergel starter install shop`, smoke, mağaza bütçesi, ödeme
    e2e, mağaza pattern'lerinin axe/taşma denetimi ve editör denetimi. Sunucu ayağa kalktıysa
    her adım bir öncekinin hatasından sonra da çalışır (`!cancelled()` + adım sonucu), böylece
    tek bir kırmızı adım diğerlerini gizlemez.
  - WooCommerce'in etkinleştirmeden sonraki ilk yüklemesi kendi bildirimini yazar (paketindeki
    Jetpack bağlantısı `plugins_loaded`'da cron kurar, `__()` `init`'ten önce çalışır). Yalnızca
    o satır `debug.log`'dan silinir; "PHP notices" adımı gerisini görmeye devam eder.
  - Mağaza bütçesi (`tests/budget/shop.json`) WP_DEBUG kapalıyken ölçülür: WooCommerce
    WP_DEBUG açıkken ürün süzgeci önbelleğini atlar ve her görüntülemede yeniden yazar. Adım
    ölçümden sonra WP_DEBUG'ı geri açar. Sınırlar ölçülen değer + ~%10.
  - Bütçeye `writesIgnore` eklendi: ürün sayfasında WooCommerce ilgili ürünleri
    (`wc_related_*` transient'i) kategori listeden az ürün tuttuğu sürece her görüntülemede
    yeniden yazar. Bu yazımlar raporda ayrı başlıkta listelenir, sayılmaz.
  - Ölçülen gerçekler: sepet bloğu tek başına ~660 KB WooCommerce script'i getirir (boş sepet
    `jsKB` 730); ürün sayfalarında JSON-LD 2'dir (Pergel + WooCommerce'in ürün verisi).
  - Bulunan ve düzeltilen hatalar:
    - Varyasyon seçicide yalnızca ilk özellik satırı `role="listitem"` alıyordu (axe
      `listitem`); artık her satır alır (`Woo\Markup`).
    - WooCommerce'in klasik formlarındaki parola göster düğmesi 22px'ti; WCAG 2.2 hedef boyutu
      için 24px yapıldı (`classic_shop_styles()`, yalnızca o stiller yüklenince).
    - Starter'ın "Home" başlıklı kategori görseli `home` slug'ını alıyor, anasayfayı `home-2`'ye
      itiyordu; ek dosyalarının slug'ı artık dosya adından gelir.
  - `smoke.sh` ortak betik oldu (bütçe ve mağaza işleri): `php -S` arada çöktüğünde (000)
    sunucu geri gelince yolu bir kez daha ister; tekrarlayan çöküş yine kırmızıdır.
  - Ödeme e2e (`tests/shop`, `npm run test:shop`): WP-CLI yardımcısıyla ayarları değiştirip
    geri koyar, bu yüzden tek işçiyle çalışır; Windows'ta `PERGEL_WP_PHAR` ile wp-cli.phar
    kullanılır. Yalnızca çevrimdışı ödeme yöntemleri; klasik ödeme testi
    `woocommerce_checkout_page_id`'yi geçici olarak klasik sayfaya çevirir. Sipariş yöntem,
    müşteri, oluşturma yolu (`store-api` / `checkout`) ve varyasyon kimliğiyle doğrulanır.
  - Editör denetimi için CI'da rastgele parolalı geçici `pergel-qa` yöneticisi açılır.
  - Testler katı kaldı, Playwright `retries` eklenmedi: yerelde görülen iki kararsızlık
    aşağıda; CI'da görülürse ayrı ele alınır.
  - Yerel kararsızlık 1 (açık, kök neden bulunamadı): üye testinde `/my-account/` (girişten
    hemen sonra) ya da `/my-account/orders/` sayfasında, ~1/10–1/30 çalıştırmada, tarayıcıya
    gelen HTML'de tam olarak iki öğe yok: `woocommerce-blocktheme-css` ve
    `global-styles-inline-css` (~17 KB, yan yana). Sayfa tema yazı tipi ve boşlukları olmadan
    kalır, axe gezinme bağlantılarında hedef boyutu hatası verir. Bilinenler:
    - WordPress ikisini de `wp_head` → `wp_print_styles` içinde basılmış sayar (`done`).
    - Şablon çıktı tamponunun son süzgecine kayıt eklenen 35 çalıştırmada (197 sayfa) eksik
      sayfa hiç görülmedi; o denemedeki iki hata, sunucu yeniden başladıktan sonraki ilk
      dört çalıştırmadaydı.
    - Düz ve eşzamanlı `curl`/`fetch` döngüleriyle (460 istek) yeniden üretilemedi;
      `output_buffering=4096` ile de görüldü.
    - Eksik aralık öğe sınırlarına denk geldiği için bayt kaybı (aktarım) zayıf bir açıklama.
      Soğuk önbellekte içeriğin farklı üretilmesi daha olası.
  - Ödeme bloğu formunu ~660 KB script yüklendikten sonra çizer; yerel `php -S`'te bu 5 sn'yi
    aşabildiği için testler ödeme düğmesini 20 sn bekler (`openCheckout`).
  - Yerel kararsızlık 2 (açık): ~1/20 çalıştırmada `/my-account/orders/`'ta axe
    `aria-hidden-focus`, mini sepet çekmecesinde (`aria-hidden="true"` ve odaklanabilir kapat
    düğmesi). Pergel'in CSS'i gizli çekmeceye `visibility: hidden` verir; etkileşim API'sinin
    açılışı sırasında sınıfın bir an değiştiği sanılıyor. CI'da izlenecek.
  - Mağaza ve ölçek işlerinde JIT tümden kapalıdır (setup-php `ini-values`'ta
    `opcache.jit=disable`; `php -S`, WP-CLI ve PHPUnit). İlk CI koşusunda (PHP 8.4.26) JIT
    açıkken sunucu `/product/basic-t-shirt/` (varyasyonlu ürün) ilk isteğinde ve bütçe
    ölçümünde segfault verdi (çıkış 139), sepete ekleme isteği
    (`POST /wp-json/wc/store/v1/batch`) her seferinde `rest-api.php` içinde 30 saniyelik
    sınırı aştı. Yalnızca sunucuda kapatınca bu kez e2e temizliğindeki `wp user delete`
    çıktısız düştü (MariaDB aynı anda "Aborted connection" yazdı), yani WP-CLI de çöküyordu.
    Yerelde (Windows, JIT yok) hiçbiri görülmedi. Çekirdek bütçe işi JIT açık kalır.
    **Canlı sunucu için açık soru:** Hostinger'da PHP 8.4 JIT açıksa WooCommerce'te aynı çökme
    olabilir; F7'de canlıya geçmeden `opcache.jit` ayarı kontrol edilmeli (kullanıcı kararı,
    sunucuya dokunulmadı).
  - WooCommerce hatası (11.2.1 ve trunk, `Internal\ProductFilters\FilterData`): boş filtre
    verisi (henüz puan yok, listedeki hiçbir ürünün taşımadığı bir nitelik, alt kategorisi
    olmayan kategori) transient'a yazılıyor ama `get_cache()` boş değeri "yok" sayıyor. Ürün
    filtreleri olan mağaza ve kategori sayfaları bu yüzden her görüntülemede o filtreleri
    yeniden sayıyor (CI'da +~40 sorgu: 171/172) ve transient'larla sayaç transient'ını yeniden
    yazıyor; sayaç günlük 1000 sınırına varınca yazma durur. Pergel WooCommerce'in iç sınıfına
    yama yapmaz. `shop.json` ve `scale.json` bu yazmaları `writesIgnore`'a alır, mağaza ve
    kategori sorgu limiti 190'dır (gerekçe `$comment`'te). **Açık:** WooCommerce'e hata
    bildirimi (dış servise gönderim, kullanıcı onayı gerekir); düzelince limitler 145'e döner.
- **F4c-3 uygulama notları (18 Ekim 2026; kullanıcı uzaktayken alınan kararlar):**
  - Üretici `tools/fixtures/scale.php` (`wp eval-file … products=N posts=M`, `remove`): 20
    kategoride basit ürünler; fiyat, SKU, indirim ve stok dışı ürün numaradan türetilir, böylece
    her koşu aynı kataloğu verir. Sayılar toplamı anlatır, büyük sayıyla yeniden çalıştırmak
    yalnızca eksikleri ekler. Ürettiği her şey `_pergel_scale` meta'sını taşır; `remove` onunla
    siler. Yerelde ürün başına ~86 ms, yazı başına ~18 ms (5.000 ürün 6,5 dk).
  - Perf probe artık en yavaş tek sorguyu (`slowest_ms`, ilk 300 karakteri `slowest`) yazar;
    `measure.mjs` toplam sorgu süresini (`query_ms`) ve en yavaş sorguyu tabloya, en yavaş
    sorgunun metnini rapora ekler. Böylece katalogla büyüyen bir sorgu (tam tablo tarama,
    `LIKE '%x%'`) sayı değişmeden de yakalanır.
  - CI'da yeni `scale` işi (WooCommerce `latest`, JIT kapalı): mağaza starter'ı + 5.000 ürün +
    2.000 yazı + arama indeksi, smoke ve `tests/budget/scale.json`. Sayfalar: mağaza, son sayfa
    (`/shop/page/300/`, sayfa başına 16 ürün), fiyata göre sıralama, fiyat filtresi, kategori ve
    son sayfası, ürün, ürün araması, günlük ve 150. sayfası, yazı, yazı araması, yazı kategorisi
    50. sayfa. Limitler yerel ölçümün ~%10 üstü; `query_ms ≤ 80` ve `slowest_ms ≤ 40` CI
    makinesinin yavaşlığına pay bırakır.
  - Yerel ölçüm (5.000 ürün, 2.000 yazı, WP_DEBUG kapalı): sorgu sayısı katalogla artmıyor
    (mağaza 108, kategori 119, ürün 92, yazı sayfaları ~80). En yavaş sorgular fiyat filtresi
    (17,2 ms, `wc_product_meta_lookup` JOIN), mağaza son sayfası (10,5 ms, `SQL_CALC_FOUND_ROWS`
    + `LIMIT 4784, 16`) ve yazı araması (8,9 ms, `wp_pergel_search` önek araması). Kategori
    sayfaları yukarıdaki WooCommerce boş filtre hatasına düşer (skala ürünlerinin puanı ve
    niteliği yok); yazmalar `writesIgnore`'da.
  - İlk CI koşusu (PR #18): yazı sayfaları, ürün ve arama yerel ölçüme eşit; mağaza sayfaları
    145–153, kategori sayfaları 146 sorgu çıktı (yerelde 108 ve 119). Fark, F4c-2'de shop.json'da
    görülen WooCommerce boş filtre verisinin her görüntülemede yeniden sayılması (taze CI sitesinde
    ~40 sorgu); yerel sitede bu yeniden sayım görünmedi. Mağaza limitleri 168, kategori limitleri
    160'a çekildi (CI değeri + ~%10). WooCommerce hatası düzelince 120 ve 132'ye dönülür. Sorgu
    süreleri CI'da da düşük: `query_ms` en çok 77, en yavaş sorgu 11 ms (mağaza listesinin
    `NOT IN` alt sorgusu). İkinci koşudaki runner genelde ~%35 yavaştı (mağaza sayfa süresi 238 →
    348 ms): sorgu sayıları aynı kaldı ama mağaza sayfalarının `query_ms` değeri 87–102 oldu.
    Süre CI makinesine göre oynadığı için mağaza sayfalarına `query_ms ≤ 150`, kategorilere
    `≤ 110` verildi; diğer sayfalar `≤ 80`de kaldı. Katalogla büyüyen sorguyu asıl
    `slowest_ms ≤ 40` yakalar (en kötü görülen 14,7 ms).
  - Aynı koşuda iki rastgele hata, ikisi de bu PR'ın koduyla ilgisiz ve test altyapısında
    sağlamlaştırıldı:
    - SEO matrisi: `php -S` `/blog/` isteğinde segfault (139) verdi; bu işte opcache CLI hiç açık
      değil, yani çöküş JIT'ten bağımsız (F4c-1'deki `/blog/` çöküşüyle aynı yer). `serve.sh`
      sunucuyu yeniden başlattı ama `tools/seo/check.mjs` tek düşen isteği hata saydı. Artık
      bağlantı koparsa 2 saniye sonra bir kez daha soruyor (smoke'taki 000 kuralıyla aynı). Kök
      sebep açık (PHP 8.4.26 yerleşik sunucusu; canlı sunucu `php -S` kullanmaz).
    - Mağaza (latest) misafir blok checkout: nakit ödeme seçimi toplamları yeniden hesaplatıyor
      (`checkout?__experimental_calc_totals=true`); "Place Order" tıklaması bu sırada geldiği için
      yutuldu ve sipariş isteği hiç gitmedi. `tests/shop/helpers.mjs` → `placeOrder()` sipariş
      isteği gidene kadar tıklamayı tekrarlar (her denemede 10 sn bekler). Gerçek kullanıcıda
      karşılığı ikinci tıklama; WooCommerce davranışı, Pergel'in değil.
  - **Yerel 50.000 ürün + 10.000 yazı** (aynı CI sitesi, WP_DEBUG kapalı, Windows MariaDB 11.8;
    üretim 72 dk ürün + 3 dk yazı, arama indeksi 971.631 satır, yeniden kurulumu 193 sn).
    `scale.json` sayfalarına son sayfalar eklenerek ölçüldü (`/shop/page/3100/`, kategori
    150. sayfa, `/journal/page/1000/`, yazı kategorisi 900. sayfa, ürün 49000, yazı 9000):
    - **Sorgu sayısı katalogla artmıyor:** mağaza 100–108, kategori 119, ürün 92, arama 86, yazı
      sayfaları 79–81 (5.000'dekiyle aynı). HTML, istek ve JS boyutları da aynı; axe 0.
    - Ürün ve yazı sayfaları, günlük ve yazı kategorisinin son sayfaları düz kalıyor (en yavaş
      sorgu ≤ 8 ms). Ürün araması (`copper lamp`) 14 ms.
    - **Katalogla büyüyen sorgular (5.000'de CI limiti `slowest_ms ≤ 40` içinde, 50.000'de üstünde):**
      - WooCommerce'in mağaza ana sorgusu (`SQL_CALC_FOUND_ROWS` + katalogda gizli ürünler için
        `NOT IN` alt sorgusu + `GROUP BY`): 1. sayfa 32 ms, 300. sayfa 70 ms, 3100. sayfa 117 ms.
        Fiyata göre sıralama ve fiyat filtresi (`wc_product_meta_lookup` JOIN) 58–71 ms. Bu
        sorgular WooCommerce çekirdeğinin; Pergel değiştirmez. Canlıda sayfa önbelleği
        (LiteSpeed Cache) misafirlerde bu maliyeti saklar.
      - Pergel arama indeksi, yazı araması `scale post 1500`: 64 ms (5.000'de 9 ms). Sebep sahte
        veri: `scale` kelimesi üretilen 60.000 içeriğin hepsinde var, önek araması indeksi
        kullanıyor (PRIMARY `kind, token`) ama 60.000 satırı toplamak zorunda. Gerçek katalogda
        her belgede geçen kelime nadirdir; yine de çok yaygın kelimeler için bir üst sınır
        (eşleşme sayısı kesme veya durak kelimeler) F6 sertleştirmesinde değerlendirilecek.
      - Kategori 150. sayfa: toplam sorgu süresi 336 ms, ama en yavaş "sorgu" WooCommerce boş
        filtre hatasının `wp_options` UPDATE'leri (122 ms, Windows'ta disk yazması); okuma
        sorguları ≤ 12 ms.
    - Sonuç: Pergel'in eklediği hiçbir sorgu katalogla sayıca çoğalmıyor; büyüyen süreler
      WooCommerce'in sayfalama/sıralama sorgularında ve aşırı yaygın arama kelimesinde. CI
      bütçesi 5.000 üründe kalıyor; 50.000 için limit yazılmadı (yerel, tek seferlik ölçüm).

## F5 kararları (18 Ekim 2026)

- **F5 altı PR'da:** F5a paket okuyucu ve denetim (`wp pergel package validate`,
  `wp pergel pattern list|fields`); F5b `wp pergel import`; F5c dışa aktarma; F5d Abilities/MCP
  (salt okunur, varsayılan kapalı, istek sınırlı); F5e Claude skill'leri; F5f prova paketi ve
  CI'da iki kez kurulumla idempotentlik denetimi.
- **YAML ve Markdown okuyucuları motorun kendi kodu:** Paketin YAML alt kümesi
  (`engine/src/Package/Yaml.php`) ve Markdown alt kümesi (`Markdown.php`) saf PHP'dir; vendor
  bağımlılığı, PHP eklentisi veya derleme yoktur. Okunmayan sözdizimi (anchor, tag, girintide
  sekme, `#` başlık, HTML) sessizce başka türlü okunmaz, satır numarasıyla reddedilir. YAML 1.2
  çekirdek şeması: `yes`/`no` metindir. `Yaml::dump` dışa aktarma ve `pattern fields` için blok
  biçiminde yazar; yazdığı her şey okuyucudan aynı değerle döner (birim testi).
- **Pattern alanları bloklardan çıkarılır:** Elle tutulan bir alan listesi yok. `Fields`
  pattern'in bloklarını tarar: başlık, ilk başlıktan önceki paragraf (`eyebrow`), paragraflar,
  düğmeler, görsel, kapak görseli, liste, details başlığı, alıntı; alanları eşleşen kardeş
  bloklar `items` olur, ikinci alan `_2` adını alır. 144 pattern'in tamamı örnek değerleriyle
  doldurulup denetimden geçer. Değer verilmeyen alan pattern metnini korur ve not olur; `null`
  bloğu kaldırır; öğeler arasındaki ayraçlar öğelerle gelir ve gider.
- **Görseller yalnızca paketin içinden:** Bölüm alanları, ayarlar ve Markdown yalnızca paketteki
  bir dosyayı gösterebilir; adres ve paket dışı yol reddedilir. Paket kendi başına tamdır ve her
  görselin alt metni, kaynağı ve lisansı `media.yaml`'da tutulur.
- **Sorun ve not ayrımı:** İçe aktarmayı durduran sorunlar ile durdurmayan notlar (örnek metin
  kalan alan, alt metni olmayan görsel, kullanılmayan content dosyası) ayrı bildirilir; teslim
  edilecek paket `--strict` ile temiz olmalıdır. Hepsi tek seferde, dosya ve yerle bildirilir.
- **Sayfa şablonları temanın `customTemplates` listesinden:** WooCommerce gibi eklentilerin
  eklediği şablonlar paketten seçilmez.
- **Menü varsayılanı sayfa ağacı:** `sitemap.yaml`'da `menu` yoksa menü sayfa ağacından üretilir
  (ön sayfa, sepet, ödeme, hesap ve `menu: false` sayfalar hariç).
- **İçe aktarma yalnızca kendi yaptığını değiştirir (F5b):** Kayıtlar `_pergel_package` ve
  `_pergel_package_key` meta'sıyla tanınır. Paketin bir adresinde başka bir sayfa varsa içe
  aktarma ön kontrolde durur; tek istisna aynı türdeki WooCommerce sayfasının pakete alınmasıdır.
- **Panel değişikliği ezilmez (F5b):** Kaydedilen yazının özeti tutulur; özet tutmuyorsa kayıt
  `skip` ve not, `--force` ezer. Paketten çıkan kayıtlar `stale` listelenir, yalnızca `--prune`
  ile çöpe taşınır veya silinir. Böylece tekrar içe aktarma hem idempotent hem güvenli.
- **Marka token'ları alt temaya yazılır (F5b):** Ana temanın `tokens/default.json` dosyası
  değiştirilmez (tema güncellemesi ezerdi); paket adıyla imzalı bir alt tema yazılır ve
  etkinleştirilir. İmzasız aynı adlı dizine yazılmaz.
- **Dil paketi indirilmez (F5b):** `locale` yalnızca yüklü dillerden biriyse ayarlanır; içe
  aktarma ağa çıkmaz, eksik dil not olarak bildirilir.
- **Dışa aktarma içe aktarmanın tersidir ve kendini sınar (F5c):** `wp pergel export` siteyi
  pakete yazar; her bölüm pattern'e geri doldurulur, her Markdown parçası bloklara geri
  çevrilir ve sayfayla karşılaştırılır. İçe aktarmada aynı dönmeyecek her şey (Markdown'a
  yazılamayan blok, alanlarının ötesinde düzenlenmiş bölüm, ortam kütüphanesi dışındaki görsel)
  sessiz değişiklik değil nottur. Dışa aktarılan paketi aynı siteye içe aktarmak her kayıtta
  `keep` verir (entegrasyon testi ve CI duman testi).
- **Bölümler pattern adıyla tanınır (F5c):** Blok ekleyicinin yaptığı gibi içe aktarma ve
  starter'lar da bölümün kök bloğuna `metadata.patternName` yazar; dışa aktarma bu adla
  pattern'i bulur ve alanları okur. Adı olmayan bloklar Markdown gövdesine gider.
- **Varsayılan, paketin yaptığı sayfalar (F5c):** Dışa aktarma yalnızca paketin sayfalarını
  yazar; `--all` her yayımlanmış sayfayı. Ayarlardan yalnızca varsayılandan farklı olanlar,
  menü yalnızca sayfa ağacından üretilen varsayılandan farklıysa yazılır. WooCommerce
  sayfalarının blokları yazılmaz: içe aktarma o sayfaları zaten benimser.
- **Aynı içerik düzenlenmiş sayılmaz (F5c):** Kayıt paketin yazacağıyla zaten aynıysa (panelde
  düzenlenmiş olsa bile) `keep` olur ve özetleri yenilenir. Panelde düzenlenip dışa aktarılan
  bir site böylece kendi paketiyle çakışmaz.
- **Yapay zekâ erişimi çekirdeğin Abilities API'siyle (F5d):** WordPress 7.1'deki Abilities API
  kullanılır; Pergel kendi REST ucu, MCP sunucusu ya da API anahtarı yazmaz. Yetenekler salt
  okunurdur (desenler, sayfalar, token'lar, ayarlar); yazma işi `wp pergel import` ile paketten
  yapılır, böylece her değişiklik pakette iz bırakır. Modül varsayılan olarak kapalıdır, her
  yetenek bir yetki ister ve kullanıcı başına dakikada 60 çağrıyla sınırlıdır. MCP'ye açılması
  `meta.mcp.public` ile MCP Adapter gibi ayrı bir eklentiye bırakılır.
- **Skill'ler formatı tekrar etmez (F5e):** `pergel-package` ve `pergel-brand` depoda
  `.claude/skills/` altındadır (`.gitignore`'da adıyla açılır). Formatın tek kaynağı
  `docs/PROJECT-PACKAGE.md`; skill'ler iş sırasını, sınırları (canlıya içe aktarma yok, uydurma
  firma bilgisi yok, web'den görsel yok) ve bitti ölçütünü (`validate --strict` temiz, ikinci içe
  aktarma yalnızca `keep`) verir. Denetimin yakalamadığı bir sorun skill'e not olarak değil,
  denetime kural olarak eklenir: marka fontunun dosyası artık `package validate`'te aranıyor
  (önceden içe aktarmanın son adımında, sayfalar yazıldıktan sonra hata veriyordu).
- **Prova sitesi CI'da her değişiklikte kurulur (F5f):** Gerçek prova hedefi seçilene kadar
  `examples/ornek-mimarlik` (uydurma bir mimarlık ofisi, GPL görseller) prova paketidir.
  `tools/ci/rehearsal.sh` onu tohum içeriksiz (`SEED=0`) boş siteye kurar; ikinci içe aktarma
  yalnızca `keep`, dışa aktarma dosya dosya aynı paket vermelidir, ardından duman testi ve
  `rehearsal.json` bütçesi. Paket dışa aktarmanın çıktısıdır: tek biçim, tek kaynak.
- **`--strict` kayıttan önce ve sonra (F5f):** İçe aktarmada `--strict` paketin notlarını ve
  sitenin notlarını (yüklü olmayan dil) hiçbir şey kaydedilmeden sorun sayar; içe aktarmanın
  kendi notlarında (panelde düzenlenmiş sayfa vb.) işi bitirir ama sıfırdan farklı kodla çıkar.
  CI'da sessiz bir not provayı yeşil bırakamaz.
- **İçe aktarma paketin dilinde çalışır (F5f):** WordPress pattern'in metnini ilk okumada,
  başlığını `init`'te kaydederken o anki dilde saklar ve başlığı bölümün metadata'sına yazar.
  Dil ilk içe aktarmada değiştiği için sayfalar ilk seferde İngilizce, ikincide Türkçe metadata
  alıyor, ikinci içe aktarma `update` veriyordu. İçe aktarma artık pattern okunmadan önce
  `switch_to_locale` ile paketin diline geçer ve temanın pattern'lerini yeniden kaydeder
  (çekirdeğin `_register_theme_block_patterns()`'ı).

### F6: sertleştirme ve 1.0 (18 Ekim 2026, kullanıcı kararları)

- **Plan:** beş PR, sırayla F6a test matrisi → F6b güvenlik incelemesi (Plugin/Theme Check
  CI'da, bulunan açıklar aynı PR'da) → F6c `uninstall.php`, yaygın kelime sınırı ve bilinen
  açık işler → F6d WordPress 7.2 (beta/RC çıkınca; çıkmazsa 1.0.0 7.1.x ile yayımlanır) →
  F6e 1.0.0. Çeviriler Türkçe ve İngilizce; başka dil yok.
- **Sürüm dağıtımı:** GitHub sürümü ve iki zip (tema, motor), CI'da üretilip doğrulanır. Etiket
  ve sürüm kullanıcı onayıyla açılır; WordPress.org'a gönderilmez.
- **Geniş test matrisi (F6a):** PHP 8.1–8.5, MariaDB 10.6 ve 11.8, MySQL 8.0 ve 8.4, WordPress
  7.1 (en eski desteklenen), son sürüm ve nightly. Bütün birleşimler yerine her değer en az bir
  kez çalışır (CI `compat` işi, altı satır): en eskiler bir arada (PHP 8.1, WP 7.1, MariaDB
  10.6, WooCommerce 11.2.1), ardından her yeni sürüm. Her satırda entegrasyon testleri, WP-CLI
  komutları, duman testi ve PHP uyarısı denetimi. Bütçe, mağaza ve prova işleri canlının
  kurulumunda (PHP 8.4, MariaDB 11.8) kalır; ölçümlerin karşılaştırılabilir olması için.
  Nightly bir sonraki WordPress sürümüdür ve CI'ı kırmadan başarısız olabilir; F6d bu satırı
  zorunlu yapar. Sözdizimi denetimi en eski ve en yeni PHP'de (8.1, 8.5).

### F6b: güvenlik incelemesi (18 Ekim 2026)

İnceleme eklentinin dışa açık her yüzünü (REST, formlar, stok uyarısı, arama önerileri,
Abilities, paket içe aktarma, bloklar, gizlilik araçları) tek tek okudu; bulunan açıklar aynı
PR'da kapatıldı ve her biri bir testle sabitlendi.

- **Satış modları REST'te de geçerli:** `pergel/v1/shop-list` gibi rotalar teklif, katalog ve
  girişe kapalı ürünlerin fiyatını ve sepet bağlantısını anonim ziyaretçiye çiziyordu; modlar
  REST isteklerinde yalnızca Store API'ye uygulanıyordu. Artık mağaza yöneticisi dışında herkese
  her rotada uygulanır. Bir listede herhangi bir mod fiyatı gizliyorsa fiyat filtresi, fiyata
  göre sıralama ve Store API fiyat aralığı kapanır (gizli fiyat aralıktan okunamaz).
- **Ziyaretçi ve sınırlar (`Core\Visitor`):** formlar, stok uyarısı ve arama önerileri aynı
  sınırlayıcıyı kullanır. Adres `REMOTE_ADDR`'dır; vekil sunucu veya CDN arkasında
  `wp-config.php`'deki `PERGEL_CLIENT_IP_HEADER` başlığı adlandırır (yalnızca her istek o
  vekilden geçiyorsa; yoksa başlığı herkes yazabilir). IPv6 /64 başına sayılır. Sınırlar sabit
  pencerelidir; kalıcı nesne önbelleğiyle atomik, onsuz transient ile yaklaşık.
- **Formlar:** başka bir siteden gelen tarayıcı POST'u (`Origin` başka) geri çevrilir. Yalnızca
  alanları geçerli mesaj sınıra sayılır; yazım hatası düzelten ziyaretçi hakkını harcamaz. Site
  saatte en çok 50 mesaj alır (`pergel/forms/hourly_cap`). Jetonun yaş sınırı yok: sayfalar
  günlerce önbellekte kalır ve önbellekteki formun gönderebilmesi gerekir; kopyalanan jeton yine
  bağlantı ve site sınırlarından geçer. E-postalar `text/plain; charset=UTF-8`.
- **Stok uyarısı:** misafir her durumda aynı yanıtı alır; bir adresin kayıtlı olup olmadığı
  öğrenilemez. Bir adrese günde en çok üç onay e-postası gider. E-posta bağlantıları düğmeli bir
  sayfa açar; e-posta tarayıcıları (link scanner) bağlantıyı açınca hiçbir şey onaylanmaz veya
  silinmez. Bağlantılar uyarılar kapatıldıktan sonra da çalışır. Teklif ve katalog ürünleri uyarı
  almaz ve göndermez. Arama önerileri iki karakterden başlar.
- **İçerik ve paket girdileri:** Markdown bağlantısında boşluk veya kontrol karakteri reddedilir
  (`java\tscript:` gibi şema atlatmaları). Font adları ve `fontFace` değerleri CSS kuralının
  dışına taşamaz. Harita bloğu yalnızca `https://www.google.com/maps…` gömme adreslerini yükler.
  Paket dosyaları gerçek yoluyla paketin içinde olmalıdır (sembolik bağlantı dışarıyı okutamaz).
  YAML 32, Markdown alıntı ve listeleri 16 düzeyden derin iç içe geçemez (yığın tüketimi). Ayar
  metni sınırın sekiz katından uzunsa temizlenmeden reddedilir. Child tema başlığındaki site adı
  tek satırda kalır.
- **Görünürlük:** `pergel/list-pages` yeteneği yalnızca kullanıcının düzenleyebileceği sayfaları
  listeler; içindekiler bloğu parolalı veya okunamayan yazının başlıklarını göstermez; SEO başlığı
  kaçışlanır; SEO eklentisine verilen JSON `< > &` karakterlerini kaçışlı tutar. Bir yeteneğin
  beklenmeyen hatası mesajını göstermez (yol ve sorgu sızmasın), genel bir metin ve 500 döner;
  geçersiz girdi 400 ile kendi mesajını verir.
- **Gizlilik araçları:** kişisel veri dışa aktarma ve silme çöp kutusundaki form mesajlarına da
  ulaşır; silinemeyen kayıt "tutuldu" olarak bildirilir ve tur biter. Kayıtlı istek listesi
  (wishlist) dışa aktarılır ve silinir (modül kapalıyken de). Stok uyarıları hesabın önceki
  e-posta adresiyle verilenler dahil bulunur. Gizlilik metni Turnstile'ı anar.
- **Plugin Check ve Theme Check CI'da (`review` işi):** zip'lerin içeriği (`git archive`)
  Plugin Check 2.1.0 ve Theme Check 20260901 ile denetlenir; her Plugin Check bulgusu ve Theme
  Check'in REQUIRED/WARNING satırları işi kırar. Bilerek kabul edilenler gerekçesiyle kodda
  (`phpcs:ignore … --`) veya `tools/ci/review.*` içinde durur: `Update URI` başlığı
  (WordPress.org başka bir eklentinin güncellemesini bu slug'a önermesin), Plugin Check'in
  koddan tahmin edip PHPCS'e verdiği geçersiz önek (`InvalidPrefixPassed`, kanca adından
  `pergel/forms/subject`; önek eksikleri kendi kodlarıyla yine yakalanır), Turnstile betiği ve
  doğrulaması (yalnızca Cloudflare sunar), kendi tablolarımızın önekten gelen adları,
  `load_plugin_textdomain` (WordPress.org'da değiliz, çeviriler `languages/`'da), WP-CLI ve REST
  JSON'a giden istisna mesajları, `.l10n.php` dosyalarındaki bağlam ayırıcı (`\x04`). Ekran
  görüntüsü eksikliği F6e'ye kadar kabul edildi (F6e'de eklendi, istisna kalktı). Blokların `render.php` dosyaları doğrudan
  çağrılınca çıkar (`ABSPATH`).

### F6c: kaldırma ve yaygın arama terimi (18 Ekim 2026)

- **Eklenti silinince verisi gider (`uninstall.php` → `Core\Uninstall`):** devre dışı bırakmak
  hiçbir şey silmez; Eklentiler ekranında "Sil" WordPress'in "eklenti ve verileri silinsin mi?"
  onayından sonra şunları kaldırır: ayarlar, arama indeksi ve stok uyarısı tabloları, zamanlanmış
  olaylar, sınır sayaçları (`_transient_pergel_*`), form mesajları ve eklentinin yazı, terim ve
  kullanıcı meta'ları (SEO başlığı/açıklaması/noindex, satış modları, teslim süresi, beden
  rehberi, istek listesi, starter ve paket işaretleri), stok uyarısı e-postalarının WooCommerce
  ayarları. Çoklu sitede her site için ayrı çalışır.
- **Kalanlar:** starter'ın veya paketin yaptığı sayfalar, ürünler, menüler, terimler, medya ve
  paketin yazdığı child tema. Bunlar sitenin içeriğidir; eklentiyle birlikte gitmeleri beklenmez.
- **`PERGEL_KEEP_DATA`:** `wp-config.php`'de `true` ise silme hiçbir veriye dokunmaz (eklentiyi
  zip'ten yeniden kurmak gibi durumlar için).
- **Yaygın arama terimi:** 5.000'den fazla indeks satırında geçen terim (`Query::COMMON_ROWS`,
  `pergel/search/common_rows` süzgeci) kendi başına toplanmaz; diğer terimlerin bulduğu içerikte
  tek tek aranır, puanı ve "her terim eşleşmeli" kuralı aynı kalır. Bir terimin yaygın olup
  olmadığını anlamak en çok 5.000 indeks girdisi okur. Durak kelime listesi kurulmadı: dile ve
  kataloğa bağlı olurdu, "the" ya da "ve" de gerçek bir ürün adında geçebilir. Yalnızca yaygın
  terimlerden oluşan sorgu (ör. tek başına "lamba") hepsini toplamaya devam eder; sonuç sayısı
  zaten 1.000 ile sınırlı ve sonuç bir saat önbellekte kalır. 60.000 içerikli ölçüm: iki yaygın
  ve bir nadir terim 111 → 2,4 ms, bir yaygın ve bir nadir 53 → 1,4 ms.

### F6d–F6e: 1.0.0 (18 Ekim 2026)

- **WordPress 7.2 beklenmedi (F6d):** 18 Ekim'de 7.2'nin beta'sı ya da RC'si yok (son sürüm
  7.1.3, nightly 7.2-alpha). Plan gereği 1.0.0 "Tested up to: 7.1" ile çıkar. Nightly satırı
  CI'da zorunlu değil; 7.2-alpha'daki iki fark (fiyat HTML'i, bağlı metnin kaçışı) testlerde
  çözümlenmiş metinle karşılaştırılarak giderildi (F6c). 7.2 beta'sı çıkınca nightly zorunlu
  yapılır ve "Tested up to" yükseltilir.
- **Sürüm aracı (`tools/release.php`):** `check` sürümün yedi yerde aynı olduğunu (tema
  `style.css` ve readme, motor başlığı, `VERSION` sabiti ve readme, iki `.pot`), iki readme'de
  o sürümün changelog girdisini, "Requires at least / Tested up to / Requires PHP" değerlerinin
  dosyalar arasında aynı olduğunu ve temanın 1200x900 `screenshot.png`'sini denetler; CI'ın
  lint işinde çalışır. `bump X.Y.Z` başlıkları yazar; changelog elle yazılır, ardından
  `composer pot` ve `composer i18n`. `package <dizin>` iki zip'i `git archive HEAD`'den üretir
  (`pergel-X.Y.Z.zip`, `pergel-engine-X.Y.Z.zip`; her biri slug adlı tek klasör). `notes`
  sürüm notunu changelog'dan yazar.
- **Zip'ler CI'da kurulur:** `review` işi zip'leri WP-CLI ile site sahibi gibi kurar, Plugin
  Check ve Theme Check'i kurulmuş hâlde çalıştırır ve zip'leri `release-zips` artifact'ı olarak
  saklar.
- **Taslak sürüm (`.github/workflows/release.yml`):** `main` üzerindeki bir `v*` etiketi önce
  etiketin sürümü adlandırdığını ve commit'in `main`'de olduğunu denetler, sonra zip'lerle
  taslak bir GitHub sürümü açar. Taslağı yayımlamak elle yapılır. Etiket kullanıcı onayıyla
  atılır.
- **`.gitattributes` yolları köke bağlandı:** `starters/` kalıbı her derinlikte eşleşiyordu;
  Windows'ta (`core.ignorecase`) `engine/src/Starters/` klasörünü de dışarıda bırakıyordu ve
  orada üretilen zip'te starter sınıfları eksik kalıyordu. Linux CI'da sorun görünmüyordu. Kök
  dizindeki PHPUnit ve Playwright ayarları ile `examples/` da arşiv dışına alındı.
- **Ekran görüntüsü:** `theme/screenshot.png` kurumsal starter'ın ön sayfası, 1200x900, ayrı ve
  geçici bir yerel sitede (boş veritabanı) alındı.

### CI dakikaları: hafif PR denetimi (18 Ekim 2026, kullanıcı kararı)

- **Sorun:** Repo özel; GitHub ücretsiz planda özel repolara ayda 2.000 Actions dakikası verir.
  Tam CI (17 iş) bir çalıştırmada yaklaşık 62 dakika tutuyordu ve her PR birleştikten sonra
  `main` için bir kez daha çalışıyordu. Ekim'de 82 çalıştırmayla kota doldu; v1.0.0 etiketinin
  sürüm işi başlatılamadı (taslak sürüm aynı araçla yerelde hazırlandı). Kullanıcı ücret ödemek
  istemedi.
- **Karar:** PR'da yalnızca hızlı işler çalışır: `lint`, iki `syntax`, `budget` (entegrasyon
  testleri, WP-CLI, smoke, çeviriler, bütçe, editör kontrolü) ve `review` (zip'ler); yaklaşık
  10 dakika. `compat`, `shop`, `scale`, `visual`, `seo-matrix` ve `rehearsal` PR'a `full-ci`
  etiketi eklenince, elle başlatılan çalıştırmada ve haftada bir (pazartesi 03:00 UTC, yalnızca
  `yakupdrgn/pergel`'de) çalışır. `main`'e birleştirme yeniden çalıştırılmaz (PR'ın denetlediği
  ağaçtır); yalnızca `docs/` ve `.md` değişiklikleri CI başlatmaz. Başka bir etiket hiçbir iş
  başlatmaz ve süren çalıştırmayı iptal etmez.
- **`full-ci` ne zaman:** WooCommerce, arama, pattern/tema görünümü, SEO köprüsü, paket içe
  aktarma ya da desteklenen sürümler (PHP/WordPress/veritabanı) değiştiğinde ve sürüm etiketinden
  önce. Ağır testlerin yerelde koşabilenleri (WooCommerce'li ve WooCommerce'siz entegrasyon,
  zip incelemesi) her PR'dan önce yerelde de çalıştırılır.
- **Elenenler:** Repoyu herkese açmak (sınırsız dakika, ama kod görünür olur); kendi
  bilgisayarında runner (WSL2 + Docker kurulumu, makine açık kalmalı); ücretli dakika.

## v1.1: bölümlü ürün sayfası (18 Ekim 2026, kullanıcı kararları)

İhtiyaç F7'den geldi: ariumlab'ın 93 ürün sayfası, başlangıç paketindeki yapılandırılmış veriden
(özellikler, teknik özellikler, uyumluluk, karşılaştırma, uygulamalar, bakım, SSS, sipariş)
tek bir şablonla kurulacak. Pergel'de ürün içeriği için ne bölüm ne şema ne de paket desteği
vardı. Özellik ariumlab'a değil Pergel'e yazılır (K3, K12); ariumlab tarafında yalnızca kendi
verisini paket dosyalarına çeviren bir eşleyici olur.

- **Bölümler ürün açıklamasında blok olarak durur** (K4). Ayrı bir veri alanı
  (`pergel_product_sections` JSON + kendi bloğu) elendi: panelde düzenlemek için derlenmiş özel
  bir editör gerekirdi (K6), paketin alan modeli, `stale` tanıması ve dışa aktarması ürünlerde
  yeniden yazılmak zorunda kalırdı. Bloklarla ekip bir SSS'yi blok editöründe düzeltir; tekrar
  içe aktarma bunu `stale` olarak bildirir.
- **Ürün bölümleri pattern'dir** ("Ürün" kategorisi): genel bakış, öne çıkanlar, görsel ve
  açıklama, teknik özellikler, uyumluluk, karşılaştırma, uygulamalar, bakım ve güvenlik, SSS,
  sipariş ve garanti, dosyalar. Alan modeline iki tür eklenir: `table` (tablo başlığı ve
  satırları) ve `file` (PDF bağlantısı).
- **Şema bloklardan üretilir** ve SEO eklentisinin grafiğine eklenir (K8): SSS bölümündeki
  açılır bloklardan `FAQPage`, teknik özellik tablosundan Product `additionalProperty`.
  Soru, `pergel-faq` sınıflı bir bloğun içinde olmalıdır: sayfanın başka yerindeki açılır
  bloklar SSS sayılmaz. Bütün SSS pattern'leri bu sınıfı taşır, kart düzenindeki SSS de
  (H3 ile açılan gruplar) okunur. Eklentinin kendi `FAQPage`'i varsa o kalır.
- **Bölümlü ürün ayrı bir şablondur** (`single-product-sections`, "Bölümlü ürün"): açıklama
  akordeon yerine tam genişlikte, üstte bölüm menüsü. Paket ürünü bu şablona atar; şablonu
  seçmeyen dükkânların ürün sayfası değişmez.
- **Paket formatı 2: `products/<sku>.md`.** Var olan ürün SKU ile benimsenir (`adopt`), yeni
  ürün açılmaz; ürün ID'si, siparişler ve yorumlar korunur. Pakette fiyat yazılmazsa fiyata
  dokunulmaz. `validate`, `import` ve `export` ürünleri de kapsar.
- **Sıra (dört PR, sonra v1.1.0):** 1) alan türleri, ürün pattern'leri, bölümlü şablon
  2) şema 3) paket formatı 2 4) sürüm.
- **Format 2 ayrıntıları (PR3).** Dosya adı SKU'dur (büyük-küçük harf duyarsız tekil). Yalnız
  dosyanın yazdığı alanlar kaydedilir; `_pergel_package_saved` özeti de yalnız bu alanları
  kapsar (`alanlar:özet`), yoksa stok veya satış sayısı değişince ürün "panelde düzenlenmiş"
  sayılırdı. Fiyat yalnız basit ve harici üründe, WooCommerce'in kendi setter'larıyla yazılır
  (fiyat önbelleği ve arama tablosu güncel kalsın). `--prune` ürünü çöpe atmaz, bırakır
  (`release`): ürün siparişlerle bağlı ticari kayıttır, paket onu yapmadı. Yalnız ürün içeren
  pakette `sitemap.yaml` isteğe bağlıdır ve yoksa sayfalara, okuma ayarlarına, menüye
  dokunulmaz. Motor 1'den kendi numarasına kadar her formatı okur; dışa aktarma ürünsüz paketi
  format 1 yazar ki eski motorlar okuyabilsin.
- **Ürün görselleri kırpılmaz (#35).** `product-figure` (16/9) ve `product-specs` (1/1)
  görseli `object-fit: cover` ile kesiyordu; fotoğrafa uyar, ama bu bölümler şema ve ölçü
  çizimi içindir ve ariumlab'ın 1280x606 çizimlerinin kenarları kayboluyordu. Görsel kendi
  oranında gösterilir, kart köşesi kalır.
- **v1.1.0 (19 Ekim 2026):** dört PR (#32–#34, #35) birleşti; "Tested up to" 7.1 kalır (7.2
  beta'sı yok). Etiket ve taslak sürüm kullanıcı onayıyla.

## v1.2: form ek alanları (19 Ekim 2026, kullanıcı kararı)

İhtiyaç F7'den geldi. ariumlab'ın FormCraft formlarında Pergel formunun sabit alanlarında
olmayan bilgiler soruluyor: ülke, talep türü ve ürün listeleri, VAT, fatura ve teslimat
adresi, zorunlu telefon. FormCraft ön yüze jQuery istediği için ve formlar bloğun kendi
mesaj kutusunda toplanmadığı için kullanılmıyor.

- **Ek alanlar blok ayarıdır.** Ayrı bir form oluşturucu ya da form yazı türü yok. Editör
  alanları blokta tanımlar: etiket, tür, zorunlu mu, liste seçenekleri. Bir form tek yerde
  durur, kalıp (pattern) ve şablon içinde de çalışır.
- **Tanımlar imzalı belirteçte taşınır.** Mevcut tasarımla aynı: sunucu mesajı gösterilen forma
  göre doğrular, sayfayı yeniden ayrıştırmaz, önbellekli sayfada da çalışır. Sınırlar belirteci
  küçük tutar: 10 alan, listede 30 seçenek, etiket ve seçenekte 100 karakter.
- **Dört tür:** kısa metin, uzun metin, telefon, liste. E-posta, tarih, dosya ve çoklu seçim
  şimdilik yok. Zorunlu telefon için yerleşik telefon kapatılır, yerine zorunlu bir telefon
  ek alanı konur.
- **Adlar sıradan gelir (`extra_N`), etiketler mesajla saklanır.** Editör sonradan alanların
  sırasını değiştirse de eski mesajlar kendi etiketleriyle okunur.
- **Ek alanlar teklif sağlayıcısına (QuoteRequest) gitmez.** Sağlayıcı arayüzü 1.1'deki gibi
  kalır. İhtiyaç çıkarsa `pergel/forms/received`'deki `$values` kullanılır.

## v1.2: sayfa sahiplenme (19 Ekim 2026, kullanıcı kararı)

İhtiyaç F7'den geldi. ariumlab.com'un mevcut sayfaları (ana sayfa, OEM, hakkımızda, iletişim...)
paketin adreslerinde duruyor; ön kontrol bunları ezmemek için içe aktarmayı durduruyordu.
Sayfaları silip yeniden yapmak kimliklerini, yorumlarını ve iç bağlantılarını koparırdı.

- **Sahiplenme açık bir karardır.** Sayfada `adopt: true` yazılmadıkça ön kontrol eskisi gibi
  durur; siteyi ezen bir varsayılan hâlâ yok. Başka paketin sayfası ve iki kez alınan sayfa sorundur.
- **İçerik dosyası yoksa içerik korunur.** Böylece sayfalar önce menü ve adresler için pakete
  alınır, metinler sonra yazılır. SEO da yalnız içerikle birlikte paketten gelir.
- **`--prune` alınmış sayfayı silmez, bırakır** (`release`), ürünlerde olduğu gibi: paket yapmadığı
  bir sayfayı çöpe taşımamalı. Kayıt `_pergel_package_adopted` meta'sıyla tanınır.
- **Hata düzeltmesi:** var olan kayıt `wp_insert_post` yerine `wp_update_post` ile kaydedilir.
  `wp_insert_post` kimlikle çağrılıp içerik verilmezse içeriği boşaltıyordu; içeriği korunması
  gereken WooCommerce sayfaları da bundan etkileniyordu.
- **Menüde kök göreli adres sitenin adresiyle çözülür** (`home_url`). Sayfa bağlantıları zaten
  tam adresle yazılıyordu; `/product-category/...` gibi bir adres alt dizindeki bir sitede (yerel
  prova) kök alana gidiyordu. Dışa aktarma site adresini zaten siliyordu, gidiş-dönüş değişmez.
  İçerikteki (Markdown) bağlantılar şimdilik olduğu gibi yazılır.

## v1.3: içerik boşlukları (19 Ekim 2026, kullanıcı kararı)

F7 metinleri yazılırken üç sayfa paketle ifade edilemedi; üçü tek değişiklikte kapatıldı.

- **Form bir paket alanıdır** (`form`). İletişim ve teklif sayfalarının ek alanlı formları
  (ülke, talep türü, ürün listesi) içerik dosyasında yazılabilir; yoksa bu sayfalar ya
  içeriksiz sahiplenilecek ya da elle düzenlenecekti. Değer bloğun ayarlarıdır, bloğun kendi
  doğrulamasıyla aynı sınırlar (en çok 10 ek alan, 30 seçenek, 100 karakter etiket) kontrol
  edilir. Yazılmayan ayar pattern'inkini korur; varsayılana eşit ayar, editörün yaptığı gibi,
  işaretlemeye yazılmaz. Dışa aktarma bütün ayarları yazar, böylece gidiş-dönüş kayıpsızdır.
- **Markdown başlığı çapa alır:** `## Garanti {#warranty}`. Hukuki bilgi sayfası ve
  yönlendirmeler `#gts`, `#warranty` gibi çapalara gidiyor. Ad harfle başlar; harf, rakam, `-`
  ve `_` içerir; geçersiz ad satır numarasıyla reddedilir. Çapa, editörün kaydettiği gibi
  başlığın `id` özniteliğidir.
- **İçerikteki kök göreli bağlantılar da `home_url` ile yazılır** (Markdown, metin alanları ve
  düğmeler), menüde olduğu gibi. `Markdown::url()` yalnız denetim olarak kalır (menü doğrulaması
  ve dışa aktarma onu kullanır); adresi çözen `Markdown::href()`, tersini
  `MarkdownWriter::address()` yapar. Pattern'lerdeki düz `/x/` adresleri artık yeniden
  yazıldığında site adresini alır; kök alandaki sitede davranış değişmez.

## v1.2.0 sürümü (19 Ekim 2026, kullanıcı kararı)

- **"v1.2" ve "v1.3" diye ayrılan işler tek sürümde çıkar.** Bunlar PR #37–#43: form ek
  alanları, sayfa sahiplenme, kök göreli bağlantılar, `form` paket alanı, başlık çapaları,
  isteğe bağlı mesaj alanı ve uzun kelimelerin satır kırması. Hepsi geriye uyumlu eklemeler,
  bu yüzden numara 1.2.0 oldu. 1.3.0 olarak ayrı bir sürüm çıkarılmadı.
- **Neden şimdi:** ariumlab.com canlı geçişi bu kodu istiyor. ariumlab-site reposundaki
  `docs/DECISIONS.md`, "Canlı geçiş kontrol listesi", adım 3.
- "Tested up to" 7.1 olarak kalır. Etiket ve taslak sürüm kullanıcı onayıyla oluşturulur.

## Bölüm ritmi (19 Ekim 2026, kullanıcı kararı)

- **Arka plansız iki tam genişlik bölüm art arda gelirse ikincinin üst dolgusu sıfırlanır.**
  Pattern'lerin çoğu üstte ve altta `spacing|80` dolgu kullanır (133 bölümden 113'ü).
  İki beyaz bölüm arasında 104 + 16 + 104 = 224 px boş alan kalıyordu, telefonda 132 px.
  Arada renk değişmediği için bu alan bölüm sınırı olarak değil, kopukluk olarak
  görünüyordu. ariumlab f7 provasında services ve ariumbot-s sayfalarında fark edildi.
- Kuralla boşluk, önceki bölümün alt dolgusu artı blok aralığı kadar olur: 120 px, telefonda
  74 px. Renkli bölümler (`has-background`) ve cover blokları kendi dolgularını korur.
- **`!important` gerekiyor:** dolgu, blok ayarı olarak satır içi stilde yazılı. Kural yalnız
  `post-content` içindeki doğrudan bölümleri kapsar. Editörde bir bölüme elle verilen üst
  dolgu da bu durumda sıfırlanır; bunu kabul ettik.
- **Mağaza ana sayfası bütçesi: istek sınırı 35 → 42.** Bölümler yukarı kayınca ilk ekrana
  dört küçük ürün görseli daha giriyor. İstek sayısı 34'ten 38'e, toplam boyut 304'ten
  309 KB'a çıktı. Yeni sınır, ölçülen değerin yaklaşık %10 üstünde (bütçe dosyasının kuralı).

## Aşamalar ve bitti tanımları

| Aşama | İçerik | Bitti |
| --- | --- | --- |
| F0 | Ortam, repo, CI iskeleti, docs, junction'lar, boş tema + eklenti | Boş Pergel etkin, CI yeşil, bütçe ölçümü ilk raporu üretiyor |
| F1 | Token formatı ve theme.json üreticisi, temel şablonlar, 3 style variation, 6 header + 6 footer | Bütçe içinde, axe temiz, Site Editor'den tüm görünüm değiştirilebiliyor |
| F2 | Modül sistemi, ayar ekranı, marka + Block Bindings, SEO sözleşmesi, performans modülü, `wp pergel` | SEO matrisi 6 senaryoda temiz |
| F3 | Pergel blokları ve uzantıları, 120+ pattern, kurumsal starter (dönen video hero ilk projeye kaldı) | Tüm pattern'ler geçerli, görsel testler oturdu, kurumsal demo kuruluyor |
| F4 | WooCommerce şablonları, hızlı arama, satış modları, P2 özellikleri, ölçek testi | Mağaza bütçesi tutuyor, ödeme akışı test modunda geçiyor |
| F5 | Proje paketi ve `wp pergel import`, Abilities/MCP, skill'ler, prova sitesi (hedef açık) | Prova sitesi paketten tek seferde ve tekrar üretilebiliyor |
| F6 | Tam test matrisi, güvenlik incelemesi, çeviriler, WP 7.2 uyumu (7.2 beta'sı çıkmadığı için 7.1.x ile) | Tüm kontroller yeşil, v1.0.0 |
| F7 | ariumlab.com: içerik, staging, geçiş, 30 gün izleme | Yeni site yayında |

## Bilinen açık işler

- ~~Anasayfa (blog listesi) şu an H1 içermiyor~~ → F1'de çözüldü (`home.html` + bütçe `h1 = 1`).
- ~~Çekirdeğin emoji script'i (`wp-emoji-release`) yükleniyor~~ → F2'de performans modülüyle çözüldü (bütçe `emoji = 0`).
- ~~K8'deki breadcrumb hizalaması (SEO eklentisi aktifken görünen breadcrumb'ın eklentiden gelmesi)~~ → F3a'da `pergel/breadcrumb` ile çözüldü (SEO matrisi görünen yolu BreadcrumbList ile karşılaştırır).
- ~~HTML şablonlardaki sabit metinler çevrilemiyor~~ → F1'de `patterns/hidden-*.php`'ye taşındı.
- ~~Arama indeksinde çok yaygın kelime (her belgede geçen) 60.000 içerikte 64 ms~~ → F6c'de çözüldü: yaygın terim yalnızca diğer terimlerin bulduğu içerikte aranır (`scale post 1500` biçimi 111 ms → 2 ms). Yalnızca yaygın terimlerden oluşan sorgu hâlâ hepsini toplar (60.000'de 55–105 ms; sonuç nesne önbelleğinde bir saat kalır).
- WooCommerce boş filtre verisi hatası (`FilterData::get_cache`): her görüntülemede yeniden sayım ve yazma; shop.json ve scale.json limitleri bunu içeriyor. WooCommerce'e hata bildirimi kullanıcı onayı bekliyor; düzelince limitler geri çekilir (F4c-2, F4c-3).
- Canlı sunucuda (Hostinger) `opcache.jit` ayarı F7 öncesi kontrol edilecek; CI'da PHP 8.4 JIT `php -S` ve WP-CLI'yi WooCommerce isteklerinde çökertiyor (F4c-2).
- CI'da `php -S` `/blog/` isteğinde opcache kapalıyken de ara sıra segfault veriyor; kök sebep bilinmiyor, testler bir kez yeniden soruyor (F4c-3).
