# Mimari

## Parçalar

```
Proje paketi (brief · tokens · sitemap · içerik · ürün CSV)
        │  wp pergel import (F5)
        ▼
Child tema (ör. ariumlab) ──► pergel (blok tema)        ──► WordPress 7.x çekirdeği
                          └─► pergel-engine (eklenti)   ──► WooCommerce (isteğe bağlı)
                                                        └─► SEO eklentisi (varsa devreder)
```

| Parça | Yer | Sorumluluk |
| --- | --- | --- |
| `pergel` | `theme/` → `wp-content/themes/pergel` | Yalnızca görünüş: `theme.json`, şablonlar, şablon parçaları, pattern'ler, style variation'lar, fontlar |
| `pergel-engine` | `engine/` → `wp-content/plugins/pergel-engine` | Tüm işlev: bloklar, SEO/AI, WooCommerce özellikleri, içerik türleri, performans, WP-CLI |
| Child tema | Proje reposu | Projeye özel token'lar, pattern'ler, şablon değişiklikleri |

Tema eklentisiz de çalışır (çekirdek bloklarla); eklenti de başka bir blok temayla çalışır. Tema
değiştiğinde içerik, SEO verisi ve özel içerik türleri kaybolmaz.

## Repo düzeni

```
pergel/
├─ theme/                 blok tema (dağıtılır)
├─ engine/                eklenti (dağıtılır)
│  ├─ pergel-engine.php   başlık, sabitler, autoloader, boot
│  ├─ src/<Modül>/        PSR-4: Pergel\<Modül>\...
│  ├─ blocks/<blok>/      block.json + render.php (sunucuda çizilen bloklar)
│  ├─ build/              derlenmiş editör JS'i (repoya girer, K6)
│  └─ languages/
├─ starters/<ad>/         starter paketleri: starter.json (dağıtılmaz; F3: corporate, F4c: shop)
├─ tools/
│  ├─ budget/             perf-probe.php (mu-plugin) + measure.mjs (Playwright)
│  ├─ ci/                 CI'da WordPress kurulumu ve php -S router'ı
│  ├─ dev/link.ps1        yerel junction'lar
│  ├─ fixtures/seed.php   sabit test içeriği (idempotent)
│  ├─ stubs/              statik analiz için WP-CLI stub'ları
│  └─ validate-json.mjs   theme.json / block.json şema doğrulaması
├─ tests/php/             birim testleri (WordPress olmadan)
├─ tests/integration/     WordPress gerektiren testler (PERGEL_WP_DIR)
├─ tests/budget/          performans bütçesi (K11)
├─ tests/visual/          pattern erişilebilirlik ve görsel regresyon testleri (Playwright)
└─ docs/
```

## Engine çalışma modeli

1. `pergel-engine.php` yalnızca sabitleri tanımlar, autoloader'ı kaydeder ve `plugins_loaded`
   (öncelik 5) üzerine `Core\Plugin::boot` bağlar. Başka kod çalıştırmaz.
2. **Autoloader:** `Pergel\X\Y` → `src/X/Y.php`. Composer'a ve `vendor/`'a gerek yoktur; yalnızca
   kullanılan sınıf dosyası yüklenir. (Plandaki "Composer classmap"ten bilinçli sapma, bkz.
   DECISIONS.md.)
3. **Modüller:** Her modül `Core\Module` arayüzünü uygular:
   - `id()` — sabit makine adı, ayar anahtarı olarak da kullanılır.
   - `should_load()` — bu istek için gerekli mi? Ucuz olmalı: autoload'lu option'lar dışında
     sorgu yok. Yanlış dönerse modülün diğer sınıfları hiç yüklenmez.
   - `register()` — hook'ları ekler.
4. `pergel/modules` filtresi child temaların ve proje eklentilerinin kendi modüllerini eklemesine
   izin verir. Tüm modüller kaydedildikten sonra `pergel/loaded` çalışır.

Planlanan modüller ve yüklenme koşulları:

| Modül | Yüklenme |
| --- | --- |
| Core | Her zaman |
| Settings | Her zaman (hafif: hook'lar; ekran yalnızca admin) |
| Blocks | Her zaman, anahtarsız (pattern'ler bu bloklarla kurulur); blok CSS/JS'i yalnızca blok sayfadaysa |
| Brand | Her zaman (hafif: `init` + editör script'i yalnızca editörde) |
| Seo | Her zaman (meta kaydı admin/REST için; `Head` yalnızca ön yüzde, eklenti varsa yalnızca entegrasyon filtreleri) |
| Ai | İsteğe bağlı |
| Content | Yalnızca açılan içerik türleri |
| Woo | WooCommerce aktifse |
| Performance | Ön yüz (`is_admin()` dışı; her parça kendi ayar anahtarıyla) |
| Import / Cli | Yalnızca WP-CLI (`Cli` şu an var; bkz. "WP-CLI") |

## Ayar modeli (F2)

Tek option (`pergel_settings`), autoload ≤ 20 KB, REST şemasıyla doğrulanır. Tasarım ayarları bu
option'da değil Global Styles'tadır (K5).

- `Settings\Schema` — gruplar ve alanlar (`modules`, `brand`, `seo`, `performance`). Varsayılanlar, temizleme ve REST JSON Schema bu tek tanımdan üretilir.
- `Settings\Settings` — okuma: `all()`, `get( 'brand.email' )`, `module_enabled( 'seo' )`,
  yazma: `update( $değişiklikler )`. Değerler istek başına bir kez şemaya göre normalize edilir;
  önbellek option her değiştiğinde düşer.
- `Settings\Module` — her zaman yüklenir; `sanitize_option_pergel_settings` filtresi, önbellek
  hook'ları ve `admin_init` / `rest_api_init` üzerinde `register_setting` (`/wp/v2/settings`,
  yalnızca `manage_options`).
- `Core\Plugin::boot` bir modülü yalnızca `modules.<id>` anahtarı açıksa (veya hiç yoksa) ve
  `should_load()` doğruysa yükler.
- Eklenti etkinleştirilirken varsayılanlar autoload ile yazılır; böylece okuma sorgu yapmaz.
- `Settings\Screen` — yalnızca admin'de yüklenir. Üst düzey "Pergel" menüsü, her şema grubu
  için bir sekme. Form `options.php`'ye gider ve yalnızca açık sekmenin alanlarını gönderir;
  diğer gruplar birleştirerek yazma sayesinde korunur. Hatalı alanlar eski değerini korur,
  hata hem üst bildirimde hem alanın altında (`aria-invalid`, `aria-describedby`) görünür.
- `Settings\Labels` — sekme, bölüm, alan etiketleri ve hata metinleri; şemanın saf PHP
  kalması için ayrı tutulur. Labels'ta yer almayan alan "Other" bölümünde yine gösterilir.
- `engine/assets/settings.js` — build'siz; görsel alanları için çekirdek medya seçicisi.

## Marka ve Block Bindings (F2)

Şirket bilgileri bir kez ayarlarda girilir; bloklar ve yapılandırılmış veri aynı kaynaktan okur.

- `Brand\Brand` — `brand` grubunu okur ve boşlukları doldurur: ad yoksa site başlığı, adres
  için ana sayfa, ana logo için Site Logo. `value( $anahtar )` boş değerde `null` döner.
- `Brand\Format` — saf PHP: `tel:` / `mailto:` bağlantısı, tek satır adres, profil URL'sinden
  sosyal ağ, schema.org `Organization`. Birim testleriyle sınanır.
- `Brand\Module` — `pergel/brand` bağlama kaynağı. Argümanlar: `{"key": "name"}`; sosyal için
  `{"key": "social", "network": "linkedin"}`. Anahtarlar: tüm metin alanları ile `url`,
  `address`, `email_url`, `phone_url`, `copyright`, `logo_inverse` (görselde `id`, `url`, `alt`).
  Boş değer bloğun kayıtlı metnini korur. Zengin metin özelliklerinde (`content`, `text`,
  `caption`) değer burada kaçışlanır; diğer özellikleri çekirdek yazarken kaçışlar.
- `engine/assets/brand-bindings.js` — build'siz, yalnızca blok ve site editöründe: güncel
  değerler ve bloğun "Özellikler" panelindeki alan listesi (`getFieldsList`). Değerler editörde
  salt okunur.
- `Brand::organization()` SEO modülünün `Organization` düğümünü besler.

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"pergel/brand","args":{"key":"phone"}}}}} -->
<p>+90 000 000 00 00</p>
<!-- /wp:paragraph -->
```

## SEO (F2)

K8 sözleşmesi: bir SEO eklentisi aktifse Pergel hiçbir SEO etiketi basmaz, yalnızca eklentinin
`Organization` düğümünü marka bilgisiyle tamamlar. Eklenti yoksa çekirdek API'lerle bir yedek
katman çalışır.

- `Seo\Detect` — Rank Math, Yoast SEO, AIOSEO, SEOPress, TSF'yi sürüm sabitinden tanır (ilk
  eşleşen kazanır); `pergel/seo/plugin` filtresiyle değiştirilebilir.
- `Seo\Integrations` — eklenti başına tek filtre: `rank_math/json_ld` (100),
  `wpseo_schema_organization`, `aioseo_schema_output`, `seopress_schemas_organization_html`
  (JSON dizesi), `the_seo_framework_schema_graph_data`. Yalnızca boş özellikler doldurulur,
  `sameAs` birleştirilir; `Person` düğümlerine dokunulmaz.
- `Seo\Graph` — saf PHP: `Organization`, `WebSite` (+ `SearchAction`), `WebPage` türü,
  yazıda `BlogPosting` / `Article`, en az iki halkada `BreadcrumbList`. Birim testli.
- `Seo\Head` — yedek katman: belge başlığı, `wp_robots`, açıklama, Open Graph, Twitter
  kartı, liste sayfalarında sayfa numaralı canonical (tekil sayfanın canonical'ı çekirdekte),
  tek bir JSON-LD `@graph` (`pergel/seo/graph` filtresi). 404'te hiçbir şey basılmaz.
  Açıklama sırası: SEO paneli → özet → içeriğin başı; ön sayfada site sloganı.
- `Seo\Facts` — saf PHP, içeriğin zaten taşıdığı yapılandırılmış veri (birim testli):
  `pergel-faq` sınıflı bir bloğun içindeki Details blokları (soru `<summary>`, cevap iç
  bloklarının düz metni) ve H3 ya da daha alt bir başlıkla açılan gruplar (soru kartları,
  `faq-cards`) `FAQPage` olur; "Specifications" stilli tablolarda (`is-style-pergel-specs`) iki
  hücreli satırlar ad + değer olur (başlık satırı ve üç sütunlu tablolar dışarıda). Ziyaretçi
  aynı metni gördüğü için veri sayfadan ayrışamaz. Bütün `faq-*` pattern'lerinin kökü bu sınıfı
  taşır.
- `Seo\Faq` — tekil sayfada (parola korumalı değilse) `FAQPage` düğümünü etkin katmanın grafiğine
  ekler: Pergel tek başınaysa `pergel/seo/graph`, Rank Math `rank_math/json_ld` (100), Yoast
  `wpseo_schema_graph`, AIOSEO `aioseo_schema_output`, TSF `the_seo_framework_schema_graph_data`.
  Grafiği süzülemeyen SEOPress'te ayrı bir betik basılır (`class="pergel-faq-schema"`, eklentinin
  grafiğine dokunmaz). Grafikte zaten bir `FAQPage` varsa (eklentinin kendi SSS bloğu) eklenmez.
  Ürünlerde teknik özellikler `Woo\Properties` ile Product düğümünün boş `additionalProperty`'sine
  yazılır: WooCommerce'in kendi Product verisi (`woocommerce_structured_data_product`, 30) ve
  Rank Math / Yoast / AIOSEO grafikleri (110).
- `Seo\Meta` — `_pergel_seo_title`, `_pergel_seo_description`, `_pergel_seo_noindex`
  (REST'te, `edit_post` yetkisiyle) ve editörde build'siz "SEO" paneli
  (`engine/assets/seo-panel.js`).
- `Seo\Sitemap` — çekirdek site haritası: gizli yazılar çıkarılır, `lastmod` eklenir, yazar
  sayfaları ayarla açılmadıkça listelenmez.
- Ayarlardaki SEO sekmesi (paylaşım görseli, X kullanıcı adı, yazar site haritası) eklenti
  aktifken bunu söyleyen bir bildirim gösterir.

**SEO matrisi** (`tools/seo/`): `matrix.sh` Pergel'i önce tek başına, sonra her ücretsiz SEO
eklentisiyle sırayla çalıştırır; `check.mjs` her senaryoda sekiz sayfanın (ön sayfa, blog, yazı,
sayfa, SSS sayfası, kategori, arama, 404) `<head>`'ini ve site haritalarını okur. Kurallar: tek `<title>`,
en fazla bir açıklama / robots / Open Graph ve Twitter etiketi, dizine girecek sayfalarda tek
canonical, sayfa başına en fazla bir `Organization` ve bir `WebSite`, eklenti varken Pergel
JSON-LD'si yok (`class="pergel-schema"`), her `Organization` düğümünde marka telefonu ve sosyal
profili, SSS sayfasında iki sorulu tek `FAQPage`, diğer sayfalarda hiç `FAQPage`, tek XML site
haritası (robots.txt'teki de aynısı). CI'da `seo-matrix` işi eklentilerin
wordpress.org'daki son sürümlerini kurar (`INSTALL=1`). Yerelde:

```bash
WP_DIR=/c/xampp/htdocs/pergel-tema SITE_URL=http://localhost/pergel-tema bash tools/seo/matrix.sh
```

## Performans modülü (F2)

Yalnızca ön yüzde yüklenir; dört anahtarın hepsi varsayılan açıktır (Ayarlar → Performance).

- **Emoji:** çekirdeğin emoji algılama script'i, stilleri ve `s.w.org` DNS prefetch'i sayfadan ve
  embed'lerden kalkar. Akışlar ve e-postalar çekirdeğin görsel yedeğini korur.
- **oEmbed keşif bağlantıları:** `<head>`'deki iki `alternate` bağlantısı kalkar. Başka siteler
  sayfa adresinden embed kartı üretemez; site içi embed'ler çalışır (çekirdek onları HTTP'siz çözer).
- **Dashicons:** giriş yapmamış ziyaretçide kuyruktan çıkar (`wp_enqueue_scripts` 100 ve `wp_footer` 1).
  Dashicons'a bağımlı bir stil onu yine getirir.
- **Font önyükleme:** `Performance\Fonts` (saf PHP, birim testli) birleşik Global Styles'tan kök ve
  başlık (`heading`, `h1`–`h6`) font preset'lerini bulur; bu fontların düz (italik olmayan),
  temel Latin'i kapsayan woff2 dosyaları `wp_preload_resources` ile önyüklenir. URL'ler çekirdeğin
  `@font-face` adresiyle birebir aynıdır (`file:./` → `get_theme_file_uri()`). Sistem fontlu temel
  temada hiçbir şey önyüklenmez. `pergel/performance/font_preloads` filtresi listeyi değiştirir.

Bütçe bunları denetler: `emoji = 0`, `fontPreloads ≤ 2`, `unusedPreloads = 0` (önyüklenip hiçbir
`@font-face` kuralınca kullanılmayan dosya), `jsonld ≤ 1`.

## Bloklar (F3)

Pergel blokları sunucuda çizilir ve `engine/blocks/<slug>/` altında durur:

- `block.json`: ad `pergel/<slug>`, `apiVersion` 3, `textdomain` `pergel-engine`, kategori
  (`pergel` ekleyicide çekirdek kategorilerin ardından gelir), `render: file:./render.php`.
- `render.php`: blok çıktısı; `$attributes`, `$content`, `$block` değişkenleriyle çağrılır.
- `style.css` (isteğe bağlı): çekirdek yalnızca blok sayfada çizildiğinde ekler ve küçükse
  satır içine alır; ayrı istek oluşmaz.
- Editör betiği olmayan blok `supports.autoRegister: true` taşır. WP 7.0'dan beri editör böyle
  bir bloğu sunucu önizlemesiyle kendisi kaydeder ve ayar alanlarını `attributes`'tan üretir
  (breadcrumb böyledir).
- İçerik tutan bloklar derlemesiz bir `index.js` (`editorScript: file:./index.js`) ve bağımlılıkları
  sayan `index.asset.php` taşır. Ortak yardımcı `engine/assets/blocks-editor.js`
  (`pergel-blocks-editor`) `window.pergelBlocks.container()` ile bloğu çekirdek bloklardan oluşan
  bir InnerBlocks şablonu, isteğe bağlı sınıf ve Inspector kontrolleriyle kaydeder; `save` yalnızca
  iç blokları yazar, sarmalayıcıyı `render.php` basar. Böylece metin, görsel ve bağlantılar çekirdek
  blokların kendi araçlarıyla düzenlenir, contentOnly pattern'lerde de açık kalır ve çevrilebilir.
- `render.php` ve `block.json`'un gösterdiği her dosya (`style`, `editorScript`,
  `viewScriptModule`...) var olmalıdır; `tools/blocks.php` bunu da denetler.

`php tools/blocks.php` bu kuralları denetler ve tüm `block.json`'ları `engine/blocks/manifest.php`
dosyasına yazar. `Blocks\Module` blokları bu dosyadan bir metadata koleksiyonu olarak kaydeder;
istek başına JSON okunmaz. CI `--check` ile manifest'in güncel olduğunu, `npm run validate:json`
ile `block.json`'ların WP 7.1 şemasına uyduğunu denetler.

### Header seçenekleri

`Blocks\Header`, header alanını gösteren Template Part bloğuna iki öznitelik ekler:
`pergelSticky` (`""`, `always`, `smart`: aşağı kaydırırken gizlenir, yukarıda geri gelir) ve
`pergelOverlay` (header sayfanın ilk bölümünün üstüne biner, sayfa kayana kadar arka planı
yoktur). Seçenekler bloğun Ayarlar sekmesindeki "Header behaviour" panelindedir
(`engine/assets/header-controls.js`, derlemesiz). Öznitelik şablondaki blok örneğinde durduğu için
her şablon kendi davranışını seçer; overlay için olağan yer `front-page` şablonudur.

Ön yüzde `render_block_core/template-part` süzgeci sarmalayıcıya `pergel-header` ve durum
sınıflarını ekler, `header.css`'i (satır içi) ve `pergel/header` script modülünü yalnızca seçenek
açıkken yükler. Script; gizleme, `is-scrolled` ve çapa bağlantıları için `--pergel-header-height`
değişkenini yönetir. Odak header'a girerse ya da mobil menü açıksa header gizlenmez. Gizlenmemiş
header'da `transform` bulunmaz; aksi halde `position: fixed` mobil menü header'ın içine hapsolurdu.

### Logo boyutu

Header ve footer pattern'lerindeki Site Logo bloklarında sabit genişlik yoktur. Genişliği
verilmemiş logo (`is-default-size`) temanın `assets/css/blocks/site-logo.css` stiliyle
yüksekliğe göre boyutlanır: `logo.height` (40px) ve `logo.max-width` (240px) token'ları
`--wp--custom--logo--*` olur. Yatay yazı logosu da kare işaret de okunur kalır. Editörde genişlik
verilirse sınıf kalkar ve o genişlik geçerlidir. Kural theme.json `css` alanında değildir:
WordPress o alanı `:where()` ile sardığı için çekirdeğin 120px kuralını geçemez.

### Breadcrumb

`pergel/breadcrumb` sayfanın yolunu `<nav><ol>` olarak basar; son adım bağlantısız ve
`aria-current="page"` taşır, iki adımdan kısa yolda hiçbir şey basmaz. Yolu `Seo\Trail` verir:

- Rank Math, Yoast, AIOSEO ve TSF aktifse yol eklentinin kendi BreadcrumbList'ini ürettiği listeden
  okunur (Rank Math `Breadcrumbs::get()->get_crumbs()`, Yoast `YoastSEO()->meta->for_current_page()`,
  AIOSEO `breadcrumbs->frontend->getBreadcrumbs()`, TSF `Meta\Breadcrumbs::get_breadcrumb_list()`).
- Eklenti yoksa yol Pergel'in yedek grafiğindeki yoldur (`Head::page()['crumbs']`).
- SEOPress (ücretsiz) yol üretmez: Pergel'in yolu kullanılır ve blok BreadcrumbList'i kendisi
  ekler (`pergel/breadcrumb/schema` filtresiyle kapatılabilir).

Her kaynak `Trail::clean()`'den geçer: adı ve bağlantısı olan, schema'dan gizlenmemiş adımlar
kalır. Eklentiler de BreadcrumbList'e tam bu adımları koyduğu için görünen yol ile schema aynıdır;
SEO matrisi (`tools/seo/check.mjs`) bunu altı senaryoda denetler. Rank Math yolu yalnızca destek
bildiren temalarda kurduğundan Pergel `rank-math-breadcrumbs` desteğini ekler; blog sayfası gibi
bağlantısız bırakılan son adıma sayfanın adresini verir. Editörde blok örnek bir yol gösterir.
Blok `single`, `page`, `archive` ve `home` şablonlarında başlığın üstündedir.

### İçerik blokları (F3b)

Hepsi sunucuda çizilir; CSS'leri yalnızca blok sayfadayken yüklenir. Ön yüz script'leri küçük
script modülleridir (`viewScriptModule`), jQuery ve Interactivity kullanmaz.

- **Sayaç** (`pergel/counter`): sayı ve etiket paragrafı. Sunucu son sayıyı basar; script blok
  görünür olunca sıfırdan sayar (binlik ayırıcı, ondalık ve çevresindeki metin korunur: `250+`,
  `%98`, `1.200`). `prefers-reduced-motion`'da ve süre 0 iken sayı hiç değişmez. Süre 0–10000 ms.
- **Zaman çizelgesi** (`pergel/timeline` > `pergel/timeline-item`): `<ol>`/`<li>`; blok 40rem'den
  darsa tek sütun, genişse çizginin iki yanında sırayla. Ölçü ekran değil bloğun kendi genişliğidir
  (container query); dar bir sütundaki zaman çizelgesi tek tarafta kalır.
- **Logo şeridi** (`pergel/logo-marquee`): Image blokları iki kez basılır, ikinci kopya
  `aria-hidden` ve `inert`'tir ve id'leri silinir. Kayma saf CSS'tir (süre 5–120 sn), üzerine
  gelince, logolara odaklanınca ve "Duraklat" düğmesiyle durur (WCAG 2.2.2); düğmeyi script
  gösterir. Hareket azaltılmışsa logolar sabit, sarılan bir satırdır. Editörde de sabit satırdır.
- **Fiyat tablosu** (`pergel/pricing-table` > `pergel/pricing-plan`): sığdığı kadar sütun, butonlar
  altta hizalı; öne çıkan plan `is-featured` alır.
- **Karşılaştırma tablosu:** yeni blok değil, `core/table` için `pergel-comparison` blok stili
  (`Blocks\Styles`). İlk sütun yapışkan, tablo en az 32rem; dar ekranda tablo kendi içinde yatay
  kayar ve çerçevesi klavyeyle kaydırılabilsin diye `tabindex="0"` alır.
- **Özellik tablosu:** `core/table` için `pergel-specs` blok stili ("Specifications",
  `assets/specs-table.css`): etiket + değer satırları, çizgili, etiket sütunu soluk ve %40.
  Karşılaştırma stili gibi CSS'i yalnızca kullanılan sayfada yüklenir, çerçeve `tabindex="0"` alır.
- **İçindekiler** (`pergel/table-of-contents`): yazının H2–H3 (ayarla H4'e kadar) başlıklarını
  iç içe `<ol>` olarak listeler; iki başlıktan azsa hiçbir şey basmaz. Başlık listesi
  `parse_blocks` ile içerikten çıkarılır. `Blocks\Toc` tekil sayfalarda içeriğin her başlığına
  aynı id'yi verir: HTML çapası olan kendi id'sini korur, diğerleri metinden üretilir ve tekrar
  edenler `-2`, `-3` alır. Eşleşme düzey+metin ve sıraya göredir; sıra her `the_content`
  geçişinde baştan başlar (meta açıklaması için üretilen özet başlıkları önce bir kez çizer).
  Editör önizlemesi kayıtlı içerikten gelir. "Bar" stili (`is-style-bar`, geniş/tam hizalanabilir)
  yalnızca H2'leri yatay, yapışkan bir bölüm çubuğu olarak gösterir: başlık gizli, alt listeler
  yok, sığmazsa yatay kayar; yapışkan başlık varsa onun altına oturur ve çapa kaydırması
  (`scroll-padding`) çubuk yüksekliğini hesaba katar.
- **Harita** (`pergel/map`): adres/yer ve yakınlık. Önce statik görsel (ya da adresli bir yer
  tutucu) ve Google Maps'e giden bir bağlantı basılır; iframe yalnızca "Show the map"e basınca
  oluşur. Sayfa yüklenirken üçüncü taraf isteği yoktur; API anahtarı gerekmez. Adres boş
  bırakılırsa marka ayarlarındaki adres kullanılır; ikisi de boşsa blok hiçbir şey basmaz.

### Etkileşimli bloklar (F3b)

Bu üç blok çekirdeğin Interactivity API'sini kullanır (`supports.interactivity`, `data-wp-*`
yönergeleri, `viewScriptModule`); jQuery yoktur. Sunucu tam HTML'i basar, script yalnızca durum
ekler; JavaScript olmadan da içerik görünür ve kullanılabilir.

- **Carousel** (`pergel/carousel` > `pergel/carousel-slide`): WAI-ARIA carousel deseni
  (etiketli `region`, `aria-roledescription`, slayt başına "2 of 5" etiketi). Görünümde 1–4 slayt
  (`perView`; dar ekranda bir sonraki slayt kenardan görünür), önceki/sonraki düğmeleri ve isteğe
  bağlı noktalar. Kaydırma CSS scroll-snap'tir; script düğmeleri ve noktaları kaydırma konumuyla
  eşler. Otomatik oynatma yoktur. Script'siz carousel dokunma, trackpad ya da odaktayken ok
  tuşlarıyla kaydırılan bir şerittir.
- **Mega menü** (`pergel/mega-menu`): Navigation bloğunun bir öğesidir (`Blocks\MegaMenu` blok
  türünü `core/navigation`'ın izinli blokları arasına ekler). Paneli `pergel-mega-menu` alanındaki
  bir template part'tır (tema varsayılanı `parts/mega-menu.html`, metinler
  `patterns/hidden-mega-menu.php`'de); editörde blok listeden bir parça seçer ve parça Site
  Editor'de header gibi bloklarla düzenlenir. Tıklama açar; Esc, dışarı tıklama ve odağın
  dışarı çıkması kapatır (Esc mobil menüyü kapatmaz, yalnızca paneli). Header'da panel header
  boyunca açılır, footer'da yukarı açılır, mobil menüde öğesinin altında yerinde açılır. Script'siz
  panel üzerine gelince ve odakta açılır. İçeriği boş (metin ve medya yok) bir parça hiçbir şey
  basmaz, boş kutu açılmaz.
- **Form** (`pergel/form`): iletişim veya teklif formu; ayrıntılar aşağıda.

### Formlar (F3b)

`Forms` modülü (`modules.forms` ile kapatılır; kapalıyken Form bloğu hiçbir şey basmaz) Form
bloğunun mesajlarını alır.

- **Çizim** (`Forms\Form`): ad, e-posta ve mesaj zorunludur; telefon ve şirket bloğun ayarıyla
  eklenir. Teklif formunda mesaj alanı "What do you need?" olur ve e-posta konusu değişir.
  İsteğe bağlı onay kutusu gizlilik sayfasına bağlanır.
  Formda imzalı bir belirteç (`Form::sign()`, HMAC: blok ayarları, sayfa, oluşturma zamanı) ve
  `website` adlı bir tuzak alanı vardır. Belirteç nonce değildir: önbellekli sayfada da geçerli
  kalır ve bloğun ayarlarını sunucuya taşır.
- **Gönderim** (`Forms\Submit`): script `POST /wp-json/pergel/v1/form` kullanır; script'siz form
  `admin-post.php` (`action=pergel_form`) üzerinden gider ve sonuç koduyla sayfaya geri döner.
  İkisi de aynı sırayla denetler: dolu tuzak sessizce "gönderildi" alır; belirteç imzalı olmalı;
  yüklemeden 3 saniye içinde gelen form geri çevrilir; bağlantı başına 10 dakikada 5 mesaj (IP
  yalnızca özetlenerek sayaç anahtarında kullanılır); Turnstile anahtarları varsa doğrulanır;
  sonra alanlar sunucuda doğrulanır. Geçen mesaj önce saklanır, sonra e-postayla gönderilir.
- **Saklama** (`Forms\Inbox`): gizli `pergel_submission` yazı türü, yönetimde Pergel →
  "Form messages" (ad, e-posta, form, sayfa, e-posta gönderildi mi, tarih). Yalnızca yöneticiler görür;
  mesaj elle oluşturulamaz, okunur ve silinir. Kişisel veri dışa aktarma/silme araçlarına bağlıdır ve gizlilik
  politikasına önerilen metin ekler. Yazı türü yalnızca yönetimde, WP-CLI'da ve mesaj
  saklanmadan hemen önce kaydedilir.
- **E-posta:** alıcı `forms.recipient`, boşsa site yönetici e-postası; `Reply-To` gönderendir.
- **Turnstile:** `PERGEL_TURNSTILE_SITE_KEY` ve `PERGEL_TURNSTILE_SECRET_KEY` `wp-config.php`'de
  tanımlıysa (ya da `pergel/forms/turnstile` süzgeciyle) kullanılır; Cloudflare script'i yalnızca
  formlu sayfalarda yüklenir.
- **Bir yazı hakkındaki form:** bloğun iç ayarı `aboutPost` açıksa formun konusu (`subject`)
  bulunduğu yazıdır ve belirteçle imzalanır. Konunun alanlarını onu bilen kod verir:
  `pergel/forms/subject_fields` (mesaj alanından önce basılacak HTML) ve
  `pergel/forms/subject_values` (`values` + `errors`; formun kendi alanlarının adları
  kullanılamaz, değerler mesajdan önce saklanır ve e-postaya girer). Saklanan ve gönderilen her
  mesajdan sonra `pergel/forms/received` (`$entry, $spec, $values, $input`) çalışır.
- **Editörün ek alanları** (`fields` niteliği, v1.2): editör en çok 10 alan ekler. Her alanın
  etiketi, türü (`text`, `textarea`, `tel`, `select`), zorunlu olup olmadığı ve listede en çok
  30 seçeneği vardır. Etiketsiz alan ve seçeneksiz liste atlanır.
  - **Adlar ve sıra:** Alanlar sırayla `extra_1`, `extra_2` … adını alır. Konunun alanlarından
    sonra, mesajdan önce gelirler.
  - **Doğrulama:** Temizlenmiş tanımlar belirteçle imzalanır. Sunucu, değeri o tanıma göre
    doğrular; liste yalnızca kendi seçeneklerinden birini kabul eder. Konunun alanları bu adları
    kullanamaz.
  - **Etiketler:** Etiketler mesajla birlikte `_pergel_labels` meta'sında saklanır. E-posta,
    yönetim ekranı ve kişisel veri dışa aktarımı onları kullanır. Ek alanlar
    `pergel/forms/received`'deki `$values` içinde gelir, `QuoteRequest`'e girmez.
- **Analytics:** başarılı her gönderim `generate_lead` olayı verir (`gtag`, yoksa
  `dataLayer`; `lead_source`: `contact_form` / `quote_form`). Site etiketi yoksa olay hiçbir
  yere gitmez.

### Ön yüzde yalın yükleme

Sayfa görüntülemesinde Pergel bloklarının editör betik ve stilleri kaydedilmez:
`Blocks\Module` kayıt sırasında `block_type_metadata` süzgeciyle `pergel/*` bloklarının
`editorScript`/`editorStyle` alanlarını düşürür (yönetim, WP-CLI ve REST istekleri hariç).
Böylece her blok için `index.asset.php` okunmaz. Form sınıfları da yalnızca Form bloğu çizildiğinde
ya da bir mesaj geldiğinde yüklenir. Bütçe ölçümü (`pergel_files ≤ 45`) bunu denetler.

### Blok uzantıları (F3b)

Üç uzantıdan ikisini WordPress 7.0/7.1 çekirdeği zaten sağlar; Pergel onları açar ve ayarlar,
yalnızca kaydırma animasyonunu kendisi ekler.

- **Kaydırma animasyonu** (`Blocks\Animation`): bölüm ve içerik bloklarına "Scroll animation"
  paneli (Fade in, Rise, Zoom in, Slide in from the side) ekler. Öznitelik `pergelAnimation`,
  sunucuda `register_block_type_args`, editörde `blocks.registerBlockType` süzgeciyle aynı
  listeye eklenir (`Animation::BLOCKS`, `pergel/animation/blocks` süzgeciyle değişir; carousel
  slaytları, mega menü ve menü öğeleri dışarıda). Çizimde blok sarmalayıcısına
  `pergel-animate pergel-animate--<efekt>` sınıfı eklenir ve küçük CSS satır içi yüklenir; ayar
  kullanılmayan sayfaya hiçbir şey gelmez. Animasyon yalnızca CSS'tir (`animation-timeline:
  view()`), script yoktur. Kural `prefers-reduced-motion: no-preference` ve
  `@supports (animation-timeline: view())` içindedir: desteklemeyen tarayıcıda ve azaltılmış
  hareket isteyen ziyaretçide blok olduğu gibi görünür. Aralık `entry 0% entry 90%`: blok
  tamamen görünür olmadan animasyon biter, sayfanın son bloğu da soluk kalmaz. Bedeli, kısa
  bloklar kendi yükseklikleri kadar kaydırmada belirir. Editörde bloklar durağan görünür.
- **Cihaza göre gizleme** çekirdeğindir (`metadata.blockVisibility.viewport`): blok menüsünde
  "Hide" (Ctrl+Shift+H) Masaüstü, Tablet ve Mobil seçeneklerini sunar. Sınırlar tema
  `theme.json` içindeki `settings.viewport`'tan gelir: mobil ≤ 600px, tablet ≤ 1024px (çekirdek
  varsayılanı 480/782). Çekirdek `wp-block-hidden-<cihaz>` sınıfı ve `@media (width <= …)`
  kuralı basar.
- **Yapışkan konum** çekirdeğindir (`position.sticky`, `appearanceTools` ile açık): Group bloğunun
  Styles sekmesinde Position → Sticky. Header'ın yapışkan davranışı ise template part
  özniteliğidir (yukarıda).

## Pattern'ler (F3c)

Pattern'ler `theme/patterns/*.php` dosyalarıdır; WordPress dosya başlığından kaydeder. Metinler
`esc_html_e( '…', 'pergel' )` ile çevrilir, görseller `get_theme_file_uri()` ile temadan gelir.

- **Kurallar** (`Pergel\Patterns\Rules`, `php tools/patterns.php --check`, CI'da): Title, Slug
  (`pergel/<dosya adı>`), Categories ve Description zorunlu (`Inserter: no` olan gizli
  pattern'lerde son ikisi değil); kategori bilinen listeden; `ABSPATH` koruması; yalnızca çekirdek
  ve Pergel blokları, `core/html`, `freeform`, `shortcode` yok; metinde, `alt`/`title`/
  `aria-label`/`placeholder`/`value` özniteliklerinde ve blok JSON'undaki metin alanlarında sabit
  harf yok; çeviri alan adı `pergel`; en fazla bir H1, o da yalnızca hero (`banner`) ve tam sayfa
  (`pergel-pages`) pattern'lerinde; bölüm pattern'inde tek kök blok (tam sayfalar yalnızca
  `core/pattern` bloklarından oluşur); `src`/`href`/`srcset`/`poster` ve JSON `url`'lerinde dış
  adres yok; `<script>`, `<style>`, `<link>`, `<iframe>` yok; her `<img>` `alt` taşır; başvurulan
  her renk, yazı boyutu, yazı ailesi, boşluk ve gölge preset'i `theme.json`'da tanımlı.
- **Kategoriler:** Çekirdeğin kategorileri yeniden kullanılır (`banner`, `services`, `about`,
  `team`, `testimonials`, `call-to-action`, `contact`, `posts`, `portfolio`, `gallery`, `header`,
  `footer`); eksikler Pergel'den gelir: `pergel-stats` (Numbers), `pergel-process` (Process),
  `pergel-logos` (Clients), `pergel-pricing` (Pricing), `pergel-faq` (FAQ), `pergel-pages`
  (Full pages), `pergel-product` (Product, bkz. Bölümlü ürün sayfası) ve yalnızca WooCommerce
  etkinken `pergel-shop` (Shop, bkz. Mağaza bölümleri).
- **Kaynaklar:** Çekirdeğin hazır pattern'leri (`core-block-patterns` desteği kaldırılır) ve
  wordpress.org Pattern Directory (`should_load_remote_block_patterns`) yüklenmez. Eklentilerin ve
  sitenin kendi pattern'leri kalır.
- **Bölüm = tek kök blok:** WordPress 7.1, tek kök bloklu senkronsuz bir pattern eklendiğinde köke
  `metadata.patternName` yazar ve bölümü "yalnızca içerik" modunda açar: metin ve görseller
  düzenlenir, yerleşim kilitli kalır ("Edit pattern" ile açılır). Tam sayfa pattern'leri
  `<!-- wp:pattern {"slug":"pergel/…"} /-->` listesidir; eklenince her biri kendi bölümüne açılır.
- **Başlıksız sayfa şablonu:** `page-landing` ("Landing page (no title)", `customTemplates`) sayfa
  başlığını basmaz; hero veya tam sayfa pattern'i ile kurulan sayfada tek H1 hero'nunkidir.
- **Tam sayfalar** (`pergel-pages`, yalnızca `page` yazı tipinde, yeni sayfa açılırken önerilir):
  Home, About, Services, Contact, Pricing, Team, Quote landing ve Portfolio. Bölümlerin arka planı
  sırayla değişir (base, surface, contrast veya accent; aynı ton art arda gelmez); bir sayfada
  aynı başlık veya paragraf iki kez geçmez. Her tam sayfanın hero'su farklıdır, böylece bir
  starter'ın sayfaları aynı H1'i taşımaz.
- **Yer tutucu görseller:** `theme/assets/images/placeholders/` içinde nötr gri, yazısız SVG'ler
  (wide 16:9, landscape 3:2, portrait 4:5, square, avatar, logo-1…6), her biri 330 bayttan küçük.
  Süs oldukları için `alt=""` taşırlar; gerçek görseller projede değiştirilir.
- **Editör kontrolü** (`tools/patterns/editor-check.mjs`, CI'da bütçeden sonra): geçici bir yönetici
  ile sayfa editörünü açar, her Pergel pattern'ini `wp.blocks.parse` ile çözer ve her bloğu
  `wp.blocks.validateBlock` ile sıkı denetler (çekirdeğin sessizce kabul ettiği eski işaretleme ve
  deprecation'lar da hata sayılır). Sonra pattern'i ekleyiciyle aynı yoldan ekler ve her kökün
  `patternName` taşıdığını, yalnızca içerik modunda düzenlenecek bir şey kaldığını doğrular.
  Header/footer (yalnızca Site Editor) ve gizli pattern'ler yalnızca çözümleme denetiminden geçer.
  Konsol hatası da başarısızlıktır.
- **Erişilebilirlik ve görsel regresyon** (`tests/visual/patterns.spec.mjs`, CI'da `visual` işi):
  Pattern'ler örnek sayfada perf probe'un `X-Pergel-Pattern: <ad>[,<ad>…]` başlığıyla, hiçbir şey
  kaydedilmeden ve `page-landing` şablonuyla çizilir. Varsayılan stil ve her variation için bölüm
  pattern'leri kategori başına bir sayfada, tam sayfalar tek tek 1280 ve 360 piksel genişlikte axe
  (WCAG 2.2 A/AA) ve yana kaydırma denetiminden geçer. Tam sayfaların variation'lardaki ekran
  görüntüleri (1280/800/360) `tests/visual/baselines/` ile karşılaştırılır; referanslar yalnızca
  CI'da (Linux) üretilir. Eksik referans `visual-report` artifact'ına yazılır; hepsini yenilemek
  için iş `update-baselines` girdisiyle elle çalıştırılır. Yerelde `npm run test:a11y` (ekran
  görüntüsüz) çalışır.
- **Mağaza** (CI'da `shop` işi, WooCommerce `latest` ve `11.2.1`): `setup-wp.sh` `WOOCOMMERCE`
  ile eklentiyi kurar, `tools/ci/shop.php` mağazayı çevrimdışı ödeme yöntemleriyle ayarlar,
  `wp pergel starter install shop` kataloğu kurar. Sonra smoke (`tools/ci/smoke.sh`), mağaza
  bütçesi (`tests/budget/shop.json`, WP_DEBUG kapalı), ödeme ve satış modu e2e
  (`tests/shop`, `npm run test:shop`), mağaza pattern'lerinin axe/taşma ve editör denetimi.
  E2E ayarları WP-CLI ile değiştirip geri koyduğu için tek işçiyle çalışır.
- **Ölçek** (CI'da `scale` işi): mağaza starter'ının üstüne `tools/fixtures/scale.php` 5.000 ürün
  (20 kategori) ve 2.000 yazı üretir, arama indeksi yeniden kurulur. Smoke ve ölçek bütçesi
  (`tests/budget/scale.json`) son sayfalar, sıralama, fiyat filtresi, arama ve yazı arşivlerinde
  sorgu sayısını, toplam sorgu süresini (`query_ms`) ve en yavaş tek sorguyu (`slowest_ms`)
  denetler; perf probe en yavaş sorguyu rapora yazar. Mağaza ve ölçek işlerinde PHP JIT kapalıdır.
- **İnceleme** (CI'da `review` işi): `tools/ci/review.sh` sürüm zip'lerini
  (`tools/release.php package`, `git archive HEAD`) WP-CLI ile kurar, Plugin Check ve Theme
  Check'i sabit sürümleriyle çalıştırır ve zip'leri `release-zips` artifact'ı olarak saklar. Araçların çıkış kodu yetmediği için çıktı okunur: her Plugin Check bulgusu ve Theme
  Check'in REQUIRED/WARNING satırları (`tools/ci/review.php`'deki gerekçeli istisnalar dışında)
  işi kırar. Kabul edilen bulgular DECISIONS F6b'de.
- **Sürüm** (F6e): `php tools/release.php check` sürümün her yerde aynı olduğunu, changelog
  girdilerini, gereksinim başlıklarını ve ekran görüntüsünü denetler (lint işi); `bump X.Y.Z`
  sürümü yazar. `main`'deki bir `v*` etiketi `.github/workflows/release.yml` ile zip'li taslak
  GitHub sürümü açar.
- **Ne zaman ne çalışır:** PR'da `lint`, `syntax`, `budget` ve `review`. `compat`, `shop`,
  `scale`, `visual`, `seo-matrix` ve `rehearsal` PR'da `full-ci` etiketiyle, elle başlatılan
  çalıştırmada ve haftada bir. `main`'e birleştirme yeniden çalıştırılmaz, yalnızca belge
  değişikliği CI başlatmaz (Actions dakikaları, DECISIONS "CI dakikaları").

## Starter'lar (F3c)

Starter, tam sayfa pattern'lerinden kurulan hazır bir site iskeletidir: sayfalar, ana menü ve
okuma ayarları (ön sayfa, yazılar sayfası). `starters/<ad>/starter.json` paketi tanımlar:

```json
{
  "format": 1,
  "title": "Corporate",
  "pages": [
    { "key": "about", "title": { "en": "About us", "tr": "Hakkımızda" },
      "slug": { "en": "about", "tr": "hakkimizda" }, "pattern": "pergel/page-about" },
    { "key": "blog", "title": "Blog", "slug": "blog", "posts_page": true }
  ],
  "menu": { "title": { "en": "Main menu", "tr": "Ana menü" }, "pages": [ "about", "blog" ] }
}
```

- **Dil:** Başlık ve adresler düz metin veya dil haritasıdır; site dili (`tr_TR`, sonra `tr`,
  sonra `en`) seçer.
- **Sayfa içeriği:** Tam sayfa pattern'inin `core/pattern` referansları açılır ve her bölümün
  kökü ekleyicinin verdiği `metadata`'yı (`patternName`, ad, açıklama, kategoriler) taşır;
  sayfa editörde ekleyiciden kurulmuş gibi içerik modunda açılır. Pattern'li sayfalar
  `page-landing` şablonunu alır (tek H1 hero'da).
- **Menü:** `wp_navigation` yazısı, sayfa bağlantılarıyla. Header'lardaki Navigation bloğu bir
  menüye bağlı olmadığı için en yeni menüyü, yani starter'ınkini gösterir.
- **İşaretleme ve tekrar:** Starter'ın yaptığı her sayfa ve menü `_pergel_starter` (starter adı)
  ve `_pergel_starter_key` (öğe anahtarı) meta'sını taşır. Kurulum tekrar çalışınca bunları
  bulur ve dokunmaz (`keep`); `--reset` başlık, içerik ve şablonu starter'ınkine döndürür.
  Adresi dolu bir sayfa varsa WordPress yeni bir adres seçer, rapor bunu söyler.
- **Kaldırma:** Meta'sı olan sayfa ve menüler kalıcı silinir; okuma ayarları yalnızca silinen
  bir sayfayı gösteriyorsa "son yazılar"a döner. Başka içerik etkilenmez.
- **Yer:** `Starter::dir()` eklentinin yanındaki `starters/` klasörüdür; yerelde junction ile
  depoya bakar (CI'da symlink). Kopyalanmış eklentide (`LINK_MODE=copy`, zip kurulumu) paket klasör yoluyla verilir:
  `wp pergel starter install /yol/starters/corporate`.

### Mağaza starter'ı (F4c)

`starter.json`'daki `shop` bölümü ürün kategorilerini, global öznitelikleri ve ürünleri tanımlar
(`Starters\Shop`). Böyle bir paket WooCommerce ister: mağazasız sitede `starter list` onu
göstermez, `install` hiçbir şey yapmadan "needs WooCommerce" hatası verir.

```json
"shop": {
  "categories": [ { "key": "apparel", "name": { "en": "Apparel", "tr": "Giyim" }, "slug": "apparel" } ],
  "attributes": [ { "slug": "color", "name": "Colour", "terms": [ { "key": "black", "name": "Black", "color": "#1f2328" } ] } ],
  "products": [
    { "key": "tee", "type": "variable", "name": "Tee", "slug": "tee", "price": "349.00",
      "categories": [ "apparel" ], "attributes": { "color": [ "black" ] } }
  ]
}
```

- **Ürün türleri:** `simple`, `variable` (her öznitelik birleşimi için bir varyasyon, en fazla
  50), `grouped` (`children`: basit ve harici ürünler) ve `external` (`url`, `button`). Fiyatlar
  metindir (`"49.90"`); `sale`, `stock` (sayı; yoksa stok sayılmaz, stokta), `featured`, `sales`
  (çok satanlar sırası için), `sku`, `short`, `description`, `cross_sells`, `upsells`.
- **Sıra:** Önce ürünler (kategoriler, öznitelikler, ürünler), sonra sayfalar, en son menü;
  menü `wc:shop` (mağaza sayfası) ve `category:<key>` (ürün kategorisi, `kind: taxonomy`)
  girdileri alabilir.
- **Öznitelikler:** Aynı slug'lı global öznitelik varsa o kullanılır, terimleri slug'la eşleşir;
  yoksa yaratılır ve kimliği `pergel_starter_attributes` seçeneğine yazılır. Renkli terimler
  `color` term meta'sını, hepsi `order`'ı alır; WooCommerce'te `wc-visual` türü varsa yeni
  renk özniteliği bu türle gelir (Pergel swatch'ları).
- **Görseller (`Starters\Images`):** Depoda görsel yoktur; GD her ürün (değişkende her renk)
  ve kategori için etkin stilin renklerinden düz bir şekil çizer, WebP (yoksa PNG) olarak ortam
  kitaplığına ekler. Şekil anahtardan gelir, aynı öğe her kurulumda aynı resmi alır; varyasyon
  rengine ait görseli taşır.
- **İşaretleme, tekrar ve kaldırma:** Ürün, varyasyon, görsel ve terimler de `_pergel_starter`
  ve `_pergel_starter_key` meta'sını taşır; tekrar kurulum dokunmaz, `--reset` alanları ve
  varyasyonları yeniden yazar (görseller yeniden çizilmez). `remove` önce bunları, sonra
  yarattığı öznitelikleri siler; var olan özniteliklere ve başka ürünlere dokunmaz.

## Mağaza (F4)

`Woo\Module` yalnızca WooCommerce etkinken yüklenir (`class_exists( 'WooCommerce', false )`);
mağazasız sitede modülün hiçbir dosyası açılmaz, ayar ekranında "Shop" sekmesi ve modül anahtarı
görünmez (`Settings\Screen::shown`).

- **Uyumluluk:** `before_woocommerce_init` üzerinde HPOS (`custom_order_tables`) ve blok
  sepet/ödeme (`cart_checkout_blocks`) uyumu bildirilir.
- **Dosyalar (`Woo\Assets`, yalnızca ön yüz):** `woo.shop_assets_only` açıkken klasik CSS'ler
  (`woocommerce_enqueue_styles` boş döner, `woocommerce-blocktheme` ve `woocommerce-inline`
  kuyruktan çıkar) ve klasik jQuery betikleri (`woocommerce`, `wc-add-to-cart`,
  `wc-add-to-cart-variation`, `wc-single-product`) yalnızca klasik işaretleme olan sayfada
  kalır (`Assets::classic_request()`): hesap sayfası, WooCommerce kısa kodu ya da içerikte veya
  mağaza şablonunda klasik blok (`legacy-template`, `classic-shortcode`, `add-to-cart-form`,
  `product-image-gallery`). `pergel/woo/classic_files` süzgeci kararı değiştirir.
  `woo.no_cart_fragments` `wc-cart-fragments` betiğini her yerde kaldırır.
- **Şablonlar (tema):** `archive-product` (mağaza, kategori, etiket, öznitelik de buna düşer),
  `product-search-results`, `single-product`, `page-cart`, `page-checkout`,
  `order-confirmation`. `archive-product`'ta ürünler solda bir filtre sütununun
  (`pergel-shop-layout`, dar ekranda WooCommerce çekmecesi) yanında listelenir. Sabit metinler
  ve WooCommerce blokları gizli pattern'lerde: `pergel/hidden-product-terms`, `-details`
  (açıklama, özellikler, yorumlar akordeonu), `-related`, `-filters` (seçili filtreler, fiyat,
  kategori, stok ve ilk üç öznitelik). Pattern kuralları WooCommerce bloklarına yalnızca bu
  öneki taşıyan gizli pattern'lerde (`Patterns\Rules::SHOP_PREFIX`) ve mağaza bölümlerinde
  (`SHOP_SECTION_PREFIX`, aşağıda) izin verir.
- **Mağaza düzeni (`Woo\Layout`, `Woo\LayoutFields`):** `shop` ayar grubu (Pergel → Ayarlar →
  Mağaza düzeni; yalnızca WooCommerce açıkken) mağaza, ürün kategorisi, ürün etiketi ve ürün arama
  sayfalarındaki ana ürün ızgarasına (sorguyu sayfadan alan `inherit:true` Product Collection)
  uygulanır: geniş ekranda sütun (`render_block_context` ile Product Template bağlamına, çünkü
  WordPress alt blokları üst bloğun ayrıştırılmış haliyle kurar), tablet/telefon sütunu (ızgarada
  `pergel-columns-tablet|phone` sınıfı ve `--pergel-columns-*` değişkeni), sayfa başına ürün (`loop_shop_per_page`), tam
  genişlik (`pergel-shop-full` gövde sınıfı). Varsayılanlar (`template`, `auto`, `wide`, 0) şablona
  dokunmaz; Site Editor'da değiştirilmiş ya da alt temanın kendi şablonu seçilene kadar aynen
  kalır. Şemada `levels: ['category']` taşıyan alanları bir ürün kategorisi kendisi seçebilir
  (`pergel_shop` terim metası, yalnızca gerçek seçimler saklanır, `Schema::level_values()`); en
  yakın seçen kategori, yoksa site ayarı geçerlidir, alt kategoriler üsttekini izler. Ayarın
  ulaşamadığı (ızgarası silinmiş) özel şablonlar ayar ekranında uyarıyla listelenir. Görsel
  seçimler (`Labels` `cards`) radyo düğmeli kartlar olarak çizilir (`Settings\Pictures`).
  Düzen CSS'i temada değil motorda (`engine/assets/shop-layout.css`, `Layout::STYLE`): yalnızca bir
  ayar sınıf eklediğinde kuyruğa girer; varsayılan mağaza sayfası hiçbir ek dosya almaz.
- **Filtre alanı (`Woo\Filters`):** `shop.filters` filtre sütununu sola/sağa alır
  (`pergel-filters-left|right`), WooCommerce Product Filters bloğunun kendi panelini her ekranda
  açar (`overlayMode: always`, `render_block_data`; bloğun alt bağlamı olmadığı için öznitelik
  render öncesi değişebilir) ya da filtre sütununu hiç çizmez. `shop.drawer_side` panelin yönü
  (telefonda da). `shop.filters_sticky` sütunu ekranda tutar ya da kendi içinde kaydırır (783px
  üstü). `shop.filters_fold` grup başlığını sunucu tarafında `<button aria-expanded>` yapar,
  böylece WooCommerce filtreleri her seçimde yeniden çizdiğinde düğme kalır; açık/kapalı durumunu
  ve liste içi aramayı (`shop.filters_search`, sekizden uzun listeler; aramadan önce
  WooCommerce'in "daha fazla göster"ini açar) `assets/shop-filters.js` tutar ve her yeniden
  çizimde geri koyar. `shop.filter_brand|filter_rating` temanın filtre pattern'ine
  `pergel/shop/filters` süzgeciyle marka (`product_brand`) ve puan filtresini ekletir.
- **Görünüm ve sayfalar (`Woo\Listing`):** `shop.view` ana ızgaraya `pergel-shop-grid
  pergel-view-list` sınıfı koyar; liste mümkünse (`view` liste ya da `view_switch` açık) fiyattan
  sonra `woocommerce/product-summary` (30 kelime) çizilir, ızgarada CSS ile gizlenir.
  `shop.view_switch` sıralama menüsünün yanına (`render_block_woocommerce/catalog-sorting`,
  `.pergel-shop-toolbar`) betik açana kadar gizli ızgara/liste düğmeleri koyar; ziyaretçinin seçimi
  `localStorage['pergel-shop-view']` → `<html data-pergel-view>`, head'deki küçük satır içi betik
  sayfa çizilmeden uygular. `shop.per_page_menu` aynı yere düz bir GET formu (`per_page`, diğer
  sorgu argümanları gizli alanlarla) ekler; `Layout::per_page` → `Listing::per_page` ziyaretçinin
  sayısını menüde varsa ya da "daha fazla yükle" açıksa (1–96) kabul eder; `per_page` içeren
  sayfalar `noindex, follow`. `shop.paging` more/scroll sayfa numaralarından sonra sonraki sayfaya
  giden gerçek bir bağlantı ekler; `assets/shop-listing.js` bunu Interactivity router'ın
  `navigate()`'iyle aynı sayfayı daha büyük `per_page` ile istemeye çevirir (sepet düğmeleri
  çalışır kalır), ilk yeni ürüne odaklanır; kaydırma modu IntersectionObserver kullanır ve
  sayfadan kendiliğinden çıkmaz. Yalnızca ilk sayfa büyür, 96'dan sonra bağlantı izlenir.
  `navigate()`'in sözü directive dışında çağrılınca tamamlanmadığı için betik yeni ürünleri
  MutationObserver ile bekler. WooCommerce ürün koleksiyonunda `clientNavigation` desteği
  bildirmeyen bir blok varsa istemci gezinmesini sayfa için kapatır; bu yüzden
  `pergel/product-actions` desteği bildirir ve `shop-lists.js` sonradan çizilen düğmeleri gösterir.
  Kancaları `Layout` sınıfı yüklemeyen dizi çağrılarıyla kurar, ürün kancalarını
  (`Listing::prepare`) ana ızgara başlarken ekler; ürün sayfaları bu iki sınıfı yüklemez (mağaza
  bütçesinde `pergel_files` sınırı).
- **Ürünlerin üstü (`Woo\ShopHeader`):** mağaza ve ürün kategorisi sayfalarında
  `core/term-description`'dan sonra (mağaza sayfasında bu blok boş çizilir) `shop.banner` seçilen
  senkron deseni (`wp_block`, yalnızca yayımlanmış; kendini yeniden çizmeye karşı korumalı) ve
  `shop.category_strip` mağazada ana, kategoride alt kategorileri (boş olanlar ve varsayılan
  kategori hariç, en çok 24) yuvarlak resim, kutu, alt kategorili kutu ya da ad olarak bir `<nav>`
  içinde, tek sıra halinde gösterir. Kartları `Woo\CategoryCards` çizer; aynı kartlar
  `pergel/category-cards` bloğuyla (stil, üst kategori, sayı) her sayfaya konabilir ve CSS'leri
  bloğun stil dosyasındadır (`pergel-category-cards-style`).
  `pattern` türündeki ayarlar site içi kimlik taşıdığı için proje paketine yazılmaz.
- **Ürün kartları (`Woo\Cards`, `Woo\BadgeField`):** her Product Collection ızgarasındaki kartlar
  (mağaza, kategori ve başka sayfalardaki ızgaralar). `shop.card_style` (kategori düzeyinde de)
  ızgaraya `pergel-cards pergel-card-standard|boxed|overlay|plain` koyar, CSS
  `assets/shop-cards.css`. Kategori/marka satırı `core/post-title`'dan önce, puan
  (`woocommerce/product-rating`, yalnızca yorumlu ürünlerde) sonra; indirim yüzdesi ve stok hapı
  fiyattan sonra; rozetler (ürünün kendi rozeti `_pergel_badge`, "Yeni" N gün, "Popüler" öne
  çıkan, "Tükendi") resmin iç kabına; ikinci resim (galerinin ilki) ilk `<img>`'den sonra, yalnızca
  `hover: hover` ekranlarda. İndirim rozeti yüzde ya da hiç: hem rozet bloğunda hem resim bloğunun
  çıktısında işlenir, çünkü resim bloğu rozet bloğu yoksa kendi rozetini çizer. "Resim üstünde
  düğmeler" stilinde `pergel/product-actions` resmin içinde (bağlantının dışında) yeniden çizilir ve
  karttaki asıl kopyası boş döner; ızgaradakini ürün sayfasındakinden ayırmak için blok `queryId`
  bağlamını ister (`core/post-title` de yalnızca `queryId` aldığı için ürün tespiti `query` ya da
  `queryId` + `postType: product` ile yapılır). `Layout::cards` (`wp` kancası) `Cards`'ı yalnızca
  listeleme sayfalarında ve bir kart/rozet ayarı seçilmişse yükler; varsayılanlarla ürün sayfalarının
  dosya sayısı değişmez. Ayarlar ürün nesnesi istenmeden önce bakılır: varsayılanlarla listeleme
  sayfalarının sorgu sayısı da değişmez (ölçek bütçesi).
  İndirim geri sayımı (`shop.card_countdown`) bitiş tarihi olan indirimde resmin altına gün/saat/
  dakika/saniye kutuları koyar, ekran okuyucuya bitiş tarihini söyler; stok çubuğu
  (`shop.card_stock_bar`) stoğu sayılan ve en az bir satılmış üründe satılan/kalan oranını gösterir.
  Hızlı bakış (`shop.quick_view`) resmin köşesine bir düğme koyar; `assets/shop-cards.js`
  (script module `pergel/shop-cards`, yalnızca düğme ya da geri sayım çizildiğinde yüklenir) yerel
  `<dialog>` açar ve içeriği `GET /pergel/v1/quick-view?id=` (`Woo\QuickView`, yalnızca ayar
  açıkken ve yalnızca REST isteklerinde yüklenir) ile getirir: resimler, fiyat, kısa açıklama, stok
  kodu, stok ve basit ürünlerde ürün sayfasındakiyle aynı düz sepete ekle formu. Yayımlanmamış,
  gizli ya da parolalı ürün 404. Fiyat ve düğme satış modlarına uyar.
- **Ürün sayfası (`Woo\ProductPage`):** "Ürün sayfası" ve "Resimlerin yanında" ayarları ürün
  şablonuna, şablon çizilirken uygulanır (K10). Değer sırası: ürünün kendi değeri (post meta
  `pergel_shop`, ürün düzenleme ekranındaki "Ürün sayfası yerleşimi" kutusu), sonra gezinti yolunun
  gösterdiği kategori ve üstleri, sonra site (`Layout::get`). Yerleşim, galeriyi tutan
  `core/columns`'a sınıf ekler (`is-pictures-left|right|three`, `is-sticky-details`); üç sütunda
  önerilen ürünler (yukarı satış, yoksa benzer ürünler) üçüncü sütun olarak eklenir. Küçük
  resimlerin yeri `render_block_data` ile galeri bloğunun `layout` özniteliği ve iç blok sırasıyla
  değişir (her iç bloğun `innerContent`'te bir `null`'ı var, sırayla doldurulur); "Bütün resimler"
  galeri yerine bir resim ızgarası çizer (varyasyon seçilince resim değişmez). Yakınlaştırma büyük
  resim bloğunun bağlamından kapatılır: galeri bloğunun özniteliği değişse de WordPress iç bloklara
  giden bağlamı önceden hesaplamış olur. Kategori, marka (resmiyle), indirim ve stok hapları,
  kampanya deseni ve önceki/sonraki ürün okları `queryId` bağlamı olmayan ve `postId`'si ekrandaki
  ürün olan bloklara eklenir; sayfadaki ürün ızgaraları etkilenmez. Rozet ve hap CSS'i kartlarla
  ortaktır (`shop-cards.css`), yerleşim CSS'i `assets/product-page.css`. `Layout::product_page`
  (`wp` kancası) `ProductPage`'i yalnızca bir ürün sayfası ayarı varsayılandan farklıysa yükler.
  Satın alma (S4b): "Hemen al" sepete ekleme formunun (`woocommerce/add-to-cart-with-options`)
  sonuna ve yapışkan çubuğa bir düğme koyar; `assets/product-page.js` formun kendi düğmesine basar,
  WooCommerce `wc-blocks_added_to_cart` olayını yayınca ödeme sayfasına gider (seçenek eksikse
  ilk seçeneğe götürür). Kuponlar: kupon ekranındaki "Ürün sayfalarında göster" kutusu
  (`Woo\CouponField`, meta `_pergel_show`) ile seçilen, yayımlanmış, süresi ve kullanım sınırı
  dolmamış, e-posta kısıtı olmayan ve ürün/kategori kurallarına göre bu ürüne uygulanan kuponlar
  formun altında, kodu kopyalayan düğmeyle listelenir. Paylaşım bağlantıları `pergel/product-cta`
  bloğundan sonra gelir (iki ürün şablonunda da var, satış modunda boş çizilir): düz bağlantılar,
  sitelerden betik yüklenmez; bağlantıyı kopyala düğmesi. Kartlarda renkler
  (`shop.card_swatches`): seçenekli üründe ilk `wc-visual` türündeki niteliğin terimleri
  (WooCommerce'in `color`/`image` term meta'sı), resmi olan seçenek kartın resmini değiştiren bir
  düğme; WooCommerce'in iç (`Internal`) sınıfları kullanılmaz.
- **Mini sepet (`Woo\MiniCart`):** `hooked_block_types` ile `woocommerce/mini-cart`, kategorisi
  `header` olan `pergel/*` pattern'lerinde `core/navigation`'dan sonra bir kez eklenir; sepet ve
  ödeme sayfasında eklenmez. `woo.mini_cart` ayarına ve satış modlarına bağlı (ziyaretçiye hiçbir
  şey satılmıyorsa eklenmez). WooCommerce kendi `register_hooked_block`'u (öncelik 9) ile mini
  sepeti her header pattern'ine ekler; Pergel süzgeci öncelik 20'de çalışır, Pergel header'larında
  bu kaydı önce listeden çıkarır ve yalnızca koşullar tutuyorsa geri koyar. Başka temaların ve
  pattern'lerin kaydına dokunmaz.
- **Satış modları (`Woo\Modes`):** `sale` (fiyat ve sepet herkese), `login` (fiyat girişten
  sonra), `quote` (fiyat yok, teklif) ve `catalog` (fiyat yok). Mağazanın modu `woo.sales_mode`
  ayarında; ürün kategorisi (`pergel_sales_mode` term meta) ve ürün (`_pergel_sales_mode` post
  meta) kendi modunu seçebilir. Çözüm sırası: ürünün kendi modu → kategorilerinin en katısı
  (kategori modunu alt kategorilerine geçirir) → mağaza. Varyasyon ürününü izler. Kategori
  modları ve kullanılan modlar tek bir otomatik yüklenen seçenekte (`pergel_woo_modes`:
  `terms`, `slugs`, `used`) tutulur; mod meta'sı, kategori ağacı değişince yeniden kurulur,
  sayfa görüntülemesi ek satır okumaz, yazmaz. Modlar ön yüzde, Store API'de ve
  `edit_products` yetkisi olmayanların AJAX isteklerinde uygulanır; yönetim ekranları, mağaza
  yöneticisinin REST isteği, cron ve WP-CLI tüm fiyatları görür (`Modes::enforced()`,
  `pergel/woo/enforce_modes` süzgeci).
- **Fiyatın kaldırılması (`Woo\Selling`):** Ziyaretçiye satılmayan üründe fiyat veri
  katmanında kalkar: `price`/`regular_price`/`sale_price` görünüm getter'ları boş, varyasyon
  fiyat aralığı boş (önbellek anahtarı ayrı: `pergel-no-price`), `price_html` boş, satın
  alınamaz; fiyatsız varyasyonlar seçilebilir kalır. WooCommerce yapılandırılmış verisinde ve
  Rank Math / Yoast / AIOSEO graph süzgeçlerinde `offers` silinir; Google for WooCommerce
  feed'ine yalnızca herkese satılan ürünler girer. Kullanımdaki modlardan biri (mağaza,
  kategori ya da ürün) ziyaretçiden fiyat gizliyorsa bütün listelerde `min_price`/`max_price` ve
  fiyat sıralaması istekten düşer, sıralama menüsünden
  ve varsayılan sıralamadan çıkar, fiyat filtresi blokları basılmaz; Store API ürün
  listelerinde aynı parametreler ve fiyat aralığı kaldırılır. Gizli üründe sepete ekleme formu
  blokları (`add-to-cart-with-options`, `add-to-cart-form`) hiç basılmaz; yoksa boş bir form ve
  değişken üründe "Stokta yok" görünüyordu.
- **Sayfalar (`Woo\Pages`):** Sepet, ödeme ve hesap sayfaları çekirdek site haritasına girmez.
  Ziyaretçiye hiçbir şey satılmıyorsa (`Modes::nothing_sells()`) sepet ve ödeme bağlantıları
  navigasyon bloklarından, sayfa listesinden ve klasik menülerden çıkar.
- **Alanlar (`Woo\Fields`):** Ürün verisinin Genel panelinde ve ürün kategorisi formlarında
  mod seçimi; boş seçim kategoriyi / üst kategoriyi / mağazayı izler. Meta anahtarları sanitize
  ve yetki callback'iyle kayıtlıdır (`edit_products`, `manage_product_terms`).
- **Yol (`Woo\Trail`):** `pergel/seo/crumbs` süzgeciyle ürün ve ürün kategorisi yoluna mağaza
  sayfası ve kategori zinciri girer (`Seo\Head::term_trail`).
- **İşaretleme düzeltmeleri (`Woo\Markup`, yalnızca ön yüz):** varyasyon özniteliklerine
  `role="listitem"`; `data-wp-class--disabled` taşıyan ürün düğmesine aynı duruma bağlı
  `aria-disabled` (`data-wp-bind--aria-disabled`).
- **Blok başına stiller (`theme/assets/css/blocks/`, `functions.php`):** `woocommerce/catalog-sorting`,
  `product-sale-badge` (küçük dolu etiket), `product-image` (placeholder `surface` zemin), `product-template`
  (ürün kartı: `contrast` başlık, çerçeveli düğme, kare `contain` görsel), `mini-cart` (kapalı
  çekmece klavyeden gizli), `product-filters`
  (WooCommerce custom property'leri token'lara bağlı; kapalı filtre çekmecesi klavyeden gizli)
  ve sepet ile ödemenin
  ortak form alanları (`woocommerce-forms.css`); hepsi preset renk token'larını kullanır.
  `woocommerce-forms.css` dar sepette ürün sütununu esnek yapar (WooCommerce 132 px sabitliyor)
  ve satır toplamının altındaki tasarruf rozetinin kırılmasına izin verir; Türkçe metinle sepet
  360 px'te yana taşıyordu.
  `woocommerce-product-review-form.css` yorum formunun alanlarını form genişliğine sığdırır
  (45 sütunluk yorum kutusu 360 px'te kesiliyordu).
- **Ürün sorusu bloğu (`pergel/product-cta`, `Woo\Cta`):** ürün şablonunda sepet formunun
  altında. Ziyaretçiye satılmayan üründe ne yapabileceğini gösterir: `login` modunda oturumsuz
  ziyaretçiye "Log in to see prices" bağlantısı; `quote` modunda katlanmış bir teklif formu
  (`<details>`; script'siz gönderimden dönünce açık); satılmayan her üründe WhatsApp
  (`woo.whatsapp`), telefon (`brand.phone`) ve iletişim sayfası (`woo.contact_url`)
  bağlantıları. Satılan üründe yalnızca `onSale` açıksa iletişim bağlantılarını gösterir.
  Bağlantılar `generate_lead` olayı verir (`lead_source`: `whatsapp`, `phone`,
  `contact_page`). WooCommerce yoksa blok kaydedilmez (`Blocks\Module::SHOP_BLOCKS`).
- **Teklif formu (`Woo\Quote`):** Form bloğunu `kind=quote`, `aboutPost` ile çizer. Konu alanları:
  ürün adı ve SKU, değişken üründe yayımlanmış varyasyonlardan zorunlu seçim (değer
  varyasyonun SKU'suyla saklanır) ve adet (1–999999). Mesaj saklandıktan sonra istek
  `QuoteRequest` olarak her kullanılabilir `QuoteProvider`'a verilir
  (`pergel/woo/quote_providers` süzgeci; varsayılan boş). Çağrılar eşzamanlıdır; hata veren
  sağlayıcı "gönderilmedi" sayılır. Sonuçlar mesajın `_pergel_handoff` meta'sında tutulur ve
  "Form messages" ekranında "Passed on to" satırında görünür.
- **İstek listesi ve karşılaştırma (`Woo\Lists`, `Woo\ListView`, F4b P2-1):** `woo.wishlist`
  ya da `woo.compare` açık değilse `Woo\Module` sınıfı hiç yüklemez. Açıkken:
  - Bloklar: `pergel/product-actions` (düğmeler; ürün sayfasında `labels` ile yazılı),
    `pergel/shop-list` (`kind`: `wishlist`|`compare`) ve `pergel/shop-links` (header
    bağlantıları ve sayılar). WooCommerce yoksa kaydedilmezler (`Blocks\Module::SHOP_BLOCKS`).
  - Block hooks (`hooked_block_types`, öncelik 21): düğmeler `woocommerce/product-button` ve
    `woocommerce/add-to-cart-with-options`'dan sonra gelir; sepet formu şablon parçasında
    (`*add-to-cart-with-options`) ve bloğu zaten içeren içerikte eklenmez. Bağlantılar Pergel
    header pattern'lerinde `core/navigation`'dan sonra, mini sepetten önce ve yalnızca bir liste
    sayfası adresi varsa eklenir.
  - `shop-lists.js` (script modülü, `fetchpriority=low`) ve verisi
    (`script_module_data_pergel/shop-lists`): REST adresleri, sınırlar, metinler; oturum açmış
    müşteride nonce ve kayıtlı istek listesi.
  - REST: `GET pergel/v1/shop-list?kind=&ids=` (herkese açık; yalnızca yayımlanmış ve görünür
    ürünler, `{ids, html}`) ve `POST pergel/v1/wishlist` (`is_user_logged_in`; `add`, `remove`,
    `merge`; user meta yalnızca liste değiştiyse yazılır).
  - Sayfa görüntülemesi veritabanına yazmaz; liste sayfası ve REST isteği de yazmaz.
- **Ürün sayfası: seçenek görünümü ve yapışkan sepete ekle (`Woo\Swatches`,
  `Woo\StickyCart`, F4b P2-2):** `woo.swatches` / `woo.sticky_cart` açık değilse
  `Woo\Module` sınıfları yüklemez. Açıkken:
  - `Swatches`: `render_block_woocommerce/add-to-cart-with-options-variation-selector`
    süzgeci, boş olmayan içerikte `pergel-swatches` stilini kuyruğa alır (`path` verisiyle
    satır içi). Veri ve işaretleme WooCommerce'indir.
  - `StickyCart`: `pergel/sticky-add-to-cart` bloğu (WooCommerce yoksa kaydedilmez,
    `Blocks\Module::SHOP_BLOCKS`). Block hooks (`hooked_block_types`, öncelik 22) onu
    `woocommerce/add-to-cart-with-options`'dan sonra ekler; bloğu zaten içeren içerikte
    eklenmez. Basit/değişken, satın alınabilir, stoktaki ürün yoksa boş döner; editörde
    sunucu bir açıklama çizer.
  - `view.js` (script modülü): formun gerçek düğmesine basar, eksik seçenekte forma odaklar,
    fiyatı ve düğme metnini izler, `--pergel-sticky-cart` ile sayfaya alt boşluk verir.
- **Tahmini teslimat (`Woo\Delivery`, F4b P2-3):** `woo.delivery` açık değilse `Woo\Module`
  sınıfı yüklemez. Açıkken:
  - `pergel/delivery-estimate` bloğu (WooCommerce yoksa kaydedilmez,
    `Blocks\Module::SHOP_BLOCKS`). Block hooks (`hooked_block_types`, öncelik 22) onu
    `woocommerce/add-to-cart-with-options`'dan sonra ekler; bloğu zaten içeren içerikte
    eklenmez. Kargo açık değilse ya da basit/değişken, satın alınabilir, stoktaki, sanal
    olmayan ürün yoksa boş döner.
  - Sunucu `data-pergel-delivery` içinde kuralları basar: son saat (dakika), hazırlama, en
    kısa/en uzun süre, iş günleri, tatiller, saat dilimi (`tz` ya da sabit `offset`), dil ve
    değişken üründe tahmini olmayan seçeneklerin değerleri (`skip`).
  - `view.js` (script modülü) tarihleri sayfa açılınca hesaplar ve `Intl.DateTimeFormat` ile
    yazar; seçenek değişince `skip`'e göre gizler/gösterir.
  - Kargo sınıfı: WooCommerce'in sınıf tablosuna "Delivery time" sütunu ve alanı eklenir
    (`woocommerce_shipping_classes_*`), değer term meta `pergel_delivery_days`'tedir.
- **Stok bildirimi (`Woo\StockAlert`, `StockAlertStore`, `StockAlertList`, `StockEmail`,
  F4b P2-3):** `woo.stock_alert` açık değilse `Woo\Module` `StockAlert`'i yüklemez; tablo
  kuruluysa `StockAlertStore::register()` yine çalışır: gizlilik araçları ayar kapansa da
  çalışır; kuyruktaki gönderim ise ayar kapalıyken hiçbir şey göndermez, satırlar bekler.
  - `pergel/stock-alert` bloğu sepet formundan sonra gelir (block hooks, öncelik 22). Tükenmiş
    basit/değişken üründe formu çizer; bazı seçenekleri tükenmiş üründe `hidden` başlar ve
    `view.js` tükenmiş bir seçenek seçilince formu o varyasyonla gösterir. Linkten dönen sonuç
    (`?pergel_stock_alert=`) formsuz da gösterilir.
  - Gönderim: `POST pergel/v1/stock-alert` (script) ya da `admin-post.php`
    (`action=pergel_stock_alert`, script'siz; ürün sayfasına sonuçla döner). İkisi de
    `StockAlert::handle()`'a gider. Belirteç `ürün.zaman.HMAC`; varyasyon, belirteçteki ürünün
    tükenmiş bir çocuğu olmalıdır.
  - Tablo `{prefix}pergel_stock_alerts` (`id`, `product_id`, `email`, `user_id`, `status`,
    `created`, `confirmed`, `sent`; `product_id + email` benzersiz). İlk gönderimde ya da
    yönetim panelinde kurulur; sürüm `pergel_stock_alert_db` option'ındadır.
  - E-posta bağlantıları `?pergel_stock=confirm|remove&pergel_stock_id=&pergel_stock_key=` biçimindedir;
    anahtar satırın `id`, adres ve `created` değerinden HMAC'tir, satır yeniden başlayınca eski
    bağlantı 410 döner. `template_redirect` (öncelik 5) bağlantıyı işler ve ürüne yönlendirir.
  - Stok dönüşü: `woocommerce_(product|variation)_set_stock_status` `instock` olunca
    `pergel/stock_alert/send` Action Scheduler işini kuyruğa alır (Action Scheduler yoksa
    WP-Cron). İş 50 adres gönderir, kalan varsa bir sonrakini kuyruğa alır; gönderimden önce
    ürünün hâlâ stokta ve satın alınabilir olduğuna, ayarın açık olduğuna bakar. Süresi dolan satırlar (`pending` 7, `sent` 30 gün)
    gönderimde ve yeni kayıtta silinir.
  - E-postalar `woocommerce_email_classes` ile eklenir (`pergel_stock_alert_confirm`,
    `pergel_back_in_stock`); ortak gövde `StockEmail`'dedir (HTML ve düz metin).
    `woocommerce_prepare_email_for_preview` önizlemeye örnek bir ürün verir.
  - Yönetim listesi: `admin.php?page=pergel-stock-alerts` (WooCommerce menüsü).
- **Beden rehberi (`Woo\SizeGuide`, F4b P2-4):** `woo.size_guide` açık değilse `Woo\Module`
  sınıfı yüklemez. Açıkken:
  - `pergel/size-guide` bloğu (WooCommerce yoksa kaydedilmez, `Blocks\Module::SHOP_BLOCKS`).
    Block hooks (`hooked_block_types`, öncelik 22) onu `woocommerce/add-to-cart-with-options`'dan
    önce ekler; bloğu zaten içeren içerikte eklenmez. Editörde sunucu bir açıklama çizer.
  - Rehber seçimi `SizeGuide::guide()`: ürünün post meta'sı `pergel_size_guide` (desen kimliği
    ya da `none`), yoksa ürün kategorileri ve ataları arasında term meta'sı olan en yakın
    kategori (eşitlikte düşük kimlik). Yalnızca yayımlanmış, parolasız `wp_block` geçerlidir.
    Meta anahtarları `init`'te sanitize ve yetki geri çağrılarıyla kaydedilir.
  - Desen `core/block` (`ref`) olarak çizilir; çekirdeğin özyineleme koruması geçerlidir. Stil
    ve `view.js` (script modülü) yalnızca rehber çizildiğinde kuyruğa alınır.
  - Yönetim: ürün verisi → Genel sekmesinde seçim kutusu
    (`woocommerce_product_options_general_product_data`, `woocommerce_admin_process_product_object`),
    kategori ekleme/düzenleme formlarında alan (`product_cat_*_form_fields`,
    `created_/edited_product_cat`).
- **Birlikte sık alınanlar (`Woo\BoughtTogether`, F4b P2-4):** `woo.bought_together` açık
  değilse `Woo\Module` sınıfı yüklemez. Açıkken:
  - `pergel/bought-together` bloğu sepet formundan sonra gelir (block hooks, öncelik 22).
    Basit/değişken, satın alınabilir, stoktaki ürün ve gösterilecek en az bir çapraz satış
    (yayımlanmış, görünür, stokta; en fazla `LIMIT` = 3) yoksa boş döner.
  - Satırlar: ürünün kendisi ve `addable()` çapraz satışlar onay kutusuyla (`data-price` =
    `wc_get_price_to_display`), diğerleri bağlantıyla. Değişken ana ürün kapalı başlar;
    `data-pergel-together` içindeki `variations` (öznitelikler, fiyat, satın alınabilirlik;
    en fazla 100) `view.js`'in sepet formunun `attribute_*` alanlarıyla eşleştirmesi içindir.
  - `view.js` (script modülü): toplamı günceller; gönderimde `GET wc/store/v1/cart`'tan
    `Nonce` başlığını alır, `POST wc/store/v1/batch` ile her ürün için `cart/add-item` yollar,
    başarıda `wc-blocks_store_sync_required` ve `wc-blocks_added_to_cart` olaylarını gönderir.
  - Script'siz: form `pergel_together_product` + `pergel_together[]` ile ürün sayfasına
    gönderilir; `BoughtTogether::submit()` (`wp_loaded`, öncelik 30) yalnızca izin verilen
    kimlikleri ekler, `wc_add_to_cart_message` ile sepete yönlendirir.
- **Fiyat HTML'i (`Woo\Price`):** `Price::html()` WooCommerce fiyat HTML'ini `post` izin
  listesi + `<bdi>` + `span[translate]` ile süzer; `BoughtTogether`, `StickyCart` ve
  `ListView` kullanır.
- **Mağaza bölümleri (F4c):** `theme/patterns/shop-*.php`, kategori `pergel-shop` ("Shop").
  Kategori ve pattern'ler yalnızca WooCommerce etkinken vardır: tema kategoriyi yalnızca o zaman
  kaydeder, `drop_shop_patterns()` (`init`, öncelik 20) mağazasız sitede `pergel/shop-*`
  kayıtlarını siler. Kurallar (`Rules::SHOP_SECTION_PREFIX`, `SHOP_CATEGORY`): bu bölümler
  WooCommerce blokları taşıyabilir; `shop-*` adı ile `pergel-shop` kategorisi birlikte gelir.
  - Bölümler: `shop-hero` (H1), `shop-categories` (Terms Query + `pergel/term-image`), ürün
    ızgaraları `shop-new-arrivals`, `shop-best-sellers` (surface zemin), `shop-on-sale` (koyu
    zemin) ve `shop-featured` (WooCommerce Product Collection, `queryId` 11–14, sayfada çakışmaz),
    `shop-promo-pair` ve `shop-benefits`. Tam sayfa `shop-page-home` (`pergel-pages` ve `pergel-shop`) hero,
    kategoriler, yeni gelenler, promo, çok satanlar, indirimdekiler ve avantajları dizer.
  - Kart başlığı ve düğmesi `color: inherit` alır, düğme çerçevesi `currentcolor`'dan karışır
    (`woocommerce-product-template.css`); koyu zeminli bölümde de okunur.
  - `tests/visual/patterns.spec.mjs` `shop-*` dosyalarını yalnızca `PERGEL_SHOP=1` iken dener
    (WooCommerce'li site gerekir) ve hiçbirini görsel referansa katmaz.
- **Bölümlü ürün sayfası (v1.1):** Ürün açıklaması bölümlerden oluşur; bölümler ürünün
  `post_content`'inde blok olarak durur. Şablon `single-product-sections` ("Product with
  sections", `theme.json` `customTemplates`, yalnızca ürün) ürün özetinin altına İçindekiler'i
  "Bar" stiliyle (yalnızca H2) `main`'in doğrudan çocuğu olarak koyar (yapışkan kalsın diye),
  ardından içeriği (960px, sola yaslı) ve `hidden-product-reviews`'i (yorumlar kapalıysa boş),
  en sonda benzer ürünleri. Varsayılan `single-product` aynen kalır; şablonu ürün başına paket
  ya da editör seçer.
  - Ürünü bu şablonda olan ya da açıklaması blok taşıyan ürün blok editöründe açılır
    (`Woo\Sections`, `use_block_editor_for_post`); WooCommerce ürünleri klasik editörde açar,
    o da bloklu açıklamayı işaretleme olarak gösterir. Ürün verisi altta meta kutusu olarak kalır;
    diğer ürünler klasik editörde kalır.
  - Bölümler `theme/patterns/product-*.php`, kategori `pergel-product` ("Product"); sayfalarda
    da kullanılabilir. Yalnızca çekirdek bloklar, kök
    `section` grubu, H2 başlık: `product-list` (özellikler), `product-figure` (görsel + metin),
    `product-specs` (`pergel-specs` tablosu + görsel), `product-compatibility`,
    `product-compare` (`pergel-comparison`), `product-care` (iki liste), `product-faq`
    (`pergel-faq` sınıflı kök, details öğeleri), `product-ordering` (sipariş kodları tablosu) ve
    `product-downloads` (her biri kart içinde bir dosya bloğu, öğe olarak çoğalır).
- **Kategori görseli (`pergel/term-image`, `Blocks\TermImage`, F4c):** Terms Query'deki
  (`core/term-template` içinde) terimin görseli; term meta `thumbnail_id` (WooCommerce ürün
  kategorisinin görseli), diğer taksonomiler için `pergel/term_image_id` süzgeci. Oran 1, 4/3
  (varsayılan), 3/4 veya 16/9; görsel yoksa aynı oranda düz bir kutu ızgarayı korur. Görsel
  yanındaki terim adını tekrarladığı için `alt=""` taşır, bağlantısı `tabindex="-1"
  aria-hidden="true"` ile sekme sırasından çıkar. WooCommerce gerektirmez.

## Arama (F4b)

`Search` modülü kendi indeksini kullanır. Sayfa görüntülenirken indeks yalnızca okunur;
içerik değiştiğinde güncellenir.

- **Metin (`Search\Text`):** Metin küçük harfe çevrilir; aksanlar ve Türkçe harfler katlanır
  (ı/İ → i, ş → s…); harf ve rakam dışındaki her şey boşluk olur. Token'lar en fazla 32 karakterdir.
  Tek harfler atılır, tek rakamlar tutulur. SKU ayrıca noktalamasız biçimiyle de indekslenir.
  Bir sorgu en fazla 6 terimden oluşur.
- **İndeks (`Search\Index`):** `{prefix}pergel_search` tablosu (kind, token `utf8mb4_bin`,
  object_id, weight). Ağırlıklar: başlık 10, SKU 9, terimler 5, öznitelikler 4, özet 3, içerik 1.
  İçerikten en fazla 600 token alınır. Değişiklikler istek sonunda (shutdown) yazılır.
  Yeniden adlandırılan bir kategorinin yazıları cron'da parti parti güncellenir. Tam kurulum
  `pergel/search/build` cron'uyla yapılır. Hangi içeriğin indeksleneceği şu süzgeçlerle
  ayarlanır: `pergel/search/post_types`, `pergel/search/taxonomies`, `pergel/search/searchable`,
  `pergel/search/fields`.
- **Sorgu (`Search\Query`):** Her terim tam bir token ya da bir token'ın başı olarak eşleşmelidir.
  Puan, her terimin en iyi ağırlığının toplamıdır; tam kelime eşleşmesi +2 alır. Eşitlikte daha
  yeni içerik öne geçer. Ön yüz aramaları `pre_get_posts` (öncelik 20) üzerinden `post__in`
  olur; ziyaretçi bir sıralama seçmediyse sonuçlar puan sırasıyla gelir. Yönetim paneli, REST
  ve feed'ler WordPress'in kendi aramasını kullanır (`pergel/search/applies`). 5.000'den fazla
  indeks satırında geçen terim (`pergel/search/common_rows`) yalnızca diğer terimlerin bulduğu
  içerikte aranır; bütün terimler yaygınsa hepsi toplanır.
- **Öneriler (`Search\Suggest`):** `GET pergel/v1/search?q=&type=product`. Yanıtta en fazla
  `search.live_limit` sonuç ve 4 kategori, toplam sayı ve arama sayfasının adresi bulunur.
  Fiyat yalnızca `sale` modundaki ürünlerde verilir.
- **Kutu (`Search\Box`):** `render_block_core/search` süzgeci formu ARIA combobox'a çevirir
  (listbox ve status alanı eklenir). `search.js` (script modülü) ve `search.css` yalnızca Search
  bloğu olan sayfalarda yüklenir. Ürün aramasına ayarlı bir Search bloğu yalnızca ürün önerir.
- **WP-CLI:** `wp pergel search status`, `wp pergel search reindex`,
  `wp pergel search query <metin> [--type=product]`.

## Yapay zekâ erişimi (F5d)

`Abilities` modülü siteyi WordPress Abilities API'siyle yapay zekâ ajanlarına anlatır.
Varsayılan olarak kapalıdır (`modules.abilities`, panelde "Yapay zekâ erişimi"). Açıkken
`pergel` kategorisinde beş yetenek kaydeder; hiçbiri bir şey değiştirmez:

| Yetenek | Döndürdüğü | Yetki |
| --- | --- | --- |
| `pergel/list-patterns` | Pergel desenleri, kategorileri ve alan özeti (`category` süzgeci) | `edit_posts` |
| `pergel/get-pattern-fields` | Bir desenin alan şeması ve paket içerik dosyasına yazılacak örnek bölüm | `edit_posts` |
| `pergel/list-pages` | Sayfalar menü sırasıyla: adres, durum, şablon, yapan paket ve bölümlerin desenleri (`page`, en fazla 100 `per_page`) | `edit_pages` |
| `pergel/get-design-tokens` | Etkin temanın token'ları (ana tema + alt temanın değiştirdikleri) | `edit_theme_options` |
| `pergel/get-settings` | `pergel_settings` gruplarıyla | `manage_options` |

- **Kimler:** Yalnızca oturum açmış ve yetkisi olan kullanıcılar. REST'te uygulama şifresi gibi
  WordPress'in kendi kimlik doğrulaması kullanılır; Pergel ayrı anahtar tutmaz.
- **Nereden:** REST'te `GET /wp-abilities/v1/abilities/<ad>/run?input[...]=` (salt okunur
  yetenekler POST'u 405 ile reddeder). Yetenekler `meta.public` ve `meta.mcp.public` taşır;
  WordPress MCP Adapter gibi bir MCP sunucusu onları araç olarak sunar. MCP sunucusu Pergel'in
  parçası değildir.
- **Sınır:** Kullanıcı başına dakikada 60 çağrı (`pergel/abilities/limit` süzgeci), transient
  sayaçla. Aşılırsa `pergel_ability_limit` (429).

## Güvenlik (F6b)

- **Ziyaretçi** (`Core\Visitor`): herkese açık uç noktalar (form, stok uyarısı, arama önerisi)
  adresi, sınırları ve kaynağı buradan okur. Adres `REMOTE_ADDR` ya da `PERGEL_CLIENT_IP_HEADER`
  sabitinin adlandırdığı vekil başlığı (`X-Forwarded-For`'da son adres); IPv6 /64 başına sayılır,
  sınırlarda yalnızca özeti tutulur. `hit( anahtar, sınır, pencere )` sabit pencereli sayaçtır;
  kalıcı nesne önbelleğinde `wp_cache_incr` ile atomik, yoksa transient. `foreign_origin()`
  başka siteden gelen tarayıcı POST'unu tanır (sitenin kendi kaynağı portuyla karşılaştırılır).
- **Kapılar:** form gönderimi sırayla tuzak alanı, imzalı jeton, süre, kaynak, alan
  denetimi, bağlantı sınırı, Turnstile (anahtar varsa) ve site sınırından geçer; yalnızca alanları
  geçerli istek sınıra sayılır. Satış modları her REST rotasında mağaza yöneticisi dışında herkese
  uygulanır.
- **Girdi derinliği:** paket okuyucuları (YAML, Markdown) iç içe geçmeyi sınırlar, paket
  dosyaları gerçek yoluyla paketin içinde aranır, font adları ve `fontFace` değerleri
  `Css::font_family` ve anahtar listesiyle süzülür.
- **Gizlilik:** form mesajları, stok uyarıları ve kayıtlı istek listesi WordPress'in kişisel veri
  dışa aktarma ve silme araçlarına bağlıdır; silinemeyen kayıt `items_retained` ile bildirilir.

## Kaldırma (F6c)

- `engine/uninstall.php` yalnızca eklenti silinirken çalışır (`WP_UNINSTALL_PLUGIN`); eklenti
  dosyası o sırada yüklenmediği için kendi autoloader'ını kurar ve `Core\Uninstall::run()`'ı
  çağırır. Çoklu sitede her site için `switch_to_blog` ile `site()` çalışır.
- Silinecekler `Core\Uninstall`'daki listelerdedir (seçenekler, cron kancaları, yazı, terim ve
  kullanıcı meta anahtarları); tablolar kendi sınıflarının `uninstall()` yöntemiyle düşer. Yeni
  bir seçenek, meta veya tablo ekleyen kod bu listeye de eklenir; `UninstallTest` en yaygın
  olanları sınar.
- `PERGEL_KEEP_DATA` `true` ise hiçbir şey silinmez. İçerik (sayfa, ürün, menü, terim, medya,
  child tema) hiçbir durumda silinmez.

## WP-CLI (F2)

`Cli` modülü yalnızca WP-CLI altında yüklenir; web isteklerine kod eklemez.

| Komut | İş |
| --- | --- |
| `wp pergel info` | Motor, tema, WordPress ve PHP sürümleri, yüklenen modüller |
| `wp pergel tokens build [--check]` | Tasarım token'larından `theme.json` ve variation'ları üretir; `--check` yalnızca farkı denetler |
| `wp pergel settings get [<yol>]` | Tüm ayarlar, bir grup (`brand`) veya bir alan (`brand.email`); metin düz, gerisi JSON |
| `wp pergel settings set <grup.alan> <değer> [--format=json]` | Tek alan; açık/kapalı `true/false`, `1/0`, `yes/no` alır, listeler satır satır veya JSON dizi |
| `wp pergel settings export [<dosya>]` | Tüm ayarlar JSON olarak (dosyaya veya çıktıya) |
| `wp pergel settings import <dosya\|-> [--dry-run]` | JSON'dan okur; dosyada olmayan alanlar korunur, değişen alanlar listelenir |
| `wp pergel modules list` | Modül, anahtar (`on`/`off`/`always`), bu komutta yüklendi mi |
| `wp pergel modules enable\|disable <id>...` | Anahtarı olan modülleri açar/kapatır |
| `wp pergel search status\|reindex\|query <metin>` | İndeksin durumu, hemen yeniden kurulum, deneme araması |
| `wp pergel starter list` | Pergel'le gelen starter'lar |
| `wp pergel starter install <ad\|klasör> [--dry-run] [--reset]` | Starter'ın sayfalarını, menüsünü, okuma ayarlarını ve (mağaza starter'ında) kategori, öznitelik, ürün ve görsellerini kurar; tekrar çalışınca bir şey değiştirmez |
| `wp pergel starter remove <ad> [--dry-run] [--yes]` | Starter'ın yaptığı her şeyi kalıcı siler, okuma ayarlarını düzeltir |
| `wp pergel pattern list\|fields <ad>` | Desenler ve bir desenin paket alanları (F5a) |
| `wp pergel package validate <klasör>` | Proje paketini denetler (F5a) |
| `wp pergel import <klasör> [--dry-run]` | Proje paketinden siteyi kurar; tekrar çalışınca bir şey değiştirmez (F5b) |
| `wp pergel export <klasör> [--dry-run] [--all]` | Siteyi proje paketine yazar (F5c). Ayrıntı: `docs/PROJECT-PACKAGE.md` |

Ayar komutları ekranla aynı şemadan geçer: geçersiz veya bilinmeyen bir alan varsa hiçbir şey
kaydedilmez ve komut 1 ile çıkar (ekran ise geçerli kısmı kaydeder). Görsel alanları ek olarak
bu sitede görsel olan bir ekin kimliğini ister: `set` başka bir değeri reddeder, `import` başka
siteden gelen kimlikleri uyarıyla sıfırlar. `tools/ci/cli-smoke.sh` tüm komutları CI'da gerçek
WP-CLI ile dener (starter'ı kurar, tekrar kurar ve kaldırır) ve ayarları sonunda geri yükler.

## Ölçüm

`tools/budget/perf-probe.php` yalnızca `X-Pergel-Perf` başlığı taşıyan isteklerde çalışır ve
istek sonunda bir JSON satırı yazar: süre, sorgu sayısı ve süresi, DB yazmaları, yüklenen dosya
sayısı (toplam ve Pergel'e ait), bellek. `measure.mjs` her sayfayı 3 kez sıcak ölçer (medyan),
Playwright ile tarayıcı tarafını (HTML KB, istek sayısı, JS KB, gövdedeki CSS bağlantıları, DOM,
H1, JSON-LD, emoji yükleyicisi, font önyüklemeleri ve kullanılmayanları, LCP, CLS) toplar ve `tests/budget/budget.json` ile karşılaştırır.

## Geliştirme ortamı

Yerelde site `C:\xampp\htdocs\pergel-tema`, repo `D:\dev\pergel`; `tools/dev/link.ps1` tema ve
eklentiyi junction ile bağlar. Site Apache + PHP 8.4 FastCGI + MariaDB 11.8 (3308) üzerinde
çalışır; canlı ortam (Hostinger: PHP 8.4, MariaDB 11.8, nginx/LiteSpeed, LSCache, Cloudflare Free)
ile aynı sürümler. Pergel hiçbir davranışı `.htaccess`'e bağlamaz.
