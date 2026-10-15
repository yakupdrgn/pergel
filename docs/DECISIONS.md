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
  gelince yeniden değerlendirilir.
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

## Aşamalar ve bitti tanımları

| Aşama | İçerik | Bitti |
| --- | --- | --- |
| F0 | Ortam, repo, CI iskeleti, docs, junction'lar, boş tema + eklenti | Boş Pergel etkin, CI yeşil, bütçe ölçümü ilk raporu üretiyor |
| F1 | Token formatı ve theme.json üreticisi, temel şablonlar, 3 style variation, 6 header + 6 footer | Bütçe içinde, axe temiz, Site Editor'den tüm görünüm değiştirilebiliyor |
| F2 | Modül sistemi, ayar ekranı, marka + Block Bindings, SEO sözleşmesi, performans modülü, `wp pergel` | SEO matrisi 6 senaryoda temiz |
| F3 | Pergel blokları ve uzantıları, 120+ pattern, kurumsal starter, dönen video hero | Tüm pattern'ler geçerli, görsel testler oturdu, kurumsal demo kuruluyor |
| F4 | WooCommerce şablonları, hızlı arama, satış modları, P2 özellikleri, ölçek testi | Mağaza bütçesi tutuyor, ödeme akışı test modunda geçiyor |
| F5 | Proje paketi ve `wp pergel import`, Abilities/MCP, skill'ler, prova sitesi (hedef açık) | Prova sitesi paketten tek seferde ve tekrar üretilebiliyor |
| F6 | Tam test matrisi, güvenlik incelemesi, çeviriler, WP 7.2 uyumu | Tüm kontroller yeşil, v1.0.0 |
| F7 | ariumlab.com: içerik, staging, geçiş, 30 gün izleme | Yeni site yayında |

## Bilinen açık işler

- ~~Anasayfa (blog listesi) şu an H1 içermiyor~~ → F1'de çözüldü (`home.html` + bütçe `h1 = 1`).
- ~~Çekirdeğin emoji script'i (`wp-emoji-release`) yükleniyor~~ → F2'de performans modülüyle çözüldü (bütçe `emoji = 0`).
- K8'deki breadcrumb hizalaması (SEO eklentisi aktifken görünen breadcrumb'ın eklentiden gelmesi) → F3'te breadcrumb bloğuyla.
- ~~HTML şablonlardaki sabit metinler çevrilemiyor~~ → F1'de `patterns/hidden-*.php`'ye taşındı.
